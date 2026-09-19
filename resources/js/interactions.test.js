import { afterEach, beforeEach, expect, mock, test } from 'bun:test';
import { initInteractions } from './interactions';

const originalDocument = globalThis.document;
const originalWindow = globalThis.window;
let menu;
let summary;
let mobileViewport;

function dispatch(target, type, properties = {}) {
    const event = new Event(type);
    for (const [key, value] of Object.entries(properties)) {
        Object.defineProperty(event, key, { value });
    }
    target.dispatchEvent(event);
}

beforeEach(() => {
    summary = { focus: mock() };
    menu = Object.assign(new EventTarget(), {
        open: false,
        querySelector: () => summary,
        contains: (target) => target === summary,
    });
    mobileViewport = Object.assign(new EventTarget(), { matches: true });
    globalThis.document = Object.assign(new EventTarget(), { querySelector: () => menu });
    globalThis.window = { matchMedia: () => mobileViewport };
});

afterEach(() => {
    globalThis.document = originalDocument;
    globalThis.window = originalWindow;
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

test('pages without a menu do not attach menu enhancements', () => {
    document.querySelector = () => null;
    window.matchMedia = mock();

    initInteractions();

    expect(window.matchMedia).not.toHaveBeenCalled();
});
