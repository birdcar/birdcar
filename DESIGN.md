---
name: Birdcar — The clear argument
description: "Integrated locally built and reviewed marketing system: cyan editorial fields, blue-green ink, yellow emphasis, white explanation space, Barlow hierarchy, Commit Mono code, static figures, Cal booking shells, print review, and one Alkaline wordmark."
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
  admin-operational:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
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

Phase 4 records the integrated locally built marketing system after full rendered review. The homepage, Walkthrough, Work, Writing index, individual articles, authored figures, booking shells, no-JavaScript states, reduced-motion states, and local print specimen now share one direct explanatory world: cyan editorial fields introduce invitations and archives, blue-green ink carries prose and linework, white creates sustained reading space, yellow marks useful recommendations and booking actions, Barlow keeps the argument upright, and Commit Mono appears only for code.

The integrated system is built and independently reviewed at bounded local scope; it is not deployed. This record does not expand the existing owner approval beyond the Phase 1 opening, Barlow typography, Walkthrough figure, mobile behavior, and static/print sketch approved on 2026-09-19. Phase 4 finish review returned ship with no material fixes, but owner launch acceptance remains pending and no production booking or remote PostHog write is claimed.

Phase 4 evidence includes 390/768/1440 captures for home, Walkthrough, Work, Writing, representative articles, and specimen under `.impeccable/review/phase-4-*`, plus saved Chromium PDF rasters under `.impeccable/review/print/phase-4-*`. Full-page calendar blanks are offscreen capture artifacts; separate calendar and modal captures loaded. The 720×450 captures are 200% equivalent reflow evidence only; native browser-toolbar 200% zoom remains unverified. Print artifacts are local review evidence, not a PDF product or deployed handout system.

**Key Characteristics:**

- Cyan offer/invitation fields, white implementation approach and story sections, and an ink personal note section.
- Upright Barlow hierarchy with many purposeful intermediate steps; Alkaline appears once as the native-tracked wordmark.
- Authored static figures: the Walkthrough report diagram, the shared reporting component, the pattern hub on `/tools/where-work-gets-stuck`, and the article diagram wrapper for only the first two approved subjects, all reviewed in integrated page, no-JavaScript, reduced-motion, repeated-instance, and print contexts.
- 65ch article prose, article headings capped at `4.5rem`, visible chart data tables in print, scrollable small-screen plots with explicit hints, and 16mm paper margins without forced page size.
- Flat surfaces, thin ink rules, square report/platform geometry, sparse rounded controls, and two-column numbered Walkthrough stages in print.
- Shared navigation destinations: How I work, Selected work, Writing, and the Walkthrough. Header/mobile use “Book a free Walkthrough”; the footer uses “The Walkthrough” and adds “Where work gets stuck.” Native mobile details/summary remains; Cal booking preserves real href fallbacks; print hides navigation and booking chrome.

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
- **Cyan wash:** article notes, blockquotes, and article diagram fields in the completed Reading extension. Chart plots remain on white with ink rules.
- **Soft ink rules:** translucent ink rules separate footer, article rows, FAQ rows, and archive lines.

**The Argument Contrast Rule.** Text and diagram strokes stay ink on cyan or white. Yellow is emphasis, not a background system for whole sections.

**The Proof Boundary Color Rule.** Use yellow for a recommendation or booking action only when the copy already states the boundary. Color may emphasize “where I’d start”; it must not imply free implementation, measured results, or proof that was not supplied.

## Typography

**Display Font:** Barlow via `--font-marketing`, with UI sans-serif/system fallbacks.
**Wordmark Font:** licensed Alkaline, with cursive fallback.
**Operational Font:** Inter remains for Admin/customer surfaces only.
**Mono Font:** Commit Mono OFL is used for inline code and code blocks in articles, not marketing labels.

Barlow supplies the marketing and Reading voice: plainspoken, technical enough to be precise, and flexible across very large page titles, compact diagrams, archive rows, long-form prose, captions, and print. The integrated system intentionally keeps the additional CSS type steps; the detector's type-step advisories are documentation advisories, not defects to flatten.

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
- **Article prose:** 65ch sustained reading column with `1.1875rem` screen text, looser line-height, `1.125rem` mobile step, and 11pt print step.
- **Article heading:** individual article titles cap at `4.5rem`, wrap at 24ch, and reduce on small screens so long original titles remain readable.

