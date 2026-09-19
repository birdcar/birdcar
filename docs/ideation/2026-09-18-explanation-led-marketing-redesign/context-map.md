# Context Map: 2026-09-18-explanation-led-marketing-redesign

**Phase**: 1
**Gates**: 5/5 ready
**Verdict**: GO

## Gates

| Gate | Status | Evidence |
| --- | --- | --- |
| Scope clarity | ready | Phase 1 spec names every new/modified file: `walkthrough-diagram.blade.php`, `diagrams.js`, `diagrams.test.js`, `acceptance.md`, plus 13 modified files with concrete change descriptions. |
| Pattern familiarity | ready | Read required patterns: `resources/css/marketing.css`, `resources/views/components/marketing/layout.blade.php`, `.impeccable/surfaces/resources-views-pages-index-blade-php.md`, `resources/views/components/marketing/article-chart.blade.php`, `resources/js/interactions.js`, and tests. |
| Dependency awareness | ready | Mapped consumers via grep for CSS entries, layout component, JS initializers, homepage selectors, font config, and test files. |
| Edge case coverage | ready | Spec plus current tests identify mobile reflow, repeated figure IDs, reduced-motion changes, hidden/offscreen/print states, no-JS menu/booking fallback, static semantic explanation, and owner-change preservation. |
| Test strategy | ready | Validation commands are explicit: `bun test resources/js/interactions.test.js resources/js/diagrams.test.js`, targeted `php artisan test --compact ...`, `vendor/bin/pint --dirty --format agent`, `composer types:check`, `bun run build`; existing Pest/Bun patterns read. |

## Key Patterns

- `resources/css/marketing.css` — Current custom marketing UI is organized in plain CSS with `@layer base/components`, CSS variables from `app.css`, full-width fields, 7%/6% gutters, max-width breakpoints at `1100px`, `1050px`, and `760px`, and reduced-motion media queries. Current homepage opening uses `.home-opening`, `.home-hero`, `.future-horizon`, `.assessment-strip`; diagram/chart styles use semantic figure/caption/data table classes.
- `resources/views/components/marketing/layout.blade.php` — Shared marketing shell: one header wordmark, desktop nav, native `<details class="mobile-menu">`, `<main id="main" tabindex="-1">`, footer nav, PostHog data attributes only when configured, `@fonts`, and Vite entry `resources/css/app.css` + `resources/js/app.js`.
- `.impeccable/surfaces/resources-views-pages-index-blade-php.md` — Existing homepage surface brief records the prior “Future, in person” direction, homepage story, motion grammar, first viewport, and preservation constraints; Phase 1 must update this to the new selected “The clear argument” contract rather than silently following the old world.
- `resources/views/components/marketing/article-chart.blade.php` — Semantic figure pattern: `<figure>`, accessible visual (`role="img"`/`aria-label`), visible `<figcaption>`, and a disclosure-backed source/data table. For the new walkthrough diagram, reuse the semantic figure/caption/data-completeness idea, not the chart scales.
- `resources/js/interactions.js` — Small module exporting `initInteractions()`, imports Motion `animate`/`stagger`, enhances native mobile menu, starts homepage motion only after `document.fonts.ready`, skips for reduced motion/hidden/scrolled/late load, completes animations on reduced-motion change/visibility/pagehide.
- `resources/js/interactions.test.js` — Bun tests mock `motion`, replace global `document`/`window`, use `EventTarget` and controlled promises, assert lifecycle behavior without browser automation.
- `tests/Feature/MarketingSiteTest.php` — Pest feature tests assert server-rendered marketing content, approved offer boundaries, wordmark invariants, native menu state, redirects, archive preservation, and accessible chart data.
- `tests/Unit/MarketingFontsTest.php` — Reads CSS files directly to assert font imports, token names, local font files, and `font-display: swap`.
- `tests/Unit/MarketingLayoutTest.php` — Reads `marketing.css` directly to preserve owner alignment intent: `.home-work` and `.assessment-strip` share layout rules at every breakpoint.

## Dependencies

