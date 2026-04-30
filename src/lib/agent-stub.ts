import type { Env } from '../types';

// All admin pages talk to the same singleton agent instance: the one
// addressed by the `global` name. The agent fans out to many workflow
// instances internally; the DO holding pendingApprovals state is shared.
export function getTriageAgent(env: Env) {
  return env.LEAD_TRIAGE_AGENT.get(env.LEAD_TRIAGE_AGENT.idFromName('global'));
}
