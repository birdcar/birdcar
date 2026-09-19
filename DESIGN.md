---
name: Birdcar — The clear argument
description: "Reviewed Phase 2 marketing system: cyan editorial fields, blue-green ink, yellow emphasis, white explanation space, ink personal notes, Barlow hierarchy, and one Alkaline wordmark."
colors:
  ink: "#102a33"
  paper: "#ffffff"
  cyan: "#b7edf1"
  emphasis: "#f7cb58"
  deep-teal: "#214b57"
  cyan-wash: "#b7edf130"
  ink-rule-soft: "#102a3330"
  ink-rule-mid: "#102a3360"
  ink-code-wash: "#102a3309"
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
  page-display:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(4rem, 7.5vw, 7.2rem)"
    fontWeight: 600
    lineHeight: 1.08
  section-display:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2.8rem, 4.7vw, 4.5rem)"
    fontWeight: 600
    lineHeight: 1.08
  headline:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2.3rem, 3.6vw, 3.5rem)"
    fontWeight: 600
    lineHeight: 1.08
  title:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.75rem, 2.5vw, 2.25rem)"
    fontWeight: 600
    lineHeight: 1.15
  subhead:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.2
  lead:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.35rem, 1.7vw, 1.6rem)"
    fontWeight: 400
    lineHeight: 1.4
  body:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 400
    lineHeight: 1.55
  body-article:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.1875rem"
    fontWeight: 400
    lineHeight: 1.8
  body-compact:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  caption:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: ".95rem"
    fontWeight: 400
    lineHeight: 1.45
  fine:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: ".875rem"
    fontWeight: 400
    lineHeight: 1.45
  button:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.075rem"
    fontWeight: 600
    lineHeight: 1.55
  chart-label:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "13px"
    fontWeight: 400
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
  section-y-mobile: "3.5rem"
  rule-gap: "2.5rem"
components:
  button-yellow:
    backgroundColor: "{colors.emphasis}"
    textColor: "{colors.ink}"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
    padding: "1rem 1.6rem"
  button-ink:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.paper}"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
    padding: "1rem 1.6rem"
  button-hover-paper:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
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
  calendar-shell:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: ".5rem"
---

# Design System: Birdcar

## Overview

**Creative North Star: "The clear argument"**

Phase 2 extends the approved Phase 1 foundation across the reviewed homepage, Walkthrough page, and Work page. The system is still direct and explanatory: cyan editorial fields introduce invitations and offers, blue-green ink carries copy and linework, white creates implementation and reading space, yellow marks the useful action or first recommendation, and Barlow makes the reasoning feel upright rather than decorative.

The reviewed Phase 2 surfaces are complete at bounded-review scope: homepage, Walkthrough, and Work. That is not an owner approval of new pages, and it does not expand the existing Phase 1 owner approval beyond the opening, Barlow typography, Walkthrough figure, mobile behavior, and static/print sketch approved on 2026-09-19. The finish reviewer disposition was ship with no fixes, and the independent proof-boundary review reported no findings.

Writing/articles and a finished print specimen await Phase 3. The current article CSS is an inherited, usable reading treatment in the same token family, not a completed Phase 2 article design. No shipping raster was created; the reviewed screenshots are evidence, not product assets.

**Key Characteristics:**

- Cyan offer/invitation fields, white implementation approach and story sections, and an ink personal note section.
- Upright Barlow hierarchy with many purposeful intermediate steps; Alkaline appears once as the native-tracked wordmark.
- Authored static figures: the Walkthrough report diagram and the shared reporting component used on the homepage and Work page.
- Flat surfaces, thin ink rules, square report/platform geometry, and sparse rounded controls.
- Current navigation in header and footer: How I work, Selected work, Writing, and The Walkthrough, with native mobile details/summary fallback.

## Colors

The palette is small and semantic. Cyan is the public invitation field, ink is both text and drawing material, white is the explanatory workspace, yellow is a constrained action/recommendation marker, and deep teal supports states and calendar theming.

### Primary

- **Cyan field:** header, homepage opening, offer sections, Work opening, steps fields, and closing invitations. It creates the editorial field where the offer is made.
- **Yellow emphasis:** primary Walkthrough buttons and the “Where I’d start” recommendation. It marks the next useful action, not a whole-page mood.

### Secondary

