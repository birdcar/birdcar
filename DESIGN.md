---
name: Birdcar — Future, in person
description: Personal technical expertise expressed through clear typography, quiet color, and a luminous horizon.
colors:
  ink: "#291e2e"
  paper: "#f3eaf3"
  lilac: "#d9c9f8"
  rose: "#a45983"
  aubergine: "#563750"
  reading-tint: "#d9c9f830"
typography:
  display:
    fontFamily: "Alkaline, cursive"
    fontSize: "clamp(4rem, 7.5vw, 7.2rem)"
    fontWeight: 500
    lineHeight: 1.05
  headline:
    fontFamily: "Alkaline, cursive"
    fontSize: "clamp(2.8rem, 4.7vw, 4.5rem)"
    fontWeight: 500
    lineHeight: 1.05
  article-title:
    fontFamily: "Alkaline, cursive"
    fontSize: "clamp(3.25rem, 6.5vw, 6rem)"
    fontWeight: 500
    lineHeight: 1.05
  title:
    fontFamily: "Karla, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.5rem, 2.5vw, 2rem)"
    fontWeight: 600
    lineHeight: 1.2
  body:
    fontFamily: "Karla, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 400
    lineHeight: 1.55
  reading:
    fontFamily: "Karla, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.1875rem"
    fontWeight: 400
    lineHeight: 1.8
  label:
    fontFamily: "Commit Mono, ui-monospace, monospace"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.55
  button:
    fontFamily: "Karla, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.075rem"
    fontWeight: 600
    lineHeight: 1.55
  wordmark:
    fontFamily: "Alkaline, cursive"
    fontSize: "3.4rem"
    fontWeight: 500
    lineHeight: 1.15
rounded:
  square: "0"
  control: "5px"
spacing:
  compact: "1rem"
  comfortable: "1.5rem"
  panel: "2rem"
  section-gap: "3rem"
components:
  button-lilac:
    backgroundColor: "{colors.lilac}"
    textColor: "{colors.ink}"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
    padding: "1rem 1.6rem"
  button-lilac-hover:
    backgroundColor: "{colors.paper}"
  button-paper:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
    padding: "1rem 1.6rem"
  button-paper-hover:
    backgroundColor: "{colors.lilac}"
  button-ink:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.paper}"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
    padding: "1rem 1.6rem"
  button-ink-hover:
    backgroundColor: "{colors.aubergine}"
  navigation:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.paper}"
    padding: "1rem 6.64% 1rem 6.25%"
  archive-row:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    typography: "{typography.title}"
    padding: "2.2rem 0"
  article-note:
    backgroundColor: "{colors.reading-tint}"
    textColor: "{colors.ink}"
    typography: "{typography.reading}"
    rounded: "{rounded.square}"
    padding: "{spacing.comfortable}"
  report-panel:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "2.5rem"
---

# Design System: Birdcar

## Overview

**Creative North Star: "Future, in person"**

Birdcar presents one person's technical expertise through expressive lettering and direct, readable explanation. Black cherry openings, pale orchid reading space, and a restrained luminous horizon connect the owner's Silicon Valley technology identity to a personal conversation. Typography supplies most of the visual character.

The public marketing pages use custom UI with licensed Alkaline, Karla, and Commit Mono. Broad flat sections, measured controls, fine rules, and generous prose spacing keep the experience clean and classic. Motion adds a brief opening gesture while the text and actions remain available without it.

Admin and customer project surfaces use Flux UI Pro with Inter. Their operational themes and component states are separate from these marketing tokens and were not established by this build.

**Key Characteristics:**

- Licensed Alkaline for the single header wordmark and expressive headings.
- Karla for explanation and reading; Commit Mono for selected details and code.
- Black cherry, pale orchid, and lilac fields with aubergine supporting sections.
- Flat surfaces, lightly rounded controls, and continuous reading columns.
- A luminous homepage horizon and a short, reduced-motion-aware opening sequence.

This record was extracted from `resources/css/{app,fonts,marketing}.css`, `resources/js/app.js`, the marketing components, and the built pages under `resources/views/pages`, checked against the supplied finish captures. The selected homepage composition and page-specific strategies live in `.impeccable/surfaces/`; PRODUCT.md owns product truth.

## Colors

