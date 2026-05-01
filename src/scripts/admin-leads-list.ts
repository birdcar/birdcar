import { connectAgent, type PendingApprovalView } from './admin-client';

const list = document.getElementById('approval-list') as HTMLUListElement | null;
const empty = document.getElementById('empty-state') as HTMLParagraphElement | null;

if (list && empty) {
  connectAgent((state) => {
    const approvals = state?.pendingApprovals ?? [];
    empty.hidden = approvals.length > 0;
    list.replaceChildren(...approvals.map(renderRow));
  });
}

function renderRow(a: PendingApprovalView): HTMLLIElement {
  const li = document.createElement('li');

  const link = document.createElement('a');
  link.href = `/admin/leads/${encodeURIComponent(a.leadId)}`;
  link.className = 'bc-post-row';
  li.appendChild(link);

  const date = document.createElement('span');
  date.className = 'bc-post-date';
  date.textContent = new Date(a.enqueuedAt).toISOString().slice(0, 10);
  link.appendChild(date);

  const title = document.createElement('span');
  title.className = 'bc-post-title';
  title.textContent = `${a.name} — ${a.category}`;
  link.appendChild(title);

  const tag = document.createElement('span');
  tag.className = 'bc-post-tag';
  tag.textContent = `score ${a.score ?? '—'}`;
  link.appendChild(tag);

  return li;
}
