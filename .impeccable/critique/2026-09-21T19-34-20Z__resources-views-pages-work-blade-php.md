---
target: /work (Selected work page)
total_score: 21
max_score: 24
na_heuristics: 5,7,9,10
p0_count: 0
p1_count: 1
target_identity: "file:/Users/birdcar/Code/birdcar/birdcar/resources/views/pages/work.blade.php"
target_fingerprint: "sha256:bf6d871ebfecafc6e7f26ea6efd8cce5b29c928e5306b54facbda3f008804591"
target_path: /Users/birdcar/Code/birdcar/birdcar/resources/views/pages/work.blade.php
timestamp: 2026-09-21T19-34-20Z
slug: resources-views-pages-work-blade-php
---
**Method: dual-agent (A: assessment-a · B: assessment-b)**

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Current page is underlined in header and footer. The yellow button opens Cal's overlay with no cue that it stays on the page. |
| 2 | Match System / Real World | 4 | "Finding the numbers by hand" is the buyer's own sentence. No software vocabulary anywhere. |
| 3 | User Control and Freedom | 4 | Skip link, native menu that closes, real `/walkthrough` href under the Cal hook. Nothing traps. |
| 4 | Consistency and Standards | 3 | Nav and title tag say "Selected work"; the page never does. The closing button is the only mobile CTA that is not full width. |
| 5 | Error Prevention | n/a | No inputs on this surface; booking happens inside Cal. |
| 6 | Recognition Rather Than Recall | 4 | Every option visible; the figure carries its meaning in labels; no memory from another page needed. |
| 7 | Flexibility and Efficiency | n/a | Persuade surface, five pages. No accelerators warranted. |
| 8 | Aesthetic and Minimalist Design | 3 | No clutter, but the "before" panel is three identical boxes and the caption rule stops mid-figure. |
| 9 | Error Recovery | n/a | No error states exist on this surface. |
| 10 | Help and Documentation | n/a | Self-explanatory proof page; the Walkthrough FAQ does this job. |
| **Total** | | **21/24** | **Good (88%)** |

Four heuristics scored n/a (5, 7, 9, 10), so the applicable maximum is 24.

## Design Specificity Verdict

**LLM assessment.** The page is authored inside its system: cyan title band, ink Barlow, white story grid, a square outlined platform, one yellow action, one Alkaline wordmark. No generic consultancy could ship it without adopting the whole DESIGN.md world. Within that world, though, specificity ends at the client's name. Swap "Craft & Communicate" for any other agency and nothing else on the page would change: the three identical source boxes (`resources/views/components/marketing/reporting-diagram.blade.php:7-10`), the "Performance data / Client management" labels (`:16-17`), and a closing question shared with the rest of the site. The figure is a real argument (scattered boxes resolve into one ruled container) and the caption is unusually honest, but as proof it reads like a wireframe of a promise rather than a record of something built. Missed opportunities: no named buying trigger from PRODUCT.md (reporting assembled from five or more sources); no true texture about what the agency's client sees; no callback from the invitation to the story just told.

**Deterministic scan.** CLI detector: zero findings, exit 0, across `work.blade.php` and its five components (layout, reporting-diagram, assessment-invitation, booking-link, arrow). In-page detector: clean at 1440px. At 390px it reported four anti-patterns (one `text-overflow`, three `text-occlusion` on "How I work", "Selected work", "Writing", "Book a free Walkthrough"). All four are false positives: those are the mobile menu's links inside the closed native `details`, which have zero-size boxes at the top of the title band, and the desktop nav is `display: none` below 760px (`resources/css/marketing.css:324`). Assessment A's 390px capture shows no collision. The detector caught nothing the review missed; the review caught what the detector cannot see (thinness of proof, tablet measure, label mismatch).

**Visual overlays.** Injection succeeded at 1440 and 390 and the detector ran in-page. The `[Human]` tab is open at 1440, where the detector reported nothing, so no markers are drawn. The overlay live server on port 8400 was stopped.

## Overall Impression

A clean, honest, well-built page that under-argues. Everything mechanical passes: contrast 11.7:1 to 15:1 on every pairing, no horizontal overflow at 390, 768, or 720, a real two-part focus ring, print hides chrome and keeps the caption, reduced motion leaves zero animations, zero console errors, zero failed requests. The earlier critique's Work-page findings are fixed: the closing heading now sits in column 1 with its paragraph in column 2, and the header booking control is a proper 205×44 target. The single biggest opportunity is the proof itself. One story carries the page, and that story is told in abstractions. Two or three true details from the owner and a redrawn "before" panel would turn a tidy illustration into evidence.

## What's Working

- **The figure is an argument, not decoration.** Scattered boxes on the left resolve into one ruled, outlined container on the right. The relationship reads in static HTML and SVG, in print, and without JavaScript, exactly as the brief demands.
- **Color economy points at the ask.** Cyan bookends the page, white carries the reasoning, and yellow appears once, on the only action that matters. The Argument Contrast Rule and Proof Boundary Color Rule both hold.
- **Copy candor.** The caption states "not a product screenshot or measured results" in the open. The story never claims a metric, a testimonial, or a result the owner has not supplied.

## Priority Issues

