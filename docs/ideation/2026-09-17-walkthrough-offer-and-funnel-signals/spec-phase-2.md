# Implementation Spec: Walkthrough Offer Copy and Funnel Signals - Phase 2

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Phase 2 renames the offer from "free assessment" to "The Walkthrough" everywhere the old name appears and, because the Full tier was selected, moves the page from `/assessment` to `/walkthrough` with a permanent redirect. The rename is mechanical: nav labels, button text, action notes, page title, meta description, footer link, the `Service` schema name in `App\Services\MarketingSite`, and the Cal.com slug and namespace in `config/marketing.php`. Copy that changes meaning (turnaround, promises, questions) belongs to Phase 3; this phase only swaps names so the two phases never rewrite the same sentence twice.

The URL move follows the repo's existing conventions. Folio pages live in `resources/views/pages`, so the page file is renamed and its `name()` call becomes `public.walkthrough`. Redirects live in `routes/web.php` inside the marketing-host group, which already holds `permanentRedirect('/contact', '/assessment')`; that redirect retargets to `/walkthrough` and a new one covers `/assessment`. The sitemap route list swaps the route name. Tests are updated in the same phase so the suite is green at the end of it, including `tests/Feature/MarketingAnalyticsTest.php` from Phase 1, which requests `/assessment` today.

The Cal.com event `birdcar/walkthrough` does not exist yet. Config moves to it now; the owner creates the event at the human gate before deploy. Locally, the embed will fail to load a slug that does not exist yet; that is expected until the gate, and the plain fallback link renders regardless.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **Name the offer The Walkthrough in this project** — rejected: keeping free assessment, The Untangling Hour, and The Tuesday Walkthrough. The container word is what the prospect does in the hour and ties to the Tuesday line and step two.
- **Move config and tests to the birdcar/walkthrough Cal.com slug, with the owner creating the new event before deploy and retiring the old one after** — rejected: keeping the free-assessment slug and only retitling. The owner wants the slug to match the name and accepts that the old booking URL stops working.
- **Keep the /assessment URL in MVP and place the /walkthrough move with a redirect in the Full tier** — the Full tier was selected, so the move is in this phase. Rejected in MVP because of file churn across Folio, sitemap, and tests.
- **Target the Full tier; the server-side booking webhook moves to Future Considerations** — rejected: the Stretch tier.
- **Sequential execution** — this phase runs after Phase 1 and edits files Phase 1 touched.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiscoveryTest.php tests/Feature/MarketingAnalyticsTest.php`

**Playground**: Test suite. Every rename target is a string in rendered HTML, a route, or a config value, all of which the three Pest files assert.

**Why this approach**: The phase is string replacement plus one route move; Pest renders every page in well under five seconds and the existing tests already lock the strings that must survive, so a failing assertion is the fastest way to find a missed spot.

## File Changes

### New Files

None. `resources/views/pages/walkthrough.blade.php` is a `git mv` of `assessment.blade.php`.

### Modified Files

| File Path | Changes |
| --- | --- |
| `config/marketing.php` | `booking_url` → `https://cal.com/birdcar/walkthrough`, `booking_calendar` → `birdcar/walkthrough`, `booking_namespace` → `walkthrough` |
| `app/Services/MarketingSite.php` | `head()` parameter `bool $assessment` → `bool $walkthrough`; Service `name` → `The Walkthrough`; keep `serviceType` |
| `resources/views/pages/assessment.blade.php` → `walkthrough.blade.php` | `git mv`; `name('public.walkthrough')`; `active="walkthrough"`; title, description, and name strings below |
| `resources/views/components/marketing/layout.blade.php` | `$active === 'assessment'` → `'walkthrough'` in the head call and both nav links; nav text `Book a free Walkthrough`; footer link `route('public.walkthrough')` with text `The Walkthrough`; default description string |
| `resources/views/components/marketing/booking-link.blade.php` | Non-inline `href` → `route('public.walkthrough')` |
| `resources/views/components/marketing/assessment-invitation.blade.php` | Button text `Book a free Walkthrough`; action note `About an hour. A practical report. Yours to keep.` unchanged here (Phase 3 edits it) |
| `resources/views/pages/index.blade.php` | Button text ×2, `assessment-terms` note, strip sentence `The Walkthrough is free.` |
| `routes/web.php` | `permanentRedirect('/contact', '/walkthrough')`; add `permanentRedirect('/assessment', '/walkthrough')`; sitemap list uses `public.walkthrough` |
| `tests/Feature/MarketingSiteTest.php` | Paths, route names, slug strings, button text; add redirect test and no-old-name test |
| `tests/Feature/MarketingDiscoveryTest.php` | Canonical `/walkthrough`; Service name `The Walkthrough`; host-404 path `/walkthrough` |
| `tests/Feature/MarketingAnalyticsTest.php` | Request `/walkthrough` instead of `/assessment` |

