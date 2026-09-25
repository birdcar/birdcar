---
name: Birdcar — Your business, in miniature
description: "Homepage-built marketing world: pale daylight ground, ink, canary yellow for actions and packets, a teal fixed-state accent, Mona Sans grotesk, one Alkaline wordmark, and a canvas-drawn packet-flow diorama. The clear argument (cyan fields, Barlow, flat diagrams) is retired as direction but still literally renders on every page this build hasn't reached yet; Admin keeps its Flux Pro/Inter system unchanged."
colors:
  studio-ground: "#edf3f0"
  studio-ink: "#0b141a"
  studio-ink-soft: "#323945"
  studio-muted: "#5c646d"
  studio-rule: "#d5dbd8"
  studio-yellow: "#f7c848"
  studio-yellow-deep: "#efb925"
  studio-teal: "#5fb8bf"
  studio-red: "#e2483d"
  ink: "#102a33"
  paper: "#ffffff"
  cyan: "#b7edf1"
  emphasis: "#f7cb58"
  deep-teal: "#214b57"
  cyan-wash: "#b7edf130"
typography:
  wordmark:
    fontFamily: "Alkaline, cursive"
    fontSize: "clamp(2.6rem, 4vw, 4rem)"
    fontWeight: 600
    lineHeight: 1.15
    letterSpacing: "normal"
  studio-display:
    fontFamily: "Mona Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(3rem, 5.35vw, 6rem)"
    fontWeight: 700
    lineHeight: 1.075
    letterSpacing: "-.04em"
  studio-headline:
    fontFamily: "Mona Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2.25rem, 4vw, 4rem)"
    fontWeight: 700
    lineHeight: 1.02
    letterSpacing: "-.04em"
  studio-story-heading:
    fontFamily: "Mona Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2rem, 3.1vw, 3.25rem)"
    fontWeight: 700
    lineHeight: 1.08
    letterSpacing: "-.035em"
  studio-lead:
    fontFamily: "Mona Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.125rem, 1.3vw, 1.3125rem)"
    fontWeight: 450
    lineHeight: 1.42
    letterSpacing: "-.035em"
  studio-body:
    fontFamily: "Mona Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.0625rem"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "-.01em"
  studio-label:
    fontFamily: "Mona Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(.75rem, .9vw, .9375rem)"
    fontWeight: 500
    lineHeight: 1.25
    letterSpacing: "-.005em"
  studio-button:
    fontFamily: "Mona Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.0625rem, 1.43vw, 1.4rem)"
    fontWeight: 600
    letterSpacing: "-.01em"
  studio-fine:
    fontFamily: "Mona Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(.9375rem, 1vw, 1.0625rem)"
    fontWeight: 400
    lineHeight: 1.45
    letterSpacing: "-.035em"
  studio-employer-mark:
    fontFamily: "Mona Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.25rem, 2.3vw, 2.25rem)"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "-.035em"
  admin-operational:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  display:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(3rem, 6.65vw, 6rem)"
    fontWeight: 600
    lineHeight: 1.02
    letterSpacing: "-.025em"
  body:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 400
    lineHeight: 1.55
rounded:
  studio-tag: "7px"
  studio-control: "8px"
  studio-button: "10px"
  studio-pill: "999px"
  nav: "3px"
  control: "5px"
  circle: "50%"
  square: "0"
spacing:
  studio-gutter: "4.95vw"
  studio-gutter-mobile: "6%"
  studio-section-y: "7vw"
  studio-section-y-mobile: "4.5rem"
  gutter: "7%"
  gutter-compact: "6%"
components:
  studio-button-primary:
    backgroundColor: "{colors.studio-yellow}"
    textColor: "{colors.studio-ink}"
    typography: "{typography.studio-button}"
    rounded: "{rounded.studio-button}"
    padding: "0 1.85vw"
    height: "clamp(56px, 4.35vw, 72px)"
  studio-button-primary-hover:
    backgroundColor: "{colors.studio-yellow-deep}"
  studio-nav-booking:
    backgroundColor: "{colors.studio-yellow}"
    textColor: "{colors.studio-ink}"
    rounded: "{rounded.studio-control}"
    padding: "0 1.25rem"
    height: "48px"
  studio-tag-pill:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.studio-ink}"
    typography: "{typography.studio-label}"
    rounded: "{rounded.studio-tag}"
    padding: ".3em .72em .34em"
  button-yellow:
    backgroundColor: "{colors.emphasis}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "1rem 1.6rem"
  nav-booking-legacy:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    rounded: "{rounded.nav}"
    padding: ".7rem 1rem"
  calendar-shell:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: ".5rem"
