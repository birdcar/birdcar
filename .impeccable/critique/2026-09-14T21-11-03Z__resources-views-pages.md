---
target: Whole marketing site (resources/views/pages)
total_score: 20
max_score: 24
na_heuristics: 5,7,9,10
p0_count: 0
p1_count: 1
target_identity: "file:/Users/birdcar/Code/birdcar/birdcar/resources/views/pages"
timestamp: 2026-09-14T21-11-03Z
slug: resources-views-pages
---
**Method: dual-agent (A: assessment-a · B: assessment-b)**

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Native `details` disclosures announce state correctly. Nothing else on a static site needs status. |
| 2 | Match System / Real World | 4 | Copy names the buyer's actual week, not software. No jargon anywhere. |
| 3 | User Control and Freedom | 3 | Mobile menu closes on outside tap, link, and Escape with focus return. Nothing destructive exists to undo. |
| 4 | Consistency and Standards | 3 | Header booking link is the only control sized in raw `vw`, so it breaks scale below 1300px. Button class names drift from DESIGN.md. |
| 5 | Error Prevention | n/a | No forms or inputs on this surface. Booking happens on cal.com. |
| 6 | Recognition Rather Than Recall | 4 | Current page underlined; the booking invitation repeats verbatim at every depth. |
| 7 | Flexibility and Efficiency | n/a | Persuade/Read surface with five pages. No accelerators are warranted. |
| 8 | Aesthetic and Minimalist Design | 3 | Hero lettering shows seams at every join. Work page ends on a half-empty grid. |
| 9 | Error Recovery | n/a | No error states exist on this surface. |
| 10 | Help and Documentation | n/a | Persuade/Read surface. The FAQ does this job. |
| **Total** | | **20/24** | **Good (83%)** |

Four heuristics scored n/a (5, 7, 9, 10), so the applicable maximum is 24.

## Design Specificity Verdict

**LLM assessment.** Authored, not interchangeable. The connected-script Alkaline wordmark and headline appear nowhere else in this genre. The offer copy names its own mechanics ("About an hour. A free assessment. A report to keep.") instead of generic calls to action. The single work story is a labeled diagram captioned as illustrated rather than a faked dashboard. The Field Pairing Rule holds with zero violations across all five pages and every measured state, with contrast between 8.7:1 and 13.6:1 everywhere sampled. No gradient mesh, no logo row, no testimonial, no invented metric. Assessment A missed one thing the owner caught: the hero's letter joins are visibly broken at display scale.

**Deterministic scan.** CLI detector: zero findings, exit 0, across all ten Blade files under `resources/views/pages` and `resources/views/components/marketing`; scanning of .blade.php verified with a synthetic file. Browser overlay against the live stylesheet: one recurring `cramped-padding` hit on every page (the header booking link), plus `line-length` on 23 article paragraphs at ~88 characters per line. An earlier browser pass that reported 16 findings on / and /assessment ran against a stale Sep 1 build after the Vite hot file was deleted; it was discarded and redone with the stylesheet host verified.

**Visual overlays.** Injection succeeded and the detector ran in-page on all five URLs; the assessment tab was closed and the live server stopped after capture, so no overlay remains open. Screenshots: scratchpad `assessment-b/`.

## Overall Impression

A disciplined, confident brand surface that mostly earns its "Future, in person" thesis. Type carries the identity, color pairings are flawless, and the mobile build is finished rather than tolerated. The gap is craft at the two extremes of scale: at hero size the signature lettering shows seams because tracking fights the font's connected joins; at tablet widths the header's primary action shrinks to unreadable because it was sized for one desktop composition.

## What's Working

- **Contrast and focus discipline are excellent.** Every pairing measured exceeds WCAG AAA. The two-color focus ring is visible and in correct tab order on the header link and primary button.
- **Mobile is a first-class layout.** Native `details` menu, 52–59px touch targets, full-width primary button, single-column reading order. Server-rendered HTML is complete without JavaScript.
- **The Craft & Communicate diagram is honest.** Captioned as illustrated, built from labeled blocks and arrows, not a faked screenshot.

## Priority Issues

