import type {
  D1Database,
  DurableObjectId,
  KVNamespace,
  Queue,
  Workflow,
  Ai,
  SendEmail,
} from '@cloudflare/workers-types';
// Type-only import — erased at runtime by `verbatimModuleSyntax`. Exists so
// the LEAD_TRIAGE_AGENT binding's stub type carries the agent's RPC method
// signatures (e.g. `queueLead`) without pulling the agent's `cloudflare:*`
// imports into modules that consume `Env` from prerender-eligible code.
import type { LeadTriageAgent } from './agents/lead-triage-agent';

/**
 * Narrow stub for the LeadTriageAgent DO binding.
 *
 * The Agents SDK's `Agent` class extends workerd's built-in `DurableObject`
 * from `cloudflare:workers`, which lacks the `DurableObjectBranded` marker
 * that `@cloudflare/workers-types`' generic `DurableObjectNamespace<T>`
 * requires. Rather than fight the brand, declare a hand-rolled namespace
 * type covering exactly the RPC surface we call from outside the agent —
 * which is also a forcing function to keep that surface intentional.
 */
type LeadTriageStub = Pick<
  LeadTriageAgent,
  'queueLead' | 'approveLead' | 'discardLead' | 'getRecentActivity' | 'sweepStuckRows'
>;

interface LeadTriageNamespace {
  idFromName(name: string): DurableObjectId;
  newUniqueId(): DurableObjectId;
  get(id: DurableObjectId): LeadTriageStub;
}

/**
 * Body shape for messages on the LEAD_TRIAGE_QUEUE. Producers (the contact
 * action) and the consumer (worker entry's `queue` handler) share this.
 */
export interface TriageMessage {
  leadId: string;
}

/**
 * Augment the global Cloudflare.Env so the Agents SDK's default
 * `this.env` resolves to our binding shape without per-class generics.
 */
declare global {
  namespace Cloudflare {
    interface Env {
      LEADS_DB: D1Database;
      SESSION?: KVNamespace;
      AI: Ai;
      LEAD_TRIAGE_AGENT: LeadTriageNamespace;
      LEAD_TRIAGE_WORKFLOW: Workflow;
      LEAD_TRIAGE_QUEUE: Queue<TriageMessage>;
      EMAIL: SendEmail;
      // WorkOS AuthKit secrets — gate /admin/* and /agents/*. See
      // wrangler.jsonc and .env.example for setup. Required at runtime;
      // typed as required so middleware/auth route code can rely on them
      // without inline narrowing.
      WORKOS_API_KEY: string;
      WORKOS_CLIENT_ID: string;
      WORKOS_REDIRECT_URI: string;
      WORKOS_COOKIE_PASSWORD: string;
    }
  }
}

export type Env = Cloudflare.Env;

/**
 * Module augmentations for the Agents SDK.
 *
 * The Agents SDK class chain (Agent -> Server -> DurableObject and
 * AgentWorkflow -> WorkflowEntrypoint) inherits `env` and `step.do`
 * at runtime, but those members don't propagate cleanly through
 * `cloudflare:workers`'s `export = CloudflareWorkersModule` syntax.
 * Re-declare them here as merged interfaces so the type checker
 * sees what the runtime actually has.
 */
// TypeScript's module augmentation requires the augmenting interface to
// use the SDK's exact type-parameter names; renames break the merge with
// "All declarations of X must have identical type parameters." That
// leaves us with names we don't reference in the interface body, which
// trips noUnusedParameters. The phantom optional `_phantom` property
// references the otherwise-unused generics in a non-emitting type so the
// declaration is well-formed without polluting the public surface.
declare module 'agents' {
  interface Agent<Env extends Cloudflare.Env, State, Props extends Record<string, unknown>> {
    env: Env;
    _phantom?: [State, Props];
  }
}

declare module 'agents/workflows' {
  interface AgentWorkflow<AgentType, Params, ProgressType, Env extends Cloudflare.Env> {
    env: Env;
    _phantom?: [AgentType, Params, ProgressType];
  }
  interface AgentWorkflowStep {
    do<T>(name: string, callback: () => Promise<T>): Promise<T>;
    do<T>(
      name: string,
      config: {
        retries?: { limit?: number; delay?: string | number; backoff?: 'constant' | 'linear' | 'exponential' };
        timeout?: string | number;
      },
      callback: () => Promise<T>,
    ): Promise<T>;
  }
}

