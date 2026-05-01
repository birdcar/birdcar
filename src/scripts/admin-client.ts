import { AgentClient } from 'agents/client';

// Shape of the agent state the dashboard subscribes to. Keep this in sync
// with `AgentState` in src/agents/lead-triage-agent.ts. A duplicate is
// preferable here over importing the agent module directly, which would
// pull `cloudflare:workers`-bound code into the browser bundle.
export interface PendingApprovalView {
  workflowId: string;
  leadId: string;
  name: string;
  email: string;
  category: string;
  score: number | null;
  draftPreview: string;
  enqueuedAt: number;
}

export interface AgentStateView {
  pendingApprovals: PendingApprovalView[];
}

export function connectAgent(
  onStateUpdate: (state: AgentStateView) => void,
): AgentClient {
  return new AgentClient({
    agent: 'lead-triage-agent',
    name: 'global',
    host: window.location.host,
    onStateUpdate: (state) => {
      onStateUpdate(state as AgentStateView);
    },
  });
}
