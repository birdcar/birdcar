# Implementation Spec: Explanation-led marketing redesign — Phase 2

**Contract**: ./contract.md
**Source of truth**: ./contract-data.json
**Scope**: Full — the reporting figure is required
**Estimated effort**: L
**Prerequisite**: Phase 1 complete and the owner's visual approval recorded in `acceptance.md`.

## Technical Approach

Finish the referral-led public marketing journey using the phase-1 approved **The clear argument** foundation. Keep the homepage, Walkthrough, and work page distinct in purpose while sharing typography, color meanings, figures, navigation, and controls. Do not restart visual exploration or preserve discarded horizon/cherry/lilac styling merely because it remains in an old record.

Reuse the current Folio routes, marketing layout, booking-link component, and Cal loader. The owner sells operational understanding, implementation, and care—not a software subscription or a report-only product. The offer explanation earns attention; the real Craft & Communicate story proves implementation capability. No new package, invented case-study screenshot, testimonial, client result, or commercial promise is needed.

## Decisions Considered and Rejected

- Referral/outreach prospects first; reject an SEO campaign or a new content funnel as the redesign's primary job.
- Understandable expertise and the buying journey outrank aesthetic novelty.
- The clear argument and only the Alkaline wordmark are approved visual authority; do not reroll or restore the old visual family.
- Walkthrough explanation leads; reporting is supporting implementation proof, required by the approved Full scope.
- Keep the free, roughly one-hour, pitch-free conversation and report within three business days; implementation is optional and separately purchased.
- No GHX disclosure, WorkOS employer reference, fake metrics/testimonials, scarcity, or DataDash public title.
- Use concrete authored figures, not a diagram registry or arbitrary SVG loader.
- Preserve actual behavior rather than obsolete markup; no test deletion without owner approval.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingAnalyticsTest.php`

**Playground**: Existing dev server, with analytics disabled locally, plus desktop/mobile browser inspection of `/`, `/walkthrough`, and `/work`.

**Why**: HTTP tests protect facts, links, and hooks during layout edits; the browser proves actual hierarchy and booking behavior. No fabricated conversion improvement is inferred from either.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `resources/views/components/marketing/reporting-diagram.blade.php` | Authored illustration of the supplied reporting story with complete static semantics |

### Modified Files

| File Path | Changes |
| --- | --- |
| `resources/views/pages/index.blade.php` | Complete problem → offer explanation → real work → approach/person → invitation journey |
| `resources/views/pages/walkthrough.blade.php` | Tangible deliverable, sequence, objections, and clear booking access |
| `resources/views/pages/work.blade.php` | Craft & Communicate story and reusable reporting figure |
| `resources/views/components/marketing/layout.blade.php` | Final shared navigation/footer states and consistent invitation |
| `resources/views/components/marketing/booking-link.blade.php` | Presentation only as needed; preserve placement and fallback behavior |
| `resources/views/components/marketing/assessment-invitation.blade.php` | New visual treatment, unchanged factual offer |
| `resources/views/components/marketing/arrow.blade.php` | Shared line/icon treatment if needed for the approved grammar |
| `resources/css/marketing.css` | Page compositions, figure styles, responsive/focus/control states |
| `resources/js/booking.js` | Align Cal theme with new tokens without unrelated loader/event refactoring |
| `tests/Feature/MarketingSiteTest.php` | Stable offer/proof/route/navigation assertions and new static reporting figure |
| `tests/Feature/MarketingAnalyticsTest.php` | Preserve required placement markers through rewritten markup |
| `tests/Unit/MarketingLayoutTest.php` | Preserve aligned shared layout intent where phase-2 sections change |
| `.impeccable/surfaces/resources-views-pages-index-blade-php.md` | Final homepage strategy |
| `.impeccable/surfaces/resources-views-pages-walkthrough-blade-php.md` | New offer-page strategy |
| `.impeccable/surfaces/resources-views-pages-work-blade-php.md` | New proof-page strategy |
| `DESIGN.md` | Record the reviewed marketing pages; identify Writing as still awaiting phase 3 |
| `docs/ideation/2026-09-18-explanation-led-marketing-redesign/acceptance.md` | Actual phase checks and review evidence |

### Deleted Files

None. Remove obsolete selectors during the CSS edits, not by leaving parallel old/new themes indefinitely.

## Implementation Details

### 1. Page-specific jobs

Patterns: existing three Folio pages, `booking-link.blade.php`, and `MarketingSiteTest.php`.

- **Homepage:** recognize a real operating problem, explain what I do, make the Walkthrough useful and low-pressure, show Craft & Communicate, explain implementation/care, and keep the person visible through first-person reasoning. Booking is available directly; reading a case study is not a prerequisite. Do not promote archived essays on the homepage.
- **Walkthrough:** show what the conversation covers, the report's observed problems/recommendations/Where I'd start, timing, who it fits, the pitch-free commitment, and the client's choices afterward. Keep the live calendar and plain fallback. Do not imply a functioning implementation is delivered free within three days. Preserve the one unpriced paid-discovery-week mention.
- **Work:** describe the supplied reporting project, not a hypothetical business transformation. Explain manual performance-number gathering, the client-facing platform, client management/live-updating data, and its role as an agency offering. No invented savings, delivery timeframe, growth result, or customer quote.

Targeted headings, figure labels, and connective prose may change for clarity, but approved facts and voice remain. Replace fragile layout-specific or verbatim-copy assertions only with equally strong fact/behavior assertions; do not weaken the no-GHX/no-WorkOS/no-fake-results boundaries or treat pronoun counts as proof of persuasion.

**Feedback loop**: Check each page at 390/768/1440px, with short and long headings naturally wrapped, and with JS disabled. Run the inner-loop command; compare the page's stated job to the actual rendered path, not just its section order.

### 2. Reporting illustration

Pattern: current `work.blade.php` reporting flow and the phase-1 Walkthrough component.

Use plain-language labels connecting the known inputs and reporting/client-facing result. A before/after juxtaposition may show manual number gathering versus consolidated reporting, but must not imply measured savings or promise that all work became automatic. Caption it as an illustration, not a product screenshot. Keep full information static. Reuse type, strokes, semantic color, captions, and spacing; do not build a shared graph data model to connect two authored components.

Ensure repeated instances have unique references and print/mobile text stays readable. Optional motion can reuse the small existing enhancement if it genuinely explains the transition; a second custom animation is not a requirement.

**Feedback loop**: Inspect normal/static/grayscale examples and two copies on the same page during development. Assert the known relationships and illustrative caption in `MarketingSiteTest.php`; check `php artisan test --compact tests/Feature/MarketingSiteTest.php`.

### 3. Navigation, booking, and integration

Preserve named Folio routes and configured-origin URLs. Keep native mobile `details/summary`, current-page indication, keyboard focus, Escape/outside-click behavior, and the real `#how-i-work` anchor. Render the Alkaline wordmark once in the header, not again as a footer signature.

