import { getAgentByName } from 'agents';
import type { Env } from '../types';
// Runtime imports so the worker bundle pulls these classes in. The
// astro.config.ts `exportAgentsForCloudflare` plugin then promotes them
// to named exports on the SSR entry chunk so miniflare can wrap them.
import { LeadTriageAgent } from '../agents/lead-triage-agent';
import { LeadTriageWorkflow } from '../workflows/lead-triage-workflow';

export { LeadTriageAgent, LeadTriageWorkflow };

/**
 * Single global instance of the lead-triage agent.
 * The whole pipeline routes through one agent; the workflow is per-lead.
 */
export function getTriageAgent(env: Env) {
  return getAgentByName(env.LEAD_TRIAGE, 'global');
}