- `resources/css/app.css:1-16` — consumed by → `vite.config.js:9`, `resources/views/components/marketing/layout.blade.php:12`, `tests/Unit/MarketingFontsTest.php:4`, all marketing pages via Vite. Defines current marketing tokens: Alkaline, Alkaline Caps, Karla, Commit Mono, purple palette.
- `resources/css/marketing.css:1-416` — consumed by → `resources/css/app.css:3`, all marketing Blade pages/classes, `.impeccable/surfaces/*`, `tests/Unit/MarketingLayoutTest.php:4`, `DESIGN.md`. High blast radius: homepage, walkthrough, work, writing index/article, booking/calendar, charts, mobile nav.
- `vite.config.js:1-27` — consumed by → Vite build/dev pipeline. Current Bunny font pipeline loads `Karla` weights `[400,500,600,700]`; Phase 1 changes likely affect font tests and build output.
- `resources/views/components/marketing/layout.blade.php:1-45` — consumed by → `resources/views/pages/index.blade.php`, `resources/views/pages/walkthrough.blade.php`, `resources/views/pages/work.blade.php`, `resources/views/pages/writing/index.blade.php`, `resources/views/pages/writing/[slug].blade.php`; also covered by `MarketingAnalyticsTest.php` and `MarketingSiteTest.php`.
- `resources/views/pages/index.blade.php:1-57` — consumed by → Folio route named `public.index`; tests in `MarketingSiteTest.php`, analytics placement assertions in `MarketingAnalyticsTest.php`, motion selectors in `interactions.js` (`.home-opening`, `.future-horizon`), CSS selectors in `marketing.css`, surface brief.
- `resources/js/app.js:1-7` — consumed by → `vite.config.js:9` and layout Vite include. Currently initializes interactions, analytics, booking; Phase 1 must add `initDiagrams()` independently without breaking ordering.
- `resources/js/interactions.js:1-73` — consumed by → `resources/js/app.js:3/5`, `resources/js/interactions.test.js:19`. Existing mobile menu enhancement must remain; obsolete horizon choreography may be removed/replaced.
- `resources/js/interactions.test.js:1-190` — consumed by → `bun test resources/js/interactions.test.js`; should keep native menu/lifecycle coverage while updating old opening expectations.
- `tests/Feature/MarketingSiteTest.php:1-218` — consumed by → `php artisan test --compact tests/Feature/MarketingSiteTest.php`; will need new assertions for static explanatory labels/caption/complete offer and continued wordmark/offer invariants.
- `tests/Unit/MarketingFontsTest.php:1-42` — consumed by → targeted PHPUnit/Pest command; must update for retained licensed fonts plus Barlow/Bunny setup if Phase 1 changes sans font.
- `tests/Unit/MarketingLayoutTest.php:1-27` — consumed by → targeted test command; owner-intent test should be adapted, not deleted, as homepage section structure changes.
- `PRODUCT.md:1-300+` — consumed by → project rules, copy/product truth, tests; Phase 1 should record new visual freedom/explanatory diagram preference without changing offer truth.
- `.impeccable/surfaces/resources-views-pages-index-blade-php.md:1-37` — consumed by → Impeccable workflow and builder guidance; must be updated from old “Future, in person”/horizon brief to selected “The clear argument”.
- `DESIGN.md:1-250+` — consumed by → `.ai/rules/resources.md`, future design work; must document reviewed new foundation and remaining unconverted surfaces.
- New `resources/views/components/marketing/walkthrough-diagram.blade.php` — analogous consumers likely `resources/views/pages/index.blade.php` first; should be reusable and safe for repeated instances.
- New `resources/js/diagrams.js` — consumer should be `resources/js/app.js`; tests in new `resources/js/diagrams.test.js`.
- New `resources/js/diagrams.test.js` — consumer is Bun test command only.
- New `docs/ideation/2026-09-18-explanation-led-marketing-redesign/acceptance.md` — no existing acceptance analogue found under `docs/ideation`; should record actual command/review/owner-gate evidence and never pre-mark pending approval.

## Conventions

