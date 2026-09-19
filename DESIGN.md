---
name: Birdcar — The clear argument
description: "Phase 1 marketing foundation: cyan editorial fields, blue-green ink, yellow emphasis, white explanation space, upright Barlow, and one Alkaline wordmark."
colors:
  ink: "#102a33"
  paper: "#ffffff"
  cyan: "#b7edf1"
  emphasis: "#f7cb58"
  deep-teal: "#214b57"
  cyan-wash: "#b7edf130"
typography:
  wordmark:
    fontFamily: "Alkaline, cursive"
    fontSize: "3.4rem"
    fontWeight: 500
    lineHeight: 1.15
  display:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(3rem, 6.65vw, 6rem)"
    fontWeight: 600
    lineHeight: 1.02
    letterSpacing: "-.025em"
  headline:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2rem, 3.3vw, 3rem)"
    fontWeight: 600
    lineHeight: 1.08
  body:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 400
    lineHeight: 1.55
  lead:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.35rem, 1.7vw, 1.6rem)"
    fontWeight: 400
    lineHeight: 1.4
  button:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.075rem"
    fontWeight: 600
    lineHeight: 1.55
rounded:
  nav: "3px"
  control: "5px"
  circle: "50%"
  square: "0"
spacing:
  gutter: "7%"
  gutter-compact: "6%"
  button-y: "1rem"
  button-x: "1.6rem"
  section-y: "clamp(4.5rem, 7vw, 7rem)"
components:
  button-yellow:
    backgroundColor: "{colors.emphasis}"
    textColor: "{colors.ink}"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
    padding: "1rem 1.6rem"
  button-yellow-active:
    backgroundColor: "{colors.paper}"
  navigation:
    backgroundColor: "{colors.cyan}"
    textColor: "{colors.ink}"
    padding: "1rem 7%"
  nav-booking:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    rounded: "{rounded.nav}"
    padding: ".7rem 1rem"
  report-document:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.square}"
    padding: "2rem 1.5rem 1.5rem"
  recommendation-callout:
    backgroundColor: "{colors.emphasis}"
    textColor: "{colors.ink}"
    rounded: "{rounded.square}"
    padding: "1rem"
---

# Design System: Birdcar

## Overview

**Creative North Star: "The clear argument"**

Phase 1 establishes the new marketing visual foundation through the homepage opening and the Walkthrough figure only. The built world is direct and explanatory: cyan editorial fields, blue-green ink, white explanatory space, yellow recommendation emphasis, upright Barlow type, and a single retained Alkaline wordmark. The composition makes the argument readable before it becomes decorative.

The completed Phase 1 surface covers the header, first viewport, Walkthrough explanation, geometric SVG/HTML report figure, and their responsive/static behavior. The lower homepage still carries retained narrative and older section structure; Writing, Work, Walkthrough detail, and other marketing pages have inherited some shared color/type CSS but are not visually finished. They await Phases 2–3. Print support is an early static sketch, not a finished print specimen.

Admin and customer project surfaces remain separate Flux UI Pro surfaces using Inter. Marketing uses `--font-marketing` Barlow for text and hierarchy, with Alkaline only for the header wordmark. No shipping raster was created for Phase 1; retired horizon assets remain legacy material for Phase 4 decisions, not current system tokens.

The owner approved the Phase 1 opening, Barlow typography, Walkthrough figure, mobile behavior, and static/print sketch on 2026-09-19. Phase 2's visual prerequisite is satisfied; its execution and the remaining surfaces are not approved by this decision.

**Key Characteristics:**

- Cyan opening field with ink typography and a yellow booking action.
- Upright Barlow hierarchy; Alkaline appears once as the native-tracked wordmark.
- Figure-led explanation: preparatory stages fold into a geometric report, then branch to three optional choices.
- Flat surfaces, hard SVG strokes, square report geometry, and sparse rounded controls.
- Static meaning first; optional Motion only emphasizes already-visible diagram relationships.

## Colors

The Phase 1 palette is functional and editorial: cyan sets the opening field, ink carries the argument, white holds explanation and the folded report, yellow marks the recommended starting point, and deep teal supports shared states outside the completed opening.

### Primary

- **Cyan field:** opening/header background and stage-number fills. It is the launch field for the clear argument.
- **Yellow emphasis:** primary homepage action and the report's “Where I’d start” recommendation. Use it sparingly for the one thing to act on.

### Secondary

- **Deep teal:** shared selection, hover/active support, and inherited lower-page accents. It is present in code but not the focal Phase 1 opening accent.

### Neutral

- **Blue-green ink:** text, strokes, outlines, rules, and diagram connectors.
- **White paper:** explanation space and the report document surface.
- **Cyan wash:** light article/figure tint inherited by shared CSS; not a new finished page surface by itself.

**The Argument Contrast Rule.** Text and diagram strokes stay ink on cyan or white. Yellow is emphasis, not a background system for whole sections.

## Typography

