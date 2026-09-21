import { afterEach, beforeEach, expect, mock, spyOn, test } from 'bun:test';
import posthog from 'posthog-js';
import { initAnalytics } from './analytics';
import { initBooking } from './booking';

const originals = Object.fromEntries(['document', 'window', 'getComputedStyle', 'customElements'].map((key) => [key, globalThis[key]]));
let api;
let source;
let colors;
let ready;
let inline;
let capture;
let scripts;

beforeEach(() => {
    api = mock();
    source = { dataset: { calNamespace: 'walkthrough', calLink: 'birdcar/walkthrough' } };
    colors = {};
    ready = false;
    inline = false;
    scripts = [];
    globalThis.document = Object.assign(new EventTarget(), {
        body: { dataset: { posthogToken: 'local-test-token', posthogHost: 'https://analytics.invalid' } },
        documentElement: {},
        head: { appendChild: (script) => { scripts.push(script); return script; } },
        createElement: () => new EventTarget(),
        querySelector: (selector) => selector === '[data-cal-inline]' && !inline ? null : source,
    });
    globalThis.window = {
        Cal: Object.assign(mock(), { ns: { walkthrough: api } }),
        location: { search: '', pathname: '/walkthrough' },
    };
    spyOn(posthog, 'init').mockImplementation(() => {});
    capture = spyOn(posthog, 'capture').mockImplementation(() => {});
    initAnalytics();
    globalThis.getComputedStyle = () => ({ getPropertyValue: (key) => colors[key] ?? '' });
    globalThis.customElements = { get: () => ready ? class {} : undefined };
});

afterEach(() => {
    mock.restore();
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

test('pages without booking controls do not load or configure Cal', () => {
    source = null;

    initBooking();

    expect(window.Cal).not.toHaveBeenCalled();
    expect(api).not.toHaveBeenCalled();
    expect(scripts).toHaveLength(0);
});

test.each([false, true])('the official loader queues configuration and callbacks until its script loads (inline: %s)', (isInline) => {
    inline = isInline;
    delete window.Cal;

    initBooking();

    expect(scripts).toHaveLength(1);
    expect(scripts[0].src).toBe('https://app.cal.com/embed/embed.js');
    expect(window.Cal.config.forwardQueryParams).toBe(true);
    expect(window.Cal.q.map((args) => [...args])).toEqual([['initNamespace', 'walkthrough']]);
    const queued = window.Cal.ns.walkthrough.q.map((args) => [...args]);
    expect(queued[0]).toEqual(['init', 'walkthrough', { origin: 'https://app.cal.com' }]);
    expect(queued.filter(([command]) => command === 'on').map(([, options]) => options.action)).toEqual(['linkReady', 'bookingSuccessfulV2']);
    expect(queued.some(([command]) => command === 'inline')).toBe(isInline);
    expect(capture).not.toHaveBeenCalled();

    scripts[0].dispatchEvent(new Event('error'));

    expect(click().defaultPrevented).toBe(false);
    expect(scripts).toHaveLength(1);
});

test('loaded Cal reuses its script and leaves already-cancelled navigation alone', () => {
    ready = true;
    initBooking();

    const event = new Event('click', { cancelable: true });
    event.preventDefault();
    Object.defineProperty(event, 'target', { value: { closest: () => source } });
    event.stopPropagation = mock();
    document.dispatchEvent(event);

    expect(scripts).toHaveLength(0);
    expect(event.stopPropagation).not.toHaveBeenCalled();
});

test.each([false, true])('readiness retains its legacy event name and mode (inline: %s)', (isInline) => {
    inline = isInline;
    initBooking();
    const callback = api.mock.calls.find(([command, options]) => command === 'on' && options.action === 'linkReady')[1].callback;

    callback();
    callback();

    expect(capture.mock.calls).toEqual([
        ['booking_embed_opened', { mode: isInline ? 'inline' : 'modal' }],
        ['booking_embed_opened', { mode: isInline ? 'inline' : 'modal' }],
    ]);
});

test.each([false, true])('completed booking forwards only allowed properties (inline: %s)', (isInline) => {
    inline = isInline;
    initBooking();
    const callback = api.mock.calls.find(([command, options]) => command === 'on' && options.action === 'bookingSuccessfulV2')[1].callback;

    callback({ detail: { data: {
        uid: 'synthetic-booking', eventTypeId: 42, startTime: '2030-01-02T12:00:00Z', status: 'ACCEPTED',
        email: 'private@example.invalid', attendees: [{ name: 'Private name' }], title: 'Private title',
        responses: { notes: 'Private form text' }, mode: 'untrusted',
    } } });

    expect(capture.mock.calls).toEqual([['booking_completed', {
        mode: isInline ? 'inline' : 'modal', booking_uid: 'synthetic-booking', event_type_id: 42,
        start_time: '2030-01-02T12:00:00Z', status: 'ACCEPTED',
    }]]);
});

test.each([false, true])('completed booking preserves a nullable event type (inline: %s)', (isInline) => {
    inline = isInline;
    initBooking();
    const callback = api.mock.calls.find(([command, options]) => command === 'on' && options.action === 'bookingSuccessfulV2')[1].callback;

    callback({ detail: { data: { uid: 'nullable-type', eventTypeId: null } } });

    expect(capture.mock.calls).toEqual([['booking_completed', {
        mode: isInline ? 'inline' : 'modal', booking_uid: 'nullable-type', event_type_id: null,
        start_time: undefined, status: undefined,
    }]]);
});

test('incomplete inline booking data retains inline mode without inventing optional values', () => {
    inline = true;
    initBooking();
    const callback = api.mock.calls.find(([command, options]) => command === 'on' && options.action === 'bookingSuccessfulV2')[1].callback;

    callback({ detail: {} });

    expect(capture.mock.calls).toEqual([['booking_completed', {
        mode: 'inline', booking_uid: undefined, event_type_id: undefined,
        start_time: undefined, status: undefined,
    }]]);
});

test.each([{}, { detail: {} }, { detail: { data: null } }, { detail: { data: { uid: 'partial' } } }])('incomplete booking payloads do not throw or forward unexpected values %j', (event) => {
    initBooking();
    const callback = api.mock.calls.find(([command, options]) => command === 'on' && options.action === 'bookingSuccessfulV2')[1].callback;

    callback(event);

    expect(capture.mock.calls).toEqual([['booking_completed', {
        mode: 'modal', booking_uid: event.detail?.data?.uid, event_type_id: undefined,
        start_time: undefined, status: undefined,
    }]]);
});
