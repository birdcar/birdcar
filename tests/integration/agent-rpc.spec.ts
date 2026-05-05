import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { env } from 'cloudflare:workers';
import { runInDurableObject } from 'cloudflare:test';
import { eq } from 'drizzle-orm';
import { getDb } from '../../src/db/client';
import { leads } from '../../src/db/schema';
import type { LeadTriageAgent } from '../../src/agents/lead-triage-agent';
import { captureEmail } from '../helpers/email';
import { waitForLeadStatus } from '../helpers/workflow';
import { STUCK_ROW_THRESHOLD_MINUTES } from '../../src/lib/triage-config';

// Mock AI for any test that drives the workflow (e.g. via sweepStuckRows
// retriggering a pending row). Tests that don't reach the workflow are
// unaffected. The async factory dodges the hoisting hazard: vi.mock is
// hoisted above imports, so referencing top-level imports synchronously
// throws. Dynamic import inside the factory resolves at execution time.
vi.mock('ai', async () => {
  const { mockWorkersAI } = await import('../mocks/workers-ai');
  return {
    generateText: mockWorkersAI({
      classify: { category: 'consulting-fit', confidence: 0.9, reasoning: 'fits' },
      qualify: {
        industry: 'cpa',
        geography: 'fort-worth',
        size_signal: 'small-2-10',
        problem_shape: 'review-queue',
        urgency_signal: 'in-pain',
      },
      draft: 'Reply draft body for tests.',
    }).generateText,
  };
});

const stubFor = (name: string) =>
  env.LEAD_TRIAGE_AGENT.get(env.LEAD_TRIAGE_AGENT.idFromName(name));

beforeEach(async () => {
  // Provision agent_activity. The Agents SDK's onStart() lifecycle hook only
  // fires on the first message dispatch, not on a no-op runInDurableObject
  // callback (scout risk #8). Call it explicitly so the table exists.
  // Then wipe rows — DO sqlite isn't cleared by setup.ts's per-test D1 wipe
  // (scout risk #7).
  await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) => {
    await instance.onStart();
    instance.sql`DELETE FROM agent_activity`;
  });
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('recordActivity', () => {
  it('inserts a row that getRecentActivity returns', async () => {
    await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) => {
      await instance.recordActivity({
        leadId: 'L_REC',
        workflowId: 'W_REC',
        step: 'classify',
        outcome: 'success',
        durationMs: 42,
      });
    });
    const rows = await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) =>
      instance.getRecentActivity(10),
    );
    expect(rows).toHaveLength(1);
    expect(rows[0].step).toBe('classify');
    expect(rows[0].outcome).toBe('success');
    expect(rows[0].duration_ms).toBe(42);
  });

  it('returns rows ordered by ts DESC', async () => {
    await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) => {
      await instance.recordActivity({ leadId: 'L', workflowId: 'W', step: 'first', outcome: 'success' });
      await new Promise((r) => setTimeout(r, 10));
      await instance.recordActivity({ leadId: 'L', workflowId: 'W', step: 'second', outcome: 'success' });
      await new Promise((r) => setTimeout(r, 10));
      await instance.recordActivity({ leadId: 'L', workflowId: 'W', step: 'third', outcome: 'success' });
    });
    const rows = await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) =>
      instance.getRecentActivity(10),
    );
    expect(rows.map((r: { step: string }) => r.step)).toEqual(['third', 'second', 'first']);
  });

  it('respects the limit argument', async () => {
    await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) => {
      for (let i = 0; i < 5; i++) {
        await instance.recordActivity({
          leadId: `L${i}`,
          workflowId: 'W',
          step: 'classify',
          outcome: 'success',
        });
      }
    });
    const rows = await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) =>
      instance.getRecentActivity(2),
    );
    expect(rows).toHaveLength(2);
  });
});