- **Naming**: Blade marketing components live under `resources/views/components/marketing/*.blade.php` and are invoked as `<x-marketing.*>`. Folio pages under `resources/views/pages` use `name('public.*')`. JS modules export `initX()` functions (`initInteractions`, `initBooking`, `initAnalytics`). CSS uses descriptive kebab-case classes.
- **Imports**: JS uses ESM relative imports without file extensions. `app.js` is the single Vite entry and currently initializes interactions → analytics → booking. CSS imports are centralized in `resources/css/app.css`.
- **Error handling**: Front-end enhancement modules are defensive and return early for absent DOM/config. Booking/analytics preserve no-JS fallbacks and avoid throwing when optional services are disabled. PHP tests assert omissions rather than exceptions for disabled PostHog.
- **Types**: No TypeScript. PHP tests use Pest closures; Blade props use `@props`. CSS tokens are Tailwind v4 `@theme` variables.
- **Testing**: Bun unit tests mock browser APIs and Motion for JS behavior. Pest feature/unit tests assert rendered HTML and inspect CSS strings. Modified PHP files require Pint. Build/type checks are part of final validation.
- **Project rules**: `.ai/rules/index.md` maps `resources/**` to `.ai/rules/resources.md`; marketing work must use PRODUCT.md, DESIGN.md, and matching Impeccable surface brief. Dev server rule: use `POSTHOG_DISABLED=true php artisan dev` / configured host, not `php artisan serve` or raw IP.
- **Accessibility/static content**: Existing chart pattern provides visible caption + data table; mobile menu remains native `<details>` without JS; booking links keep real href fallbacks.
- **Visual boundary**: Marketing is custom UI; Admin/customer remain Flux UI Pro and Inter. Do not let marketing font/token changes leak into operational surfaces.

## Risks

- **Rule/spec contradiction on sans font** — Phase spec says “start with Barlow through the existing Bunny/Vite font pipeline” and rejects mandatory Karla preservation, but `.ai/rules/resources.md:15`, `DESIGN.md`, `app.css:11`, and `vite.config.js:12` currently establish Karla. Builder should update durable docs/rules/tests consistently or ask if rule changes are allowed.
- **High CSS blast radius** — `resources/css/marketing.css` styles every marketing page, not just the homepage; palette/font/layout changes can regress `/walkthrough`, `/work`, `/writing/`, article charts, booking calendar, and mobile nav.
- **Owner-change preservation** — Spec notes prior modified `resources/css/marketing.css` and untracked `tests/Unit/MarketingLayoutTest.php`; current repo contains `MarketingLayoutTest.php`. Builder must inspect working tree/diffs before editing and reconcile, not reset.
- **Motion split risk** — Current `interactions.js` combines mobile menu and homepage horizon choreography. Phase 1 must remove/replace obsolete opening animation while keeping menu Escape/outside-click/desktop-switch behavior and tests.
- **Repeated SVG/component instances** — New walkthrough diagram must avoid duplicate IDs or generate per-instance IDs; repeated component tests are required.
- **Semantic diagram risk** — Essential offer meaning cannot live only in SVG paths, color, arrows, hover, or animation. Visible text and captions must contain the complete explanation.
- **Acceptance gate risk** — `acceptance.md` is new and must record actual evidence. Owner visual approval blocks Phase 2; an agent review cannot mark it approved.

---

# Phase 2 Context Map: Referral-led marketing journey

**Phase**: 2
**Spec**: `docs/ideation/2026-09-18-explanation-led-marketing-redesign/spec-phase-2.md`
**Prerequisite**: satisfied — `docs/ideation/2026-09-18-explanation-led-marketing-redesign/acceptance.md` records owner visual approval on 2026-09-19.
**Gates**: 5/5 ready
**Verdict**: GO

## Phase 2 Gates

