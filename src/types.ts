import type {
  D1Database,
  DurableObjectNamespace,
  KVNamespace,
  Workflow,
  Ai,
  SendEmail,
} from '@cloudflare/workers-types';

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
      LEAD_TRIAGE: DurableObjectNamespace;
      LEAD_TRIAGE_WORKFLOW: Workflow;
      EMAIL: SendEmail;
      WORKER_PULL_TOKEN?: string;
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
declare module 'agents' {
  // Class+interface merging — adds `env` to the Agent instance type
  // without changing its visibility (still effectively protected at runtime).
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  interface Agent<Env extends Cloudflare.Env, State, Props extends Record<string, unknown>> {
    env: Env;
  }
}

declare module 'agents/workflows' {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  interface AgentWorkflow<AgentType, Params, ProgressType, Env extends Cloudflare.Env> {
    env: Env;
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
