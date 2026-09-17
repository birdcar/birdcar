import posthog from 'posthog-js';

export const EVENTS = {
    ctaClicked: 'booking_cta_clicked',
    embedOpened: 'booking_embed_opened',
    bookingCompleted: 'booking_completed',
    fallbackClicked: 'booking_fallback_clicked',
};

let enabled = false;

/**
 * Boot posthog-js from the token and host the marketing layout emits on
 * `<body>`. Returns false when the layout emitted nothing, so the page runs
 * without analytics rather than throwing.
 */
export function initAnalytics() {
    const { posthogToken: token, posthogHost: host } = document.body.dataset;

    if (!token || !host) return false;

    posthog.init(token, {
        api_host: host,
        autocapture: false,
        capture_pageview: true,
        capture_pageleave: true,
        persistence: 'localStorage+cookie',
        cross_subdomain_cookie: false,
    });
    enabled = true;

    const params = new URLSearchParams(window.location.search);
    if (params.has('ph_opt_out')) posthog.opt_out_capturing();
    if (params.has('ph_opt_in')) posthog.opt_in_capturing();

    document.addEventListener('click', (event) => {
        const cta = event.target.closest('[data-booking-cta]');
        if (cta) track(EVENTS.ctaClicked, { placement: cta.dataset.bookingCta, page_path: window.location.pathname });

        const fallback = event.target.closest('[data-booking-fallback]');
        if (fallback) track(EVENTS.fallbackClicked, { page_path: window.location.pathname });
    });

    return true;
}

export function track(event, properties = {}) {
    if (!enabled) return;

    posthog.capture(event, properties);
}
