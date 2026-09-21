import { afterEach, beforeEach, expect, mock, spyOn, test } from 'bun:test';
import posthog from 'posthog-js';

const originals = Object.fromEntries(['document', 'window'].map((key) => [key, globalThis[key]]));
let analytics;
let capture;
let init;
let optIn;
let optOut;
let moduleId = 0;

beforeEach(async () => {
    globalThis.document = Object.assign(new EventTarget(), { body: { dataset: {} } });
    globalThis.window = { location: { search: '', pathname: '/walkthrough' } };
    init = spyOn(posthog, 'init').mockImplementation(() => {});
    capture = spyOn(posthog, 'capture').mockImplementation(() => {});
    optIn = spyOn(posthog, 'opt_in_capturing').mockImplementation(() => {});
    optOut = spyOn(posthog, 'opt_out_capturing').mockImplementation(() => {});
    analytics = await import(`./analytics.js?case=${moduleId++}`);
});

afterEach(() => {
    mock.restore();
    for (const [key, value] of Object.entries(originals)) {
        if (value === undefined) delete globalThis[key];
        else globalThis[key] = value;
    }
});

function configure() {
    document.body.dataset = { posthogToken: 'local-test-token', posthogHost: 'https://analytics.invalid' };
}

function click(matches = {}) {
    const event = new Event('click');
    Object.defineProperty(event, 'target', { value: { closest: (selector) => matches[selector] ?? null } });
    document.dispatchEvent(event);
}

test.each([{}, { posthogToken: 'local-test-token' }, { posthogHost: 'https://analytics.invalid' }])('analytics stays disabled with incomplete layout configuration %j', (dataset) => {
    document.body.dataset = dataset;

    expect(analytics.initAnalytics()).toBe(false);
    analytics.track(analytics.EVENTS.bookingCompleted, { mode: 'inline' });
    click({ '[data-booking-cta]': { dataset: { bookingCta: 'hero' } } });

    expect(init).not.toHaveBeenCalled();
    expect(capture).not.toHaveBeenCalled();
});

test('configured analytics enables page measurement without automatic form or click capture', () => {
    configure();

    expect(analytics.initAnalytics()).toBe(true);
    analytics.track('local_test');

    expect(init).toHaveBeenCalledWith('local-test-token', {
        api_host: 'https://analytics.invalid',
        autocapture: false,
        capture_pageview: true,
        capture_pageleave: true,
        persistence: 'localStorage+cookie',
        cross_subdomain_cookie: false,
    });
    expect(capture).toHaveBeenCalledWith('local_test', {});
    expect(optIn).not.toHaveBeenCalled();
    expect(optOut).not.toHaveBeenCalled();
});

test.each([
    ['?ph_opt_out', true, false],
    ['?ph_opt_in', false, true],
    ['?unrelated=1', false, false],
])('analytics forwards the explicit privacy preference %s to the SDK', (search, out, inside) => {
    configure();
    window.location.search = search;

    analytics.initAnalytics();

    expect(optOut).toHaveBeenCalledTimes(Number(out));
    expect(optIn).toHaveBeenCalledTimes(Number(inside));
});

test.each(['header', 'mobile-menu', 'hero', 'homepage-strip', 'closing-invitation', 'walkthrough-hero', 'walkthrough-report', 'walkthrough-steps'])('booking CTA preserves the %s placement and current path', (placement) => {
    configure();
    analytics.initAnalytics();
    window.location.pathname = '/work';
    window.location.search = '?email=must-not-be-captured@example.invalid';

    click({ '[data-booking-cta]': { dataset: { bookingCta: placement }, textContent: 'Do not capture text' } });

    expect(capture.mock.calls).toEqual([['booking_cta_clicked', { placement, page_path: '/work' }]]);
});

test('direct booking fallback measures a link click rather than a completed booking', () => {
    configure();
    analytics.initAnalytics();

    click({ '[data-booking-fallback]': { textContent: 'Do not capture text' } });

    expect(capture.mock.calls).toEqual([['booking_fallback_clicked', { page_path: '/walkthrough' }]]);
});

test('unrelated clicks produce no booking event', () => {
    configure();
    analytics.initAnalytics();

    click();

    expect(capture).not.toHaveBeenCalled();
});
