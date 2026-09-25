import { afterEach, beforeEach, expect, mock, spyOn, test } from 'bun:test';

const animations = [];
mock.module('motion', () => ({
    animate: (element, keyframes, options) => {
        const finished = Promise.withResolvers();
        const animation = Object.assign(finished.promise, {
            element, keyframes, options,
            cancel: mock(), finish: finished.resolve,
        });
        animations.push(animation);
        return animation;
    },
}));
const { initDiagrams } = await import('./diagrams');
const originalDocument = globalThis.document;
const originalWindow = globalThis.window;
let fonts;
let figures;
let reducedMotion;
let print;
let observer;
let clock;

function figure() {
    const stages = Array.from({ length: 4 }, () => ({ style: { removeProperty: mock() } }));
    const recommendation = { style: { removeProperty: mock() } };
    return { isConnected: true, stages, recommendation, querySelectorAll: () => stages, querySelector: () => recommendation };
}

function intersect(target = figures[0], ratio = 1) {
    observer.callback([{ target, isIntersecting: ratio > 0, intersectionRatio: ratio }]);
}

async function ready() {
    fonts.resolve();
    await fonts.promise;
}

beforeEach(() => {
    animations.length = 0;
    fonts = Promise.withResolvers();
    figures = [figure()];
    reducedMotion = Object.assign(new EventTarget(), { matches: false });
    print = Object.assign(new EventTarget(), { matches: false });
    observer = undefined;
    globalThis.document = Object.assign(new EventTarget(), {
        hidden: false, body: {}, fonts: { ready: fonts.promise }, querySelectorAll: () => figures,
    });
    globalThis.window = Object.assign(new EventTarget(), {
        matchMedia: (query) => query === 'print' ? print : reducedMotion,
        getComputedStyle: () => ({ getPropertyValue: (name) => name === '--studio-yellow' ? '#f7c848' : '#efb925' }),
        IntersectionObserver: class {
            constructor(callback) {
                this.callback = callback;
                this.observe = mock();
                this.unobserve = mock();
                this.disconnect = mock();
                observer = this;
            }
        },
    });
    clock = spyOn(performance, 'now').mockReturnValue(100);
});

afterEach(() => {
    globalThis.document = originalDocument;
    globalThis.window = originalWindow;
    clock.mockRestore();
});

test('the explanation waits for fonts and viewport entry without hiding any content', async () => {
    initDiagrams();
    expect(observer.observe).not.toHaveBeenCalled();
    await ready();
    expect(observer.observe).toHaveBeenCalledWith(figures[0]);
    expect(animations).toHaveLength(0);

    intersect();

    expect(animations).toHaveLength(5);
    expect(animations.slice(0, 4).map((animation) => animation.options.delay)).toEqual([0, 0.32, 0.64, 0.96]);
    for (const animation of animations) {
        expect(animation.keyframes.opacity).toBeUndefined();
        expect(animation.options.repeat).toBeUndefined();
        expect(animation.keyframes.transform[0]).toBe(animation.keyframes.transform.at(-1));
    }
    expect(animations[4].element).toBe(figures[0].recommendation);
});

test.each(['reduced motion', 'hidden tab', 'printing', 'late fonts'])('keeps the static explanation with %s', async (condition) => {
    initDiagrams();
    if (condition === 'reduced motion') reducedMotion.matches = true;
    if (condition === 'hidden tab') document.hidden = true;
    if (condition === 'printing') print.matches = true;
    if (condition === 'late fonts') clock.mockReturnValue(1500);

    await ready();

    expect(observer.observe).not.toHaveBeenCalled();
    expect(animations).toHaveLength(0);
});

test('restored scroll does not animate a figure outside the viewport', async () => {
    window.scrollY = 1800;
    initDiagrams();
    await ready();

    intersect(figures[0], 0);
    intersect(figures[0], 0.1);

    expect(animations).toHaveLength(0);
    expect(observer.unobserve).not.toHaveBeenCalled();
});

test.each(['reduced motion', 'hidden tab', 'page exit', 'beforeprint', 'print media'])('restores the static explanation when interrupted by %s', async (condition) => {
    initDiagrams();
    await ready();
    intersect();

    if (condition === 'reduced motion') {
        reducedMotion.matches = true;
        reducedMotion.dispatchEvent(new Event('change'));
    } else if (condition === 'hidden tab') {
        document.hidden = true;
        document.dispatchEvent(new Event('visibilitychange'));
    } else if (condition === 'print media') {
        print.matches = true;
        print.dispatchEvent(new Event('change'));
    } else {
        window.dispatchEvent(new Event(condition === 'page exit' ? 'pagehide' : 'beforeprint'));
    }

    expect(observer.disconnect).toHaveBeenCalledTimes(1);
    for (const animation of animations) {
        expect(animation.cancel).toHaveBeenCalledTimes(1);
        expect(animation.element.style.removeProperty).toHaveBeenCalledWith('transform');
        expect(animation.element.style.removeProperty).toHaveBeenCalledWith('background-color');
    }
});

test('a tab hidden while fonts load does not replay when restored', async () => {
    initDiagrams();
    document.hidden = true;
    document.dispatchEvent(new Event('visibilitychange'));
    document.hidden = false;
    document.dispatchEvent(new Event('visibilitychange'));

    await ready();

    expect(observer.observe).not.toHaveBeenCalled();
});

test('switching motion back on does not replay a dismissed figure', async () => {
    reducedMotion.matches = true;
    initDiagrams();
    reducedMotion.matches = false;
    reducedMotion.dispatchEvent(new Event('change'));
    await ready();

    expect(observer.observe).not.toHaveBeenCalled();
});

test('finished animations release styles and are not cancelled again', async () => {
    initDiagrams();
    await ready();
    intersect();
    for (const animation of animations) animation.finish();
    await Promise.resolve();

    window.dispatchEvent(new Event('beforeprint'));

    for (const animation of animations) {
        expect(animation.cancel).not.toHaveBeenCalled();
        expect(animation.element.style.removeProperty).toHaveBeenCalledWith('transform');
    }
});

test('pages without a figure do not run a sequence or attach observers', () => {
    figures = [];

    initDiagrams();

    expect(observer).toBeUndefined();
    expect(animations).toHaveLength(0);
});

test('repeated figures animate independently and initializing again does not attach twice', async () => {
    figures.push(figure());
    initDiagrams();
    initDiagrams();
    await ready();
    expect(observer.observe).toHaveBeenCalledTimes(2);

    intersect(figures[0]);
    expect(animations).toHaveLength(5);
    expect(observer.unobserve).toHaveBeenCalledWith(figures[0]);
    intersect(figures[0]);
    expect(animations).toHaveLength(5);
    intersect(figures[1]);
    expect(animations).toHaveLength(10);
    expect(animations[5].element).toBe(figures[1].stages[0]);
});

test('a detached figure is not animated by an already queued observer callback', async () => {
    initDiagrams();
    await ready();
    figures[0].isConnected = false;

    intersect();

    expect(animations).toHaveLength(0);
});

test('browsers without intersection observation retain the static explanation', () => {
    window.IntersectionObserver = undefined;

    initDiagrams();

    expect(animations).toHaveLength(0);
});