| Gate | Status | Evidence |
| --- | --- | --- |
| Scope clarity | ready | `spec-phase-2.md` has concrete new/modified file lists, page-specific jobs, reporting-figure requirements, booking/navigation constraints, validation commands, and acceptance-recording expectations. |
| Pattern familiarity | ready | Read all in-scope existing files and analogues: current homepage, Walkthrough, Work, layout, booking link, assessment invitation, arrow, CSS, booking JS, walkthrough diagram, article chart, tests, PRODUCT/DESIGN, acceptance, and surface briefs. |
| Dependency awareness | ready | Grep mapped consumers for layout, booking markers, Cal hooks, route names, diagram selectors, CSS classes, analytics placement assertions, and layout invariants across `resources/`, `tests/`, `.impeccable/surfaces`, and specs. |
| Edge case coverage | ready | Spec and current tests cover JS-disabled booking fallbacks, modifier-click preservation, inline vs modal Cal behavior, native menu behavior, current-page nav, repeated static figure semantics, mobile/print/readability, no invented claims, and GHX/DataDash/proof boundaries. |
| Test strategy | ready | Inner loop and validation are explicit. Existing Pest coverage is strong for page facts/routes/analytics; Bun currently covers mobile menu and walkthrough diagram behavior. Note: contract references future `analytics.test.js`/`booking.test.js`, but Phase 2 validation does not require them. |

## Phase 2 Key Patterns

- `resources/views/pages/index.blade.php` — Phase 1 homepage is only partially complete: opening and walkthrough diagram are in The clear argument world, while lower sections (`.home-work`, `.assessment-strip`, approach/person/invitation) still need Phase 2 narrative completion.
- `resources/views/pages/walkthrough.blade.php` — Current page already contains the approved offer facts, inline `#choose-a-time` calendar, native FAQ disclosures, fit requirement, optional implementation, and single unpriced paid-discovery-week mention. Visual/copy is partly stale (`button-lilac`, older report panel).
- `resources/views/pages/work.blade.php` — Current Craft & Communicate story has an inline simple reporting flow. Phase 2 should extract/replace this with `resources/views/components/marketing/reporting-diagram.blade.php`, preserving only owner-supplied facts.
- `resources/views/components/marketing/walkthrough-diagram.blade.php` — Best analogue for the new reporting figure: authored semantic HTML figure, decorative SVG only, complete visible text, no IDs, reusable repeated-instance safety, visible figcaption.
- `resources/views/components/marketing/article-chart.blade.php` — Accessible figure precedent: `role="img"`/`aria-label`, visible caption, and source table. Reporting figure should copy the semantic completeness principle, not the chart implementation.
- `resources/css/marketing.css` — Central blast-radius file. Current selectors include both approved Phase 1 (`.home-opening`, `.walkthrough-diagram`) and retained/stale sections (`.button-lilac`, `.reporting-flow`, `.signature`, older work/walkthrough layouts). Phase 2 should remove obsolete selectors as part of replacement, not leave parallel old/new themes indefinitely.
- `resources/views/components/marketing/booking-link.blade.php` — Critical fallback component. Non-inline CTAs keep `href="{{ route('public.walkthrough') }}"`, `data-cal-link`, `data-cal-namespace`, and `data-cal-config`; inline CTAs point to `#choose-a-time`.
- `resources/js/booking.js` — Loader and click-preservation behavior is already aligned with spec intent: prevents navigation only after `customElements.get('cal-modal-box')`; modifier clicks are not intercepted. Theme tokens still reference obsolete purple names (`--color-lilac`, `--color-aubergine`) and need Phase 2 palette alignment only.
- `resources/views/components/marketing/layout.blade.php` — Single Alkaline wordmark, native mobile `<details>`, `How I work` anchor, current-page markers, footer links, PostHog data attributes. Footer has no repeated wordmark/signature.
- `tests/Feature/MarketingSiteTest.php` — Strong assertions for approved offer/proof boundaries, homepage walkthrough semantic order, repeated walkthrough figures, work story privacy, Walkthrough calendar/fallback facts, redirects, archive preservation, and wordmark count.
- `tests/Feature/MarketingAnalyticsTest.php` — Protects PostHog layout config and exact booking CTA placement markers; Phase 2 must update only if placements intentionally change and remain equally strong.
- `tests/Unit/MarketingLayoutTest.php` — Current owner-alignment test requires `.home-work` and `.assessment-strip` to share horizontal layout declarations at every breakpoint. Phase 2 can adapt this invariant but should not delete it silently.

## Phase 2 Dependencies

