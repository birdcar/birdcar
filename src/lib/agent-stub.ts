import { getAgentByName } from 'agents';
import type { Env } from '../types';

/**
 * Single global instance of the lead-triage agent.
 * The whole pipeline routes through one agent; the workflow is per-lead.
 */
export function getTriageAgent(env: Env) {
  return getAgentByName(env.LEAD_TRIAGE, 'global');
}
