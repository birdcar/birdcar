import { Extension, Node } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { createBlockId, documentToEditorJson, editorToDocumentJson, ensureStableBlockIds } from './document-helpers.js';

const registered = new WeakSet();

const stableAttributes = {
    id: { default: null, parseHTML: (element) => element.getAttribute('data-id'), renderHTML: (attrs) => (attrs.id ? { 'data-id': attrs.id } : {}) },
    protected: { default: false, parseHTML: (element) => element.getAttribute('data-protected') === 'true', renderHTML: (attrs) => (attrs.protected ? { 'data-protected': 'true' } : {}) },
};

const StableBlockAttributes = Extension.create({
    name: 'stableBlockAttributes',
    addGlobalAttributes() {
        return [{ types: ['paragraph', 'heading', 'blockquote', 'bulletList', 'orderedList', 'listItem', 'codeBlock', 'horizontalRule', 'note', 'callout', 'chart', 'diagram'], attributes: stableAttributes }];
    },
    addCommands() {
        return {
            setProtectedBlock: (isProtected = true) => ({ chain }) => chain().focus().updateAttributes('paragraph', { protected: isProtected }).updateAttributes('heading', { protected: isProtected }).updateAttributes('blockquote', { protected: isProtected }).updateAttributes('note', { protected: isProtected }).updateAttributes('callout', { protected: isProtected }).run(),
        };
    },
    addProseMirrorPlugins() {
        return [new Plugin({
            key: new PluginKey('publishingStableBlockIds'),
            appendTransaction(transactions, oldState, newState) {
                if (!transactions.some((transaction) => transaction.docChanged) && oldState.doc.eq(newState.doc)) return null;

                const seen = new Set();
                let transaction = null;
                newState.doc.descendants((node, pos) => {
                    if (node.isText) return;
                    const typeAllowsId = node.type.spec.attrs?.id !== undefined;
                    if (!typeAllowsId) return;

                    const id = node.attrs.id;
                    if (typeof id === 'string' && id.length > 0 && !seen.has(id)) {
                        seen.add(id);
                        return;
                    }

                    transaction ??= newState.tr;
                    const nextId = createBlockId();
                    transaction.setNodeMarkup(pos, undefined, { ...node.attrs, id: nextId });
                    seen.add(nextId);
                });

                return transaction;
            },
            props: {
                handlePaste(view, event) {
                    const html = event.clipboardData?.getData('text/html') ?? '';
                    if (/<script|on\w+=|javascript:/i.test(html)) {
                        event.preventDefault();
                        return true;
                    }
                    return false;
                },
            },
        })];
    },
});

const Note = Node.create({
    name: 'note',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes() { return { ...stableAttributes, title: { default: 'Note' } }; },
    parseHTML() { return [{ tag: '[data-article-note]' }]; },
    renderHTML({ HTMLAttributes }) { return ['aside', { ...HTMLAttributes, 'data-article-note': '' }, 0]; },
    addCommands() {
        return {
            insertNote: (attrs = {}) => ({ commands }) => commands.insertContent({ type: this.name, attrs: { id: createBlockId(), title: 'Note', ...attrs }, content: [{ type: 'paragraph', attrs: { id: createBlockId() }, content: [{ type: 'text', text: 'Note text' }] }] }),
        };
    },
});

const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes() { return { ...stableAttributes, title: { default: 'Key takeaway' }, style: { default: 'key' } }; },
    parseHTML() { return [{ tag: '[data-article-callout]' }]; },
    renderHTML({ HTMLAttributes }) { return ['aside', { ...HTMLAttributes, 'data-article-callout': '' }, 0]; },
    addCommands() {
        return {
            insertCallout: (attrs = {}) => ({ commands }) => commands.insertContent({ type: this.name, attrs: { id: createBlockId(), title: 'Key takeaway', style: 'key', ...attrs }, content: [{ type: 'paragraph', attrs: { id: createBlockId() }, content: [{ type: 'text', text: 'Callout text' }] }] }),
        };
    },
});