**The One Script Rule.** Use Alkaline only for the Birdcar wordmark. Do not revive script headings, signatures, footer logos, or decorative script emphasis.

**The Upright Argument Rule.** Marketing headings are Barlow, sentence-case, and direct. Do not bring back Karla or purple/script display hierarchy for new marketing work.

**The Documented Step Rule.** Literal font-size steps in `resources/css/marketing.css` are part of the authored responsive hierarchy when they serve nav, diagrams, cards, captions, article prose, print, or chart labels. Do not flatten them to appease a detector; document the step and keep the role clear.

## Layout

The base page uses a `7%` gutter, reduced to `6%` below `1100px`, with section padding around `clamp(4.5rem, 7vw, 7rem)` and mobile sections around `3.5rem`. Desktop layouts favor two-column editorial grids: a large claim or heading on the left, explanatory copy or an authored figure on the right. Mobile follows document order and keeps labels readable instead of shrinking diagrams into thumbnails.

The homepage sequence is complete: approved opening, Walkthrough figure, cyan/white offer strip, shared reporting component, white implementation approach, ink personal note, and cyan closing invitation. The Walkthrough page uses a cyan offer opening with a square folded report, white outcome explanation, cyan three-step process, white FAQ, and cyan calendar close. The Work page uses a cyan client title, white story/proof section, shared reporting figure, and cyan closing invitation. The first `/tools/*` page, Where work gets stuck, uses a cyan opening with the claim and yellow booking action in five columns beside the pattern hub in six, white two-column pattern spreads, one cyan interlude with a yellow booking action after the fourth pattern, and the cyan closing invitation. Above `1100px` each spread's number, name, and definition stay sticky beside the detail column; at `1100px` and below the opening stacks with the hub centred at up to `640px`.

The current header and footer share the same navigation destinations. Desktop shows inline navigation and an outlined Walkthrough booking control. Mobile uses native `details`/`summary`; JavaScript may close it, but the native fallback remains. Footer navigation keeps the same public destinations and active underline state.

Writing is a reading surface, not a conversion page. The index uses a compact cyan title beside the original introduction, then a chronological white ledger with years in the left column and whole-row essay links. Article pages use a quiet return link, original title/description/date/byline, and a centered 65ch prose column. On small screens, plots remain horizontally scrollable with a visible hint; on print, source tables are visible and chart disclosures do not hide essential data.

Print uses browser output with `@page` margins of 16mm and no forced paper size, so Letter and A4 are both valid review formats. Site navigation, booking chrome, analytics/Cal behaviors, and local development scaffolding do not enter article handouts. The local `/__design/figures` specimen is CSS-only, three pages in both Letter and A4 review captures, and is not a public PDF or reusable handout product.

**The Static Meaning Rule.** Every essential relationship must read as HTML/SVG without animation, JavaScript, screenshots, or raster art. Article diagrams are static by default; homepage marketing may opt into motion explicitly, but motion cannot supply missing meaning.

## Elevation & Depth

The system is flat. Depth comes from field changes, white paper/platform surfaces on cyan, thin ink outlines, folded-corner geometry, separators, and focus rings. Shadows are not used for marketing cards at rest. The Cal embed brings its own native light UI inside the white calendar shell; Birdcar only supplies the surrounding cyan/paper/ink palette and fallback link. The legacy `booking_embed_opened` event name is readiness from Cal `linkReady`, not a visitor-open metric.

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

The footer is white with a short two-line statement, the current public navigation plus the Where work gets stuck tool, active underline state, and copyright. There is no footer wordmark; this preserves the one-script rule.

### Buttons and links

Yellow buttons are the primary booking action, including on every cyan field. Ink buttons appear inside white offer strips. Text links use underline plus the shared line arrow. Hover/focus/active states move yellow toward white and ink buttons toward deep teal; focus keeps the two-part visible ring.

### Walkthrough report figure

The Walkthrough figure is semantic HTML with decorative SVG connectors. Three numbered stages sit on one line: the conversation about real work leads to a folded report, and the report branches to optional choices. “Where I’d start” is yellow. The figure has no fixed caption; articles supply their own. The same meaning remains in vertical, print, no-JavaScript, and reduced-motion states.

### Pattern hub figure

