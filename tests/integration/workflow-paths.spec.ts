import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { env } from 'cloudflare:workers';
import { runInDurableObject } from 'cloudflare:test';
import { eq } from 'drizzle-orm';
import { getDb } from '../../src/db/client';
import { leads } from '../../src/db/schema';
import { captureEmail } from '../helpers/email';
import { waitForLeadStatus } from '../helpers/workflow';
import type { LeadTriageAgent } from '../../src/agents/lead-triage-agent';
import type {
  ClassifyOutput as ClassifyOutputT,
  QualifyOutput as QualifyOutputT,
} from '../../src/lib/ai-types';

// Mutable AI plan — tests reassign before driving the workflow so each
// scenario produces the categorization it needs (spam, consulting-fit,
// off-fit, etc.). vi.hoisted runs before any imports/factories so the
// reference is stable across the mocked module.
const { aiPlan } = vi.hoisted(() => ({
  aiPlan: {
    classify: undefined as ClassifyOutputT | undefined,
    qualify: undefined as QualifyOutputT | undefined,
    draft: undefined as string | undefined,
  },
}));

vi.mock('ai', () => ({
  generateText: async ({
    system,
  }: {
    system?: string;
    prompt: string;
  }) => {
    const sys = system ?? '';
    if (/sorting incoming inquiries/i.test(sys)) {
      if (!aiPlan.classify) throw new Error('test forgot to set aiPlan.classify');
      return { text: JSON.stringify(aiPlan.classify) };
    }
    if (/profiling.*inquiry/i.test(sys)) {
      if (!aiPlan.qualify) throw new Error('test forgot to set aiPlan.qualify');
      return { text: JSON.stringify(aiPlan.qualify) };
    }
    if (/drafting.*reply/i.test(sys)) {
      if (aiPlan.draft === undefined) throw new Error('test forgot to set aiPlan.draft');
      return { text: aiPlan.draft };
    }
    throw new Error(`unrecognized system: ${sys.slice(0, 80)}`);
  },
}));

const stubFor = (name: string) =>
  env.LEAD_TRIAGE_AGENT.get(env.LEAD_TRIAGE_AGENT.idFromName(name));

const seedLead = async (id: string, message = 'A real message that triggers the workflow.') => {
  const db = getDb(env.LEADS_DB);
  await db.insert(leads).values({
    id,
    submittedAt: new Date().toISOString(),
    name: 'Test Lead',
    email: `${id}@example.com`,
    message,
    userAgent: null,
    source: 'test',
  });
};