The palette pairs dark purple warmth with quiet pale surfaces. The frontmatter owns exact values; the five core colors are CSS custom properties in `app.css`, while the reading wash comes from `marketing.css`.

### Primary

- **Lilac:** the opening assessment action and broad explanatory sections. It carries ink text and changes to paper on primary-action hover.

### Secondary

- **Aubergine:** closing invitations, the conceptual reporting diagram, prose links, chart marks, and selection backgrounds.
- **Rose:** the scrollbar accent and a companion to the horizon's light. It is not a general body-text or button color.

### Neutral

- **Black cherry / ink:** dark openings, navigation, the approach section, primary text on light surfaces, and rules.
- **Pale orchid / paper:** the reading canvas, light text, report panel, and closing actions.
- **Reading tint:** translucent lilac for source notes and quotations, retaining the reading page underneath.

**The Field Pairing Rule.** Use paper text on ink or aubergine, and ink text on paper or lilac. Keep these pairings when adapting actions to another section.

Sidecar tonal ramps are generated palette previews, not additional implemented CSS tokens.

## Typography

**Display Font:** licensed Alkaline, with cursive fallback.
**Body Font:** Karla, with UI sans-serif and system fallbacks.
**Detail Font:** Commit Mono, with UI monospace fallback.

Alkaline's connected lettering establishes the personal presence at medium weight. Karla carries navigation, actions, explanatory subheadings, and sustained prose. Commit Mono appears selectively in homepage assessment terms, archive metadata, code, and chart axes. Alkaline Caps is bundled but has no visible role in the current pages.

### Hierarchy

- **Display and headline:** fluid Alkaline titles and section headings. The homepage has its own larger three-line composition, documented in its brief.
- **Article title:** Alkaline with a short measure (19ch maximum) and right-side room for letter overhang. Preserve complete original titles and natural wrapping.
- **Title:** Karla semibold for linked archive titles. Essay subheadings remain Karla; second-level headings use (1.85rem, 600, 1.2 leading), third-level headings use (1.35rem, 600, 1.3 leading).
- **Body and reading:** the reading role has more leading and a maximum measure (70ch). General mobile body text becomes (1.0625rem); reading becomes (1.125rem, 1.75 leading).
- **Label:** measured archive metadata in Commit Mono. Other metadata, navigation, and bylines remain Karla; mono is not a universal label treatment.
- **Button and wordmark:** sentence-case controls with ordinary tracking. The header wordmark becomes (2.7rem) on mobile.

**The Single Wordmark Rule.** Render the Birdcar wordmark once, in the header. Footer copy, article bylines, and the personal section use ordinary text; do not add logo signatures to them.

**The Reading Voice Rule.** Introduce an essay with Alkaline and carry its reasoning with Karla. Keep source note titles and original editorial content within the reading hierarchy.

## Layout

Public pages use full-width color fields and generous two-column compositions. Most sections have horizontal padding (7%), narrowing to (6%) on smaller screens; general vertical section padding is fluid (`clamp(4.5rem, 7vw, 7rem)`). Headings and explanatory text face each other across a deliberate gap rather than sitting inside repeated cards.

The homepage's desktop opening and work preview use viewport-scaled dimensions to match the selected composition. At (1100px) they switch to more bounded type and spacing; (1050px) adjusts supporting layouts and navigation gaps. At (760px), major sections stack in document order, the native mobile menu replaces desktop navigation, and the opening action expands to full width. These are the implemented max-width breakpoints, not Tailwind's default grid.

The essay container is centered (1020px maximum) with a narrower prose measure inside it. Archive years occupy a side column on desktop and move above their entries on mobile. Reporting diagrams stack vertically, keeping their labels and relationships readable. Code blocks scroll horizontally when necessary. Print removes navigation and return links and gives the article the full page width.

**The Reading Order Rule.** Reflow headings, explanation, actions, and supporting material in their document order on mobile. Preserve full words, readable figures, and space around controls.

## Elevation & Depth

Resting surfaces are flat. Color changes, spacing, and fine translucent rules separate sections and content; the report panel is level with no shadow or rotation. The homepage horizon supplies the one atmospheric depth effect, using `public/images/future-horizon.png` as decorative light beneath the opening. It carries no factual information.

**The Flat Field Rule.** Establish hierarchy with type, space, color, and boundaries. Reserve the visible outer shadow for keyboard focus, where it communicates state.