- **Deep teal state:** selection, active/focus support, article/chart emphasis, scrollbar, and Cal brand-emphasis fallback. It is a state/support color, not a second marketing voice.

### Neutral

- **Blue-green ink:** body text, large headings, borders, rules, arrows, SVG connectors, selected calendar day, and the dark personal-note field.
- **White paper:** page background, report/platform cards, mobile menu, calendar shell, and implementation approach sections.
- **Cyan wash:** article notes, blockquotes, chart panels, and inherited reading aids awaiting Phase 3 refinement.
- **Soft ink rules:** translucent ink rules separate footer, article rows, FAQ rows, and archive lines.

**The Argument Contrast Rule.** Text and diagram strokes stay ink on cyan or white. Yellow is emphasis, not a background system for whole sections.

**The Proof Boundary Color Rule.** Use yellow for a recommendation or booking action only when the copy already states the boundary. Color may emphasize “where I’d start”; it must not imply free implementation, measured results, or proof that was not supplied.

## Typography

**Display Font:** Barlow via `--font-marketing`, with UI sans-serif/system fallbacks.
**Wordmark Font:** licensed Alkaline, with cursive fallback.
**Operational Font:** Inter remains for Admin/customer surfaces only.
**Mono Font:** Commit Mono exists for code in articles, not marketing labels.

Barlow supplies the marketing voice: plainspoken, technical enough to be precise, and flexible across very large page titles, compact diagrams, FAQs, captions, and article prose. Phase 2 intentionally uses more steps than Phase 1 documented; the 87 detector advisories are all literal font-size values already present in CSS and now represented as actual typography roles rather than CSS changes.

### Hierarchy

- **Wordmark:** Alkaline header mark, once per page, desktop `3.4rem`, mobile `2.7rem`.
- **Display:** homepage problem statement, large sentence-case, tight line-height, slight negative tracking.
- **Page display:** Work and generic page titles; oversized but still Barlow semibold.
- **Section display:** major section questions and closing invitations, generally `2.8rem–4.8rem` fluid steps.
- **Headline:** two-column editorial section headings and offer/story headings.
- **Title/Subhead:** cards, report/platform headings, diagram stage labels, FAQ/step headings.
- **Lead:** first-person opening explanation and larger page-summary copy.
- **Body:** marketing explanation, report body, diagram copy, FAQ answers, and invitation copy.
- **Compact, caption, and fine:** navigation, figure labels, terms, footer, metadata, chart labels, and small fallback text.
- **Article prose:** inherited Phase 3 candidate treatment with looser line-height for sustained reading.

**The One Script Rule.** Use Alkaline only for the Birdcar wordmark. Do not revive script headings, signatures, footer logos, or decorative script emphasis.

**The Upright Argument Rule.** Marketing headings are Barlow, sentence-case, and direct. Do not bring back Karla or purple/script display hierarchy for new marketing work.

**The Documented Step Rule.** Literal font-size steps in `resources/css/marketing.css` are part of the authored responsive hierarchy when they serve nav, diagrams, cards, captions, article prose, or chart labels. Do not flatten them to appease a detector; document the step and keep the role clear.

## Layout

The base page uses a `7%` gutter, reduced to `6%` below `1100px`, with section padding around `clamp(4.5rem, 7vw, 7rem)` and mobile sections around `3.5rem`. Desktop layouts favor two-column editorial grids: a large claim or heading on the left, explanatory copy or an authored figure on the right. Mobile follows document order and keeps labels readable instead of shrinking diagrams into thumbnails.

The homepage sequence is complete: approved opening, Walkthrough figure, cyan/white offer strip, shared reporting component, white implementation approach, ink personal note, and cyan closing invitation. The Walkthrough page uses a cyan offer opening with a square folded report, white outcome explanation, cyan three-step process, white FAQ, and cyan calendar close. The Work page uses a cyan client title, white story/proof section, shared reporting figure, and cyan closing invitation.

The current header and footer share the same navigation destinations. Desktop shows inline navigation and an outlined Walkthrough booking control. Mobile uses native `details`/`summary`; JavaScript may close it, but the native fallback remains. Footer navigation keeps the same public destinations and active underline state.

**The Static Meaning Rule.** Every essential relationship must read as HTML/SVG without animation, JavaScript, screenshots, or raster art. Motion can point at relationships; it cannot supply missing meaning.

## Elevation & Depth