The hub on `/tools/where-work-gets-stuck` is an in-page `nav` of eight real anchor links over a decorative, non-scaling ink SVG. A square white “Your desk” box sits at the centre; four numbered routes arrive from each side as single ink curves ending in open chevrons on the box edge. True-circle numbers sit on cyan, labels are Barlow `.95rem` (`.875rem` from `1101px` to `1320px`) with balanced wrapping. Hover or focus on a route link fills its number and strokes its route in deep teal at `2.5` via `:has()`; the static figure carries the full meaning. At `640px` and below the SVG is hidden: the desk becomes a full-width box, and the numbered links hang from a vertical ink spine with an arrowhead into it. Print lists the links in two columns under the desk.

### Pattern entry spreads

Each pattern is an `article` at a permanent slug anchor (reports deep-link to them): an ink-ruled two-column spread with a white true-circle number, a section-display name, and a lead-size definition on the left; the scene, “You’ll notice” list with short ink dash markers and soft ink row rules, “What usually helps,” and text links on the right. An entry that is the URL target or holds focus fills its number in deep teal. The names are the shared pattern vocabulary used verbatim in Walkthrough reports; renaming one is a URL change.

### Shared reporting component

The authored static reporting component appears on the homepage and Work page. It contrasts “Finding the numbers by hand” with a white outlined “client-facing reporting platform,” then connects performance data and client management to “Part of the agency’s service.” Its caption explicitly says it is an illustration, not a screenshot or measured result.

### Walkthrough page report card

The offer opening uses a square folded report card with observed problems, recommendations, and the yellow first recommendation. It explains the free deliverable without making implementation look included.

### FAQ disclosures

FAQ rows are native `details` elements with ink rules, a small rotating arrow, deep-teal open/focus state, and no dependency on JavaScript.

### Calendar and booking modal

The inline calendar sits in a white rounded shell on cyan with a plain Cal.com fallback link. The Cal UI uses Birdcar palette inputs: ink brand, deep-teal brand emphasis, paper background, and cyan/dark fallbacks. Modal booking uses the same Cal theme over the page with a native close affordance and the real anchor fallback when the embed cannot take over.

### Writing index

The Writing index is a chronological ledger. A cyan title band introduces the section without turning the archive into a sales page. The archive itself is white, rule-led, grouped by year, and uses whole-row links with deep-teal hover/focus emphasis and RSS kept visible.

### Article and Writing surfaces

Articles use the original title, description, date, byline, links, notes, chart data, and source text. The reading column is centered at 65ch with Barlow prose, Commit Mono code, cyan-wash notes/blockquotes, deep-teal links and chart marks, and source-data disclosures. Print hides chrome and reveals chart tables so the evidence is visible without interaction.

### Article charts and notes

Article notes are cyan-wash callouts with preserved titles. Charts keep their source values and labels; line charts sit in a scrollable region on small screens with a visible hint, and every chart carries a data disclosure that is visible in print.

### Article diagrams and local specimen

`article-diagram` only renders the Walkthrough and reporting components. Article diagrams are static by default, flow as labeled stages for reading, and switch to print-specific layouts: the Walkthrough becomes two-column numbered stages and reporting keeps the relationship/caption adjacent. The standalone figure specimen is local/testing only, uses the real renderer and CSS, has no analytics or Cal, and exists only as review evidence.

## Admin operational extension

Admin uses Flux Pro and Inter rather than the marketing typography and page composition. Native Brand components pair the existing `public/favicon.svg` B mark with “Admin”; Alkaline remains exclusive to the marketing wordmark. The shell has one collapsible module sidebar, a contextual header, and module workspaces. Only Home and authorized Publishing are present.

The approved Attention ledger Home answers “What needs me?” before “Where was I?” Blocked work and human decisions occupy the wide column; Continue working is narrower and follows the ledger on mobile. Links lead to authoritative workspaces, not Home-level approval or publishing actions. Empty, unauthorized, unavailable, and loaded states remain distinct.

Native Flux appearance respects saved preferences and defaults to the system theme. Light accent/content uses deep teal `#214b57` with white foreground; dark accent/content uses cyan `#b7edf1` with ink `#102a33` foreground. Current navigation, keyboard focus, and text selection carry restrained accent treatment. Public article previews remain independently styled.

