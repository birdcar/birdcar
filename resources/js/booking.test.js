import { afterEach, beforeEach, expect, mock, spyOn, test } from 'bun:test';
import posthog from 'posthog-js';
import { initAnalytics } from './analytics';
import { initBooking } from './booking';

const originals = Object.fromEntries(['document', 'window', 'getComputedStyle'].map((key) => [key, globalThis[key]]));
let api;
let inline;
let colors;
let present;
let capture;
let scripts;

beforeEach(() => {
    api = mock();
    inline = Object.assign(new EventTarget(), { dataset: { calInline: '', calNamespace: 'walkthrough', calLink: 'birdcar/walkthrough' } });
    colors = {};
    present = true;
    scripts = [];
    globalThis.document = Object.assign(new EventTarget(), {
        body: { dataset: { posthogToken: 'local-test-token', posthogHost: 'https://analytics.invalid' } },
        head: { appendChild: (script) => { scripts.push(script); return script; } },
        createElement: () => new EventTarget(),
        querySelector: (selector) => selector === '[data-cal-inline]' && present ? inline : null,
    });
    globalThis.window = {
        Cal: Object.assign(mock(), { ns: { walkthrough: api } }),
        location: { search: '', pathname: '/walkthrough' },
    };
    spyOn(posthog, 'init').mockImplementation(() => {});
    capture = spyOn(posthog, 'capture').mockImplementation(() => {});
    initAnalytics();
    globalThis.getComputedStyle = () => ({ getPropertyValue: (key) => colors[key] ?? '' });
});

afterEach(() => {
    mock.restore();
    for (const [key, value] of Object.entries(originals)) {
        if (value === undefined) delete globalThis[key];
        else globalThis[key] = value;
    }
});

function callback(action) {
    return api.mock.calls.find(([command, options]) => command === 'on' && options.action === action)[1].callback;
}

test('calendar theme falls back to the studio palette when tokens are unavailable', () => {
    initBooking();

    expect(api.mock.calls.find(([command]) => command === 'ui')[1].cssVarsPerTheme).toEqual({
        light: { 'cal-brand': '#f7c848', 'cal-brand-emphasis': '#efb925', 'cal-brand-text': '#0b141a', 'cal-bg': '#ffffff' },
    });
});

test('calendar theme follows the active studio tokens', () => {
    colors = { '--studio-yellow': ' yellow ', '--studio-yellow-deep': ' deep ', '--studio-ink': ' ink ', '--studio-paper': ' paper ' };

    initBooking();

    expect(api.mock.calls.find(([command]) => command === 'ui')[1].cssVarsPerTheme).toEqual({
        light: { 'cal-brand': 'yellow', 'cal-brand-emphasis': 'deep', 'cal-brand-text': 'ink', 'cal-bg': 'paper' },
    });
    expect(api.mock.calls.find(([command]) => command === 'inline')[1]).toMatchObject({
        elementOrSelector: inline,
        calLink: 'birdcar/walkthrough',
        config: { theme: 'light' },
    });
});

test('pages without the inline calendar do not load or configure Cal', () => {
    present = false;

    initBooking();

    expect(window.Cal).not.toHaveBeenCalled();
    expect(api).not.toHaveBeenCalled();
    expect(scripts).toHaveLength(0);
});

test('a gated calendar loads Cal only once the fit card opens it', () => {
    inline.dataset.calDefer = '';
    delete window.Cal;

    initBooking();

    expect(window.Cal).toBeUndefined();
    expect(scripts).toHaveLength(0);

    inline.dispatchEvent(new Event('booking:open'));
    inline.dispatchEvent(new Event('booking:open'));

    expect(scripts).toHaveLength(1);
    expect(window.Cal.ns.walkthrough.q.filter(([command]) => command === 'inline')).toHaveLength(1);
});

test('the official loader queues configuration and callbacks until its script loads', () => {
    delete window.Cal;

    initBooking();

    expect(scripts).toHaveLength(1);
    expect(scripts[0].src).toBe('https://app.cal.com/embed/embed.js');
    expect(window.Cal.config.forwardQueryParams).toBe(true);
    expect(window.Cal.q.map((args) => [...args])).toEqual([['initNamespace', 'walkthrough']]);
    const queued = window.Cal.ns.walkthrough.q.map((args) => [...args]);
    expect(queued[0]).toEqual(['init', 'walkthrough', { origin: 'https://app.cal.com' }]);
    expect(queued.filter(([command]) => command === 'on').map(([, options]) => options.action)).toEqual(['linkReady', 'bookingSuccessfulV2']);
    expect(queued.some(([command]) => command === 'inline')).toBe(true);
    expect(capture).not.toHaveBeenCalled();
});

test('readiness retains its legacy event name in inline mode', () => {
    initBooking();

    callback('linkReady')();
    callback('linkReady')();

    expect(capture.mock.calls).toEqual([
        ['booking_embed_opened', { mode: 'inline' }],
        ['booking_embed_opened', { mode: 'inline' }],
    ]);
});

test('completed booking forwards only allowed properties', () => {
    initBooking();

    callback('bookingSuccessfulV2')({ detail: { data: {
        uid: 'synthetic-booking', eventTypeId: 42, startTime: '2030-01-02T12:00:00Z', status: 'ACCEPTED',
        email: 'private@example.invalid', attendees: [{ name: 'Private name' }], title: 'Private title',
        responses: { notes: 'Private form text' }, mode: 'untrusted',
    } } });

    expect(capture.mock.calls).toEqual([['booking_completed', {
        mode: 'inline', booking_uid: 'synthetic-booking', event_type_id: 42,
        start_time: '2030-01-02T12:00:00Z', status: 'ACCEPTED',
    }]]);
});

test('completed booking preserves a nullable event type', () => {
    initBooking();

    callback('bookingSuccessfulV2')({ detail: { data: { uid: 'nullable-type', eventTypeId: null } } });

    expect(capture.mock.calls).toEqual([['booking_completed', {
        mode: 'inline', booking_uid: 'nullable-type', event_type_id: null,
        start_time: undefined, status: undefined,
    }]]);
});

test.each([{}, { detail: {} }, { detail: { data: null } }, { detail: { data: { uid: 'partial' } } }])('incomplete booking payloads do not throw or forward unexpected values %j', (event) => {
    initBooking();

    callback('bookingSuccessfulV2')(event);

    expect(capture.mock.calls).toEqual([['booking_completed', {
        mode: 'inline', booking_uid: event.detail?.data?.uid, event_type_id: undefined,
        start_time: undefined, status: undefined,
    }]]);
});
