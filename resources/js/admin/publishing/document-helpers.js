const BLOCK_ID_PATTERN = /^(blk|imp)_[0-9a-f]{16}$/;
const SCHEMA_VERSION = 1;

export function createMutationId(prefix = 'mut') {
    const bytes = new Uint8Array(8);
    if (globalThis.crypto?.getRandomValues) crypto.getRandomValues(bytes);
    else for (let index = 0; index < bytes.length; index += 1) bytes[index] = Math.floor(Math.random() * 256);
    return `${prefix}_${Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('')}`;
}

export function createBlockId(prefix = 'blk') {
    return createMutationId(prefix).replace(/^mut_/, `${prefix}_`);
}

export function isSupportedDocument(document) {
    return document?.version === SCHEMA_VERSION && document?.type === 'doc' && Array.isArray(document?.content);
}

export function normalizeDocument(document) {
    if (!isSupportedDocument(document)) {
        throw new Error('Document must be canonical version 1 doc JSON.');
    }
    return ensureStableBlockIds({ version: 1, type: 'doc', content: normalizeNodes(document.content) });
}

function normalizeNodes(nodes) {
    return nodes.map((node) => normalizeNode(node));
}

function normalizeNode(node) {
    if (!node || typeof node !== 'object' || typeof node.type !== 'string') throw new Error('Unsupported document node.');
    const normalized = { type: node.type };
    if (node.attrs && typeof node.attrs === 'object') normalized.attrs = { ...node.attrs };
    if (Array.isArray(node.content)) normalized.content = normalizeNodes(node.content);
    if (Array.isArray(node.marks)) normalized.marks = node.marks.map((mark) => ({ ...mark, attrs: mark.attrs ? { ...mark.attrs } : undefined })).filter(Boolean);
    if (typeof node.text === 'string') normalized.text = node.text;
    return normalized;
}

export function ensureStableBlockIds(document) {
    const seen = new Set();
    const clone = typeof structuredClone === 'function' ? structuredClone(document) : JSON.parse(JSON.stringify(document));
    walk(clone.content ?? [], (node) => {
        if (!node || typeof node !== 'object' || node.type === 'text') return;
        node.attrs = node.attrs && typeof node.attrs === 'object' ? node.attrs : {};
        if (typeof node.attrs.id !== 'string' || !BLOCK_ID_PATTERN.test(node.attrs.id) || seen.has(node.attrs.id)) {
            node.attrs.id = createBlockId(node.type === 'importedBlock' ? 'imp' : 'blk');
        }
        seen.add(node.attrs.id);
    });
    return clone;
}

function walk(nodes, callback) {
    if (!Array.isArray(nodes)) return;
    nodes.forEach((node) => {
        callback(node);
        walk(node?.content, callback);
    });
}

export function documentToEditorJson(document) {
    const normalized = normalizeDocument(document);
    return { type: 'doc', content: normalized.content };
}

export function editorToDocumentJson(editorJson) {
    if (editorJson?.type !== 'doc' || !Array.isArray(editorJson?.content)) throw new Error('Editor JSON must be a doc.');
    return normalizeDocument({ version: SCHEMA_VERSION, type: 'doc', content: editorJson.content });
}

export function markProtected(node, protectedValue = true) {
    return { ...node, attrs: { ...(node.attrs ?? {}), protected: protectedValue } };
}

export function recoveryKey({ userId, articleId, baseRevisionId }) {
    return `admin:publishing:recovery:v${SCHEMA_VERSION}:u${userId}:a${articleId}:r${baseRevisionId ?? 'new'}`;
}

export function clearPublishingRecoveryNamespace(storage, userId) {
    if (!storage || userId === undefined || userId === null || userId === '') return;
    const prefix = `admin:publishing:recovery:v${SCHEMA_VERSION}:u${userId}:`;
    for (let index = storage.length - 1; index >= 0; index -= 1) {
        const key = storage.key(index);
        if (key?.startsWith(prefix)) storage.removeItem(key);
    }
}

export function serializeRecoveryPayload(payload) {
    if (!isSupportedDocument(payload.document)) throw new Error('Recovery payload document is invalid.');
    return JSON.stringify({
        articleId: payload.articleId,
        baseRevisionId: payload.baseRevisionId ?? null,
        mutationId: payload.mutationId ?? createMutationId(),
        document: normalizeDocument(payload.document),
        metadata: payload.metadata ?? {},
        savedAtClient: payload.savedAtClient ?? new Date().toISOString(),
    });
}

export function parseRecoveryPayload(value) {
    const payload = JSON.parse(value);
    payload.document = normalizeDocument(payload.document);
    return payload;
}

export function shouldKeepLocalEdits({ responseRevisionId, localBaseRevisionId, hasPendingChanges }) {
    return Boolean(hasPendingChanges && responseRevisionId !== localBaseRevisionId);
}