The system is flat. Depth comes from field changes, white paper/platform surfaces on cyan, thin ink outlines, folded-corner geometry, separators, and focus rings. Shadows are not used for marketing cards at rest. The Cal embed brings its own native light UI inside the white calendar shell; Birdcar only supplies the surrounding cyan/paper/ink palette and fallback link.

### Shadow Vocabulary

- **Keyboard focus outer:** `0 0 0 6px var(--color-ink)` paired with a paper outline. Use for interactive focus visibility only.

**The Flat Report Rule.** Reports and platforms are geometric explanations, not mock screenshots. Keep them level, stroked, static, and labeled.

## Shapes

Controls use small radii: `3px` for the outlined nav booking control and `5px` for primary buttons and the calendar shell. Reports and the shared reporting platform are square ink-outlined surfaces. Stage numbers are true circles. SVG arrows and connectors use thin ink strokes with square/miter geometry and non-scaling strokes.

The recurring signature shape is a square white document/platform on a cyan or white field: folded report for the Walkthrough, outlined platform for Craft & Communicate, and native white calendar shell for booking. These shapes say “authored explanation,” not software chrome.

## Components

### Header and navigation

The header is cyan with ink text. It contains the single Alkaline wordmark, desktop Barlow links, an outlined Walkthrough booking control, and native mobile `details`/`summary`. Current-page links use an underline; hover/focus underlines are animated when motion is allowed.

### Footer

The footer is white with a short two-line statement, the current public navigation, active underline state, and copyright. There is no footer wordmark; this preserves the one-script rule.

### Buttons and links

Yellow buttons are the primary booking action. Ink buttons appear inside white offer strips. Text links use underline plus the shared line arrow. Hover/focus/active states move yellow toward white and ink buttons toward deep teal; focus keeps the two-part visible ring.

### Walkthrough report figure

The Walkthrough figure is semantic HTML with decorative SVG connectors. Preparatory stages lead to a folded report, and the report branches to optional choices. “Where I’d start” is yellow. The same meaning remains in vertical, print, no-JavaScript, and reduced-motion states.

### Shared reporting component

The authored static reporting component appears on the homepage and Work page. It contrasts “Finding the numbers by hand” with a white outlined “client-facing reporting platform,” then connects performance data and client management to “Part of the agency’s service.” Its caption explicitly says it is an illustration, not a screenshot or measured result.

### Walkthrough page report card

The offer opening uses a square folded report card with observed problems, recommendations, and the yellow first recommendation. It explains the free deliverable without making implementation look included.

### FAQ disclosures

FAQ rows are native `details` elements with ink rules, a small rotating arrow, deep-teal open/focus state, and no dependency on JavaScript.

### Calendar and booking modal

The inline calendar sits in a white rounded shell on cyan with a plain Cal.com fallback link. The Cal UI uses Birdcar palette inputs: ink brand, deep-teal brand emphasis, paper background, and cyan/dark fallbacks. Modal booking uses the same Cal theme over the page with a native close affordance and the real anchor fallback when the embed cannot take over.

### Article and Writing surfaces

Writing index and article styles currently share Barlow, ink, white, cyan wash notes/charts, deep teal links/charts, and Commit Mono code. They are not Phase 2 finished specimens; Phase 3 owns article art direction and print completion.

## Do's and Don'ts

### Do:

- **Do** use cyan for offers/invitations, white for implementation explanation and proof reading, ink for personal-note contrast, and yellow for actions or first recommendations.
- **Do** keep the report/branch and reporting-platform relationships visible in static HTML/SVG.
- **Do** keep Alkaline to the one header wordmark and Barlow for marketing text.
- **Do** preserve current header/footer navigation, native mobile menu, native FAQ disclosures, Cal.com fallback links, reduced-motion behavior, and no-JavaScript readability.
- **Do** describe Phase 2 as reviewed and shipped at bounded scope for homepage, Walkthrough, and Work only; keep the existing owner approval scoped to Phase 1.

### Don't:

- **Don't** claim owner approval for Phase 2 pages.
- **Don't** describe Writing/articles or print as finished Phase 2 design work.
- **Don't** use the retired purple horizon, Karla/script-heading identity, or any horizon raster as current marketing system.
- **Don't** turn the shared reporting figure into a product screenshot, metric claim, testimonial, or automated-dashboard promise.
- **Don't** change CSS merely to silence undocumented-font-size advisories; document real type roles first.
