# Implementation Spec: Explanation-led marketing redesign — Phase 1

**Contract**: ./contract.md
**Source of truth**: ./contract-data.json
**Scope**: Full
**Estimated effort**: L
**Prerequisite**: Explicit execution authorization; this document does not authorize implementation.

## Technical Approach

Build the visual foundation and a real homepage opening for **The clear argument**, selected by the owner on the direction board. This is a replacement visual world, not a purple-site polish. Keep only the licensed Alkaline header wordmark as a mandatory visual anchor. Preserve the established business, offer, first-person voice, and functional paths.

Use the existing Laravel 13.30.1 / Folio 1.2 / Blade, Vite, CSS, and vanilla Motion 13.2 stack. No new dependency. Begin with cyan `#B7EDF1`, dark blue-green `#102A33`, yellow emphasis `#F7CB58`, and white. Cyan owns major editorial fields, not tiny accents. Use an upright sans-serif hierarchy; start with Barlow through the existing Bunny/Vite font pipeline, subject to the owner's prototype review. Commit Mono stays for code, not every label. Do not change Admin/customer typography. Keep Alkaline's native tracking.

Load Impeccable and relevant project rules before editing, and its craft floor immediately before UI work. Reuse the chosen direction; do not reroll it. The execution is code-led: no generated comp is owed. The owner's requested SVG explanations are the actual authored material, not raster substitutes. Use a fresh independent Impeccable finish-review agent for this phase's bounded surface before requesting owner approval.

## Decisions Considered and Rejected

- Prioritize understandable expertise and qualified Walkthrough bookings, not visual novelty or a capacity-management story; the business is demand-constrained.
- Optimize referred/outreach visitors first, not a new SEO/content-acquisition program.
- Keep the Alkaline wordmark only; reject mandatory preservation of cherry/lilac, the horizon, Karla, or script headings.
- Choose The clear argument over The report, unfolded, Clarity in layers, and the category-standard layout. Reference `.impeccable/mocks/decision/redesign-directions.json`; seed `d5125406`, kind `pick`.
- Explain the Walkthrough's value first, not reporting automation or an invented follow-up case.
- Use complete static SVG/HTML with optional motion; reject decoration as a substitute for explanation.
- Owner review is the human approval gate; independent agent QA is not external reader recruitment.
- Preserve and reconcile existing owner changes; never reset them to simplify the redesign.

## Feedback Strategy

**Inner-loop command**: `bun test resources/js/interactions.test.js resources/js/diagrams.test.js`

**Playground**: Existing dev server started with `POSTHOG_DISABLED=true php artisan dev`; the homepage and focused PHP tests. Use the configured marketing host, not an IP substitute. Resolve a project URL with Boost before sharing it.

**Why**: Fast tests cover motion/menu behavior; a real browser covers typography, layout, and whether the explanation makes sense. Passing one is not evidence for the other. Use Impeccable's bounded capture/fix passes rather than an endless visual loop.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `resources/views/components/marketing/walkthrough-diagram.blade.php` | Complete, reusable offer explanation with semantic text and SVG relationships |
| `resources/js/diagrams.js` | Small progressive animation initializer, no generic diagram engine |
| `resources/js/diagrams.test.js` | Motion preference, lifecycle, absent/repeated figure coverage |
| `docs/ideation/2026-09-18-explanation-led-marketing-redesign/acceptance.md` | Actual review evidence and owner-gate record; pending is never approved |

### Modified Files

| File Path | Changes |
| --- | --- |
| `resources/css/app.css` | Marketing palette and font tokens; preserve operational-surface boundary |
| `resources/css/marketing.css` | Shared visual grammar, homepage opening, diagram/responsive/static print rules |
| `vite.config.js` | Sans font through existing pipeline; no package change |
| `resources/views/components/marketing/layout.blade.php` | Shared chrome consistent with new world, preserving semantics and hooks |
| `resources/views/pages/index.blade.php` | New opening and real lead explainer, retaining lower-page facts pending phase 2 |
| `resources/js/app.js` | Initialize explanatory motion independently of booking/analytics |
| `resources/js/interactions.js` | Replace obsolete horizon choreography; retain native menu enhancement |
| `resources/js/interactions.test.js` | Update opening expectations while retaining applicable lifecycle/menu coverage |
| `tests/Feature/MarketingSiteTest.php` | Static explanatory content and invariant offer/wordmark checks |
| `tests/Unit/MarketingFontsTest.php` | Verify retained licensed fonts and actual nonblocking font setup |
| `tests/Unit/MarketingLayoutTest.php` | Preserve owner's alignment intent as layout changes; do not delete tests |
| `PRODUCT.md` | Record confirmed visual freedom and explanatory-visual preference without changing offer truth |
| `.impeccable/surfaces/resources-views-pages-index-blade-php.md` | Selected direction contract, first-viewport and motion grammar |
| `DESIGN.md` | Document the reviewed new foundation and explicitly identify remaining unconverted surfaces |

### Deleted Files

None in this phase. Retire abandoned shipping assets after confirming final references in phase 4.

## Implementation Details

### 1. Entry and visual contract

Pattern: `resources/css/marketing.css`, existing layout, and the homepage surface brief.

