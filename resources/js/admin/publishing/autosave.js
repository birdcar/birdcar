import { createMutationId, parseRecoveryPayload, recoveryKey, serializeRecoveryPayload } from './document-helpers.js';

export function createAutosaveQueue({ save, delay = 250, persist = null, onChange = null } = {}) {
    let timer = null;
    const state = { status: 'saved', pending: null, inFlight: false, error: null, conflict: null, latestRevision: null };
    const listeners = new Set();

    function notify() {
        onChange?.(state);
        listeners.forEach((listener) => listener(state));
    }

    function isClean() {
        return state.status === 'saved' && !state.pending && state.inFlight === false;
    }

    function markConflict(conflict, latestRevision = null) {
        state.status = 'conflict';
        state.conflict = conflict;
        state.latestRevision = latestRevision;
        state.error = null;
        clearTimeout(timer);
        notify();
    }

    async function flush() {
        if (state.inFlight || !state.pending || state.status === 'conflict') return;
        const payload = state.pending;
        state.pending = null;
        state.inFlight = true;
        state.status = 'saving';
        notify();
        try {
            const result = await save(payload);
            if (state.status === 'conflict') {
                return;
            }
            if (result?.ok === true) {
                state.status = 'saved';
                state.error = null;
                state.conflict = null;
                state.latestRevision = null;
                if (state.pending && result.revisionId !== undefined && result.revisionId !== null) {
                    state.pending = {
                        ...state.pending,
                        baseRevisionId: result.revisionId,
                    };
                    state.pending.recoveryKey = recoveryKey(state.pending);
                    persist?.(state.pending);
                }
            } else if (result?.conflict) {
                state.status = 'conflict';
                state.conflict = result.conflict;
                state.latestRevision = result.latestRevision ?? null;
                state.error = null;
            } else {
                state.status = 'error';
                state.error = result?.error ?? 'The revision was not saved.';
                state.conflict = null;
                state.latestRevision = null;
            }
        } catch (error) {
            state.status = 'error';
            state.error = error;
            state.conflict = null;
            state.latestRevision = null;
        } finally {
            state.inFlight = false;
            notify();
            if (state.pending && state.status !== 'conflict') queueMicrotask(flush);
        }
    }

    function enqueue(payload) {
        const mutationId = payload.mutationId ?? createMutationId();
        const pending = { ...payload, mutationId };
        pending.recoveryKey = recoveryKey(pending);
        state.pending = pending;
        persist?.(pending);
        if (state.status === 'conflict') {
            clearTimeout(timer);
            notify();
            return state.pending.mutationId;
        }
        state.status = 'unsaved';
        clearTimeout(timer);
        timer = setTimeout(flush, delay);
        notify();
        return state.pending.mutationId;
    }

    function resolveConflict({ baseRevisionId = null } = {}) {
        state.conflict = null;
        state.latestRevision = null;
        state.error = null;
        if (state.pending) {
            if (baseRevisionId !== null && baseRevisionId !== undefined) {
                state.pending = { ...state.pending, baseRevisionId };
                state.pending.recoveryKey = recoveryKey(state.pending);
            }
            state.status = 'unsaved';
            clearTimeout(timer);
            timer = setTimeout(flush, delay);
            notify();
            return;
        }
        state.status = 'saved';
        notify();
    }

    function subscribe(listener) {
        listeners.add(listener);
        return () => listeners.delete(listener);
    }

    return { state, enqueue, flush, resolveConflict, markConflict, isClean, subscribe };
}

export function saveRecovery(storage, payload) {
    const key = payload.recoveryKey ?? recoveryKey(payload);
    storage.setItem(key, serializeRecoveryPayload(payload));
    const prefix = recoveryKey({ ...payload, baseRevisionId: null }).replace(/rnew$/, 'r');
    for (let index = storage.length - 1; index >= 0; index -= 1) {
        const previousKey = storage.key(index);
        if (previousKey !== key && previousKey?.startsWith(prefix)) storage.removeItem(previousKey);
    }
    return key;
}

export function clearRecovery(storage, payload) {
    storage.removeItem(payload.recoveryKey ?? recoveryKey(payload));
}

export async function restoreRecovery(storage, element) {
    const payload = {
        userId: Number(element.dataset.userId),
        articleId: Number(element.dataset.articleId),
        baseRevisionId: element.dataset.currentRevision ? Number(element.dataset.currentRevision) : null,
    };
    const prefix = recoveryKey({ ...payload, baseRevisionId: null }).replace(/rnew$/, 'r');
    const candidates = [];
    for (let index = 0; index < storage.length; index += 1) {
        const key = storage.key(index);
        if (!key?.startsWith(prefix)) continue;
        try {
            const candidate = parseRecoveryPayload(storage.getItem(key));
            if (Number(candidate.articleId) === payload.articleId) candidates.push({ ...candidate, userId: payload.userId, recoveryKey: key });
        } catch {
            continue;
        }
    }
    return candidates.sort((a, b) => String(b.savedAtClient ?? '').localeCompare(String(a.savedAtClient ?? '')))[0] ?? null;
}

export function shouldWarnBeforeUnload(state) {
    return ['unsaved', 'saving', 'error', 'conflict'].includes(state?.status);
}
