import { getAgentByName } from 'agents';
import type { Env } from '../types';

/**
 * Single global instance of the lead-triage agent.
 * The whole pipeline routes through one agent; the workflow is per-lead.
 * The agent and workflow classes are re-exported from src/worker.ts (the
 * worker entry, set via `main` in wrangler.jsonc) so miniflare can wrap
 * them — this stub only resolves the runtime DO stub via the binding.
 */
export function getTriageAgent(env: Env) {
  return getAgentByName(env.LEAD_TRIAGE, 'global');
}
