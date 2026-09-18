import { afterEach, beforeEach, expect, mock, spyOn, test } from 'bun:test';

const animations = [];

mock.module('motion', () => ({
    animate: (elements, keyframes, options) => {
        const finished = Promise.withResolvers();
        const animation = Object.assign(finished.promise, {
            elements, keyframes, options,
            complete: mock(() => finished.resolve()),
            finish: finished.resolve,
        });
        animations.push(animation);
        return animation;
    },
    stagger: (delay) => (index) => index * delay,
}));

const { initInteractions } = await import('./interactions');
const originalDocument = globalThis.document;
const originalWindow = globalThis.window;
let fonts;
let menu;
let summary;
let reducedMotion;
let mobileViewport;
let clock;

function dispatch(target, type, properties = {}) {
    const event = new Event(type);
    for (const [key, value] of Object.entries(properties)) {
        Object.defineProperty(event, key, { value });
    }
    target.dispatchEvent(event);
}

beforeEach(() => {
    animations.length = 0;
    fonts = Promise.withResolvers();
    summary = { focus: mock() };
    menu = Object.assign(new EventTarget(), {
        open: false,
        querySelector: () => summary,
        contains: (target) => target === summary,
    });
    reducedMotion = Object.assign(new EventTarget(), { matches: false });
    mobileViewport = Object.assign(new EventTarget(), { matches: true });
    const opening = { querySelector: () => ({}), querySelectorAll: () => [{}, {}, {}] };

    globalThis.document = Object.assign(new EventTarget(), {
        hidden: false,
        fonts: { ready: fonts.promise },
        querySelector: (selector) => selector === '.mobile-menu' ? menu : opening,
    });
    globalThis.window = Object.assign(new EventTarget(), {
        scrollY: 0,
        matchMedia: (query) => query.includes('reduced-motion') ? reducedMotion : mobileViewport,
    });
    clock = spyOn(performance, 'now').mockReturnValue(100);
});

afterEach(() => {
    globalThis.document = originalDocument;
    globalThis.window = originalWindow;
    clock.mockRestore();
});

test('the opening waits for fonts and keeps content visible throughout the entrance', async () => {
    initInteractions();
    expect(animations).toHaveLength(0);

    fonts.resolve();
    await fonts.promise;

    expect(animations).toHaveLength(2);
    for (const animation of animations) {
        expect(Math.min(...animation.keyframes.opacity)).toBeGreaterThan(0);
        expect(animation.keyframes.opacity.at(-1)).toBe(1);
    }
});

test.each(['reduced motion', 'hidden tab', 'restored scroll', 'late fonts'])('skips the opening with %s', async (condition) => {
    initInteractions();
    if (condition === 'reduced motion') reducedMotion.matches = true;
    if (condition === 'hidden tab') document.hidden = true;
    if (condition === 'restored scroll') window.scrollY = 400;
    if (condition === 'late fonts') clock.mockReturnValue(1500);

    fonts.resolve();
    await fonts.promise;

    expect(animations).toHaveLength(0);
});

test.each(['reduced motion', 'hidden tab', 'page exit'])('finishes the opening when interrupted by %s', async (condition) => {
    initInteractions();
    fonts.resolve();
    await fonts.promise;

    if (condition === 'reduced motion') {
        reducedMotion.matches = true;
        dispatch(reducedMotion, 'change');
    } else if (condition === 'hidden tab') {
        document.hidden = true;
        dispatch(document, 'visibilitychange');
    } else {
        dispatch(window, 'pagehide');
    }

    expect(animations).toHaveLength(2);
    for (const animation of animations) expect(animation.complete).toHaveBeenCalledTimes(1);
});

test('a tab hidden while fonts load does not replay the opening when restored', async () => {
    initInteractions();
    document.hidden = true;
    dispatch(document, 'visibilitychange');
    document.hidden = false;
    dispatch(document, 'visibilitychange');

    fonts.resolve();
    await fonts.promise;

    expect(animations).toHaveLength(0);
});

test('finished animations are released rather than completed again', async () => {
    initInteractions();
    fonts.resolve();
    await fonts.promise;
    for (const animation of animations) animation.finish();
    await Promise.resolve();

    reducedMotion.matches = true;
    dispatch(reducedMotion, 'change');

    for (const animation of animations) expect(animation.complete).not.toHaveBeenCalled();
});

test('Escape dismisses the open menu and restores focus only when it was open', () => {
    initInteractions();
    menu.open = true;

    dispatch(document, 'keydown', { key: 'Escape' });
    dispatch(document, 'keydown', { key: 'Escape' });

    expect(menu.open).toBe(false);
    expect(summary.focus).toHaveBeenCalledTimes(1);
});

test('clicking a menu link dismisses the menu without delaying navigation', () => {
    initInteractions();
    menu.open = true;

    dispatch(menu, 'click', { target: { closest: () => ({}) } });

    expect(menu.open).toBe(false);
    expect(summary.focus).not.toHaveBeenCalled();
});

test('outside clicks dismiss the menu but interactions inside it do not', () => {
    initInteractions();
    menu.open = true;

    dispatch(document, 'click', { target: summary });
    expect(menu.open).toBe(true);
    dispatch(document, 'click', { target: {} });
    expect(menu.open).toBe(false);
});

test('switching to desktop clears the mobile menu state without stealing focus', () => {
    initInteractions();
    menu.open = true;

    mobileViewport.matches = false;
    dispatch(mobileViewport, 'change');

    expect(menu.open).toBe(false);
    expect(summary.focus).not.toHaveBeenCalled();
});

test('reading pages do not run an opening sequence', async () => {
    document.querySelector = (selector) => selector === '.mobile-menu' ? menu : null;
    initInteractions();

    fonts.resolve();
    await fonts.promise;

    expect(animations).toHaveLength(0);
});
