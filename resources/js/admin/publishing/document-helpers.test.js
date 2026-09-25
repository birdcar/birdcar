import { describe, expect, mock, test } from 'bun:test';
import {
    clearPublishingRecoveryNamespace,
    createMutationId,
    documentToEditorJson,
    editorToDocumentJson,
    ensureStableBlockIds,
    markProtected,
    parseRecoveryPayload,
    recoveryKey,
    serializeRecoveryPayload,
    shouldKeepLocalEdits,
} from './document-helpers.js';
import { createAutosaveQueue, restoreRecovery, saveRecovery, shouldWarnBeforeUnload } from './autosave.js';

const document = { version: 1, type: 'doc', content: [{ type: 'paragraph', attrs: { id: 'blk_0000000000000001' }, content: [{ type: 'text', text: 'Hello' }] }] };

describe('document helpers', () => {
    test('round trips canonical document and editor JSON', () => {
        const editor = documentToEditorJson(document);
        expect(editor.version).toBeUndefined();
        expect(editorToDocumentJson(editor).version).toBe(1);
    });

    test('allocates stable ids once and preserves them across repeated serialization', () => {
        const inserted = { type: 'doc', content: [{ type: 'note', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Inserted' }] }] }] };

        const first = editorToDocumentJson(inserted);
        const second = editorToDocumentJson(documentToEditorJson(first));

        expect(first.content[0].attrs.id).toMatch(/^blk_[0-9a-f]{16}$/);
        expect(first.content[0].content[0].attrs.id).toMatch(/^blk_[0-9a-f]{16}$/);
        expect(second.content[0].attrs.id).toBe(first.content[0].attrs.id);
        expect(second.content[0].content[0].attrs.id).toBe(first.content[0].content[0].attrs.id);
    });

    test('pasted clone canonicalization assigns duplicates new stable ids', () => {
        const cloned = ensureStableBlockIds({ version: 1, type: 'doc', content: [
            { type: 'paragraph', attrs: { id: 'blk_0000000000000001' }, content: [{ type: 'text', text: 'A' }] },
            { type: 'paragraph', attrs: { id: 'blk_0000000000000001' }, content: [{ type: 'text', text: 'B' }] },
        ] });

        expect(cloned.content[0].attrs.id).toBe('blk_0000000000000001');
        expect(cloned.content[1].attrs.id).toMatch(/^blk_[0-9a-f]{16}$/);
        expect(cloned.content[1].attrs.id).not.toBe('blk_0000000000000001');
    });

    test('preserves stable ids on list items during editor round trips', () => {
        const listDocument = {
            version: 1,
            type: 'doc',
            content: [{
                type: 'bulletList',
                attrs: { id: 'blk_0000000000000002' },
                content: [{
                    type: 'listItem',
                    attrs: { id: 'blk_0000000000000003' },
                    content: [{ type: 'paragraph', attrs: { id: 'blk_0000000000000004' }, content: [{ type: 'text', text: 'Listed' }] }],
                }],
            }],
        };

        const roundTripped = editorToDocumentJson(documentToEditorJson(listDocument));
        expect(roundTripped.content[0].attrs.id).toBe('blk_0000000000000002');
        expect(roundTripped.content[0].content[0].attrs.id).toBe('blk_0000000000000003');
    });

    test('recovery payloads are keyed by user article and base revision', () => {
        const payload = { userId: 5, articleId: 9, baseRevisionId: 12, mutationId: 'mut_fixed', document, metadata: {}, savedAtClient: '2026-09-22T00:00:00.000Z' };
        expect(recoveryKey(payload)).toContain('u5:a9:r12');
        expect(parseRecoveryPayload(serializeRecoveryPayload(payload)).mutationId).toBe('mut_fixed');
    });

    test('mutation ids are generated and local edits win over late responses', () => {
        expect(createMutationId()).toMatch(/^mut_[0-9a-f]{16}$/);
        expect(shouldKeepLocalEdits({ responseRevisionId: 3, localBaseRevisionId: 2, hasPendingChanges: true })).toBe(true);
    });

    test('logout clears only the current user publishing recovery namespace', () => {
        const storage = new Map();
        const shim = {
            get length() { return storage.size; },
            key(index) { return Array.from(storage.keys())[index] ?? null; },
            removeItem(key) { storage.delete(key); },
        };
        storage.set('admin:publishing:recovery:v1:u5:a1:r1', 'mine');
        storage.set('admin:publishing:recovery:v1:u6:a1:r1', 'theirs');
        storage.set('unrelated', 'keep');

        clearPublishingRecoveryNamespace(shim, 5);

        expect(storage.has('admin:publishing:recovery:v1:u5:a1:r1')).toBe(false);
        expect(storage.has('admin:publishing:recovery:v1:u6:a1:r1')).toBe(true);
        expect(storage.has('unrelated')).toBe(true);
    });

    test('admin logout submit control clears only the current user recovery namespace', async () => {
        const listeners = new Map();
        const dispatched = [];
        class FakeLogoutForm {}
        const storage = new Map();
        const sessionStorageShim = {
            get length() { return storage.size; },
            key(index) { return Array.from(storage.keys())[index] ?? null; },
            getItem(key) { return storage.get(key) ?? null; },
            setItem(key, value) { storage.set(key, value); },
            removeItem(key) { storage.delete(key); },
        };
        const root = { dataset: { articleId: '2' } };
        const editorElement = {
            dataset: { userId: '1', articleId: '2', currentRevision: '', metadata: '{}' },
            closest: (selector) => {
                if (selector === '[data-article-id]' || selector === '[data-publishing-studio]') return root;
                return null;
            },
        };

        globalThis.HTMLFormElement = FakeLogoutForm;
        globalThis.sessionStorage = sessionStorageShim;
        globalThis.navigator = { userAgent: 'bun', platform: 'MacIntel', maxTouchPoints: 0 };
        globalThis.window = {
            Alpine: { data: (name) => listeners.set(`alpine:${name}`, true) },
            addEventListener: (name, listener) => listeners.set(`window:${name}`, listener),
            dispatchEvent: (event) => { dispatched.push(event); return true; },
        };
        globalThis.document = {
            documentElement: { style: {} },
            addEventListener: (name, listener, options) => listeners.set(options?.capture ? `capture:${name}` : name, listener),
            dispatchEvent: () => true,
            querySelector: () => null,
            querySelectorAll: (selector) => (selector === '[data-admin-editor]' ? [editorElement] : []),
        };

        let started = false;
        mock.module('motion', () => ({
            animate: () => ({ finished: Promise.resolve(), stop: () => {} }),
        }));

        mock.module('../../../../vendor/livewire/livewire/dist/livewire.esm', () => ({
            Alpine: { data: (name) => listeners.set(`alpine:${name}`, true) },
            Livewire: {
                start: () => {
                    expect(listeners.has('flux:editor')).toBe(true);
                    expect(listeners.has('flux:editor:ready')).toBe(true);
                    expect(listeners.has('admin:editor:ready')).toBe(true);
                    started = true;
                },
            },
        }));

        await import('../../admin.js');
        expect(started).toBe(true);
        expect(listeners.has('alpine:publishingSession')).toBe(true);
        const extensions = [];
        const enabled = [];
        listeners.get('flux:editor')({ detail: {
            registerExtensions: (registered) => extensions.push(...registered),
            enableExtension: (name) => enabled.push(name),
            init: () => {},
        } });
        expect(extensions.map((extension) => extension.name)).toEqual(['stableBlockAttributes', 'note', 'callout', 'chart', 'diagram']);
        expect(enabled).toEqual([]);
        expect(extensions[0].config.addProseMirrorPlugins()[0].key).toStartWith('publishingStableBlockIds$');

        let updateListener;
        const setContentCalls = [];
        const editor = {
            on: (name, listener) => { if (name === 'update') updateListener = listener; },
            getJSON: () => ({ type: 'doc', content: [{ type: 'paragraph', attrs: { id: 'blk_0000000000000001' }, content: [{ type: 'text', text: 'Local' }] }] }),
            commands: { setContent: (content, emitUpdate) => setContentCalls.push({ content, emitUpdate }) },
        };
        listeners.get('admin:editor:ready')({ detail: { editor, element: editorElement } });
        listeners.get('window:publishing-document-updated')({ detail: {
            articleId: 2,
            baseRevisionId: null,
            revisionId: 21,
            document: { version: 1, type: 'doc', content: [{ type: 'paragraph', attrs: { id: 'blk_0000000000000001' }, content: [{ type: 'text', text: 'Server' }] }] },
            metadata: { title: 'Server title' },
        } });
        expect(setContentCalls).toEqual([{ content: { type: 'doc', content: [{ type: 'paragraph', attrs: { id: 'blk_0000000000000001' }, content: [{ type: 'text', text: 'Server' }] }] }, emitUpdate: false }]);
        expect(editorElement.dataset.currentRevision).toBe('21');

        updateListener({ editor });
        listeners.get('window:publishing-document-updated')({ detail: {
            articleId: 2,
            baseRevisionId: 21,
            revisionId: 22,
            document: { version: 1, type: 'doc', content: [{ type: 'paragraph', attrs: { id: 'blk_0000000000000001' }, content: [{ type: 'text', text: 'Refused' }] }] },
            metadata: {},
        } });
        expect(setContentCalls).toHaveLength(1);
        expect(dispatched.some((event) => event.type === 'publishing-editor-conflict' && event.detail.articleId === 2)).toBe(true);
        globalThis.document.querySelector = () => editorElement;
        let blocked = 0;
        let stopped = 0;
        const event = { type: 'click', target: { closest: () => ({}) }, preventDefault: () => blocked++, stopImmediatePropagation: () => stopped++ };
        await listeners.get('capture:click')(event);
        await listeners.get('capture:submit')({ ...event, type: 'submit', target: { matches: () => true } });
        await listeners.get('capture:click')({ ...event, target: { closest: () => null } });
        expect(blocked).toBe(2);
        expect(stopped).toBe(2);
        expect(storage.size).toBeGreaterThan(0);

        const recoveryElement = { ...editorElement, dataset: { userId: '1', articleId: '4', currentRevision: '50', metadata: '{}' } };
        saveRecovery(sessionStorageShim, { userId: 1, articleId: 4, baseRevisionId: 49, document });
        window.confirm = () => true;
        listeners.get('admin:editor:ready')({ detail: { editor, element: recoveryElement } });
        await Promise.resolve();
        expect(recoveryElement.dataset.autosaveStatus).toBe('conflict');
        expect(setContentCalls.at(-1).content.content).toEqual(document.content);
        expect(dispatched.some(event => event.type === 'publishing-editor-conflict' && event.detail.articleId === 4 && event.detail.reason === 'recovered-older-revision')).toBe(true);

        storage.set('admin:publishing:recovery:v1:u5:a1:r1', 'mine');
        storage.set('admin:publishing:recovery:v1:u6:a1:r1', 'theirs');
        storage.set('unrelated', 'keep');

        const form = new FakeLogoutForm();
        form.dataset = { userId: '5' };
        form.matches = (selector) => selector === '[data-admin-logout]';

        listeners.get('submit')({ target: form });

        expect(storage.has('admin:publishing:recovery:v1:u5:a1:r1')).toBe(false);
        expect(storage.has('admin:publishing:recovery:v1:u6:a1:r1')).toBe(true);
        expect(storage.has('unrelated')).toBe(true);
    });

    test('recovery survives a server revision advance without crossing users or articles', async () => {
        const entries = new Map();
        const storage = { get length() { return entries.size; }, key: index => [...entries.keys()][index], getItem: key => entries.get(key), setItem: (key, value) => entries.set(key, value), removeItem: key => entries.delete(key) };
        saveRecovery(storage, { userId: 1, articleId: 2, baseRevisionId: 10, document });
        saveRecovery(storage, { userId: 2, articleId: 2, baseRevisionId: 11, document });
        saveRecovery(storage, { userId: 1, articleId: 3, baseRevisionId: 11, document });
        entries.set('admin:publishing:recovery:v1:u1:a2:r9', 'invalid payload');
        const recovered = await restoreRecovery(storage, { dataset: { userId: '1', articleId: '2', currentRevision: '11' } });
        expect(recovered.baseRevisionId).toBe(10);
        expect(recovered.document).toEqual(document);
        saveRecovery(storage, { userId: 1, articleId: 2, baseRevisionId: 11, document });
        expect(entries.has('admin:publishing:recovery:v1:u1:a2:r10')).toBe(false);
        expect(entries.has('admin:publishing:recovery:v1:u2:a2:r11')).toBe(true);
        expect(entries.has('admin:publishing:recovery:v1:u1:a3:r11')).toBe(true);
    });

    test('protected client metadata is explicit', () => {
        expect(markProtected({ type: 'paragraph', attrs: { id: 'blk_0000000000000001' } }).attrs.protected).toBe(true);
    });

    test('autosave queue exposes dirty beforeunload states', async () => {
        const queue = createAutosaveQueue({ delay: 0, save: async () => ({ ok: true }) });
        queue.enqueue({ document });
        expect(shouldWarnBeforeUnload(queue.state)).toBe(true);
        await queue.flush();
        expect(queue.state.status).toBe('saved');
    });

    test('autosave persists each queued edit before its save starts', async () => {
        const persisted = [];
        let resolver;
        const first = new Promise((resolve) => { resolver = resolve; });
        const queue = createAutosaveQueue({
            delay: 0,
            persist: (payload) => persisted.push(payload.document.content[0].content[0].text),
            save: async () => first,
        });

        queue.enqueue({ userId: 1, articleId: 2, baseRevisionId: 10, document });
        const firstFlush = queue.flush();
        queue.enqueue({ userId: 1, articleId: 2, baseRevisionId: 10, document: { ...document, content: [{ ...document.content[0], content: [{ type: 'text', text: 'Queued while saving' }] }] } });
        resolver({ ok: true, revisionId: 11 });
        await firstFlush;

        expect(persisted).toEqual(['Hello', 'Queued while saving', 'Queued while saving']);
    });

    test('an in-flight acknowledgement cannot clear an intervening remote conflict', async () => {
        let resolve;
        const statuses = [];
        const queue = createAutosaveQueue({ delay: 0, onChange: state => statuses.push(state.status), save: () => new Promise(done => { resolve = done; }) });
        queue.enqueue({ userId: 1, articleId: 2, baseRevisionId: null, document });
        const saving = queue.flush();
        queue.markConflict('Remote revision changed');
        resolve({ ok: true, revisionId: 1 });
        await saving;
        expect(statuses).toContain('saving');
        expect(queue.state.status).toBe('conflict');
        expect(queue.isClean()).toBe(false);
    });

    test('autosave rebases pending edits onto the acknowledged revision', async () => {
        const bases = [];
        let resolver;
        const first = new Promise((resolve) => { resolver = resolve; });
        const queue = createAutosaveQueue({ delay: 0, save: async (payload) => {
            bases.push(payload.baseRevisionId);
            if (bases.length === 1) return first;
            return { ok: true, revisionId: 12 };
        } });

        queue.enqueue({ userId: 1, articleId: 2, baseRevisionId: 10, document });
        const firstFlush = queue.flush();
        queue.enqueue({ userId: 1, articleId: 2, baseRevisionId: 10, document: { ...document, content: [{ ...document.content[0], content: [{ type: 'text', text: 'Newer' }] }] } });
        resolver({ ok: true, revisionId: 11 });
        await firstFlush;
        await queue.flush();

        expect(bases).toEqual([10, 11]);
    });

    test('autosave conflict preserves local base and exposes latest revision metadata', async () => {
        const savedPayloads = [];
        const queue = createAutosaveQueue({
            delay: 0,
            persist: (payload) => savedPayloads.push(payload),
            save: async () => ({
                ok: false,
                conflict: 'The article has changed since this edit began.',
                revisionId: 10,
                latestRevision: { id: 12, number: 2, excerpt: 'Saved elsewhere' },
            }),
        });

        queue.enqueue({ userId: 1, articleId: 2, baseRevisionId: 10, document });
        await queue.flush();

        expect(queue.state.status).toBe('conflict');
        expect(queue.state.latestRevision.id).toBe(12);
        expect(savedPayloads[0].baseRevisionId).toBe(10);
        expect(shouldWarnBeforeUnload(queue.state)).toBe(true);
    });

    test('autosave conflict blocks later edits from flushing until explicitly resolved', async () => {
        const savedPayloads = [];
        const queue = createAutosaveQueue({
            delay: 0,
            persist: (payload) => savedPayloads.push(payload.document.content[0].content[0].text),
            save: async (payload) => {
                savedPayloads.push(`save:${payload.document.content[0].content[0].text}`);
                return {
                    ok: false,
                    conflict: 'The article has changed since this edit began.',
                    latestRevision: { id: 12, number: 2, excerpt: 'Saved elsewhere' },
                };
            },
        });

        queue.enqueue({ userId: 1, articleId: 2, baseRevisionId: 10, document });
        await queue.flush();
        queue.enqueue({ userId: 1, articleId: 2, baseRevisionId: 10, document: { ...document, content: [{ ...document.content[0], content: [{ type: 'text', text: 'Blocked local edit' }] }] } });
        await queue.flush();

        expect(queue.state.status).toBe('conflict');
        expect(savedPayloads).toEqual(['Hello', 'save:Hello', 'Blocked local edit']);
    });

    test('autosave keeps recovery warnings active after failed non-conflict responses', async () => {
        const queue = createAutosaveQueue({ delay: 0, save: async () => ({ ok: false, error: 'Validation failed.' }) });
        queue.enqueue({ document });
        await queue.flush();
        expect(queue.state.status).toBe('error');
        expect(queue.state.error).toBe('Validation failed.');
        expect(shouldWarnBeforeUnload(queue.state)).toBe(true);
    });
});