**[P1] Hero tracking breaks Alkaline's connected joins.** `.home-hero h1 { letter-spacing: -.02em }` at `resources/css/marketing.css:51` computes to -3.1px at 1440px. Alkaline's joins depend on native advance widths, so tracking opens notches at r→o in "room", the t's in "better", and w→o in "work". Confirmed by the owner's screenshot and Assessment B's 2× capture. *Why:* the hero is the largest expression of the brand's craft claim. *Fix:* remove the tracking; tighten with line-height or size if needed; update the homepage surface brief, which records -.02em as approved. *Command:* $impeccable typeset

**[P2] Header booking link collapses between 760px and 1300px.** `.desktop-nav .nav-booking` at `marketing.css:26`: width 15.23vw, height 3.38vw, font-size .98vw, padding 0, no override until 760px. At 1100px: 10.8px text in a 37px box. The detector's `cramped-padding` finding is this element. *Why:* the primary conversion action in the most persistent position becomes the smallest text on the page on tablets. *Fix:* `font-size: clamp(.9rem, .98vw, 1rem)`, `min-height: 44px`, drop fixed height/width, restore `padding: .7rem 1rem`. *Command:* $impeccable adapt

**[P2] Work page ends on a stranded block.** `.story-bottom { grid-column: 2 }` at `marketing.css:106` pins the closing heading and paragraph to the right column, leaving the left column empty under the diagram; every other section pairs heading-left with explanation-right. *Why:* last thing seen before the invitation; breaks the page's own rhythm. *Fix:* h3 in column 1, paragraph in column 2, matching `.story-heading` / `.story-body`; spanning `1 / -1` is the fallback. Page /work, `resources/views/pages/work.blade.php:27`. *Command:* $impeccable layout

**[P2] Cal.com handoff is unprepared.** Every booking link leaves the site with only a small mono caption as warning. *Why:* the emotional-journey valley at the highest-commitment moment; peak-end rule. *Fix:* one preparatory sentence beside each booking button, a consistent external-link cue, `target="_blank" rel="noopener"` so Birdcar stays open. Pages /, /assessment, /work via `x-marketing.assessment-invitation`. *Command:* $impeccable clarify

**[P2] Article measure runs ~88 characters per line.** `.article-prose { max-width: 70ch }` at `marketing.css:134` yields ~88 real characters in Karla (`ch` measures the zero glyph). Detector flagged 23 paragraphs, consistent across three passes. *Why:* sustained reading is the Writing section's job. *Fix:* ~60ch, or raise reading size to 1.25rem; update DESIGN.md's recorded measure. *Command:* $impeccable typeset

## Persona Red Flags

- **Jordan (first-timer):** none. Offer explained in three short paragraphs before any ask; the promise repeats at every button.
- **Riley (stress tester):** Work page empty column; `button-brick`/`button-cream` vs DESIGN.md's `button-ink`/`button-paper` naming drift.
- **Casey (one-handed mobile):** none at phone width. On a landscape tablet the header booking link is 10px text.
- **DFW agency owner weighing an admin hire:** "Let's start with what's painful" names their trigger nearly verbatim from PRODUCT.md. No geography stated (deliberate tradeoff).
- **Operations lead wary of AI theater:** best served; FAQ answers "Is this an AI assessment?" pre-emptively.

## Minor Observations

- FAQ has six disclosures; Assessment A flagged the four-option ceiling. Synthesis disagrees on severity: six native accordions are a scannable list, not a simultaneous decision. Leave it.
- `.report-wordmark` at `marketing.css:186` is dead; no view uses it.
- `@media (prefers-reduced-motion: no-preference) {}` at `marketing.css:350` is empty.
- Charts render on three essays with caption and native data-table disclosure. Correct.
- Horizon image `alt=""` is correct for decorative art.
- Button min-heights match DESIGN.md: 62px desktop, 58.8px mobile.

## Questions to Consider

- The -.02em tracking tightened the hero. What carries that density once the joins get native spacing?
- The site never states geography. Is that omission earning its keep, or costing DFW recognition?
- Has booking completion vs. abandonment at the cal.com handoff been measured?
- When a second work story arrives, does the two-column story grid generalize or reproduce the empty column?
- Every control except the header booking link uses clamp() or rem. Why is the most important control the exception?
