# Implementation Spec: Walkthrough Offer Copy and Funnel Signals - Phase 1

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Phase 1 makes the visit-to-booking funnel visible. The app already runs PostHog server-side (`posthog/posthog-php` through `App\Services\PostHogService`, wired by `App\Http\Middleware\PostHogRequestContext`), but nothing runs in the browser, so pageviews, booking-button clicks, and Cal.com embed events are invisible today. This phase adds `posthog-js` as a bundled dependency, a small `resources/js/analytics.js` module that initialises it from data attributes the marketing layout emits, and event forwarding inside the existing `resources/js/booking.js` where the Cal.com embed already lives.

The layout decides whether to emit the token and host by asking the existing service through one new read-only accessor, `PostHogService::browserConfig()`, so enabled-state logic stays in one place. Every booking control carries a `data-booking-cta="<placement>"` marker, including the two controls that do not carry Cal.com's `data-cal-link` (the assessment hero anchor and the inline nav link on the assessment page), so click capture never depends on the embed attribute. Four events make up the funnel: PostHog's automatic `$pageview`, `booking_cta_clicked`, `booking_embed_opened`, and `booking_completed`. The Full tier adds `booking_fallback_clicked` and a `mode` property.

The owner's own browsers are excluded with `?ph_opt_out=1`, which calls `posthog.opt_out_capturing()`, and re-enabled with `?ph_opt_in=1`. Tests pin the PostHog environment in `phpunit.xml` so they do not depend on the developer's `.env`, and `phpunit.xml` gains `failOnEmptyTestSuite="true"` so the contract's filtered acceptance runs cannot pass on an empty suite.

