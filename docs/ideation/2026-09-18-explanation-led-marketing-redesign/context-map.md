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
