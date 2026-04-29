// Custom worker entry. Three responsibilities:
//
//   1. Re-export the Durable Object and Workflow classes so miniflare / the
//      Cloudflare runtime can wrap them. The Astro adapter defaults `main`
//      to `@astrojs/cloudflare/entrypoints/server`, which only exports a
//      default fetch handler — that's why we override it via `main` in
//      wrangler.jsonc and add the named exports here.
//   2. Forward the adapter's `fetch` handler unchanged so HTTP requests
//      still flow through Astro's SSR runtime.
//   3. Consume LEAD_TRIAGE_QUEUE messages and dispatch each lead to the
//      agent's `queueLead` RPC. The action submits to the queue rather
//      than calling the DO directly so form latency is independent of
//      agent state and we get free retry + backpressure.
import type { MessageBatch } from '@cloudflare/workers-types';
import server from '@astrojs/cloudflare/entrypoints/server';
import type { Env, TriageMessage } from './types';

export { LeadTriageAgent } from './agents/lead-triage-agent';
export { LeadTriageWorkflow } from './workflows/lead-triage-workflow';

export default {
  fetch: server.fetch,
  async queue(batch: MessageBatch<TriageMessage>, env: Env): Promise<void> {
    const stub = env.LEAD_TRIAGE.get(env.LEAD_TRIAGE.idFromName('global'));
    for (const msg of batch.messages) {
      try {
        await stub.queueLead(msg.body.leadId);
        msg.ack();
      } catch (err) {
        console.error(
          `[queue] queueLead failed for ${msg.body.leadId} (attempt ${msg.attempts}):`,
          err,
        );
        // Let the runtime retry per the consumer config (max_retries: 3).
        // The agent's cron sweep recovers anything that exhausts retries.
        msg.retry({ delaySeconds: 30 });
      }
    }
  },
};