---

# Design System: Birdcar

## Overview

**Creative North Star: "Your business, in miniature"**

On 2026-09-25 the owner retired The clear argument (cyan editorial fields, Barlow, flat line diagrams, square report shapes) as the marketing direction because it didn't demonstrate capability. The replacement, built first on the homepage, shows the owner's own business as a premium matte isometric diorama: a cutaway van bay, office, front desk, and glass owner's office where paper piles up under a red warning light. Glowing canary-yellow packets travel dotted routes onto the owner's desk; scrolling the page pins the diorama and crossfades it through three states — Today, Walkthrough, After the fix — while white pill tags name the actual work sitting on each desk, drawn from the shared "where work gets stuck" pattern vocabulary. The world is pale daylight (`#eef3f1`-family ground), heavy tight Mona Sans grotesk, one Alkaline wordmark, and a canary: dry wit and small clickable toys, never cute or small-time, never generic AI gloss.

**Rollout status.** Only the homepage (`resources/views/pages/index.blade.php`) is built in this world. The Walkthrough page, Work page, `/tools/where-work-gets-stuck`, the Writing index and individual articles, the favicon (`public/favicon.svg`, still the retired cyan/ink "B" mark), and the browser theme-color meta (`app/Services/MarketingSite.php`, still `#b7edf1`) all still run The clear argument. The header and footer (`resources/views/components/marketing/layout.blade.php`) are one shared partial across every page: on the homepage, `resources/css/home.css` repaints it (transparent, absolutely positioned over the hero, Alkaline at Demi/600 instead of Medium/500, a solid canary pill instead of an outlined ink button) rather than replacing its markup. Treat every retired-system detail recorded below as legacy identification, not as guidance for new work; do not extend cyan fields, Barlow display type, or the flat report/diagram vocabulary to any new page.

**Key Characteristics:**

- Pale daylight ground, near-black ink, canary yellow reserved for actions and traveling packets, a cool teal reserved for the "fixed" state.
- One Alkaline wordmark per page; the homepage renders it at Demi (600), the weight the rollout is standardizing on, with native (unmodified) tracking.
- The diorama is the signature figure: a static isometric plate carries all of the meaning, a canvas layer draws animated dotted routes and glowing packet cubes on top, and white pill tags carry the actual desk labels as real HTML.
- Bespoke isometric renders on transparent grounds stand in for icons everywhere a capability needs illustrating (the model shelf, the person portrait stand-in).
- A monochrome logo strip (GitHub, Heroku, Zapier, Twilio, Salesforce) is framed as work history, not clients or endorsements.
- Admin and the Publishing workspace are untouched by this redesign and keep Flux Pro, Inter, and deep-teal/cyan accents.

## Colors

The homepage palette is a daylight-and-ink base with one warm accent (canary yellow) and one cool accent (teal) reserved for state, plus a warning red used only for the diorama's alert light.

### Primary

- **Canary yellow** (`#f7c848`, deepening to `#efb925` on hover/active): the Walkthrough button, the header booking pill, and every traveling packet drawn on the canvas. It is the one color that means "act on this" or "this is moving."

### Secondary

- **Studio teal** (`#5fb8bf`): the "after the fix" state — the floor lanes work travels through once it's rerouted, and the pill-tag background once the diorama is set to "After the fix." It marks resolution, never a whole-page mood.

### Tertiary

- **Warning red** (`#e2483d`): the pulsing glow on the owner's-office warning light in the "Today" and "Walkthrough" diorama states only. It disappears once the scene reaches "After the fix."

### Neutral

- **Studio ground** (`#edf3f0`): the homepage's body background, standing in for the direction contract's pale daylight `#eef3f1`.
- **Studio ink** (`#0b141a`): headings, body text default, and the button/pill text color. Deeper than the retired system's `#102a33`.
- **Studio ink-soft** (`#323945`) and **studio muted** (`#5c646d`): secondary copy (lead paragraphs, section intros, card body text) and the smallest muted labels (proof heading, offer-terms pill text), respectively.
- **Studio rule** (`#d5dbd8`): hairline dividers between employer marks and under model-shelf cards.
- **Paper** (`#ffffff`): section backgrounds for the proof strip, story track, offer track, and person section; also the white pill tags on the diorama. Shared, unchanged, with the retired system and with Admin.

### Legacy palette (retired direction; still rendering until migrated)

