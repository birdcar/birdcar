import { createMutationId, parseRecoveryPayload, recoveryKey, serializeRecoveryPayload } from './document-helpers.js';

export function createAutosaveQueue({ save, delay = 250, persist = null } = {}) {
    let timer = null;
    const state = { status: 'saved', pending: null, inFlight: false, error: null, conflict: null, latestRevision: null };

    async function flush() {
        if (state.inFlight || !state.pending || state.status === 'conflict') return;
        const payload = state.pending;
        state.pending = null;
        state.inFlight = true;
        state.status = 'saving';
        try {
            const result = await save(payload);
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
            return state.pending.mutationId;
        }
        state.status = 'unsaved';
        clearTimeout(timer);
        timer = setTimeout(flush, delay);
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
            return;
        }
        state.status = 'saved';
    }

    return { state, enqueue, flush, resolveConflict };
}

export function saveRecovery(storage, payload) {
    const key = payload.recoveryKey ?? recoveryKey(payload);
    storage.setItem(key, serializeRecoveryPayload(payload));
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
    const key = recoveryKey(payload);
    const value = storage.getItem(key);
    if (!value) return null;
    return { ...parseRecoveryPayload(value), userId: payload.userId, recoveryKey: key };
}

export function shouldWarnBeforeUnload(state) {
    return ['unsaved', 'saving', 'error', 'conflict'].includes(state?.status);
}
