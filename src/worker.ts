// Custom worker entry. Four responsibilities:
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
//   4. Run the worker-level cron trigger that fires `sweepStuckRows` on
//      the agent. The agent's own internal alarm only registers after the
//      DO is first instantiated; if the queue path has never delivered a
//      message successfully, the DO has never started, the alarm has
//      never been scheduled, and the safety net never fires. The worker
//      cron runs independent of DO lifecycle and bootstraps the agent.
import type {
  ExecutionContext,
  MessageBatch,
  ScheduledController,
} from '@cloudflare/workers-types';
import server from '@astrojs/cloudflare/entrypoints/server';
import { routeAgentRequest } from 'agents';
import { errorFields } from './lib/log';
import { readSessionCookie, validateSession } from './lib/workos';
import type { Env, TriageMessage } from './types';

export { LeadTriageAgent } from './agents/lead-triage-agent';
export { LeadTriageWorkflow } from './workflows/lead-triage-workflow';

export default {
  // The Agents SDK ships its own router for `/agents/<class>/<name>` —
  // including the WebSocket upgrade the dashboard's AgentClient depends
  // on. We need it to run ahead of the Astro adapter so `/agents/*` never
  // hits Astro routing.
  //
  // Critically, this runs *before* Astro middleware, so the auth gate in
  // src/middleware.ts cannot protect `/agents/*` requests. The session
  // check has to happen here, inline, before we delegate. Otherwise the
  // dashboard's WebSocket (which exposes `pendingApprovals` PII and the
  // `approveLead`/`discardLead` callable surface) would be reachable
  // anonymously.
  async fetch(
    request: Request,
    env: Env,
    ctx: ExecutionContext,
  ): Promise<Response> {
    const url = new URL(request.url);
    // Use the same path parsing the Agents SDK does internally —
    // `pathname.split('/').filter(Boolean)`. A naive `startsWith('/agents/')`
    // would miss `//agents/...`, which Cloudflare's runtime preserves
    // verbatim but the SDK's matcher collapses, opening an unauthenticated
    // WebSocket to the DO.
    const parts = url.pathname.split('/').filter(Boolean);
    if (parts[0] === 'agents') {
      const cookie = readSessionCookie(request);
      const session = await validateSession(env, cookie);
      if (!session) {
        // WebSocket handshakes don't follow 302; HTTP fetches against
        // `/agents/*` shouldn't either (no useful login page to land on
        // for a programmatic client). Plain 401 in both cases.
        return new Response('unauthorized', { status: 401 });
      }
    }
    const agentResponse = await routeAgentRequest(request, env);
    if (agentResponse) return agentResponse;
    return server.fetch(request, env, ctx);
  },
  async scheduled(controller: ScheduledController, env: Env): Promise<void> {
    console.log({ event: 'cron.scheduled.fired', cron: controller.cron });
    try {
      const stub = env.LEAD_TRIAGE_AGENT.get(env.LEAD_TRIAGE_AGENT.idFromName('global'));
      await stub.sweepStuckRows();
      console.log({ event: 'cron.scheduled.ok' });
    } catch (err) {
      console.error({ event: 'cron.scheduled.failed', error: errorFields(err) });
      throw err;
    }
  },
  async queue(batch: MessageBatch<TriageMessage>, env: Env): Promise<void> {
    console.log({ event: 'queue.batch.received', count: batch.messages.length });
    const stub = env.LEAD_TRIAGE_AGENT.get(env.LEAD_TRIAGE_AGENT.idFromName('global'));
    for (const msg of batch.messages) {
      console.log({
        event: 'queue.dispatching',
        leadId: msg.body.leadId,
        attempt: msg.attempts,
      });
      try {
        const result = await stub.queueLead(msg.body.leadId);
        console.log({
          event: 'queue.dispatched',
          leadId: msg.body.leadId,
          workflowId: result.workflowId,
        });
        msg.ack();
      } catch (err) {
        console.error({
          event: 'queue.dispatch.failed',
          leadId: msg.body.leadId,
          attempt: msg.attempts,
          error: errorFields(err),
        });
        // Let the runtime retry per the consumer config (max_retries: 3).
        // The agent's cron sweep recovers anything that exhausts retries.
        msg.retry({ delaySeconds: 30 });
      }
    }
  },
};