- **Ink** (`#102a33`), **cyan** (`#b7edf1`), **emphasis yellow** (`#f7cb58`), **deep teal** (`#214b57`), and **cyan-wash** (`#b7edf130`) are The clear argument's palette. They still render literally on the Walkthrough, Work, tool, and Writing pages, in the favicon, and in the theme-color meta tag. Deep teal, ink, cyan, and paper are also the tokens the Admin operational extension uses for its light/dark accent — do not remove them even after the remaining marketing pages migrate.

**The Canary Action Rule.** Canary yellow marks the one thing to do or the one thing in motion — a booking button or a packet — never a background wash for a whole section. This carries the retired system's Argument Contrast Rule forward under a new palette.

## Typography

**Studio Font:** self-hosted Mona Sans (via `bunny()` in `vite.config.js`, weights 400–800), `--font-studio` in `resources/css/app.css`. Used for every homepage heading, body line, and button label.
**Wordmark Font:** licensed Alkaline; the homepage renders it at Demi (600) — the weight PRODUCT.md commits to for the eventual site-wide rollout — while pages still on the retired system render the same wordmark at Medium (500) via `resources/css/marketing.css`, unchanged until each page migrates.
**Legacy Marketing Font:** Barlow remains the retired system's display/body voice on every unmigrated page.
**Admin Font:** Inter, unchanged (see Admin operational extension).
**Mono Font:** Commit Mono, unchanged, used only in the retired system's article code blocks.

**Character:** Mona Sans is heavier and tighter than Barlow — negative tracking runs to `-.04em` at display size versus the retired system's `-.025em` — giving the new world a denser, more confident grotesk voice against the same sentence-case, direct copy style.

### Hierarchy

- **Wordmark** (`typography.wordmark`): Alkaline, Demi on the homepage, `clamp(2.6rem, 4vw, 4rem)`.
- **Studio display** (700, `clamp(3rem, 5.35vw, 6rem)`, line-height 1.075, `-.04em`): the hero headline only.
- **Studio headline** (700, `clamp(2.25rem, 4vw, 4rem)`, line-height 1.02, `-.04em`): the build/offer/work/close section headings.
- **Studio story heading** (700, `clamp(2rem, 3.1vw, 3.25rem)`, line-height 1.08, `-.035em`): the scroll-story's own heading above the pinned diorama.
- **Studio lead** (450, `clamp(1.125rem, 1.3vw, 1.3125rem)`, line-height 1.42, `-.035em`): the hero subline and section-intro paragraphs.
- **Studio body** (400, `1.0625rem`, line-height 1.5, `-.01em`): model-shelf, offer-track, and story-step copy.
- **Studio label** (500, `clamp(.75rem, .9vw, .9375rem)`, line-height 1.25): the white pill tags on the diorama.
- **Studio button** (600, `clamp(1.0625rem, 1.43vw, 1.4rem)`): button and pill labels.
- **Studio fine** (400, `clamp(.9375rem, 1vw, 1.0625rem)`): the booking terms line under every CTA.
- **Studio employer mark** (700, `clamp(1.25rem, 2.3vw, 2.25rem)`, `-.035em`): the past-employer names in the proof strip, set beside each monochrome mark.

**The One Wordmark Rule.** Alkaline appears once per page as the header wordmark and nowhere else. Its weight is Demi (600) on the homepage and the intended weight for the full rollout; Medium (500) elsewhere is the retired system, not a second valid option.

## Layout

The homepage uses viewport-scaled gutters and section padding rather than the retired system's fixed `7%`/`6%` percentages: a `4.95vw` side gutter (`4.3–4.75vw` around the header), collapsing to `6%` below `1100px`, and `7–8vw` of vertical section padding, collapsing to `4.5rem` on mobile.

The hero is a two-column split: copy fixed-width at `40vw` on the left, the diorama absolutely positioned at `60vw` on the right, overlapping the header. Below the fold, a proof strip (paper, monochrome logo row) sits above a scroll-driven story: a sticky diorama figure on the right tracks the reading position of three text steps on the left (`resources/js/miniature.js` pins the active step by measuring scroll against the viewport midline, or 78% down on narrow viewports). A visible segmented control (Today / Walkthrough / After the fix) lets a visitor drive the same diorama by hand instead of scrolling. Below the story: a three-column model shelf (six isometric capability renders), a four-column offer track (a connecting line with lit/unlit milestone dots), a two-column "selected work" strip, a two-column person section, and a closing invitation — all collapsing to one column under `1100px`.

