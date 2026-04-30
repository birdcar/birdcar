import { connectAgent } from './admin-client';

const root = document.getElementById('lead-actions') as HTMLElement | null;
const draftEl = document.getElementById('draft-body') as HTMLTextAreaElement | null;

if (root && draftEl) {
  const leadId = root.dataset.leadId;
  if (leadId) {
    let workflowId: string | null = null;

    const agent = connectAgent((state) => {
      const approval = state?.pendingApprovals?.find((p) => p.leadId === leadId);
      workflowId = approval?.workflowId ?? null;
      if (!approval) {
        // Already resolved — disable buttons and surface an inline note so
        // the SSR view doesn't lie about what's possible.
        setButtonsDisabled(true);
        showNotice('This lead is no longer pending approval. Refresh to see latest status.');
      }
    });

    root.addEventListener('click', async (e) => {
      const target = (e.target as HTMLElement).closest(
        'button[data-action]',
      ) as HTMLButtonElement | null;
      if (!target || !workflowId) return;
      const action = target.dataset.action;

      setButtonsDisabled(true);

      try {
        if (action === 'approve') {
          await agent.call('approveLead', [workflowId]);
        } else if (action === 'edit-approve') {
          const edited = draftEl.value.trim();
          if (edited.length < 10) {
            alert('Edited draft is too short.');
            return;
          }
          await agent.call('approveLead', [workflowId, edited]);
        } else if (action === 'discard') {
          if (!confirm('Discard this lead? No reply will be sent.')) return;
          await agent.call('discardLead', [workflowId]);
        }
        window.location.href = '/admin/leads';
      } catch (err) {
        console.error('[admin/lead] action failed:', err);
        alert('Action failed — see console.');
      } finally {
        setButtonsDisabled(false);
      }
    });

    function setButtonsDisabled(disabled: boolean): void {
      root!.querySelectorAll('button').forEach((b) => {
        (b as HTMLButtonElement).disabled = disabled;
      });
    }

    function showNotice(text: string): void {
      if (document.getElementById('lead-status-notice')) return;
      const notice = document.createElement('p');
      notice.id = 'lead-status-notice';
      notice.className = 'bc-admin-notice';
      notice.textContent = text;
      root!.parentElement?.insertBefore(notice, root);
    }
  }
}
