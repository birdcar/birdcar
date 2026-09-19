import { afterEach, beforeEach, expect, mock, test } from 'bun:test';
import { initBooking } from './booking';

const originals = Object.fromEntries(['document', 'window', 'getComputedStyle', 'customElements'].map((key) => [key, globalThis[key]]));
let api;
let source;
let colors;
let ready;
let inline;

beforeEach(() => {
    api = mock();
    source = { dataset: { calNamespace: 'walkthrough', calLink: 'birdcar/walkthrough' } };
    colors = {};
    ready = false;
    inline = false;
    globalThis.document = Object.assign(new EventTarget(), {
        documentElement: {},
        querySelector: (selector) => selector === '[data-cal-inline]' && !inline ? null : source,
    });
    globalThis.window = { Cal: Object.assign(mock(), { ns: { walkthrough: api } }) };
    globalThis.getComputedStyle = () => ({ getPropertyValue: (key) => colors[key] ?? '' });
    globalThis.customElements = { get: () => ready ? class {} : undefined };
});

afterEach(() => {
    for (const [key, value] of Object.entries(originals)) {
        if (value === undefined) delete globalThis[key];
        else globalThis[key] = value;
    }
});

function click(properties = {}) {
    const event = new Event('click', { cancelable: true });
    const values = { button: 0, target: { closest: () => source }, ...properties };
    for (const [key, value] of Object.entries(values)) {
        Object.defineProperty(event, key, { value });
    }
    event.stopPropagation = mock(event.stopPropagation.bind(event));
    document.dispatchEvent(event);
    return event;
}

test('calendar theme uses the clear argument fallback colors when tokens are unavailable', () => {
    initBooking();

    expect(api.mock.calls.find(([command]) => command === 'ui')[1].cssVarsPerTheme).toEqual({
        light: { 'cal-brand': '#102a33', 'cal-brand-emphasis': '#214b57', 'cal-brand-text': '#ffffff', 'cal-bg': '#ffffff' },
        dark: { 'cal-brand': '#b7edf1', 'cal-brand-emphasis': '#ffffff', 'cal-brand-text': '#102a33' },
    });
});

test('calendar theme follows the active marketing tokens for inline and modal views', () => {
    colors = { '--color-ink': ' ink ', '--color-paper': ' paper ', '--color-cyan': ' cyan ', '--color-deep-teal': ' teal ' };
    inline = true;

    initBooking();

    expect(api.mock.calls.find(([command]) => command === 'ui')[1].cssVarsPerTheme).toEqual({
        light: { 'cal-brand': 'ink', 'cal-brand-emphasis': 'teal', 'cal-brand-text': 'paper', 'cal-bg': 'paper' },
        dark: { 'cal-brand': 'cyan', 'cal-brand-emphasis': 'paper', 'cal-brand-text': 'ink' },
    });
    expect(api.mock.calls.find(([command]) => command === 'inline')[1]).toMatchObject({
        elementOrSelector: source,
        calLink: 'birdcar/walkthrough',
        config: { theme: 'light' },
    });
});

test('booking links retain native navigation until the modal can take over', () => {
    initBooking();

    expect(click().defaultPrevented).toBe(false);
    ready = true;
    expect(click().defaultPrevented).toBe(true);
});

test.each(['altKey', 'ctrlKey', 'metaKey', 'shiftKey'])('booking never intercepts %s clicks', (modifier) => {
    ready = true;
    initBooking();

    const event = click({ [modifier]: true });

    expect(event.defaultPrevented).toBe(false);
    expect(event.stopPropagation).toHaveBeenCalledTimes(1);
});

test('booking never intercepts middle clicks or unrelated links', () => {
    ready = true;
    initBooking();

    expect(click({ button: 1 }).defaultPrevented).toBe(false);
    expect(click({ target: { closest: () => null } }).defaultPrevented).toBe(false);
});