Reference material already in the repo: `.agents/skills/integration-laravel/references/laravel.md` and `references/identify-users.md` (PostHog's Laravel integration skill). Use them for API shape only; this project does not call `identify` and does not send attendee data.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **Standard posthog-js persistence with no consent banner** — rejected: PostHog cookieless mode. The owner wants cross-visit identity for the funnel and the option of replay without a code change, and accepts the EU exposure at this traffic level.
- **Load PostHog through posthog-js installed with bun and bundled into app.js** — rejected: PostHog's CDN loader snippet, and posthog-js-lite. A pinned, bundled dependency avoids an external script tag and keeps the Cal.com event forwarding in the same module as the embed loader; the full package supplies automatic pageview, pageleave, UTM and referrer person properties, and persisted opt-out.
- **Funnel events only** — rejected: session replay and section-visibility events. Four events read the whole funnel; replay is a project setting.
- **Key CTA click capture on a dedicated data-booking-cta placement marker** — rejected: keying on the existing data-cal-link attribute. The assessment hero anchor and the inline nav link carry no data-cal-link.
- **Gate runbook makes the live test booking from a fresh private window first and opts out everyday browsers afterwards, and the analytics module gains a matching opt-in parameter** — rejected: opting out before the test booking with no reversal. An opted-out browser emits nothing.
- **Enable failOnEmptyTestSuite in phpunit.xml** — rejected: relying on filtered `php artisan test` runs as written. PHPUnit exits 0 on an empty filtered suite.
- **Drop the admin and customer host clause from the PostHog emission scope** — rejected: a host check in the layout and a test for it. Only the marketing layout renders the bundle, and Folio is bound to the marketing host.
- **Sequential execution** — rejected: running instrumentation and rename in parallel. Both phases write the marketing layout.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/MarketingAnalyticsTest.php`

**Playground**: Test suite for the Blade and service work; `php artisan dev` plus a private browser window at `http://localhost:8000/?__posthog_debug=true` for the JavaScript, reading PostHog's debug log in the console.

**Why this approach**: The server-side half (accessor, layout attributes, placement markers) is fully testable with Pest in under two seconds. The browser half has no test runner in this project, so the dev server with PostHog's own debug logging is the tightest loop that shows real captures, including the Cal.com embed callbacks.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `resources/js/analytics.js` | Initialise posthog-js from body data attributes, handle opt-out and opt-in parameters, capture CTA and fallback clicks, export `track()` |
| `tests/Feature/MarketingAnalyticsTest.php` | Conditional emission of the PostHog config, placement markers on every booking control, fallback marker |

### Modified Files

| File Path | Changes |
| --- | --- |
| `package.json`, `bun.lock` | Add `posthog-js` (`bun add posthog-js`) |
| `resources/js/app.js` | Import and call `initAnalytics()` before `initBooking()` |
| `resources/js/booking.js` | Subscribe to Cal.com embed actions and forward `booking_embed_opened` and `booking_completed` through `track()` |
| `app/Services/PostHogService.php` | Add `isEnabled(): bool` and `browserConfig(): ?array` |
| `resources/views/components/marketing/layout.blade.php` | Emit `data-posthog-token` and `data-posthog-host` on `<body>` when `browserConfig()` is non-null; pass `placement` to the two nav booking links |
| `resources/views/components/marketing/booking-link.blade.php` | Accept a `placement` prop and render `data-booking-cta` on both branches |
| `resources/views/pages/index.blade.php` | Pass `placement="hero"` and `placement="homepage-strip"` to the two booking links |
| `resources/views/pages/assessment.blade.php` | Replace the raw hero anchor with `<x-marketing.booking-link inline placement="assessment-hero">`; add `data-booking-fallback` to the plain Cal.com link |
| `resources/views/components/marketing/assessment-invitation.blade.php` | Pass `placement="closing-invitation"` |
| `phpunit.xml` | Add `failOnEmptyTestSuite="true"`; pin `POSTHOG_DISABLED=true`, `POSTHOG_PROJECT_TOKEN=""`, `POSTHOG_HOST=https://us.i.posthog.com` |

### Deleted Files

None.

## Implementation Details

### PostHogService browser accessor

**Pattern to follow**: the existing constructor in `app/Services/PostHogService.php`, which already resolves enabled state from `config('posthog.disabled')`, a blank token, and a blank host.

```php
public function isEnabled(): bool
{
    return $this->enabled;
}

/**
 * @return array{token: string, host: string}|null
 */
public function browserConfig(): ?array
{
    if (! $this->enabled) {
        return null;
    }

    return [
        'token' => (string) config('posthog.api_key'),
        'host' => (string) config('posthog.host'),
    ];
}
```

**Key decisions**:

- The layout never re-reads config. The service is the single owner of enabled state.
- `throwWhenDebugging` is left as is. It fires when the token is blank and `app.debug` is true, before the layout renders. The blank-token test disables `app.debug` for that case.

**Implementation steps**:

1. Add the two methods after `withContext()`.
2. Run `vendor/bin/pint --dirty --format agent`.

### phpunit.xml pins

Trivial component, no feedback loop.

1. Add `failOnEmptyTestSuite="true"` to the `<phpunit>` element attributes.
2. Add to `<php>`: `<env name="POSTHOG_DISABLED" value="true"/>`, `<env name="POSTHOG_PROJECT_TOKEN" value=""/>`, `<env name="POSTHOG_HOST" value="https://us.i.posthog.com"/>`.
3. Confirm the pin works: `php artisan test --compact --filter='no such test name'` must now exit non-zero.

### Layout emission and placement markers

**Pattern to follow**: `resources/views/components/marketing/layout.blade.php` already uses `@php(...)` for the head call and `@class([...])` on `<body>`.

```blade
@php($posthog = app(\App\Services\PostHogService::class)->browserConfig())
<body @class(['reading-page' => $article, 'home-page' => $home])
    @if ($posthog) data-posthog-token="{{ $posthog['token'] }}" data-posthog-host="{{ $posthog['host'] }}" @endif>
```

`booking-link.blade.php`:

```blade
@props(['inline' => false, 'placement' => null])
@php($marker = $placement ? ['data-booking-cta' => $placement] : [])
@if ($inline)
    <a {{ $attributes->merge(['href' => '#choose-a-time', ...$marker]) }}>{{ $slot }}</a>
@else
    <a {{ $attributes->merge(['href' => route('public.assessment'), ...$marker]) }} data-cal-link="{{ config('marketing.booking_calendar') }}" data-cal-namespace="{{ config('marketing.booking_namespace') }}" data-cal-config="{{ json_encode([...]) }}">{{ $slot }}</a>
@endif
```

Placement values, one per control: `header` (desktop nav), `mobile-menu`, `hero`, `homepage-strip`, `closing-invitation`, `assessment-hero`. The assessment hero becomes:

```blade
<x-marketing.booking-link inline placement="assessment-hero" class="button button-lilac">Book my free assessment <x-marketing.arrow /></x-marketing.booking-link>
```

(Phase 2 renames the button text; keep the current text here.) The fallback link under the inline calendar gains `data-booking-fallback`.

**Key decisions**:

- Body data attributes rather than meta tags: one read in JavaScript (`document.body.dataset`), and the test asserts a single attribute pair.
- Placement is explicit per call site. No JavaScript fallback that guesses from surrounding markup.

**Implementation steps**:

1. Write `tests/Feature/MarketingAnalyticsTest.php` first with the four tests below, run it, watch it fail.
2. Add the accessor, the layout attributes, the `placement` prop, and the call-site placements.
3. Convert the assessment hero anchor and mark the fallback link.
4. Run the inner-loop command until green, then `php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiscoveryTest.php` to confirm the existing assertions (`href="#choose-a-time"`, absence of `data-cal-config` on the assessment page, `data-cal-inline` absent elsewhere) still hold.

**Feedback loop**:

- **Playground**: `tests/Feature/MarketingAnalyticsTest.php` with a describe block and one failing smoke test before touching Blade.
- **Experiment**: enabled with token → attributes present; `posthog.disabled = true` → absent; `posthog.disabled = false`, `posthog.api_key = ''`, `app.debug = false` → absent and no exception; `/` shows `data-booking-cta="header"`, `"mobile-menu"`, `"hero"`, `"homepage-strip"`, `"closing-invitation"`; `/assessment` shows `data-booking-cta="assessment-hero"` and `data-booking-fallback`.
- **Check command**: `php artisan test --compact tests/Feature/MarketingAnalyticsTest.php`

### analytics.js

**Overview**: one module owning PostHog initialisation, the opt-out and opt-in switches, delegated click capture, and a `track()` export used by `booking.js`.

```javascript
import posthog from 'posthog-js';

export const EVENTS = {
    ctaClicked: 'booking_cta_clicked',
    embedOpened: 'booking_embed_opened',
    bookingCompleted: 'booking_completed',
    fallbackClicked: 'booking_fallback_clicked',
};

let enabled = false;

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
```

**Key decisions**:

- `autocapture: false`: the funnel is four named events; autocaptured clicks on every nav link would add noise at this traffic level.
- `cross_subdomain_cookie: false`: the identity cookie stays on the marketing host and never reaches `admin.` or `customer.` subdomains.
- No `defaults` option: this is a multi-page site with full loads, so `capture_pageview: true` is the right mode; `history_change` is for SPAs.
- No `identify()` and no person properties beyond what posthog-js sets automatically (UTM, referrer, `$initial_*`).
- Debug logging uses PostHog's built-in `?__posthog_debug=true`; no custom switch.

**Implementation steps**:

1. `bun add posthog-js`, then `bun run build` once to confirm the bundle resolves the import.
2. Write the module as above; in `app.js`, `import { initAnalytics } from './analytics'; initAnalytics();` before `initBooking()`.
3. Start `php artisan dev`; the local `.env` must carry a real `POSTHOG_PROJECT_TOKEN`, `POSTHOG_HOST`, and `POSTHOG_DISABLED=false` for the layout to emit attributes (`.ai/rules/general.md` suggests `POSTHOG_DISABLED=true` for day-to-day work; flip it for this check). Local captures land in the production PostHog project. Use a private window and note its person for deletion at the gate.

**Feedback loop**:

- **Playground**: `php artisan dev`, private window at `http://localhost:8000/?__posthog_debug=true`, console open.
- **Experiment**: on load, the debug log shows `$pageview`; click the header CTA → `booking_cta_clicked` with `placement: header`; click the closing invitation CTA → `placement: closing-invitation`; visit `/?ph_opt_out=1`, click again → no capture logged and `posthog.has_opted_out_capturing()` returns true in the console; visit `/?ph_opt_in=1` → captures resume.
- **Check command**: `bun run build >/dev/null 2>&1 && grep -lE 'booking_cta_clicked|ph_opt_out|opt_out_capturing' public/build/assets/app-*.js`

### Cal.com embed forwarding in booking.js

**Pattern to follow**: `resources/js/booking.js` already calls `Cal.ns[namespace]('ui', ...)` and `Cal.ns[namespace]('inline', ...)`; subscriptions use the same namespaced API: `Cal.ns[namespace]('on', { action, callback })`, and the callback receives `e.detail = { data, type, namespace }`.

```javascript
import { track, EVENTS } from './analytics';

// inside initBooking(), after the 'ui' call
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
```

**Key decisions**:

- `bookingSuccessfulV2` is the currently documented completion action (cal.com/help/embedding/embed-events). Its documented payload has `uid`, `title`, `startTime`, `endTime`, `eventTypeId`, `status`, `paymentRequired`, `isRecurring`, `allBookings`, `videoCallUrl` and no attendee fields. Forward only the four properties above; never spread the payload.
- `linkReady` is the opened signal, with `mode` telling the funnel whether the calendar was shown inline on the assessment page or opened as a modal from a CTA. For the inline calendar, `linkReady` fires when the page renders it; the funnel insight reads the modal path as intent and the inline path as exposure.
- Unconfirmed in docs, verify on the dev server: whether `linkReady` fires on every modal open or only the first per page, and whether the plain `bookingSuccessful` action still fires alongside V2. If `linkReady` fires only once per page, switch the opened signal to `bookerViewed` and record the reason in the commit body.

**Implementation steps**:

1. Add the import and the two subscriptions.
2. On the dev server, open the modal from the homepage header CTA, close it, open it from the strip CTA; watch the debug log.
3. Complete a real test booking in the modal against the current `birdcar/free-assessment` event (Phase 2 renames it later) and confirm `booking_completed` carries exactly `mode`, `booking_uid`, `event_type_id`, `start_time`, `status` and nothing else. Cancel the booking afterwards in Cal.com.

**Feedback loop**:

- **Playground**: same private debug window as above, on `/` for modal and `/assessment` for inline.
- **Experiment**: modal open ×2 → count of `booking_embed_opened` logged; inline page load → one `booking_embed_opened` with `mode: inline`; completed booking → one `booking_completed` with the five properties; inspect the logged properties object for any `attendee`, `email`, or `name` key and fail the step if present.
- **Check command**: `bun run build >/dev/null 2>&1 && grep -lE 'booking_embed_opened' public/build/assets/app-*.js && grep -lE 'booking_completed' public/build/assets/app-*.js`

## Testing Requirements

### Feature Tests

| Test File | Coverage |
| --- | --- |
| `tests/Feature/MarketingAnalyticsTest.php` | Emission when enabled; absence when disabled; absence when the token is blank with `app.debug` off; placement markers and fallback marker |

**Key test cases** (Pest, following the `config([...])` then `$this->get()` pattern in `tests/Feature/MarketingDiscoveryTest.php`):

- `the marketing layout emits the PostHog browser config when PostHog is enabled`: `config(['posthog.disabled' => false, 'posthog.api_key' => 'phc_test_token', 'posthog.host' => 'https://us.i.posthog.com'])`, then `assertSee('data-posthog-token="phc_test_token"', false)` and `assertSee('data-posthog-host="https://us.i.posthog.com"', false)` on `/`.
- `the marketing layout omits the PostHog browser config when PostHog is disabled`: default pinned env, `assertDontSee('data-posthog-token')`.
- `the marketing layout omits the PostHog browser config when the token is blank`: `config(['posthog.disabled' => false, 'posthog.api_key' => '', 'app.debug' => false])`, `assertOk()` and `assertDontSee('data-posthog-token')`.
- `every booking control carries its placement marker`: `/` contains `data-booking-cta="header"`, `"mobile-menu"`, `"hero"`, `"homepage-strip"`, `"closing-invitation"`; `/assessment` contains `data-booking-cta="assessment-hero"` and `data-booking-fallback`.

### Manual Testing

- [ ] Debug window shows `$pageview`, `booking_cta_clicked` with placement, `booking_embed_opened` with mode.
- [ ] `?ph_opt_out=1` silences capture; `?ph_opt_in=1` restores it.
- [ ] A completed test booking logs `booking_completed` with exactly five properties and no attendee data.

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Layout emission | Service throws before render | Blank token with `app.debug=true` in a local `.env` | 500 on every marketing page locally | Existing behaviour, documented in `.ai/rules/general.md`; tests disable debug for the blank case; do not weaken the throw |
| analytics.js | Init skipped silently | Attributes absent because PostHog disabled | No events, no error | Expected in local default and tests; `initAnalytics()` returns false so the dev can see it in the console |
| analytics.js | Opt-out never applied | Param checked before `posthog.init()` | Owner's visits pollute data | Params handled after init, as written |
| booking.js forwarding | `linkReady` fires once per page | Cal.com caches the modal iframe | Second modal open unrecorded | Verify on dev server; switch to `bookerViewed` if so |
| booking.js forwarding | Payload shape differs from docs | Cal.com changes the V2 payload | Undefined properties on `booking_completed` | Destructure named fields only; event still fires with `mode` |
| booking.js forwarding | Attendee data leaks | Someone spreads `event.detail.data` later | PII in PostHog | Named destructuring plus the manual property inspection step; never forward the whole payload |
| Cookies | Identity cookie reaches admin/customer subdomains | Default `cross_subdomain_cookie` | Harmless (no PostHog on those hosts) but untidy | Set `cross_subdomain_cookie: false` |
| Tests | Fake token triggers network | `PostHog::init` with `phc_test_token` | Slow or failing tests offline | posthog-php only sends on capture/flush; no capture happens in these tests |

## Validation Commands

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/MarketingAnalyticsTest.php tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiscoveryTest.php
bun run build
grep -lE 'booking_cta_clicked' public/build/assets/app-*.js && grep -lE 'booking_embed_opened' public/build/assets/app-*.js && grep -lE 'booking_completed' public/build/assets/app-*.js && grep -lE 'ph_opt_out' public/build/assets/app-*.js && grep -lE 'opt_out_capturing' public/build/assets/app-*.js
php artisan test --compact --filter='no such test name'; test $? -ne 0
```

## Rollout Considerations

- **Feature flag**: none. Emission is controlled by the existing `POSTHOG_*` environment, already set in production for the server-side client.
- **Monitoring**: PostHog Activity view after deploy; the gate phase creates the funnel insight.
- **Rollback plan**: set `POSTHOG_DISABLED=true` in production to stop emission without a deploy; or revert the phase commit.

## Open Items

- [ ] Confirm on the dev server whether `linkReady` fires per modal open; switch to `bookerViewed` if not, and record the reason.
- [ ] Note the private-window person created during local checks so the gate phase deletes it.