**The Static Diorama Rule.** The isometric plate and its HTML `<li>` tag labels carry the full meaning of "what's stuck where" without JavaScript; the canvas packet-flow layer only adds motion on top and is never the sole carrier of a relationship. This is why `initMiniature()` still calls `drawScene()` once at load even when `prefers-reduced-motion` or missing `ResizeObserver`/`IntersectionObserver` skip the animation loop.

## Elevation & Depth

The homepage is mostly flat, like the retired system, but adds one new device: colored glow as a signal of motion. Buttons and pills use an ordinary soft, offset ambient shadow at rest (see below). Canvas-drawn elements add a `shadowBlur` glow in the packet's own hue (amber for packets in transit, teal for "fixed"-state lane markers, red for the pulsing warning light) — glow is reserved for things that are moving or urgent, never applied to static UI as decoration.

### Shadow Vocabulary

- **Button ambient** (`0 1px 2px rgb(11 20 26 / .14), 0 10px 26px -12px rgb(160 110 0 / .55)`, deepening on hover): the primary button and header booking pill.
- **Tag pill** (`0 1px 1px rgb(11 20 26 / .08), 0 4px 12px -2px rgb(11 20 26 / .22)`): every white pill tag on the diorama.
- **Model lift** (`drop-shadow(0 18px 18px rgb(11 20 26 / .08))`, deepening on hover with a small translate/scale): the six model-shelf renders.
- **Segmented-control shell** (`0 1px 2px rgb(11 20 26 / .1), 0 8px 24px -10px rgb(11 20 26 / .3)` with `backdrop-filter: blur(8px)`): the Today/Walkthrough/After-the-fix control.
- **Canvas glow** (`shadowBlur` in `resources/js/miniature.js`, amber/teal/red per element): packets in flight, fixed-state lane markers, and the pulsing warning light.

**The Glow-as-Motion Rule.** Reserve colored glow for something in motion or urgent (a packet, a lane marker, the warning light); static surfaces at rest keep the ordinary blurred, offset ambient shadow.

## Shapes

Controls use soft, generous radii: `7px` for pill tags, `8px` for the header booking pill, `10px` for the primary button, `999px` for the segmented control and the offer-terms/pill labels. This is rounder than the retired system's `3–5px` control radii and its true-circle stage numbers.

The signature shape is the isometric packet cube drawn on canvas (`drawCube()` in `resources/js/miniature.js`): three visible faces (top/left/right) in a light/mid/deep tint of the packet's hue, giving every route endpoint and traveling packet the same small 3D block silhouette that echoes the diorama's own isometric rendering style.

## Components

### Header and booking pill (homepage-scoped)

The header partial is unchanged markup, but `.home-page .site-header` repaints it transparent and absolutely positioned over the hero; `.home-page .wordmark` sets Alkaline to Demi/600; `.home-page .desktop-nav .nav-booking` replaces the retired outlined-ink button with a solid canary pill (`studio-nav-booking`). Off the homepage the same partial renders with the retired outlined treatment.

### Primary button

Solid canary yellow, ink text, `10px` radius, an arrow icon that slides right on hover, ambient shadow deepening on hover/press. Used for every "Book a free Walkthrough" placement (`hero`, `homepage-strip`, `closing-invitation`).

### Diorama figure (signature component)

`<figure data-miniature>` wraps a responsive `<picture>` plate (`x-marketing.miniature-plate`), a `<canvas class="miniature-flow">` for animated routes and packets, and a `<ul class="miniature-tags">` of white pill tags positioned by percentage coordinates. The hero instance is static (`today` only); the story instance adds the segmented control and two crossfading "patch" images (`owner-office-conversation.webp`, `owner-office-after.webp`) that fade in over the same camera framing via a feathered CSS mask, so the owner's glass office visibly changes between states without a second full render.

### Model shelf card

An isometric capability render (transparent ground) above a name and one sentence, separated from its neighbor by a hairline rule; the whole card lifts and its drop-shadow deepens on hover. Six recur in a three-column grid (two on narrow viewports).

### Offer track step

A numbered milestone on a horizontal (vertical on mobile) connecting line, lit dots for the two free steps and unlit for the two paid/optional ones; each step's terms render as a small pill (tinted yellow for the free steps, outlined for the rest).

### Employer marks (proof strip)

A monochrome inline SVG mark beside its company's name, in `studio-employer-mark` typography, separated by a hairline vertical rule; wraps to a left-aligned stack on mobile. Framed under "Where I've built systems," never as a client or endorsement list.

