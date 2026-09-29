import { expect, mock, test } from 'bun:test';

const animated = [];
mock.module('motion', () => ({
    animate: (element, keyframes, options) => {
        animated.push({ element, keyframes, options });
        return { finished: Promise.resolve(), stop: () => { element.stopped = true; } };
    },
}));

const { createPublishingArrivals, createPublishingSession, installPublishingMotion } = await import('./motion.js');

function publishingModeRoot() {
    const tabs = new Map(['develop', 'write', 'release'].map((mode) => [mode, { dataset: { sessionTab: mode } }]));
    const panels = ['develop', 'write', 'release'].map((mode) => ({ dataset: { sessionPanel: mode } }));
    return {
        tabs,
        panels,
        querySelector: (selector) => {
            const match = selector.match(/data-session-tab="(.*?)"/);
            return match ? tabs.get(match[1]) : null;
        },
        querySelectorAll: (selector) => (selector === '[data-session-panel]' ? panels : []),
    };
}

test('mode changes slide the new panel in from the side the tab sits on', async () => {
    const root = publishingModeRoot();
    globalThis.document = { querySelector: () => root };
    globalThis.matchMedia = () => ({ matches: false });

    const session = createPublishingSession('write');
    const watchers = {};
    session.$root = root;
    session.$watch = (key, callback) => { watchers[key] = callback; };
    session.init();
    await Promise.resolve();

    const panelMotion = async (from, to) => {
        animated.length = 0;
        session.selectMode(to);
        watchers.mode(to, from);
        await Promise.resolve();
        return animated.find((entry) => entry.element.dataset.sessionPanel === to);
    };

    const forward = await panelMotion('write', 'release');
    const backward = await panelMotion('release', 'develop');

    expect(session.mode).toBe('develop');
    expect(forward?.keyframes.transform).toEqual(['translateX(16px)', 'translateX(0)']);
    expect(backward?.keyframes.transform).toEqual(['translateX(-16px)', 'translateX(0)']);
    expect(animated.some((entry) => entry.keyframes.transform?.some((frame) => frame.includes('translateY')))).toBe(false);
});

test('list pages animate their arrivals without session state', async () => {
    animated.length = 0;
    const entries = [{ id: 'heading' }, { id: 'item' }];
    const root = { querySelectorAll: (selector) => (selector === '[data-publishing-enter]' ? entries : []) };
    globalThis.matchMedia = () => ({ matches: false });

    const arrivals = createPublishingArrivals();
    arrivals.$root = { closest: () => root };
    arrivals.init();
    await Promise.resolve();

    expect(arrivals).not.toHaveProperty('mode');
    expect(animated.map((entry) => entry.element)).toEqual(entries);
});

test('publishing session ignores unknown modes', () => {
    const session = createPublishingSession('write');

    session.selectMode('settings');

    expect(session.mode).toBe('write');
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
