// Vitest pool-workers needs a `main` worker so the runtime can instantiate
// the Durable Object and Workflow defined in this project. The production
// entry (src/worker.ts) imports `@astrojs/cloudflare/entrypoints/server`,
// which references `virtual:astro-cloudflare:config` — a module Astro's
// adapter only generates during `astro build`. That virtual module fails
// to resolve in the test pipeline.
//
// This file is a tests-only worker entry that re-exports the DO and Workflow
// classes plus the queue and scheduled handlers (which the production
// wrangler.jsonc declares this worker provides). The queue/scheduled bodies
// mirror src/worker.ts so integration tests exercise the real dispatch path.
import type {
  MessageBatch,
  ScheduledController,
} from '@cloudflare/workers-types';
import type { Env, TriageMessage } from '../src/types';

export { LeadTriageAgent } from '../src/agents/lead-triage-agent';
export { LeadTriageWorkflow } from '../src/workflows/lead-triage-workflow';

export default {
  async fetch(): Promise<Response> {
    return new Response('test-worker', { status: 200 });
  },

  async scheduled(_controller: ScheduledController, env: Env): Promise<void> {
    const stub = env.LEAD_TRIAGE_AGENT.get(env.LEAD_TRIAGE_AGENT.idFromName('global'));
    await stub.sweepStuckRows();
  },

  async queue(batch: MessageBatch<TriageMessage>, env: Env): Promise<void> {
    const stub = env.LEAD_TRIAGE_AGENT.get(env.LEAD_TRIAGE_AGENT.idFromName('global'));
    for (const msg of batch.messages) {
      try {
        await stub.queueLead(msg.body.leadId);
        msg.ack();
      } catch {
        msg.retry({ delaySeconds: 30 });
      }
    }
  },
};