- `docs/ideation/.../acceptance.md` — Prerequisite is met. Owner approved Phase 1 opening/Barlow/Walkthrough figure/mobile/static-print sketch. Phase 2 must append actual Phase 2 evidence without converting QA into owner approval.
- `.ai/rules/resources.md` — Current durable rule explicitly says The clear argument supersedes Future, in person; marketing uses bundled Alkaline wordmark and Barlow body text; admin/customer stay Inter. This resolves the Phase 1 font contradiction.
- `PRODUCT.md` — Owns offer truth and proof boundaries: “I help businesses,” “fifteen years,” GitHub/Heroku/Zapier allowed, WorkOS/GHX omitted, Craft & Communicate only, no DataDash public title, free roughly one-hour pitch-free Walkthrough, written report within three business days, optional separate implementation.
- `DESIGN.md` — Records Phase 1 as approved foundation only; explicitly says lower homepage, Work, Walkthrough detail, Writing, and print are not finished. Phase 2 should update this to reflect reviewed homepage/Walkthrough/Work and leave Writing for Phase 3.
- `.impeccable/surfaces/resources-views-pages-index-blade-php.md` — Current homepage brief is Phase 1-specific and says lower homepage awaits Phase 2. Needs final homepage strategy.
- `.impeccable/surfaces/resources-views-pages-walkthrough-blade-php.md` — Still describes Future, in person, Karla, lilac/aubergine, black cherry opening. Must be rewritten for The clear argument.
- `.impeccable/surfaces/resources-views-pages-work-blade-php.md` — Still describes Future, in person and the old inline reporting diagram. Must be rewritten for The clear argument and reusable reporting figure.
- `resources/views/components/marketing/reporting-diagram.blade.php` — New file; currently absent. Consumer should be `resources/views/pages/work.blade.php` first, potentially article/static-print in Phase 3. Must support repeated instances and avoid duplicate IDs.
- `resources/views/pages/index.blade.php` — Folio route `public.index`; consumed by `MarketingSiteTest`, `MarketingAnalyticsTest`, CSS selectors, layout test, surface brief, discovery metadata through shared layout.
- `resources/views/pages/walkthrough.blade.php` — Folio route `public.walkthrough`; consumed by redirects, discovery structured data, inline Cal tests, mobile current-page assertions, booking JS via `[data-cal-inline]`.
- `resources/views/pages/work.blade.php` — Folio route `public.work`; consumed by homepage link, sitemap/discovery tests, work proof assertions, booking modal/fallback tests.
- `resources/views/components/marketing/layout.blade.php` — Consumed by all marketing pages and analytics/discovery tests. Nav/footer changes have site-wide blast radius including Writing.
- `resources/views/components/marketing/booking-link.blade.php` — Consumed by layout header/mobile menu, homepage hero/strip/closing, walkthrough hero, work closing invitation, writing modal CTAs. Breakage affects fallback, analytics, Cal modal, and tests.
- `resources/views/components/marketing/assessment-invitation.blade.php` — Consumed by homepage and work page. Name remains historical; visual/copy may change, factual offer may not.
- `resources/views/components/marketing/arrow.blade.php` — Shared inline SVG used throughout marketing links, FAQ summaries, and current reporting flow; visual changes affect many controls.
- `resources/css/marketing.css` — Imported by `resources/css/app.css`; affects every marketing page, article typography/charts, diagrams, mobile nav, print. Unit tests inspect this file directly.
- `resources/js/booking.js` — Loaded by `resources/js/app.js`; controls Cal loader, inline/modal mode, event tracking, and no-JS fallback preservation.
- `resources/js/app.js` — Initializes `initInteractions()`, `initDiagrams()`, `initAnalytics()`, `initBooking()`. Phase 2 likely should not change ordering unless necessary.
- `resources/js/interactions.js` / `resources/js/interactions.test.js` — Mobile menu only after Phase 1. Phase 2 nav changes must preserve selectors or update tests.
- `resources/js/diagrams.js` / `resources/js/diagrams.test.js` — Currently only targets `[data-walkthrough-diagram]`. Reporting figure motion is optional; if added, either reuse carefully or extend tests.
- `tests/Feature/MarketingSiteTest.php` — Needs stronger Phase 2 assertions for full homepage journey, Walkthrough deliverable/objections, and static reporting figure relationships.
- `tests/Feature/MarketingAnalyticsTest.php` — Must preserve `data-booking-cta` markers: header, mobile-menu, hero/homepage/closing, walkthrough hero/fallback.
- `tests/Feature/MarketingDiscoveryTest.php` — Not directly modified, but Phase 2 validation includes it; changes to route names, metadata, sitemap page count, or visible service offer can break it.
- `tests/Unit/MarketingLayoutTest.php` — Must be adapted if `.home-work`/`.assessment-strip` are replaced; preserve owner intent as a new shared-layout invariant.
- `tests/Unit/MarketingFontsTest.php` — Not listed for Phase 2 modification, but CSS/token changes can still break Barlow/wordmark invariants.

