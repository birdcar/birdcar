import { EVENTS, track } from './analytics';

const EMBED_SCRIPT = 'https://app.cal.com/embed/embed.js';
const EMBED_ORIGIN = 'https://app.cal.com';

/**
 * Cal.com's official loader, written out: a queueing `window.Cal` that the
 * embed script drains once it arrives. Namespaced calls queue on `Cal.ns[name]`.
 */
function ensureCal() {
    if (window.Cal) return window.Cal;

    const enqueue = (api, args) => api.q.push(args);

    const Cal = function () {
        const args = arguments;

        if (!Cal.loaded) {
            Cal.ns = {};
            Cal.q = Cal.q || [];
            document.head.appendChild(document.createElement('script')).src = EMBED_SCRIPT;
            Cal.loaded = true;
        }

        if (args[0] === 'init') {
            const api = function () { enqueue(api, arguments); };
            const namespace = args[1];
            api.q = api.q || [];

            if (typeof namespace === 'string') {
                Cal.ns[namespace] = Cal.ns[namespace] || api;
                enqueue(Cal.ns[namespace], args);
                enqueue(Cal, ['initNamespace', namespace]);
            } else {
                enqueue(Cal, args);
            }

            return;
        }

        enqueue(Cal, args);
    };

    window.Cal = Cal;

    return Cal;
}

/**
 * Cal's document listener opens the modal for any `[data-cal-link]` element
 * but does not cancel an anchor's navigation. Keep the href as the
 * no-JavaScript fallback and only cancel it once the embed can take over.
 * Capture modified clicks before Cal's delegated listener opens a second UI.
 */
function keepTriggersOnPage() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('a[data-cal-link]');

        if (!trigger || event.defaultPrevented) return;
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            event.stopPropagation();
            return;
        }
        if (!customElements.get('cal-modal-box')) return;

        event.preventDefault();
    }, { capture: true });
}

function themeColor(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

export function initBooking() {
    const inline = document.querySelector('[data-cal-inline]');
    const trigger = document.querySelector('[data-cal-link]');
    const source = inline ?? trigger;

    if (!source) return;

    const namespace = source.dataset.calNamespace;
    const Cal = ensureCal();

    Cal('init', namespace, { origin: EMBED_ORIGIN });
    Cal.config = Cal.config || {};
    Cal.config.forwardQueryParams = true;

    const ink = themeColor('--color-ink', '#102a33');
    const paper = themeColor('--color-paper', '#ffffff');
    const cyan = themeColor('--color-cyan', '#b7edf1');
    const teal = themeColor('--color-deep-teal', '#214b57');

    Cal.ns[namespace]('ui', {
        cssVarsPerTheme: {
            light: { 'cal-brand': ink, 'cal-brand-emphasis': teal, 'cal-brand-text': paper, 'cal-bg': paper },
            dark: { 'cal-brand': cyan, 'cal-brand-emphasis': paper, 'cal-brand-text': ink },
        },
        hideEventTypeDetails: false,
        layout: 'month_view',
    });

    const mode = inline ? 'inline' : 'modal';

    Cal.ns[namespace]('on', {
        action: 'linkReady',
        callback: () => track(EVENTS.embedOpened, { mode }),
    });

    Cal.ns[namespace]('on', {
        action: 'bookingSuccessfulV2',
        callback: (event) => {
            const { uid, eventTypeId, startTime, status } = event.detail?.data ?? {};

            track(EVENTS.bookingCompleted, { mode, booking_uid: uid, event_type_id: eventTypeId, start_time: startTime, status });
        },
    });

    if (trigger) keepTriggersOnPage();

    if (inline) {
        Cal.ns[namespace]('inline', {
            elementOrSelector: inline,
            calLink: inline.dataset.calLink,
            config: { layout: 'month_view', useSlotsViewOnSmallScreen: 'true', theme: 'light' },
        });
    }
}
