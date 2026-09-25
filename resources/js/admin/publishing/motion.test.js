import { expect, mock, test } from 'bun:test';

const animated = [];
mock.module('motion', () => ({
    animate: (element, keyframes, options) => {
        animated.push({ element, keyframes, options });
        return { finished: Promise.resolve(), stop: () => { element.stopped = true; } };
    },
}));

const { createPublishingSession, installPublishingMotion } = await import('./motion.js');

test('publishing session exposes mode controls and tactile tab focus', async () => {
    const focused = [];
    const tabs = new Map(['develop', 'write', 'release'].map((mode) => [mode, { dataset: { sessionTab: mode }, focus: () => focused.push(mode) }]));
    const root = {
        querySelector: (selector) => {
            const match = selector.match(/data-session-tab="(.*?)"/);
            return match ? tabs.get(match[1]) : null;
        },
        querySelectorAll: (selector) => (selector === '[data-session-panel]' ? [{ dataset: { sessionPanel: 'release' } }] : []),
    };
    globalThis.document = { querySelector: () => root };
    globalThis.matchMedia = () => ({ matches: false });

    const session = createPublishingSession('develop');
    session.$root = root;
    session.focusMode('release');
    await Promise.resolve();

    expect(session.mode).toBe('release');
    expect(focused).toEqual(['release']);
    expect(animated.length).toBeGreaterThanOrEqual(2);
});

test('reduced motion keeps publishing session content state available without animating', async () => {
    animated.length = 0;
    globalThis.matchMedia = () => ({ matches: true });
    globalThis.document = { querySelector: () => null };

    const session = createPublishingSession('write');
    session.selectMode('release');
    await Promise.resolve();

    expect(session.mode).toBe('release');
    expect(animated).toHaveLength(0);
});

test('published milestones create disposable aria-hidden celebration in the matching studio', () => {
    animated.length = 0;
    const appended = [];
    const listeners = new Map();
    const root = {
        appendChild: (element) => appended.push(element),
        querySelector: () => null,
    };
    globalThis.matchMedia = () => ({ matches: false });
    globalThis.document = {
        createElement: (tag) => ({ tag, dataset: {}, style: {}, setAttribute(name, value) { this[name] = value; }, remove() { this.removed = true; } }),
        querySelector: (selector) => (selector.includes('data-article-id="7"') || selector === '[data-publishing-studio]' ? root : null),
    };
    globalThis.window = { addEventListener: (name, listener) => listeners.set(name, listener) };

    installPublishingMotion({ data: () => {} });
    listeners.get('publishing-milestone')({ detail: { kind: 'published', articleId: 7 } });

    expect(appended[0].dataset.publishingCelebration).toBe('published');
    expect(appended[0]['aria-hidden']).toBe('true');
    expect(appended.filter((element) => element.dataset.publishingSpark !== undefined)).toHaveLength(9);
});