**[P1] The proof page's only proof is abstract.** The "before" panel draws three identical unlabeled boxes (`reporting-diagram.blade.php:7-10`) for "finding the numbers by hand," and the platform card describes itself in category nouns ("Live-updating data for reporting," "clients managed alongside their reporting," `:16-17`). At 1440 the panel is vertically centered against the tall card (`marketing.css:120`), floating in white space with a 280px-max SVG (`:123`). *Why it matters:* for the two buyers this page exists to convince, the DFW agency owner and the operations lead wary of AI theater, the page's whole job is proving a real system was built for a real business. Nothing here could not be said about a hypothetical project. *Fix:* ask the owner for two or three true, approved details and use them: which kinds of sources were being gathered by hand (label the three boxes differently), what the agency's client sees when they open the platform, and what someone at the agency stopped doing. Do not invent sources or outcomes; if the owner cannot supply them, say so in the copy rather than staying generic. Redraw the "before" panel so the three sources differ in shape and the connections look like the work they replaced. Let the closing heading echo the story ("What are you still gathering by hand?") instead of the site-wide question (`work.blade.php:20`). *Command:* $impeccable clarify

**[P2] The page arrives without its own name.** The nav link, footer link, and `<title>` all say "Selected work"; the h1 says "Craft & Communicate" and nothing in the cyan band names the section (`work.blade.php:10`). The plural label also promises a collection and the page delivers one story with no acknowledgement. *Why it matters:* the first beat after clicking is "did I land in the right place?", rescued only by the nav underline. Jordan hesitates; the nav and page disagree. *Fix:* add a caption-step eyebrow above the h1 in the title band ("Selected work", styled per the `caption` token) so the destination confirms itself, and let the description acknowledge that this is one project told in detail. When a second story arrives, the eyebrow becomes the index heading. *Command:* $impeccable clarify

**[P2] The two-column story grid holds until 760px and squeezes the tablet layout.** `.work-story { grid-template-columns: .85fr 1.15fr; gap: 3rem 8% }` (`marketing.css:113`) is untouched by the 1100px block (`:275`) and collapses only at 760px (`:359`). At 768px the text column is about 358px wide: story paragraphs run 41 characters per line at 18px, and the h2 stacks four lines tall ("Getting the / numbers / was part of / the work.") beside them. *Why it matters:* iPad portrait is 8px above the collapse and gets the worst version of the page: mobile-narrow measure inside a desktop-shaped grid. *Fix:* inside the 1100px block, rebalance to `1fr 1fr` with a `5%` gap, or collapse `.work-story` to one column at 900px and let `.story-heading h2` keep its 2.6rem mobile step. Check the 768 reporting figure at the same time; the 1.5:1 comparison grid (`:120`) also gets tight there. *Command:* $impeccable adapt

**[P3] The closing button is the only mobile CTA that is not full width.** At 390px the closing-invitation button measures 271×59, left-aligned at about 70% of the column, while `.hero-action .button` and `.offer-copy .button` stretch full width at the same breakpoint (`marketing.css:48`, `:403`). *Why it matters:* a small inconsistency in an otherwise disciplined button system, on the one action the page is for. *Fix:* add `.closing-invitation .button { width: 100%; }` inside the 760px block. *Command:* $impeccable polish

**[P3] Footer navigation links are 22px tall on mobile.** `.site-footer nav a` inherits `font-size: .9rem` with no vertical padding (`marketing.css:107`, `:354`); B measured 24px at 1440 and A measured 22px at 390. WCAG 2.2 target size passes only through the spacing exception (20px row gap). The mobile menu offers the same destinations at 52px. *Why it matters:* the footer is the last exit on the page and Casey taps it with a thumb. *Fix:* `padding-block: .35rem` on `.site-footer nav a`, keeping the underline offset in sync (`:108`). *Command:* $impeccable harden

## Persona Red Flags

- **Jordan (first-timer):** one beat of doubt on arrival: clicked "Selected work," landed on "Craft & Communicate." The underline rescues it. Otherwise linear, plain, and short.
- **Riley (stress tester):** no overflow at 390, 768, or 720. No-JS booking falls back to the real `/walkthrough` href. Print hides header and CTA and keeps the caption. Reduced motion: zero animations. Long client name balances cleanly. The layout assumes exactly one story; a second one has nowhere to go.
- **Casey (one-handed mobile):** the yellow CTA sits at the bottom of the page in the thumb zone but is 271px wide and left-aligned. Footer links are 22px tall. Mobile menu rows are 52px. Nothing at the top of the screen is required.
- **DFW agency owner weighing another admin hire:** never sees the trigger PRODUCT.md names for them (reporting assembled from five or more sources). "By hand" is the closest the page gets, then it moves on.
- **Operations lead wary of AI theater:** the honest caption helps, but with no concrete detail the platform card reads like a diagram of a promise. This reader wants one true texture, not a disclaimer.

## Minor Observations

- The figcaption rule spans `max-width: 75ch` (`marketing.css:137`) and stops under the platform card's left third at 1440, reading as an unfinished line. Either span the figure and constrain the text inside, or align the rule to the "before" column.
- `.reporting-comparison { align-items: center }` (`:120`) floats the short "before" panel with about 130px of white above and below it at 1440. `align-items: start` would let it read as the origin of the flow.
- The header booking control is exactly 44px tall (`:29`), the minimum with no margin.
- All six SVGs are `aria-hidden`; the figure takes its accessible name from the figcaption. Correct.
- Heading outline is h1, h2, h3, h3, h2, h2 with no skips. Landmarks, `lang`, title, description, and canonical are all present.
- Both assessments' 768 and 1440 screenshots show right-edge clipping; both confirmed by `scrollWidth` that the page does not overflow. This is a capture artifact of the shared Chrome window, not a defect.

## Questions to Consider

- If the client name were swapped, would anything else on this page have to change?
- What two or three true details can the owner supply today (sources gathered by hand, what the client sees on login, who stopped doing what) without inventing a result?
- Does "not a product screenshot or measured results" build trust, or concede the skeptic's point before the case is made? Would one concrete detail make the sentence unnecessary?
- When the second story arrives, does `/work` become an index, and does "Selected work" then become the h1?
- Should the closing question belong to this page ("What are you still gathering by hand?") rather than to the site?
