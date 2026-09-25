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

function themeColor(name, fallback) {
    return getComputedStyle(document.body).getPropertyValue(name).trim() || fallback;
}

/**
 * The Walkthrough page's inline calendar is the only Cal surface. When the fit
 * card gates it (`data-cal-defer`), Cal loads once the card dispatches
 * `booking:open`, so visitors who never pass the fit check never load Cal.
 */
export function initBooking() {
    const inline = document.querySelector('[data-cal-inline]');

    if (!inline) return;

    if ('calDefer' in inline.dataset) {
        inline.addEventListener('booking:open', () => loadCalendar(inline), { once: true });
        return;
    }

    loadCalendar(inline);
}

function loadCalendar(inline) {
    const namespace = inline.dataset.calNamespace;
    const Cal = ensureCal();

    Cal('init', namespace, { origin: EMBED_ORIGIN });
    Cal.config = Cal.config || {};
    Cal.config.forwardQueryParams = true;

    Cal.ns[namespace]('ui', {
        cssVarsPerTheme: {
            light: {
                'cal-brand': themeColor('--studio-yellow', '#f7c848'),
                'cal-brand-emphasis': themeColor('--studio-yellow-deep', '#efb925'),
                'cal-brand-text': themeColor('--studio-ink', '#0b141a'),
                'cal-bg': themeColor('--studio-paper', '#ffffff'),
            },
        },
        hideEventTypeDetails: true,
        layout: 'month_view',
    });

    Cal.ns[namespace]('on', {
        action: 'linkReady',
        callback: () => track(EVENTS.embedOpened, { mode: 'inline' }),
    });

    Cal.ns[namespace]('on', {
        action: 'bookingSuccessfulV2',
        callback: (event) => {
            const { uid, eventTypeId, startTime, status } = event.detail?.data ?? {};

            track(EVENTS.bookingCompleted, { mode: 'inline', booking_uid: uid, event_type_id: eventTypeId, start_time: startTime, status });
        },
    });

    Cal.ns[namespace]('inline', {
        elementOrSelector: inline,
        calLink: inline.dataset.calLink,
        config: { layout: 'month_view', useSlotsViewOnSmallScreen: 'true', theme: 'light' },
    });
}
