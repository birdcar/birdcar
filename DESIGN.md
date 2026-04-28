# Design

The visual system for Birdcar. Implementation lives in `src/styles/global.css`; this document captures intent so variants stay on-brand. Source-of-truth tokens are the CSS custom properties.

## Theme

Editorial monochrome on warm paper, with one restrained clay accent. Think a printed essay, not a SaaS landing page. The site should feel like it was designed once, carefully, and then left alone, not iterated into a Figma soup.

**Light only.** The warm paper background is load-bearing for the editorial feel; mode-switching it would force a second visual system to maintain and dilute the brand. Print styles strip chrome and revert to plain black-on-white.

**Color strategy: Restrained.** Tinted neutrals (warm paper plus near-black ink) plus one accent (`--clay`) used like a stamp, well under 10% of any surface.

## Color

| Role | Token | Hex | Use |
|---|---|---|---|
| Background | `--paper` | `#F7F3EC` | Page background. Warm off-white, never pure white. |
| Background, alt | `--paper-2` | `#EFE9DD` | Section breaks, code block bg, hover tint. |
| Background, deep | `--paper-3` | `#E6DFCE` | Rare; deepest paper tone. |
| Ink | `--ink` | `#1A1814` | Body text, headings, primary button fill. |
| Ink, soft | `--ink-2` | `#4A453D` | Secondary text. |
| Ink, muted | `--ink-3` | `#7A7368` | Metadata, dates, captions. |
| Ink, faint | `--ink-4` | `#A89F90` | Placeholder, disabled. |
| Rule | `--rule` | `#D9D1BE` | Hairlines, dividers. |
| Rule, strong | `--rule-strong` | `#BFB59E` | Form-field borders, focus tracks, secondary-button border. |
| Accent (clay) | `--clay` | `#B8442B` | Links, brand mark, key accents. |
| Accent, hover | `--clay-2` | `#8E2F1B` | Link hover, pressed states. |
| Selection | `--clay-tint` | `#F2D8B6` | Text-selection background. |
| Success | `--ok` | `#3F6B43` | Inside post directives only. |
| Warning | `--warn` | `#A8741A` | Inside post directives only. |
| Error | `--err` | `#8E2F1B` | Inside post directives only. |

Semantic states (`--ok`, `--warn`, `--err`) exist but appear only inside post directives; this is not an app and should not read like one.

## Typography

Three faces, three jobs.

- **Source Serif 4** — display + body. A literary serif used at every reading size.
- **Inter Tight** — UI only, sparingly: nav, metadata, captions, button labels.
- **JetBrains Mono** — code, dates, eyebrows. Mono dates are intentional; they signal "this is a record."

Loaded from Google Fonts via `@import` in `global.css`. Static `.ttf` files for Source Serif 4 and JetBrains Mono live in `src/assets/fonts/` for OG image rendering via Satori.

**Type scale** (1.250 / major third, 18px body anchor):

| Token | Size | Line height | Use |
|---|---|---|---|
| `--t-display` | 56px | 1.05 | Home hero only. |
| `--t-h1` | 40px | 1.10 | Page titles. |
| `--t-h2` | 28px | 1.20 | Section heads. |
| `--t-h3` | 22px | 1.30 | Sub-sections, post titles in lists. |
| `--t-body` | 18px | 1.55 | Body copy. |
| `--t-small` | 15px | 1.50 | Secondary copy. |
| `--t-meta` | 13px | 1.40 | Dates, tags, captions. |

Headings use letter-spacing -0.01em to -0.025em (display) and `text-wrap: balance`. Body uses `text-wrap: pretty`.

### Line length