### Deleted Files

None (rename only).

## Implementation Details

### Config and schema

Trivial component, no feedback loop.

1. Edit the three values in `config/marketing.php`.
2. In `MarketingSite::head()`, rename the parameter and set `'name' => 'The Walkthrough'`. Update the one caller in `layout.blade.php` (`$active === 'walkthrough'`).
3. Run `vendor/bin/pint --dirty --format agent`.

### Page move and redirect

**Pattern to follow**: `routes/web.php` marketing group, which already uses `Route::permanentRedirect` for `/case-studies`, `/contact`, and `/blog`; `resources/views/pages/work.blade.php` for the Folio `name()` convention.

**Implementation steps**:

1. `git mv resources/views/pages/assessment.blade.php resources/views/pages/walkthrough.blade.php`.
2. In the moved file: `name('public.walkthrough')`, `active="walkthrough"`, `title="Book a free Walkthrough"`, description: `Find out what’s making work harder than it needs to be. A free, hour-long Walkthrough of the work with me and a written report of recommended improvements, yours to keep.` Keep the curly apostrophe the file already uses.
3. In `routes/web.php`: retarget `/contact` to `/walkthrough`, add `Route::permanentRedirect('/assessment', '/walkthrough');`, and replace `'public.assessment'` with `'public.walkthrough'` in the sitemap array.
4. `php artisan route:list --name=public` must list `public.walkthrough` and no `public.assessment`. `php artisan folio:list` must show `/walkthrough`.

**Feedback loop**:

- **Playground**: add the two new tests below to `MarketingSiteTest.php` first and watch them fail.
- **Experiment**: `GET /assessment` → 301 to `/walkthrough`; `GET /walkthrough` → 200 with `id="choose-a-time"`; `GET /contact` → 301 to `/walkthrough`; `GET /sitemap.xml` contains `/walkthrough` and not `/assessment`.
- **Check command**: `php artisan test --compact tests/Feature/MarketingSiteTest.php --filter='walkthrough'`

### Name strings

**Overview**: replace every visible instance of the old name. The list is exhaustive for the current templates; the negative test below catches anything missed.

| Location | Old | New |
| --- | --- | --- |
| `layout.blade.php` desktop and mobile nav | `Book a free assessment` | `Book a free Walkthrough` |
| `layout.blade.php` footer link | `Free assessment` | `The Walkthrough` |
| `layout.blade.php` default description | `Start with a free assessment and a practical report you keep.` | `Start with a free Walkthrough and a written report you keep.` |
| `index.blade.php` hero and strip buttons | `Book a free assessment` | `Book a free Walkthrough` |
| `index.blade.php` `assessment-terms` | `About an hour. A free assessment. A report to keep.` | `About an hour. A free Walkthrough. A report to keep.` |
| `index.blade.php` strip paragraph | `The assessment is free.` | `The Walkthrough is free.` |
| `walkthrough.blade.php` hero button | `Book my free assessment` | `Book a free Walkthrough` |
| `walkthrough.blade.php` opening paragraph | `In a free assessment, I’ll spend` | `In a free Walkthrough, I’ll spend` |
| `walkthrough.blade.php` report aside | `Your assessment report` | `Your Walkthrough report` |
| `walkthrough.blade.php` FAQ summary | `Is the assessment really free?` | `Is the Walkthrough really free?` |
| `walkthrough.blade.php` FAQ summary | `Is this an AI assessment?` | `Is this an AI project?` |
| `walkthrough.blade.php` FAQ answer | `It’s an assessment of the work.` | `It’s a Walkthrough of your work.` |
| `assessment-invitation.blade.php` button | `Book a free assessment` | `Book a free Walkthrough` |