const Chart = Node.create({
    name: 'chart',
    group: 'block',
    atom: true,
    addAttributes() {
        return { ...stableAttributes, chartType: { default: 'bar' }, x: { default: 'label' }, series: { default: [{ key: 'value', label: 'Value' }] }, data: { default: [] }, caption: { default: null } };
    },
    parseHTML() { return [{ tag: '[data-article-chart]' }]; },
    renderHTML({ HTMLAttributes }) { return ['figure', { ...HTMLAttributes, 'data-article-chart': '', 'data-article-figure': '' }, ['figcaption', HTMLAttributes.caption ?? 'Chart']]; },
    addCommands() {
        return {
            insertChart: (attrs = {}) => ({ commands }) => commands.insertContent({ type: this.name, attrs: { id: createBlockId(), chartType: 'bar', x: 'label', series: [{ key: 'value', label: 'Value' }], data: [{ label: 'Example', value: 1 }], caption: 'Chart', ...attrs } }),
        };
    },
});

const Diagram = Node.create({
    name: 'diagram',
    group: 'block',
    atom: true,
    addAttributes() {
        return { ...stableAttributes, sourceType: { default: 'preset' }, name: { default: 'walkthrough' }, safeSvg: { default: null }, caption: { default: 'Diagram' } };
    },
    parseHTML() { return [{ tag: '[data-article-diagram]' }]; },
    renderHTML({ HTMLAttributes }) { return ['figure', { ...HTMLAttributes, 'data-article-diagram': '', 'data-article-figure': '' }, ['figcaption', HTMLAttributes.caption ?? 'Diagram']]; },
    addCommands() {
        return {
            insertDiagram: (attrs = {}) => ({ commands }) => commands.insertContent({ type: this.name, attrs: { id: createBlockId(), sourceType: 'preset', name: 'walkthrough', caption: 'Diagram', ...attrs } }),
        };
    },
});

document.addEventListener('flux:editor', (event) => {
    const hooks = event.detail;
    if (!hooks || registered.has(hooks)) return;
    registered.add(hooks);

    hooks.registerExtensions?.([StableBlockAttributes, Note, Callout, Chart, Diagram]);

    hooks.init?.(({ editor }) => {
        editor.on('create', ({ editor: readyEditor }) => {
            const element = readyEditor?.options?.element?.closest?.('[data-admin-editor]') ?? readyEditor?.options?.element;
            if (!element) return;
            element.dispatchEvent(new CustomEvent('admin:editor:init', { bubbles: true, detail: { editor: readyEditor, element } }));
        });
    });
});

document.addEventListener('flux:editor:ready', (event) => {
    const editor = event.detail?.editor;
    const element = editor?.options?.element?.closest?.('[data-admin-editor]') ?? editor?.options?.element;
    if (!editor || !element) return;

    const raw = element.dataset.document;
    if (!raw) return;

    try {
        const documentJson = JSON.parse(raw);
        const stableDocument = ensureStableBlockIds(documentJson);
        const hydrated = documentToEditorJson(stableDocument);
        editor.commands?.setContent?.(hydrated, false);
        const controls = element.closest('[data-admin-editor-shell]') ?? element.parentElement;
        controls?.querySelectorAll('[data-editor-command]')?.forEach((button) => {
            if (button.dataset.editorBound === 'true') return;
            button.dataset.editorBound = 'true';
            button.addEventListener('click', () => {
                const command = button.dataset.editorCommand;
                if (command === 'note') editor.chain().focus().insertNote().run();
                if (command === 'callout') editor.chain().focus().insertCallout().run();
                if (command === 'chart') editor.chain().focus().insertChart().run();
                if (command === 'diagram') editor.chain().focus().insertDiagram().run();
                if (command === 'protect') editor.chain().focus().setProtectedBlock(true).run();
                if (command === 'unprotect') editor.chain().focus().setProtectedBlock(false).run();
            });
        });
        element.dataset.document = JSON.stringify(editorToDocumentJson(editor.getJSON()));
        element.dispatchEvent(new CustomEvent('admin:editor:hydrated', { bubbles: true, detail: { document: editorToDocumentJson(editor.getJSON()) } }));
        document.dispatchEvent(new CustomEvent('admin:editor:ready', { detail: { editor, element } }));
    } catch (error) {
        element.dispatchEvent(new CustomEvent('admin:editor:error', { bubbles: true, detail: { message: error.message } }));
    }
});