Body line-length caps at **76ch (~720px)** via `--w-prose`. This is wider than the print convention (Bringhurst recommends 45–75ch with 66ch ideal; Baymard's research finds 50–75ch optimal) but inside the upper acceptable band of every modern guideline that accounts for screen reading: Butterick's *Practical Typography* (45–90ch), WCAG 1.4.8 Level AAA (≤80ch), and the actual practice of editorial reading sites (NYT articles, Stripe Press, Simon Willison's blog all run in this band on desktop).

The decision is deliberate. A 64ch column was tried and reverted because it forced 130px+ of asymmetric inset on chrome pages (`/about`, `/contact`) and 66px misalignment under every post head; the eye retracking the misaligned left rail across pages was the larger readability cost. Per Principle 2 of `PRODUCT.md` ("the reading experience is the product"), we optimized for the actual reading geometry across the whole site, not for column width as an isolated metric.

Two compensating choices keep 76ch comfortable in practice:

- **Generous line-height.** Body runs at 1.55, well above the 1.4 default. Wider lines are forgiving when vertical tracking is relaxed.
- **Source Serif 4 at 18px.** A screen-tuned literary serif at a comfortable reading size. The same width in a sans at 16px would feel longer.

Mobile narrows to a single full-width column with the page padding the only side margin; line lengths fall back inside the 50–65ch sweet spot automatically.

## Spacing

A 4px base, used as 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 / 96 / 128 (`--s-1` through `--s-10`).

- Section rhythm: 96px between top-level sections on desktop, 64px on mobile.
- Paragraph rhythm: 1em between paragraphs in body copy.
- List items: 8px between items.
- Vary spacing for rhythm. Same padding everywhere is monotony.

## Layout

- Single column on the public site. Masthead is left-aligned, never centered.
- Page max-width 960px (chrome). Content and prose share `--w-content` (720px ≈ 76ch); body and long-form posts run on the same column so head and body left rails align across every page.
- Nav lives in the masthead; no sticky nav, no fixed header.
- Footer is plain text, left-aligned, separated from content by a double-rule.
- Forms are full-width within the content column, labels above fields, no floating labels.

**Single-column model.** Figures, code blocks, and embeds fit inside the prose column (`--w-content`, 720px). The earlier breakout grid (`prose` / `wide` / `full` tiers via `data-width` on `.bfm-figure`) was retired in `dbfaf33` because it created head/body misalignment everywhere it was used. Code blocks already overflow horizontally on long lines; charts already render at `width: 100%`. `data-width` attributes in BFM markup are now no-ops, slated for removal in the rehype pipeline.

## Borders, rules, corners

- **Hairlines, not boxes.** Section breaks are 1px horizontal rules in `--rule`, never bordered cards.
- **Corner radius is essentially 0.** `--r-1` 2px on buttons and form fields. `--r-2` 4px on code blocks. Nothing pill-shaped. Nothing `rounded-2xl`.
- **No drop shadows anywhere.** Elevation is communicated by typography and spacing, not z-axis.

**The one ornament:** a thin double-rule (two 1px lines, 4px apart, both `--rule`), used to separate the masthead from content and to mark the footer. The only ornament in the system.

## Motion

Curated and small. The site otherwise reads as a printed object.

**Tokens.**

| Token | Value | Use |
|---|---|---|
| `--ease` | `cubic-bezier(0.2, 0, 0, 1)` | Hover transitions. |
| `--ease-out-quart` | `cubic-bezier(0.25, 1, 0.5, 1)` | Paint reveals, post-row indent. |
| `--dur-fast` | 120ms | Link/icon hover. |
| `--dur` | 160ms | Button transitions. |
| `--dur-paint` | 280ms | First-paint reveal of hero items. |
| `--dur-rule` | 520ms | Hairline draw on first paint. |

**Allowed.**

- First-paint hero stagger: H1, lede, trust strip, cue link, portrait, Now box reveal with opacity 0 → 1 and `translateY(8px)` → `translateY(0)`, `--ease-out-quart`, cascading delays totaling ~600ms.
- Section hairline draw: `.bc-hr` and `.bc-rule-double` go `scaleX(0)` → `scaleX(1)` from `transform-origin: left` after the hero stagger.
- Link underlines thicken and shift on hover (~120ms).
- Buttons shift down 1px on press (2px on touch devices).
- Post-row title indents 6px on hover or focus, paired with the existing tint shift.
- Mobile nav drawer fades over 160ms.

**All motion is gated behind `@media (prefers-reduced-motion: no-preference)`.** Reduced-motion users see the static page, and never see the `from` keyframe.

**Banned.** No scroll-triggered fades. No parallax. No marquees. No bounce or elastic curves. Never animate layout properties; transform and opacity only.

## Imagery

- No illustrations, no spot illustrations, no abstract blobs, no AI-generated imagery.
- Photography, if used, is warm-toned or black-and-white. Never cool blue. Never AI-generated.
- The home portrait is the only image on most pages. About, if it has a photo, uses a real photograph, small (max 240px), offset to one side, never centered like a corporate bio.

## Iconography

Birdcar uses almost no icons. The aesthetic is editorial; icons in body copy or section headers would undermine the "competent operator who writes well" tone.

- **External link arrows** appended to outbound inline links use Unicode `↗` (U+2197), not SVG.
- **Phosphor (Regular weight)** via the official web font, monochrome `--ink-2`, used very sparingly. Never inline in body copy. Never colored, never filled (no `ph-fill`).
- **Lucide stroke 1.5px** is acceptable for form-field affordances (search icon in a search input).

The wordmark is the only visual mark. No bird icon, no car icon, no combined mark. Resist the temptation.

## Components

The implementation in `src/components/` and `src/styles/global.css` is the source of truth. Notable patterns:

- **Masthead** (`Masthead.astro`): wordmark left, nav right, hairline bottom border. Drawer collapses under 640px.
- **Footer** (`SiteFooter.astro`): three columns, separated from content by the double-rule.
- **Buttons.** Primary (ink fill on paper) and secondary (transparent with `--rule-strong` border). 2px corner radius. Press shifts down 1px (2px on touch devices).
- **Post list** (`.bc-postlist`): hairline-ruled three-column grid (date | title | tag), no cards. Hover applies `--paper-2` tint, indents title 6px, and shifts title color to `--clay`.
- **Trust strip** (home hero): mono 13px sentence-case label and tags separated by clay dots, on a single hairline-topped line.
- **Forms.** Labels above fields, 2px corner radius, focus ring is `outline: 2px solid var(--clay)`.

## Anti-patterns

The system breaks if these are violated:

- No gradients. No drop shadows. No glassmorphism. No glow. No neumorphism.
- No card grids on the home or blog index.
- No nested cards anywhere.
- No emoji in UI.
- No em dashes in copy. Use commas, colons, semicolons, periods, or parentheses. Also not `--`.
- No "Latest insights from the Birdcar blog 🚀" copy register.
- No `rounded-2xl`, no pills.
- No bare `text-decoration: none` on links without a deliberate replacement (e.g. portrait-link hairline border).
- No third-person bio voice on the site itself.

## Implementation reference

- **Tokens, base type, chrome, BFM directive treatments:** `src/styles/global.css` (single stylesheet).
- **Layouts:** `src/layouts/Base.astro` (head, masthead, footer, JSON-LD), `src/layouts/Post.astro` (post head, prose grid).
- **Components:** `src/components/Masthead.astro`, `src/components/SiteFooter.astro`.
- **Pages:** `src/pages/{index,about,work,contact,404}.astro`, `src/pages/writing/[...page].astro`, `src/pages/writing/[slug].astro`, `src/pages/tags/`.
- **OG images:** `src/pages/og/[slug].png.ts` uses Satori plus Resvg with paper background, clay eyebrow, serif title, hairline rule above wordmark/url footer. Per-post, generated at build.
- **Writing system.** Eleven BFM directives (`@callout` × 5 intents, `@figure`, `@aside`, `@details`, `@tabs`, `@embed`, `@math`, `@toc`, `@endnotes`, `@query`, plus `@mention`, `#hashtag`, footnotes, task lists). Styled in `global.css` under `.bfm-*` selectors. The full writing-system spec lives at `docs/writing/WRITING.md`; the design source bundle lives at `docs/design-system/`.

## When in doubt

Do less: fewer rules, fewer colors, fewer pixels. The system is opinionated and complete. If a question is not answered here, the answer is almost always "remove something."