The owner-only Publishing workspace is a local Admin extension, not a marketing identity change. It uses the existing Flux Pro/Inter neutral system with restrained teal/cyan accent, large rounded creation composer, paper-airplane Develop affordance, passive bookmark save, and separate Ideas, Active writing, and Published libraries with owner-scoped pagination. Session work stays organized around a persistent title/save state and three modes: Develop, Write & review, and Release.

Develop shows interview, angle, plan, sources, and living brief as readable editorial material; angle, plan, and release approvals remain explicit text approvals. Write & review gives the manuscript the broad surface with contextual feedback, sources, and brief; on mobile, the context replaces the manuscript visually without destroying the editor. Release separates metadata/date/checklist, frozen scoped release preview, timezone-aware scheduling, approval, and publication. Published shows the live title/date and marks draft-in-progress separately from the live article. Settings, shown only to users who can configure agents, is a focused form in the same shell: an agent-request pause with a separate saved-state badge and explicit Save, a configured/not-configured key notice, and per-task rows with the code recommendation, a native Flux select (Use recommended, curated models, Auto Router), pinned or reset-required state, and reset. It shows no prices, allowances, spending, or keys.

Publishing motion is useful and bounded. `resources/js/admin/publishing/motion.js` uses Motion springs for mode changes, confirmed-capture header arrival, small confirmed milestones, and a disposable published-only flourish; reduced motion suppresses decoration. Save guards block unsafe actions during saving/conflict, stale server updates preserve local edits, and older-revision recovery enters conflict before enqueue.

The bounded local finish review returned ship for shell/Home/theme/branding after 390px and 1440px light/dark fixture checks, collapse persistence, and mobile drawer checks. Publishing workspace evidence adds 20 current captures under `.impeccable/review/publishing-session` across workspace modes and populated library, 1440/390, light/dark, using synthetic in-memory DB fixtures with actual built assets and all POST blocked. Browser checks found no overflow/errors, editor booted, keyboard tab moved, and reduced motion suppressed celebrations. This is local fixture UI and automated guard evidence only: no browser-authenticated save/release, paid agents, production publication, owner editorial acceptance, deployment, or privileged account change is claimed. No shipping raster assets were added. Backend Home findings and editor/authentication follow-ups remain recorded in `docs/production-setup.md`.

## Do's and Don'ts

### Do:

- **Do** use cyan for offers/invitations, white for implementation explanation and proof reading, ink for personal-note contrast, and yellow for actions or first recommendations.
- **Do** keep the report/branch and reporting-platform relationships visible in static HTML/SVG.
- **Do** keep Alkaline to the one header wordmark and Barlow for marketing text.
- **Do** preserve current header/footer navigation, native mobile menu, native FAQ disclosures, Cal.com fallback links, reduced-motion behavior, no-JavaScript readability, and the distinction between Cal readiness and actual booking/open behavior.
- **Do** keep article prose near 65ch, article headings capped at `4.5rem`, source tables visible in print, small-screen chart scroll hints visible, and paper margins at 16mm without forced page size.
- **Do** describe the integrated Phase 4 system as locally built and independently reviewed at bounded scope; keep the existing owner approval scoped to Phase 1 and owner launch acceptance pending.
- **Do** keep Admin Publishing within Flux Pro/Inter neutral surfaces with teal/cyan accents, useful bounded motion, explicit approvals, separate draft/live states, and save/conflict guards.

### Don't:

- **Don't** claim owner launch approval, deployment, production booking, remote PostHog writes, authenticated Publishing save/release, actual paid agent runs, production publication, or native browser-toolbar 200% zoom verification.
- **Don't** describe local print artifacts as a PDF product, public handout system, deployed asset, or shipping raster.
- **Don't** use the retired purple horizon, Karla/script-heading identity, or any horizon raster as current marketing system.
- **Don't** turn the shared reporting figure into a product screenshot, metric claim, testimonial, or automated-dashboard promise.
- **Don't** embed Alkaline in print artifacts; print hides the header and the specimen has no wordmark. Barlow and Commit Mono are OFL and safe for the documented Reading/print output.
- **Don't** change CSS merely to silence detector advisories; the Phase 4 detector advisories are accepted documentation advisories, not UI changes.
- **Don't** describe the Publishing workspace motion as more spectacular than implemented; it is spring continuity, arrival/milestone feedback, and a published-only disposable flourish.