**Display Font:** Barlow via `--font-marketing`, with UI sans-serif/system fallbacks.
**Wordmark Font:** licensed Alkaline, with cursive fallback.
**Operational Font:** Inter remains for Admin/customer surfaces only.

Barlow supplies the new marketing voice: upright, plainspoken, and strong enough for large sentence-case questions. Alkaline survives as a brand mark only; it is not a display-heading system in Phase 1.

### Hierarchy

- **Wordmark:** Alkaline header mark, once per page, native tracking, desktop `3.4rem`, mobile `2.7rem`.
- **Display:** Barlow semibold homepage problem statement, large sentence-case, tight line-height, slight negative tracking.
- **Headline:** Barlow semibold section and diagram headings, balanced but not ornamental.
- **Lead:** larger explanatory first-person paragraph under the opening question.
- **Body:** Barlow regular for supporting explanation, diagram copy, terms, and captions.
- **Button:** Barlow semibold, sentence case, paired with the shared arrow.

**The One Script Rule.** Use Alkaline only for the Birdcar wordmark. Do not revive script headings, signatures, or footer logos from the retired direction.

**The Upright Argument Rule.** Marketing headings in this phase are Barlow, sentence-case, and direct. Do not bring back Karla or purple/script display hierarchy for new marketing work.

## Layout

Phase 1 uses a full-width cyan opening with a `7%` page gutter, reduced to `6%` below `1100px`. The desktop hero separates the explanatory copy and booking action into a two-column grid after the large question; mobile stacks them in reading order and makes the action full width.

The Walkthrough section begins immediately after the opening on white. Desktop uses a three-zone diagram: preparatory stages on the left, folded report in the center, choices on the right. The reviewer-requested focal relationship is explicit: stages 1–2 prepare the conversation, stage 3 produces the report, and the report branches to three optional choices. Below `1100px`, the figure becomes a vertical sequence with the same labels, arrows, and report meaning intact.

**The Static Meaning Rule.** The page must read completely as HTML/SVG without animation, JavaScript, screenshots, or raster art. Motion can point at relationships; it cannot supply missing meaning.

## Elevation & Depth

The completed foundation is flat. Hierarchy comes from fields, typography, hard rules, SVG connectors, and the folded-paper outline, not shadows. The only shadow-like treatment is the keyboard focus ring on buttons and the skip link.

**The Flat Report Rule.** The report figure is a geometric explanation, not a mock client artifact or screenshot. Keep it level, stroked, and readable.

## Shapes

Controls use small radii: the header booking link uses a tighter corner, shared buttons use the existing control radius, and stage numbers are true circles. The report document is square, with an SVG folded corner. Diagram lines use thin ink strokes, square/miter arrow geometry, and non-scaling strokes.

The Phase 1 figure's signature shape is relational: vertical preparatory stages, a central folded report, and right-side branching choices. Preserve that relationship when adapting the diagram.

## Components

### Header and navigation

The header is cyan with ink text. It contains the single Alkaline wordmark, desktop Barlow links, and an outlined Walkthrough booking control. Mobile uses native `details`/`summary`; JavaScript enhancement may close it, but the native control remains usable.

### Yellow booking button

The hero action is yellow on ink text, with a shared right arrow, minimum height, and small radius. Hover/focus/active states move toward white paper; focus keeps the two-part visible ring.

### Walkthrough diagram

The diagram is semantic HTML with decorative SVG connectors. Four numbered stages explain the process: Bring the work, Talk it through, Keep the report, Choose what happens next. The report contains three sections, with “Where I’d start” highlighted in yellow. The choices branch from the report and remain optional: use the recommendations independently, discuss implementation, or do nothing.

Motion from `resources/js/diagrams.js` scales/highlights each stage number in sequence, then nudges the recommendation. It runs only when the figure intersects, fonts are ready, reduced motion is not requested, print is not active, and the page remains visible.

### Retained lower homepage and shared page pieces

The lower homepage, Writing, Work, and Walkthrough detail pages are not Phase 1 finished specimens. Shared tokens already affect them, but their layouts and narrative surfaces are awaiting Phases 2–3. Do not document inherited reach as full visual approval.

## Do's and Don'ts

### Do:

- **Do** use cyan, ink, white, and yellow to make the first argument clear.
- **Do** keep the report/branch relationships visible in static HTML and SVG.
- **Do** keep Alkaline to the one header wordmark and Barlow for marketing text.
- **Do** treat the owner's approval as Phase 1 foundation/opening/figure approval only; request separate execution for Phase 2.
- **Do** preserve reduced-motion, print-static, no-JavaScript, and semantic-reading fallbacks.

### Don't:

- **Don't** describe all marketing pages as visually finished because shared CSS reaches them.
- **Don't** use the retired purple horizon, Karla/script-heading identity, or any horizon raster as current marketing system.
- **Don't** turn the diagram into proof, a client screenshot, or a fabricated report artifact.
- **Don't** make yellow a general field color; reserve it for action/recommendation emphasis.
- **Don't** start Phase 2 until the owner visual gate approves the real desktop/mobile opening and static/print sketch.