1. Inspect `git status` and diffs. The planning baseline was commit `3247b6da2d3b3dfa3fa0f0d2711967ae657cb75d`, with modified marketing CSS and an untracked MarketingLayoutTest. Recheck reality; do not assume that list is still complete. Ask before incorporating unrelated work into any commit.
2. Read `.ai/rules` and the current surface brief. Record the approved direction in the surface brief through Impeccable's surface-brief commands. Do not put development contracts in shipped markup.
3. Record the owner's new brand preference in PRODUCT.md: Alkaline wordmark retained; new visual world; purposeful nontechnical explanatory diagrams/SVG/infographics across site, writing, and print; equivalent static meaning.
4. Reconfirm installed versions and use Boost search-docs before framework-specific changes. Use the Motion skill and installed-version guidance for animation APIs.
5. Create the homepage opening: a large recognizable business problem, concise first-person explanation, immediate free-Walkthrough CTA, then the connected explanatory figure. The first viewport must not require watching an animation to expose the offer. Avoid a report-product hero that hides the broader implementation practice.
6. Use fluid sizes, shared page gutters, visible keyboard focus, and normal text labels. Do not shrink a desktop SVG's entire label system to fit mobile. Reflow the figure stages vertically.

**Feedback loop**: Render at 390/768/1440px and 200% zoom; inspect headline wrapping, shared gutters, CTA visibility, font loading, and lower-page regressions. Check `php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Unit/MarketingFontsTest.php tests/Unit/MarketingLayoutTest.php` after each relevant change. Explain any necessary adaptation of the owner's layout test; never silently delete it.

### 2. Walkthrough explanation

Pattern: `resources/views/components/marketing/article-chart.blade.php` for semantic figure/caption treatment, not its chart scales.

Use this factual sequence, with final wording aligned to PRODUCT.md:

- **Bring the work:** a process the team actually performs; the prospect need not diagnose or specify software.
- **Talk it through:** roughly one hour with the person doing the work; free and pitch-free.
- **Keep the report:** within three business days, describing observed problems, recommended improvements, and **Where I'd start**.
- **Choose what happens next:** use the recommendations independently, discuss separate implementation, or do nothing; no purchase obligation.

The report contents are an explanation of the offer, not a fabricated client report. Every essential relationship must appear in visible semantic text. Use SVG to connect or annotate it. Do not rely solely on arrow direction, position, color, hover, or an animation frame. Provide meaningful captions/text alternatives without screen-reader duplication; decorative paths are hidden from assistive technology. Support repeated component instances without duplicate IDs. Do not add configurable arbitrary graph data or an editor API.

**Feedback loop**: Compare complete static render, mobile vertical arrangement, and repeated instances. Add HTTP assertions for the factual labels, caption, and visible complete explanation in the existing homepage test file. Check `php artisan test --compact tests/Feature/MarketingSiteTest.php`.

### 3. Optional motion

Pattern: `resources/js/interactions.js` and its existing tests.

A brief, non-looping emphasis moves through already visible stages and highlights the first recommendation. Motion is attention guidance, not the mechanism that makes content appear. Start once when appropriate; late loading, an offscreen figure, missing DOM, a hidden document, or reduced motion must not create a broken intermediate state. Restore the static state on reduced-motion changes, page hiding, and printing. Do not animate indefinitely or intercept scrolling.

Keep the module small and independently testable. Avoid browser automation dependencies or a shared animation framework. Existing `animate`/lifecycle patterns are sufficient; verify current APIs before implementing.

**Feedback loop**: Test motion allowed/reduced, preference switching during play, zero/one/two figures, hidden document, and print entry; run `bun test resources/js/interactions.test.js resources/js/diagrams.test.js`. Confirm in-browser rather than accepting mocks as rendering evidence.

## Testing Requirements

- Preserve all approved offer/public-proof boundaries and the single-header-wordmark behavior.
- Retain native mobile menu without JS and its Escape/outside-click enhancements with JS.
- Test the new motion lifecycle, including preference changes, with controlled DOM/animation fakes.
- Inspect complete static and print-preview explanations; a print sketch here is an early check, not phase 3's finished specimen.
- Run Pint on modified PHP files; do not install a new test framework.

## Failure Modes

| Component | Failure / trigger | Impact | Mitigation |
| --- | --- | --- | --- |
| First viewport | Report dominates business promise | Looks like a report-only service | Lead with business problem; keep implementation proof immediately downstream |
| Figure | Desktop viewBox scaled to phone | Tiny unreadable text | Semantic labels and vertical reflow |
| SVG references | Figure repeated with fixed IDs | Broken connections or accessible names | Per-instance IDs or avoid unnecessary ID references; test repetition |
| Motion | Preference changes or print during playback | Incomplete explanation | Static information always present; stop/reset enhancement |
| Font/theme change | New tokens affect existing pages | Unreadable intermediate site | Check all public surfaces; do not touch operational themes |
| Existing work | Resetting CSS/test to clean baseline | Owner changes lost | Inspect/reconcile first; no reset or deletion |

## Validation Commands

```bash
bun test resources/js/interactions.test.js resources/js/diagrams.test.js
php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Unit/MarketingFontsTest.php tests/Unit/MarketingLayoutTest.php
vendor/bin/pint --dirty --format agent
composer types:check
bun run build
```

## Exit and Rollout

Capture the homepage at desktop/mobile widths with animation settled, run the Impeccable detector once over this phase's changed UI targets if no hook is active, and obtain a fresh agent finish review. Fix material findings within the skill's bounded review process. Record findings and actual command results in `acceptance.md`; document the reviewed foundation in DESIGN.md, explicitly labeling pages awaiting phases 2–3 rather than describing them as finished.

**STOP for owner visual approval.** Show the real opening, diagram, mobile behavior, and static/print sketch. Record the owner's actual decision. Phase 2 is blocked until approval; an agent cannot mark this gate approved. No deployment, live booking, or PostHog account changes.