## Phase 2 Conventions

- **Routes/pages**: Folio pages use `name('public.*')`; canonical public paths are `/`, `/walkthrough`, `/work`, `/writing/`, and `/writing/{slug}/`.
- **Blade components**: Marketing components live under `resources/views/components/marketing/` and are invoked as `<x-marketing.*>`. Components should preserve real anchors and no-JS fallbacks.
- **Copy/product truth**: `PRODUCT.md` is authoritative. Use first-person singular. Do not invent metrics, testimonials, screenshots, timelines, scarcity, or unsupported client results.
- **Visual system**: The clear argument is current: cyan `#b7edf1`, ink `#102a33`, yellow `#f7cb58`, white, deep teal support, Barlow for marketing text, Alkaline once as the header wordmark.
- **Accessibility/static semantics**: Essential figure meaning belongs in visible HTML text and captions. SVG arrows/lines are decorative unless explicitly given accessible labels backed by complete text.
- **Booking behavior**: Inline on `/walkthrough` uses `#choose-a-time` and `[data-cal-inline]`; non-walkthrough CTAs use route fallback plus Cal data attributes. Modifier clicks must pass through.
- **Analytics hooks**: Use existing `data-booking-cta` placement markers and `data-booking-fallback`; do not rename events in Phase 2.
- **JS tests**: Bun tests mock browser APIs directly; do not require browser automation for unit behavior.
- **PHP tests**: Pest feature tests assert rendered strings/DOM. Avoid weakening proof-boundary assertions when copy changes.
- **Dev server**: If browser work is performed later, use `POSTHOG_DISABLED=true php artisan dev` / configured `localhost`, not `php artisan serve` or raw IP.

## Phase 2 Risks

- **Obsolete surface-brief contradiction** — Walkthrough and Work surface briefs still mandate Future, in person/Karla/lilac/aubergine. Phase 2 explicitly modifies them; builder must not follow their stale content while editing the pages.
- **Booking theme stale tokens** — `resources/js/booking.js` still looks up `--color-lilac` and `--color-aubergine`, which no longer exist in `app.css`; Phase 2 must align Cal theme to current tokens without refactoring event semantics.
- **High CSS blast radius** — `marketing.css` affects homepage, Walkthrough, Work, Writing, articles, charts, mobile menu, and print. Removing old selectors can regress Phase 3 surfaces if not checked.
- **Reporting-figure truth boundary** — The new reporting diagram must not imply measured savings, full automation, a fake dashboard screenshot, a software subscription, or a report-only product.
- **Offer expectation risk** — Homepage/Walkthrough visuals must keep report vs optional implementation distinction clear; free report cannot look like free implementation within three business days.
- **Analytics hook regression** — Layout/CTA rewrites can drop `data-booking-cta`, `data-cal-link`, `data-cal-namespace`, `data-booking-fallback`, or real hrefs.
- **Layout-test fragility** — `MarketingLayoutTest.php` currently hardcodes `.home-work` + `.assessment-strip`; Phase 2 probably changes section names/structure. Adapt to new owner-intent invariant rather than deleting.
- **No JS booking tests yet** — Contract later calls for `analytics.test.js`/`booking.test.js`, but they do not exist and are not Phase 2 validation. Do not over-scope Phase 2 by adding a broad JS analytics suite unless necessary.
- **Acceptance-recording risk** — `acceptance.md` currently contains Phase 1 evidence. Phase 2 should record actual commands/review evidence only after they happen; no pre-filled approval.
