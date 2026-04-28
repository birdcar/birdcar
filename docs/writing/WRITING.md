# Writing System

The writing is the star of birdcar.dev in its first phase. This document defines how a post looks, how images and figures behave, how diagrams and charts get drawn, and how Open Graph previews are rendered — all mapped onto the directives in [Birdcar Flavored Markdown (BFM)](https://github.com/birdcar/markdown-spec).

The job: connect technical concepts to non-technical readers without losing technical readers. The visual system supports that by **carrying complexity in figures, callouts, and structured asides** rather than in prose density.

---

## Post anatomy

Every post has the same five-part structure. Skipping a part is fine; reordering is not.

```
1. Eyebrow            mono · date · tag · reading time
2. Title              serif · 44px · sentence case
3. Lede               serif · 22px · ink-2 · one paragraph, max 3 sentences
4. Body               serif 18/28 · 64ch · BFM directives interleaved
5. Endnotes & meta    mono · footnotes, related posts, "← back to writing"
```

### Layout

- Single column, max-width `--w-prose` (64ch ≈ 580px at 18px).
- **Figures, callouts, and asides may break out** to wider widths (see "Width tiers").
- No sidebar. No table of contents on short posts. For posts >2,500 words, BFM `@toc` renders inline at the top.
- The masthead and footer are the same as the rest of the site. No bespoke "blog chrome."

### Width tiers

Three widths, used deliberately:

| Tier | Width | Used for |
|---|---|---|
| Prose | 64ch (~580px) | Body paragraphs, headings, callouts, plain images |
| Wide | 820px | Diagrams, charts, code blocks with long lines, `@figure` with caption |
| Full | 100% of content (max 960px) | Hero comparison images, screenshots that must show context |

Tiers are set on the figure, not the surrounding paragraphs. Use `@figure[width=wide]` or `@figure[width=full]`.

---

## Type rules inside a post

Recap the system but with post-specific details:

- **H2** opens a major section. 28px serif, generous space above (`--s-7`).
- **H3** is for sub-sections inside long posts. 22px serif, 600 weight.
- **Never use H4+** in a post. If you need it, the post is a chapter and should be split.
- **Lists** use 8px gaps. Numbered lists for actual sequences only — not for "key takeaways."
- **Bold** is for the operative word in a sentence, not for whole sentences.
- **Italics** for the introduced term, the title of a thing, and the occasional aside in voice.
- **Inline code** uses `--paper-2` background, `--mono`, 0.92em.

---

## BFM directives — visual mapping

This is the contract. Every directive in BFM has exactly one visual treatment.

### `@callout`

For "the thing the non-technical reader needs to take away from this section." Five intent variants, **all monochrome** with a single accent color signaling intent. No icons in the callout body — only in the eyebrow.

| Intent | Eyebrow color | Eyebrow label | Use |
|---|---|---|---|
| `note` (default) | `--ink-3` | `Note` | Side commentary, a fact worth pausing on |
| `key` | `--clay` | `Key takeaway` | The single thing to remember from a section. Use **at most twice per post.** |
| `warn` | `--warn` | `Watch out` | A trap a reader could fall into |
| `tip` | `--ok` | `Tip` | A practical move that helps |
| `tldr` | `--ink` | `TL;DR` | Always at the top of long posts. Bullet form. |

Visual treatment: 2px left border in the intent color, `--paper-2` background, `--s-4 --s-5` padding, no rounded corners, full prose width. Eyebrow is mono uppercase 11px with 1.5px letter-spacing.

```
@callout[key]
The export is harder than the import.
@end
```

### `@figure`

The general-purpose container for an image, diagram, or chart with a caption. Captions are mandatory. Caption sits below, mono 13px `--ink-3`, italic for the descriptive part, optional `Fig. N` prefix in non-italic.

- Image: `--paper-2` background fill if the image has transparency; otherwise no chrome.
- 1px `--rule` border below the figure only — sits like a baseline rule, never a full box.
- Caption is left-aligned to the figure, never centered.
- Width inherits from `@figure[width=...]`.

### `@aside`

A parenthetical thought that's longer than parentheses can carry. **Right-set** in a margin column on desktop (negative margin into the gutter), **inline above the next paragraph** on mobile. Mono eyebrow `Aside` in `--ink-3`, body in serif 15px italic. No border. No background.

### `@details`

Collapsed by default. Summary line is mono 13px `--ink-2` with a `›` glyph that rotates to `▾` open. When open, contents render as normal prose (recursive). Used for "the long version" / "the math" / "the full SQL." A non-technical reader scrolls past; a technical reader expands.

### `@tabs` / `@tab`

For showing the same thing two ways. Most common: `Plain English` / `The technical version`. Tabs render as mono labels with a 2px `--ink` underline on the active one, sitting on a 1px `--rule` baseline. No card, no fill.

**This is the directive that does the most work for our dual audience.** Default to authoring with both tabs whenever a section has any technical depth.

### `@embed`

YouTube, Loom, CodePen, etc. Renders as a 16:9 `--paper-2` placeholder until clicked (privacy + speed). The placeholder shows the embed source name in mono and a play glyph (Phosphor `ph-play`, regular weight). Click → load the iframe. Caption rules from `@figure` apply.

### `@math`

KaTeX rendered. Block math centered in the prose column with 8px `--paper-2` padding strip on the left edge only (subtle vertical rule). Inline math sits at body baseline. Display equations get an auto-numbered tag in mono on the right edge for posts that reference equations later.

### `@toc`

Auto-generated from H2/H3. Renders as a hairline-bordered box (top + bottom rule only, no sides) at prose width, mono 13px headings, serif 14px titles. Used at the top of posts >2,500 words; suppressed otherwise.

### `@endnotes`

Renders the footnote list at the end of the post, separated by a `rule-double` ornament. Each footnote is a mono superscript number followed by serif 15px text. Backref glyph is `↩` in `--clay`.

### `@include`

No visual treatment of its own — the included content renders inline with normal rules.

### `@query`

For "live" or computed content (e.g., "posts tagged X"). Renders as a `PostListItem` list inside a hairline-bordered region. Mono eyebrow shows the query.

### Inline: `@mentions` and `#hashtags`

- `@username` — renders as a link, `--clay`, no underline by default, underline on hover. The `@` character is `--ink-3`.
- `#hashtag` — renders inline as mono 14px on `--paper-2` with 2px padding. No `#` glyph in the rendered output (it's read as a tag, not a hashtag string).

### Footnotes (`[^label]`)

Marker is mono superscript `--clay`. Definition lives in `@endnotes` at the bottom; on hover/tap the marker pops a small `--paper-2` tooltip with the footnote text in serif 15px. Tooltip max-width 320px, no border, 2px `--clay` left edge.

### Task lists (BFM extended states)

Used in posts that document a process. Visual:

| Marker | Rendered as | Color |
|---|---|---|
| `[ ]` | `○` | `--ink-3` |
| `[x]` | `●` | `--ok` |
| `[>]` | `▶` | `--clay` (in progress) |
| `[<]` | `◀` | `--ink-3` (deferred) |
| `[-]` | `–` | `--ink-3` (cancelled, item line-through) |
| `[o]` | `◐` | `--warn` (blocked) |
| `[!]` | `!` | `--err` |

Modifiers like `//due:2025-03-01` render as a mono pill in `--ink-3` after the task line.

---

## Images inside posts

The single hardest place to keep the system's discipline. Rules:

1. **No stock imagery. Ever.** No abstract gradient backgrounds. No "person at a laptop." No business handshake. If you don't have a real image, you don't add one.
2. **Two acceptable image types:**
   - **Screenshots** of real software (yours, a client's with permission, or public).
   - **Photographs**, warm-toned or black-and-white. Never cool blue. Never AI-generated.
3. **Screenshots get a 1px `--rule` border**, no shadow, no rounded corners. If the screenshot is of a window, crop tight — don't show the OS chrome unless the chrome is the point.
4. **Annotations on screenshots** use `--clay` only. 2px stroke. Numeric callouts in mono on a `--paper` filled circle, 1px `--clay` border.
5. **Photographs get no border**, but always sit inside `@figure` with a caption.
6. **Default width is prose.** Reach for `wide` or `full` only when the image's information density requires it.
7. **Alt text is mandatory** and is part of the post — write it like a sentence, not a keyword list.

---

## Diagrams & charts

This is the section that lets us connect technical to non-technical: a careful, consistent diagram does in five seconds what three paragraphs can't.

### House style

Diagrams and charts share one visual vocabulary so the whole archive reads as one body of work.

- **Background:** `--paper` (transparent SVG sits on the page).
- **Strokes:** `--ink` for primary, `--ink-3` for secondary, `--clay` for the *one thing the diagram is about*.
- **Stroke weight:** 1.5px standard, 2.5px for emphasis. Never under 1px.
- **Fills:** `--paper-2` for "container" shapes (boxes, swimlanes). `--clay-tint` for highlight fills. `--ink` filled shapes are reserved for "the answer."
- **Corners:** 2px radius on rectangles. Lines are square-cap.
- **Type in diagrams:** `--mono` 12px for labels, `--serif` 14px for titles. Both `--ink`. Avoid `--sans`.
- **Arrows:** Single-headed by default. Arrowhead is a small filled triangle (8×6px), same color as the line. Two-headed only for actual bidirectional relationships.
- **Spacing:** Multiples of 8px. Never freeform.
- **No 3D. No drop shadows. No gradients. No icons inside boxes.**

### Tooling

- **Authoring tool:** Excalidraw (with the `birdcar` palette below loaded), or hand-coded SVG for diagrams that need precision.
- **Export format:** SVG always. PNG only for things that can't be SVG.
- **File location:** `posts/<slug>/figures/<slug>-<n>.svg`.

### Excalidraw palette (paste into Excalidraw → Settings → Custom theme)

```
strokeColor:     #1A1814   (--ink)
strokeColorAlt:  #7A7368   (--ink-3)
strokeColorHi:   #B8442B   (--clay)
backgroundColor: #EFE9DD   (--paper-2)
fillColorHi:     #F2D8B6   (--clay-tint)
fontFamily:      JetBrains Mono / Source Serif 4
strokeWidth:     1.5 / 2.5
roundness:       sharp (radius 2)
```

### Charts

For data visualization (Observable Plot or D3, hand-tuned):

- **Up to four series.** If you need more, you have two charts.
- **Color order:**
  1. `--ink` (the primary series — what the chart is about)
  2. `--clay` (the comparator)
  3. `--ink-3` (context / "everything else")
  4. `--warn` `#A8741A` (only when needed for a third real series)
- **Axes:** 1px `--rule` lines, mono 11px labels, no grid (or, for sparse data, dotted `--rule` grid at 0.5 opacity).
- **No legends as separate boxes.** Label the line at its endpoint, mono 11px, in the line's color.
- **Numbers in axis labels** use mono and tabular figures.
- **Captions explain the takeaway**, not what the chart is. *"Throughput doubled after the export rewrite"*, not *"Weekly throughput, 2024–2026."*

### Default chart sizes

| Use | Size | Width tier |
|---|---|---|
| Inline | 580×320 | Prose |
| Featured | 820×440 | Wide |
| Hero | 960×540 | Full |

---

## OpenGraph image system

Every post has an OG image. They are generated, not hand-drawn, so they stay consistent.

- **Dimensions:** 1200×630 (Twitter/Facebook standard).
- **Background:** `--paper` (`#F7F3EC`). No gradient. No texture.
- **Layout:** title left-aligned at `padding 80px`, baseline at vertical center.
- **Title:** Source Serif 4 Bold, 64–80px depending on length, `--ink`. `text-wrap: balance`. Max 3 lines.
- **Eyebrow:** mono 18px `--clay`, uppercase, the post's primary tag (e.g. `TAX TOOLS`).
- **Footer strip:** bottom 80px, contains:
  - **Wordmark** "Birdcar" left-aligned, 32px serif bold, `--ink`.
  - **URL** `birdcar.dev/writing/<slug>` right-aligned, mono 14px, `--ink-3`.
- **A single 1px `--rule` line** sits above the footer strip, edge to edge.
- **No author photo. No icons. No illustration.** The system *is* the image.

A template lives at `og-template.html` with title/eyebrow/url as URL params, ready to be screenshotted by the build (Playwright or Puppeteer).

---

## Connecting technical → non-technical: authoring patterns

Three repeatable patterns, in order of preference:

1. **Lede first, jargon never.** The first paragraph of every post should make sense to a CPA. If it can't, lead with the human story, then `@callout[key]` the takeaway, *then* drop into technical detail.
2. **Use `@tabs` for any technical claim**. Author both `Plain English` and `The technical version` tabs. The non-technical reader stays on tab one. The technical reader can verify on tab two. Both feel respected.
3. **Diagrams over code, code over prose, in that order**, when explaining a *system*. Use `@figure` for the diagram, then `@details` for the code if a technical reader wants to peek.

---

## Index

| File | What |
|---|---|
| `WRITING.md` | This file — post anatomy, BFM directive treatments, diagrams, OG |
| `preview/writing-post-template.html` | Single-post template card (visual reference) |
| `preview/writing-callouts.html` | All five callout intents |
| `preview/writing-figure-tabs.html` | `@figure` + `@tabs` directives |
| `preview/writing-diagram-style.html` | Diagram house style with example |
| `preview/writing-chart-style.html` | Chart house style with example |
| `preview/writing-og-template.html` | OG image template (1200×630) |