The button and skip-link focus treatment combines a paper outline (2px, offset 2px) with an ink outer ring (`0 0 0 6px`). Other interactive elements use a current-color outline (3px, offset 6px). Preserve both focus colors so actions remain visible across dark and pale fields.

## Shapes

Sections, archive rows, notes, quotations, and diagram blocks have square edges. Buttons, the outlined navigation action, and the report panel share a small corner radius. Arrow icons have square line caps and miter joins. Fine rules structure the archive, process rows, report contents, FAQ, and footer.

The horizon is a horizontal light band with a soft top mask. Its animated clip opens from the center; the final shape spans the page. No diagonal section cuts or rotated panels appear in the built world.

## Components

### Buttons and text links

Buttons are quiet, bounded invitations with a shared right arrow. Lilac actions sit on dark openings; ink actions sit on lilac; paper actions sit on aubergine. Their frontmatter variants record the actual color states. Shared buttons have a minimum height (62px), becoming (58px) on mobile; the homepage applies its own desktop scale.

Background and text colors transition (160ms, ease). Button arrows move right (4px) with a (200ms) easing transition; underlined text-link arrows share the displacement. All buttons retain the two-color keyboard focus treatment. Reduced motion disables CSS transitions and smooth scrolling.

### Navigation

The black cherry header contains the only wordmark, a horizontal Karla navigation, and a thin outlined booking link. Hover and current-page states underline desktop links. The mobile menu uses native `details` and `summary`, opening a pale panel beneath the header. Its links provide (48px) minimum targets; JavaScript closes it after navigation, outside click, or Escape, returning focus to the summary on Escape. Native disclosure remains usable without JavaScript.

The footer is pale, with a short two-line statement, navigation, and an ordinary copyright line. It carries no second wordmark.

### Archive entries and reading components

Archive entries are whole-row links separated by fine rules, with Karla titles and descriptions, Commit Mono dates and reading time, and a right arrow. Hover underlines the title and changes it to aubergine. The reading page stays continuous rather than adopting a marketing section rhythm.

Source notes and quotations use the translucent reading wash. Code uses Commit Mono; code blocks reverse to paper on ink. Charts use aubergine marks, readable labels, a source caption, and native disclosure for a complete data table. Preserve source text and figure meaning when changing presentation.

### Report panel and questions

The report panel uses a pale surface, a small radius, Karla contents, fine dividing rules, and the Alkaline phrase “Yours to keep.” It is an explanation of the assessment report, not a fabricated client artifact, and includes no logo. Padding reduces at the intermediate breakpoint and adapts again on mobile.

FAQ rows use native disclosures with thin top and bottom rules. Their arrow points down while closed and up while open. Answers remain ordinary prose. Booking links go to the external calendar; the marketing site has no custom input form or field system to document.

### Opening motion

After fonts are ready, the homepage's three heading lines move upward (16px to 0) and settle from partial opacity (0.85 to 1), with a duration (0.85s) and stagger (0.09s). The horizon reveals from a narrow central clip over (1.2s), rising from opacity (0.4 to 1). Both use the same ease (`cubic-bezier(0.16, 1, 0.3, 1)`).

These are original animations using the public Motion API. They run only when reduced motion is not requested; a later reduced-motion preference completes active animations. The server-rendered page starts with visible content, and the unanimated end state remains complete.

## Do's and Don'ts

### Do:

- **Do** use the licensed Alkaline files for the single header wordmark and expressive marketing headings.
- **Do** use Karla for readable explanation and Commit Mono selectively for measured details and code.
- **Do** preserve the Field Pairing Rule and the two-color button focus treatment.
- **Do** keep essays continuous, preserving source notes, complete text, links, dates, and figure meaning.
- **Do** keep content and native controls usable without animation or JavaScript.
- **Do** use Flux UI Pro and Inter for the separate Admin and customer surfaces.

### Don't:

- **Don't** repeat the logo in the footer, article ending, personal section, or report panel.
- **Don't** turn the homepage's display scale or decorative horizon into a requirement for every page.
- **Don't** treat conceptual imagery or diagrams as client screenshots or measured proof.
- **Don't** carry unused legacy selectors, bundled-but-unused type roles, or generated sidecar ramps into new UI as established patterns.
