import { AgentController, setButtonsDisabled, showNotice } from './admin-controller';

const root = document.getElementById('lead-actions') as HTMLElement | null;
const draftEl = document.getElementById('draft-body') as HTMLTextAreaElement | null;

if (root && draftEl) {
  const leadId = root.dataset.leadId;
  if (leadId) {
    let workflowId: string | null = null;

    const agent = new AgentController((state) => {
      const approval = state?.pendingApprovals?.find((p) => p.leadId === leadId);
      workflowId = approval?.workflowId ?? null;
      if (!approval) {
        setButtonsDisabled(root, true);
        showNotice(
          root,
          'This lead is no longer pending approval. Refresh to see latest status.',
        );
      }
    });

    root.addEventListener('click', async (e) => {
      const target = (e.target as HTMLElement).closest(
        'button[data-action]',
      ) as HTMLButtonElement | null;
      if (!target || !workflowId) return;
      const action = target.dataset.action;

      setButtonsDisabled(root, true);

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
        setButtonsDisabled(root, false);
      }
    });
  }
}