Preserve `data-booking-cta` placement values and Cal namespace `walkthrough`. On `/walkthrough`, CTAs lead to `#choose-a-time` and the inline calendar. Elsewhere, existing modal triggers retain their real `/walkthrough` href. The direct `https://cal.com/birdcar/walkthrough` fallback remains. Keep prevented navigation conditional on the embed being able to take over; modifier-clicks should not be intercepted. Match the calendar's palette to the new world without renaming events here; phase 4 verifies callback semantics.

**Feedback loop**: Check loaded embed, blocked script, JS disabled, keyboard activation, and modified clicks. Run `php artisan test --compact tests/Feature/MarketingAnalyticsTest.php tests/Feature/MarketingDiscoveryTest.php`; no real booking or production event is authorized by this spec.

## Failure Modes

| Component | Failure / trigger | Impact | Mitigation |
| --- | --- | --- | --- |
| Offer figure | Report and implementation visually conflated | False expectation of free implementation | Explicit report/optional implementation distinction |
| Work figure | Polished invented dashboard or numeric label | Unsupported claim | Illustrate only supplied facts and label the illustration |
| CTA | Styling removes data hooks or real href | Broken measurement or fallback | Component reuse, placement assertions, browser checks |
| Calendar theme | Old purple values survive token change | Inconsistent or unreadable embed | Align theme lookup/fallbacks and inspect both inline/modal |
| Mobile layout | Large headline/diagram forced into desktop columns | Overflow and unreadable text | Reflow in reading order, no viewport-scaled tiny labels |

## Validation Commands

```bash
php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiscoveryTest.php tests/Feature/MarketingAnalyticsTest.php tests/Unit/MarketingLayoutTest.php
bun test resources/js/interactions.test.js resources/js/diagrams.test.js
vendor/bin/pint --dirty --format agent
composer types:check
bun run build
```

## Exit and Rollout

Use bounded desktop/mobile inspection and a fresh independent finish review for these pages. Record actual results in `acceptance.md`, then document the reviewed page strategies and current design system. No rollout, traffic campaign, new analytics dashboard, production booking, or operational-surface change occurs here. Phase 3 builds on this reviewed shared foundation.
