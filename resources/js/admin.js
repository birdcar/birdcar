import './admin/publishing/editor-extensions.js';
import { createAutosaveQueue, restoreRecovery, saveRecovery, shouldWarnBeforeUnload } from './admin/publishing/autosave.js';
import { clearPublishingRecoveryNamespace, editorToDocumentJson } from './admin/publishing/document-helpers.js';

const queues = new Map();

function componentFor(element) {
    const root = element.closest('[wire\\:id]');
    const id = root?.getAttribute('wire:id');
    return id && window.Livewire?.find ? window.Livewire.find(id) : null;
}

function updateRevision(element, revisionId) {
    if (revisionId === undefined || revisionId === null) return;
    element.dataset.currentRevision = String(revisionId);
    const page = element.closest('[data-article-id]');
    if (page) page.dataset.currentRevision = String(revisionId);
}


function bootEditor(element, editor = null) {
    if (queues.has(element)) return queues.get(element);

    const queue = createAutosaveQueue({
        persist: (payload) => saveRecovery(sessionStorage, payload),
        save: async (payload) => {
            saveRecovery(sessionStorage, payload);
            const component = componentFor(element);
            if (!component) throw new Error('Livewire component unavailable; recovery copy kept in this tab.');

            const result = await component.call(
                'saveDocument',
                payload.articleId,
                payload.baseRevisionId,
                payload.mutationId,
                payload.document,
                payload.metadata,
            );

            if (result?.ok) {
                updateRevision(element, result.revisionId);
                element.dataset.document = JSON.stringify(result.document ?? payload.document);
                element.dataset.metadata = JSON.stringify(result.metadata ?? payload.metadata ?? {});
                sessionStorage.removeItem(payload.recoveryKey);
            }

            return result;
        },
    });

    queue.editor = editor;
    queues.set(element, queue);
    restoreRecovery(sessionStorage, element)?.then((payload) => {
        if (!payload || !editor?.commands?.setContent) return;
        if (window.confirm('Unsaved manuscript edits were recovered for this tab. Restore them?')) {
            editor.commands.setContent({ type: 'doc', content: payload.document.content ?? [] }, false);
            queue.enqueue(payload);
        }
    });

    return queue;
}

document.addEventListener('admin:editor:ready', (event) => {
    const { editor, element } = event.detail ?? {};
    if (!editor || !element) return;

    const queue = bootEditor(element, editor);
    editor.on?.('update', ({ editor: updatedEditor }) => {
        const document = editorToDocumentJson(updatedEditor.getJSON());
        const payload = {
            userId: Number(element.dataset.userId),
            articleId: Number(element.dataset.articleId),
            baseRevisionId: element.dataset.currentRevision ? Number(element.dataset.currentRevision) : null,
            document,
            metadata: JSON.parse(element.dataset.metadata || '{}'),
        };
        queue.enqueue(payload);
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-admin-editor]').forEach((element) => bootEditor(element));
});

document.addEventListener('livewire:navigated', () => {
    document.querySelectorAll('[data-admin-editor]').forEach((element) => bootEditor(element));
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-admin-logout]')) return;
    clearPublishingRecoveryNamespace(sessionStorage, form.dataset.userId);
});

document.addEventListener('click', async (event) => {
    const control = event.target?.closest?.('[data-conflict-action]');
    if (!control) return;

    const action = control.dataset.conflictAction;
    const editorElement = document.querySelector('[data-admin-editor]');
    const queue = editorElement ? queues.get(editorElement) : null;
    const editor = queue?.editor;

    if (action === 'copy-local') {
        event.preventDefault();
        const localDocument = editor?.getJSON ? editorToDocumentJson(editor.getJSON()) : JSON.parse(editorElement?.dataset.document || 'null');
        await navigator.clipboard?.writeText?.(JSON.stringify(localDocument, null, 2));
    }

    if (action === 'recover-local') {
        event.preventDefault();
        const payload = editorElement ? await restoreRecovery(sessionStorage, editorElement) : null;
        if (payload && editor?.commands?.setContent) editor.commands.setContent({ type: 'doc', content: payload.document.content ?? [] }, false);
    }

    if (action === 'restart-from-server') {
        event.preventDefault();
        if (window.confirm('Discard this tab’s unsaved manuscript changes and reload the latest saved revision?')) window.location.reload();
    }
});

window.addEventListener('beforeunload', (event) => {
    const dirty = Array.from(queues.values()).some((queue) => shouldWarnBeforeUnload(queue.state));
    if (!dirty) return;
    event.preventDefault();
    event.returnValue = '';
});