CSS class names (`assessment-strip`, `assessment-terms`, `assessment-faq`, `offer-*`) and the `id="assessment-heading"` stay as they are; they are not prose and `resources/css/marketing.css` is out of this phase.

**Key decisions**:

- The hero button drops "my" (`Book a free Walkthrough`) so the same string appears on every control, which simplifies both the tests and Phase 3's reader-focus ratio.
- Test strings that must survive unchanged: `The conversation and the written report are free.`, `separate implementation engagement`, `I help businesses untangle work`, `fifteen years`.

**Feedback loop**:

- **Playground**: the three Pest files.
- **Experiment**: after the swaps, lower-cased HTML of `/`, `/walkthrough`, `/work`, `/writing/` contains no `free assessment`; `/walkthrough` contains `data-cal-inline data-cal-link="birdcar/walkthrough" data-cal-namespace="walkthrough"` and `href="https://cal.com/birdcar/walkthrough"`; `/`, `/work`, `/writing/` contain `data-cal-link="birdcar/walkthrough"` and `href="<route public.walkthrough>"`.
- **Check command**: the inner-loop command.

## Testing Requirements

### Feature Tests

| Test File | Coverage |
| --- | --- |
| `tests/Feature/MarketingSiteTest.php` | Updated slug and route assertions; new redirect test; new no-old-name test |
| `tests/Feature/MarketingDiscoveryTest.php` | Canonical and Service schema for `/walkthrough`; host 404 for `/walkthrough` |
| `tests/Feature/MarketingAnalyticsTest.php` | Placement markers on `/walkthrough` |

**Key test cases**:

- `the old assessment path redirects permanently to the walkthrough`: `$this->get('/assessment')->assertStatus(301)->assertRedirect('/walkthrough')`; same for `/contact`.
- `no page still calls the offer a free assessment`: for each of `/`, `/walkthrough`, `/work`, `/writing/`, `expect(mb_strtolower($response->getContent()))->not->toContain('free assessment')->not->toContain('free-assessment')`.
- Existing `the assessment explains the free report…` test renamed to `the walkthrough explains…`, asserting the new slug strings and `assertDontSee('cal.com/birdcar/60min')` retained.
- Existing homepage test: `assertSee(route('public.walkthrough'), false)` and `assertSee('Book a free Walkthrough')`.
- Discovery: `Service` `name` is `The Walkthrough`; canonical `https://birdcar.dev/walkthrough`; host-404 dataset uses `/walkthrough`.

### Manual Testing

- [ ] `php artisan route:list --name=public` shows `public.walkthrough`, `public.sitemap`, `public.feed`, and no `public.assessment`.
- [ ] Sitemap XML lists `/walkthrough`.

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Page move | Folio still serves `/assessment` | Cached Folio routes or a leftover file | Redirect shadowed by a page | `git mv` leaves no old file; `php artisan folio:list` confirms; the redirect test fails if a 200 comes back |
| Redirect | Route order | Folio catch-all registered before `web.php` redirects | 404 instead of 301 | The existing `/contact` redirect proves `web.php` wins today; the new test guards it |
| Config | Embed points at a Cal.com slug that does not exist yet | Deploy before the owner creates the event | Calendar fails to load on the live page; fallback link 404s on Cal.com | Gate order in the contract: create the event first, then deploy; do not ship this phase alone |
| Strings | A missed instance of the old name | Copy in a component not listed above | Brand inconsistency | Negative test over four pages, plus the contract's `grep` criterion |
| Schema | Downstream consumers keyed on the old Service name | None known | None | Accept |

## Validation Commands

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiscoveryTest.php tests/Feature/MarketingAnalyticsTest.php
php artisan route:list --name=public
grep -q 'cal.com/birdcar/walkthrough' config/marketing.php && ! grep -rq 'free-assessment' config resources/views resources/js
! grep -rEil 'free assessment' resources/views/pages/index.blade.php resources/views/pages/walkthrough.blade.php resources/views/components/marketing
```

## Rollout Considerations

- **Feature flag**: none.
- **Ordering**: this phase must deploy together with Phases 1 and 3 and only after the owner creates the `walkthrough` Cal.com event. The gate phase in the contract owns that order.
- **Rollback plan**: revert the phase commit; the old Cal.com event is only hidden or deleted after the live check passes.