describe('sweepStuckRows', () => {
  it('resets stale processing rows back to pending', async () => {
    const db = getDb(env.LEADS_DB);
    const stale = new Date(Date.now() - (STUCK_ROW_THRESHOLD_MINUTES + 5) * 60 * 1000).toISOString();
    await db.insert(leads).values({
      id: 'L_STALE_PROCESSING',
      submittedAt: stale,
      name: 'A',
      email: 'a@b',
      message: 'Message body needing reply.',
      userAgent: null,
      source: 'test',
      status: 'processing',
    });
    // Force updatedAt back to the stale time (insert default would make it now)
    await db.update(leads).set({ updatedAt: stale }).where(eq(leads.id, 'L_STALE_PROCESSING'));

    await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) => {
      await instance.sweepStuckRows();
    });

    const [row] = await db.select().from(leads).where(eq(leads.id, 'L_STALE_PROCESSING')).limit(1);
    expect(row?.status).toBe('pending');
  });

  it('skips rows in the deliberate awaiting-approval dwell state', async () => {
    const db = getDb(env.LEADS_DB);
    const stale = new Date(Date.now() - (STUCK_ROW_THRESHOLD_MINUTES + 5) * 60 * 1000).toISOString();
    await db.insert(leads).values({
      id: 'L_AWAITING',
      submittedAt: stale,
      name: 'A',
      email: 'a@b',
      message: 'Message awaiting approval.',
      userAgent: null,
      source: 'test',
      status: 'awaiting-approval',
    });

    await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) => {
      await instance.sweepStuckRows();
    });

    const [row] = await db.select().from(leads).where(eq(leads.id, 'L_AWAITING')).limit(1);
    expect(row?.status).toBe('awaiting-approval');
  });

  it('retriggers stale pending rows by advancing them through the workflow', async () => {
    captureEmail();
    const db = getDb(env.LEADS_DB);
    const stale = new Date(Date.now() - (STUCK_ROW_THRESHOLD_MINUTES + 5) * 60 * 1000).toISOString();
    await db.insert(leads).values({
      id: 'L_STALE_PENDING',
      submittedAt: stale,
      name: 'A',
      email: 'a@b',
      message: 'Stale pending message awaiting workflow retrigger.',
      userAgent: null,
      source: 'test',
      status: 'pending',
    });

    await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) => {
      await instance.sweepStuckRows();
    });

    // The workflow should pick up the row and transition it through processing
    // to awaiting-approval (consulting-fit happy path with mocked AI).
    const final = await waitForLeadStatus('L_STALE_PENDING', 'awaiting-approval', 10_000);
    expect(final.status).toBe('awaiting-approval');
  });

  it('is a no-op when there are no stale rows', async () => {
    const db = getDb(env.LEADS_DB);
    // A fresh row well within the threshold.
    await db.insert(leads).values({
      id: 'L_FRESH',
      submittedAt: new Date().toISOString(),
      name: 'A',
      email: 'a@b',
      message: 'Fresh message under threshold.',
      userAgent: null,
      source: 'test',
      status: 'pending',
    });

    await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) => {
      await instance.sweepStuckRows();
    });

    const [row] = await db.select().from(leads).where(eq(leads.id, 'L_FRESH')).limit(1);
    expect(row?.status).toBe('pending');
  });

  it('is idempotent — running twice produces no double workflow', { timeout: 30_000 }, async () => {
    captureEmail();
    const db = getDb(env.LEADS_DB);
    const stale = new Date(Date.now() - (STUCK_ROW_THRESHOLD_MINUTES + 5) * 60 * 1000).toISOString();
    await db.insert(leads).values({
      id: 'L_IDEMPOTENT',
      submittedAt: stale,
      name: 'A',
      email: 'a@b',
      message: 'Stale pending row for idempotency test.',
      userAgent: null,
      source: 'test',
      status: 'pending',
    });

    // First sweep retriggers the workflow.
    await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) => {
      await instance.sweepStuckRows();
    });
    await waitForLeadStatus('L_IDEMPOTENT', 'awaiting-approval', 15_000);

    // Second sweep on the same row — should NOT retrigger because the row
    // is now `awaiting-approval` (sweep skips that dwell state).
    await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) => {
      await instance.sweepStuckRows();
    });

    // Count load-lead rows in agent_activity — exactly one means only one
    // workflow ran for this lead.
    const rows = await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) =>
      instance.getRecentActivity(100),
    );
    const loadCount = rows.filter(
      (r: { lead_id: string; step: string }) =>
        r.lead_id === 'L_IDEMPOTENT' && r.step === 'load-lead',
    ).length;
    expect(loadCount).toBe(1);
  });
});

describe('queueLead', () => {
  it('returns a workflowId and increments metrics.leadsProcessed', async () => {
    const db = getDb(env.LEADS_DB);
    await db.insert(leads).values({
      id: 'L_RPC_QUEUE',
      submittedAt: new Date().toISOString(),
      name: 'A',
      email: 'a@b',
      message: 'A message body of sufficient length.',
      userAgent: null,
      source: 'test',
    });
    captureEmail();

    const before = await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) =>
      instance.state.metrics.leadsProcessed,
    );
    const result = await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) =>
      instance.queueLead('L_RPC_QUEUE'),
    );
    expect(typeof result.workflowId).toBe('string');
    expect(result.workflowId.length).toBeGreaterThan(0);

    const after = await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) =>
      instance.state.metrics.leadsProcessed,
    );
    expect(after).toBe(before + 1);
  });
});

describe('approveLead and discardLead clear pendingApprovals', () => {
  // These are exercised end-to-end via workflow-paths.spec.ts. The pure
  // boundary contract — that approveLead/discardLead remove the entry from
  // pendingApprovals — is testable here only with a workflow already in
  // waitForApproval. Skipping inline duplication; the workflow-paths approve
  // and discard tests assert the same invariant via the agent state lookup.
  it.todo('approveLead clears the matching pendingApproval entry');
  it.todo('discardLead clears the matching pendingApproval entry');
});

describe('recordActivity error handling', () => {
  it('swallows errors so workflow steps never fail because of activity logging', async () => {
    // Force an SQL error by passing a step value that violates a hypothetical
    // future check (or by deleting the table mid-call). The simplest path is
    // to drop the table first; recordActivity should not throw.
    await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) => {
      instance.sql`DROP TABLE IF EXISTS agent_activity`;
      await expect(
        instance.recordActivity({
          leadId: 'L',
          workflowId: 'W',
          step: 'classify',
          outcome: 'success',
        }),
      ).resolves.not.toThrow();
      // Restore so the afterEach in setup doesn't blow up.
      await instance.onStart();
    });
  });
});