beforeEach(() => {
  aiPlan.classify = undefined;
  aiPlan.qualify = undefined;
  aiPlan.draft = undefined;
  // Each test uses a unique agent (idFromName(leadId)) so DO state is fresh
  // by construction. No cross-test wipe of agent_activity needed here.
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('workflow: spam path', () => {
  it('short-circuits to status=done outcome=discarded with no email sent', { timeout: 30_000 }, async () => {
    const leadId = 'L_SPAM';
    aiPlan.classify = { category: 'spam', confidence: 0.99, reasoning: 'obvious spam' };
    // The workflow drafts BEFORE the spam short-circuit (lead-triage-workflow.ts
    // runs draft at step 5, then mark-done-spam at step 7). So we must still
    // supply a draft response, even though it'll never be sent. qualify is
    // genuinely skipped for non-consulting-fit categories.
    aiPlan.draft = 'unused — workflow short-circuits after draft step';
    const emails = captureEmail();

    await seedLead(leadId);
    await runInDurableObject(stubFor(leadId), async (instance: LeadTriageAgent) => {
      await instance.queueLead(leadId);
    });

    const db = getDb(env.LEADS_DB);
    // The workflow runs async — poll until done.
    const deadline = Date.now() + 15_000;
    let row;
    while (Date.now() < deadline) {
      [row] = await db.select().from(leads).where(eq(leads.id, leadId)).limit(1);
      if (row?.status === 'done') break;
      await new Promise((r) => setTimeout(r, 50));
    }
    expect(row?.status).toBe('done');
    expect(row?.outcome).toBe('discarded');
    expect(row?.category).toBe('spam');
    expect(emails).toHaveLength(0);
  });
});

describe('workflow: off-fit path', () => {
  it('skips the qualify step for non-consulting categories', { timeout: 30_000 }, async () => {
    const leadId = 'L_OFF_FIT';
    aiPlan.classify = {
      category: 'consulting-off-fit',
      confidence: 0.85,
      reasoning: 'wrong industry',
    };
    aiPlan.draft = 'Thanks for reaching out, but this is not a fit.';
    // qualify intentionally undefined — workflow must not call it.
    captureEmail();

    await seedLead(leadId);
    await runInDurableObject(stubFor(leadId), async (instance: LeadTriageAgent) => {
      await instance.queueLead(leadId);
    });

    await waitForLeadStatus(leadId, 'awaiting-approval', 15_000);
    const db = getDb(env.LEADS_DB);
    const [row] = await db.select().from(leads).where(eq(leads.id, leadId)).limit(1);
    expect(row?.category).toBe('consulting-off-fit');
    expect(row?.qualification).toBeNull();
    expect(row?.score).toBe(0);
  });
});

describe('workflow: consulting-fit happy path', () => {
  it('scores against the qualification rules and reaches awaiting-approval', { timeout: 30_000 }, async () => {
    const leadId = 'L_FIT';
    aiPlan.classify = {
      category: 'consulting-fit',
      confidence: 0.9,
      reasoning: 'fits',
    };
    aiPlan.qualify = {
      industry: 'cpa',
      geography: 'fort-worth',
      size_signal: 'small-2-10',
      problem_shape: 'review-queue',
      urgency_signal: 'in-pain',
    };
    aiPlan.draft = 'Reply draft body for tests.';
    const emails = captureEmail();

    await seedLead(leadId);
    await runInDurableObject(stubFor(leadId), async (instance: LeadTriageAgent) => {
      await instance.queueLead(leadId);
    });

    await waitForLeadStatus(leadId, 'awaiting-approval', 15_000);
    const db = getDb(env.LEADS_DB);
    const [row] = await db.select().from(leads).where(eq(leads.id, leadId)).limit(1);
    expect(row?.category).toBe('consulting-fit');
    expect(row?.qualification).toContain('cpa');
    expect(row?.score).toBeGreaterThanOrEqual(5);
    expect(row?.draft).toBe('Reply draft body for tests.');
    expect(emails.length).toBeGreaterThanOrEqual(1);
    expect(emails[0].subject).toMatch(/^New lead:/);
  });
});

describe('workflow: discard path', () => {
  it('marks the lead done/discarded and sends no reply email when discardLead is called', { timeout: 30_000 }, async () => {
    const leadId = 'L_DISCARD';
    aiPlan.classify = {
      category: 'consulting-fit',
      confidence: 0.9,
      reasoning: 'fits',
    };
    aiPlan.qualify = {
      industry: 'cpa',
      geography: 'fort-worth',
      size_signal: 'small-2-10',
      problem_shape: 'review-queue',
      urgency_signal: 'in-pain',
    };
    aiPlan.draft = 'Draft body that will be discarded.';
    const emails = captureEmail();

    await seedLead(leadId);
    await runInDurableObject(stubFor(leadId), async (instance: LeadTriageAgent) => {
      await instance.queueLead(leadId);
    });

    await waitForLeadStatus(leadId, 'awaiting-approval', 15_000);

    // Find the workflowId via agent state (pendingApprovals) and discard it.
    let workflowId: string | undefined;
    await runInDurableObject(stubFor(leadId), async (instance: LeadTriageAgent) => {
      const pending = instance.state.pendingApprovals.find((p) => p.leadId === leadId);
      workflowId = pending?.workflowId;
      if (workflowId) await instance.discardLead(workflowId, 'test discard');
    });
    expect(workflowId).toBeTruthy();

    await waitForLeadStatus(leadId, 'done', 15_000);
    const db = getDb(env.LEADS_DB);
    const [row] = await db.select().from(leads).where(eq(leads.id, leadId)).limit(1);
    expect(row?.outcome).toBe('discarded');
    // notify-nick fires before the wait, so emails has the notification but
    // not a reply. Reply email would be a second send.
    expect(emails).toHaveLength(1);
    expect(emails[0].subject).toMatch(/^New lead:/);
  });
});

describe('workflow: approve-as-drafted path', () => {
  it('sends the draft as the reply body and marks outcome=approved', { timeout: 30_000 }, async () => {
    const leadId = 'L_APPROVE';
    aiPlan.classify = {
      category: 'consulting-fit',
      confidence: 0.9,
      reasoning: 'fits',
    };
    aiPlan.qualify = {
      industry: 'cpa',
      geography: 'fort-worth',
      size_signal: 'small-2-10',
      problem_shape: 'review-queue',
      urgency_signal: 'in-pain',
    };
    aiPlan.draft = 'Approved draft body.';
    const emails = captureEmail();

    await seedLead(leadId);
    await runInDurableObject(stubFor(leadId), async (instance: LeadTriageAgent) => {
      await instance.queueLead(leadId);
    });

    await waitForLeadStatus(leadId, 'awaiting-approval', 15_000);

    let workflowId: string | undefined;
    await runInDurableObject(stubFor(leadId), async (instance: LeadTriageAgent) => {
      const pending = instance.state.pendingApprovals.find((p) => p.leadId === leadId);
      workflowId = pending?.workflowId;
      if (workflowId) await instance.approveLead(workflowId);
    });
    expect(workflowId).toBeTruthy();

    await waitForLeadStatus(leadId, 'done', 15_000);
    const db = getDb(env.LEADS_DB);
    const [row] = await db.select().from(leads).where(eq(leads.id, leadId)).limit(1);
    expect(row?.outcome).toBe('approved');
    // 2 emails: notify-nick + reply.
    expect(emails).toHaveLength(2);
    expect(emails[1].subject).toMatch(/^Re:/);
    expect(emails[1].text).toContain('Approved draft body.');
    expect(emails[1].text).toContain('- Nick');
  });
});

describe('workflow: edited-body approval path', () => {
  it('sends the edited text instead of the draft and marks outcome=edited', { timeout: 30_000 }, async () => {
    const leadId = 'L_EDITED';
    aiPlan.classify = {
      category: 'consulting-fit',
      confidence: 0.9,
      reasoning: 'fits',
    };
    aiPlan.qualify = {
      industry: 'cpa',
      geography: 'fort-worth',
      size_signal: 'small-2-10',
      problem_shape: 'review-queue',
      urgency_signal: 'in-pain',
    };
    aiPlan.draft = 'Original draft body.';
    const emails = captureEmail();
    const editedBody = 'A human edit replacing the AI draft entirely.';

    await seedLead(leadId);
    await runInDurableObject(stubFor(leadId), async (instance: LeadTriageAgent) => {
      await instance.queueLead(leadId);
    });

    await waitForLeadStatus(leadId, 'awaiting-approval', 15_000);

    let workflowId: string | undefined;
    await runInDurableObject(stubFor(leadId), async (instance: LeadTriageAgent) => {
      const pending = instance.state.pendingApprovals.find((p) => p.leadId === leadId);
      workflowId = pending?.workflowId;
      if (workflowId) await instance.approveLead(workflowId, editedBody);
    });
    expect(workflowId).toBeTruthy();

    await waitForLeadStatus(leadId, 'done', 15_000);
    const db = getDb(env.LEADS_DB);
    const [row] = await db.select().from(leads).where(eq(leads.id, leadId)).limit(1);
    expect(row?.outcome).toBe('edited');
    expect(row?.draft).toBe(editedBody);
    expect(emails).toHaveLength(2);
    expect(emails[1].text).toContain(editedBody);
  });
});

describe('workflow: approval timeout', () => {
  // Reproducing APPROVAL_TIMEOUT = '7 days' in tests requires either fake
  // timers (which need to interact with workerd's internal scheduler) or
  // pool-workers' introspectWorkflowInstance().modify().forceEventTimeout()
  // — both require identifying the exact step name `waitForApproval` registers
  // internally with the agents SDK, which isn't documented. Defer.
  it.todo('marks the lead done/discarded after the approval window elapses');
});
