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
