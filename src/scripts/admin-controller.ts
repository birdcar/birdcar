// Browser-side controller for the admin dashboard's agent connection plus
// shared DOM helpers. Wraps the AgentClient subscription so multiple admin
// pages share one factory.
import { AgentClient } from 'agents/client';
import type { AgentStateView } from './admin-client';

export class AgentController {
  private client: AgentClient;

  constructor(onState: (state: AgentStateView) => void) {
    this.client = new AgentClient({
      agent: 'lead-triage-agent',
      name: 'global',
      host: window.location.host,
      onStateUpdate: (state) => onState(state as AgentStateView),
    });
  }

  call<T = unknown>(method: string, args: unknown[] = []): Promise<T> {
    return this.client.call(method, args) as Promise<T>;
  }
}

/**
 * Disable or enable every <button> within `scope`. Used by the lead detail
 * page to lock the approve/edit/discard controls during in-flight RPCs.
 */
export function setButtonsDisabled(scope: HTMLElement, disabled: boolean): void {
  scope.querySelectorAll('button').forEach((b) => {
    (b as HTMLButtonElement).disabled = disabled;
  });
}

/**
 * Insert a <p class="bc-admin-notice"> before `scope` if one isn't already
 * present (matched by `id`). Used to surface "lead is no longer pending" and
 * similar transient state messages.
 */
export function showNotice(
  scope: HTMLElement,
  text: string,
  id = 'lead-status-notice',
): void {
  if (document.getElementById(id)) return;
  const notice = document.createElement('p');
  notice.id = id;
  notice.className = 'bc-admin-notice';
  notice.textContent = text;
  scope.parentElement?.insertBefore(notice, scope);
}
