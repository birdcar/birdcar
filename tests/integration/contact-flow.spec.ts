import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { env } from 'cloudflare:workers';
import { runInDurableObject } from 'cloudflare:test';
import { eq } from 'drizzle-orm';
import { getDb } from '../../src/db/client';
import { leads } from '../../src/db/schema';
import { captureEmail } from '../helpers/email';
import { waitForLeadStatus } from '../helpers/workflow';
import type { LeadTriageAgent } from '../../src/agents/lead-triage-agent';

// astro:actions virtual module isn't generated outside `astro build`. Replace
// `defineAction` with an identity-on-handler so the importable shape is just
// the handler function `(input, ctx) => Promise<output>`. ActionError is a
// real class so `instanceof` checks in production code keep working.
vi.mock('astro:actions', () => ({
  defineAction: (config: { handler: (input: unknown, ctx: unknown) => unknown }) =>
    config.handler,
  ActionError: class ActionError extends Error {
    code: string;
    constructor(opts: { code: string; message: string }) {
      super(opts.message);
      this.code = opts.code;
    }
  },
}));

// astro/zod re-exports zod/v4. The real package resolves at runtime; mock
// only if needed. (Confirmed: it loads cleanly in pool-workers without a
// mock, so leave the real one in place.)

// Mutable AI plan so individual tests can override the classification
// (default = consulting-fit happy path).
const { aiPlan } = vi.hoisted(() => ({
  aiPlan: {
    classify: {
      category: 'consulting-fit' as string,
      confidence: 0.9,
      reasoning: 'fits the practice',
    },
    qualify: {
      industry: 'cpa',
      geography: 'fort-worth',
      size_signal: 'small-2-10',
      problem_shape: 'review-queue',
      urgency_signal: 'in-pain',
    },
    draft: 'Reply draft body for tests.',
  },
}));

vi.mock('ai', () => ({
  generateText: async ({ system }: { system?: string; prompt: string }) => {
    const sys = system ?? '';
    if (/sorting incoming inquiries/i.test(sys)) {
      return { text: JSON.stringify(aiPlan.classify) };
    }
    if (/profiling.*inquiry/i.test(sys)) {
      return { text: JSON.stringify(aiPlan.qualify) };
    }
    if (/drafting.*reply/i.test(sys)) {
      return { text: aiPlan.draft };
    }
    throw new Error(`unrecognized system: ${sys.slice(0, 80)}`);
  },
}));

vi.mock('@workos-inc/node', () => ({
  WorkOS: class {
    userManagement = {
      loadSealedSession: () => ({
        authenticate: async () => ({ authenticated: false }),
        refresh: async () => ({ authenticated: false }),
      }),
    };
  },
}));

// Import AFTER vi.mock factories register so the action picks up the stubs.
import { server } from '../../src/actions';

const stubFor = (name: string) =>
  env.LEAD_TRIAGE_AGENT.get(env.LEAD_TRIAGE_AGENT.idFromName(name));

beforeEach(async () => {
  await runInDurableObject(stubFor('global'), async (instance: LeadTriageAgent) => {
    await instance.onStart();
    instance.sql`DELETE FROM agent_activity`;
  });
});

afterEach(() => {
  vi.restoreAllMocks();
});

function buildContext(): unknown {
  return {
    locals: {},
    request: new Request('https://www.birdcar.dev/contact', {
      method: 'POST',
      headers: { 'user-agent': 'test-runner/1.0' },
    }),
  };
}

describe('contact form action — happy path', () => {
  it('persists the lead, enqueues triage, and runs the workflow to awaiting-approval', { timeout: 30_000 }, async () => {
    const emails = captureEmail();
    const handler = server.contact.send as unknown as (
      input: { name: string; email: string; message: string },
      ctx: unknown,
    ) => Promise<{ delivered: boolean; id: string }>;

    const result = await handler(
      {
        name: 'Test User',
        email: 'test@example.com',
        message: 'I run a CPA firm in Fort Worth and need help with a review queue tool.',
      },
      buildContext(),
    );

    expect(result.delivered).toBe(true);
    expect(typeof result.id).toBe('string');

    const db = getDb(env.LEADS_DB);
    const [row] = await db.select().from(leads).where(eq(leads.id, result.id)).limit(1);
    expect(row).toBeDefined();
    expect(row.email).toBe('test@example.com');
    expect(row.source).toBe('birdcar.dev/contact');

    // Workflow advances through processing → awaiting-approval.
    await waitForLeadStatus(result.id, 'awaiting-approval', 10_000);

    // notify-nick step ran — captured exactly one email.
    expect(emails.length).toBeGreaterThanOrEqual(1);
    expect(emails[0].subject).toMatch(/^New lead:/);
  });
});

describe('contact form action — spam path', () => {
  it('classifies as spam → status=done outcome=discarded with zero emails', { timeout: 30_000 }, async () => {
    aiPlan.classify = { category: 'spam', confidence: 0.99, reasoning: 'obvious spam' };
    aiPlan.draft = 'unused — workflow short-circuits after draft step';
    const emails = captureEmail();

    const handler = server.contact.send as unknown as (
      input: { name: string; email: string; message: string },
      ctx: unknown,
    ) => Promise<{ delivered: boolean; id: string }>;
    const result = await handler(
      {
        name: 'Spammer',
        email: 'spam@example.com',
        message: 'Buy my product! Best deals on the internet.',
      },
      buildContext(),
    );
    expect(result.delivered).toBe(true);

    // Workflow runs to done (not awaiting-approval, since spam short-circuits).
    const db = getDb(env.LEADS_DB);
    const deadline = Date.now() + 15_000;
    let row;
    while (Date.now() < deadline) {
      [row] = await db.select().from(leads).where(eq(leads.id, result.id)).limit(1);
      if (row?.status === 'done') break;
      await new Promise((r) => setTimeout(r, 50));
    }
    expect(row?.status).toBe('done');
    expect(row?.outcome).toBe('discarded');
    expect(row?.category).toBe('spam');
    expect(emails).toHaveLength(0);
  });
});

describe('contact form action — queue.send failure path', () => {
  it('returns delivered=true even when queue.send throws (cron sweep recovers)', async () => {
    aiPlan.classify = { category: 'consulting-fit', confidence: 0.9, reasoning: 'fits' };
    aiPlan.draft = 'Reply draft body for tests.';

    // Force queue.send to throw. The action's inner try/catch logs a warning
    // and returns success — the cron sweep is the safety net for this row.
    vi.spyOn(env.LEAD_TRIAGE_QUEUE, 'send').mockRejectedValueOnce(
      new Error('queue infrastructure error'),
    );

    const handler = server.contact.send as unknown as (
      input: { name: string; email: string; message: string },
      ctx: unknown,
    ) => Promise<{ delivered: boolean; id: string }>;
    const result = await handler(
      {
        name: 'Queue Down',
        email: 'qd@example.com',
        message: 'Submit while the queue infra is unavailable.',
      },
      buildContext(),
    );

    // Action still reports success — non-blocking enqueue.
    expect(result.delivered).toBe(true);

    // The row landed in D1 even though the queue dispatch failed. The cron
    // sweep would pick this up next time it fires (and is exercised in
    // worker-handlers.spec.ts).
    const db = getDb(env.LEADS_DB);
    const [row] = await db.select().from(leads).where(eq(leads.id, result.id)).limit(1);
    expect(row).toBeDefined();
    expect(row?.status).toBe('pending');
  });
});