## Admin operational extension

Admin uses Flux Pro and Inter rather than the marketing typography and page composition. Native Brand components pair the existing `public/favicon.svg` B mark with "Admin"; Alkaline remains exclusive to the marketing wordmark. The shell has one collapsible module sidebar, a contextual header, and module workspaces. Only Home and authorized Publishing are present.

The approved Attention ledger Home answers "What needs me?" before "Where was I?" Blocked work and human decisions occupy the wide column; Continue working is narrower and follows the ledger on mobile. Links lead to authoritative workspaces, not Home-level approval or publishing actions. Empty, unauthorized, unavailable, and loaded states remain distinct.

Native Flux appearance respects saved preferences and defaults to the system theme. Light accent/content uses deep teal `#214b57` with white foreground; dark accent/content uses cyan `#b7edf1` with ink `#102a33` foreground. Current navigation, keyboard focus, and text selection carry restrained accent treatment. Public article previews remain independently styled.

The owner-only Publishing workspace is a local Admin extension, not a marketing identity change. It uses the existing Flux Pro/Inter neutral system with restrained teal/cyan accent, large rounded creation composer, paper-airplane Develop affordance, passive bookmark save, and separate Ideas, Active writing, and Published libraries with owner-scoped pagination. Session work stays organized around a persistent title/save state and three modes: Develop, Write & review, and Release.

Develop shows interview, angle, plan, sources, and living brief as readable editorial material; angle, plan, and release approvals remain explicit text approvals. Write & review gives the manuscript the broad surface with contextual feedback, sources, and brief; on mobile, the context replaces the manuscript visually without destroying the editor. Release separates metadata/date/checklist, frozen scoped release preview, timezone-aware scheduling, approval, and publication. Published shows the live title/date and marks draft-in-progress separately from the live article. Settings, shown only to users who can configure agents, is a focused form in the same shell: an agent-request pause with a separate saved-state badge and explicit Save, a configured/not-configured key notice, and per-task rows with the code recommendation, a native Flux select (Use recommended, curated models, Auto Router), pinned or reset-required state, and reset. It shows no prices, allowances, spending, or keys.

Publishing motion is useful and bounded. `resources/js/admin/publishing/motion.js` uses Motion springs for mode changes, confirmed-capture header arrival, small confirmed milestones, and a disposable published-only flourish; reduced motion suppresses decoration. Save guards block unsafe actions during saving/conflict, stale server updates preserve local edits, and older-revision recovery enters conflict before enqueue.

## Do's and Don'ts

### Do:

- **Do** use canary yellow only for actions and things in motion (buttons, packets); use studio teal only for the resolved/"fixed" state.
- **Do** keep the diorama's meaning readable from the static plate and its real HTML tag labels alone; treat the canvas layer as a motion enhancement, never the only carrier of a relationship.
- **Do** keep Alkaline to the one header wordmark, at Demi (600) for any newly migrated page, matching the homepage.
- **Do** self-host Mona Sans and Barlow via `bunny()` rather than a system sans fallback becoming the de facto display face.
- **Do** describe the past-employer row as work history in monochrome marks, never as clients or endorsements.
- **Do** keep Admin Publishing within Flux Pro/Inter neutral surfaces with teal/cyan accents, useful bounded motion, explicit approvals, separate draft/live states, and save/conflict guards.

### Don't:

- **Don't** describe the Walkthrough page, Work page, `/tools/where-work-gets-stuck`, Writing index/articles, the shared header/footer's default (non-home) skin, the favicon, or the theme-color meta as migrated to the new world; they are the retired system until this build reaches them.
- **Don't** carry the retired system's cyan fields, Barlow display type, square report shapes, or flat line-diagram figures into new marketing work; they're recorded above only so a future agent can recognize them as legacy, not build with them.
- **Don't** literalize the approved comp's invented signage, van-panel copy, poster, doormat slogans, or the "Waikthrough" misspelling; the built plates and copy are the source of truth, not the comp's placeholder text.
- **Don't** treat the model shelf's three-column render/heading/text grid as a general-purpose listing pattern for unrelated content; it earns its place here because the isometric renders are themselves the world's bespoke material, not because "grid of same-size cards" is a reusable page scaffold.
- **Don't** remove the legacy `ink`/`paper`/`cyan`/`deep-teal` tokens even after the remaining marketing pages migrate; Admin depends on them independently of the marketing redesign.
