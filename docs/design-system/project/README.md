# Birdcar Design System

The personal-site and consulting-practice brand for **Birdcar** — the work of **Nick Cannariato**, a Fort Worth-based product engineer and back-office automation consultant. "Birdcar" is Nick's long-standing nickname; it's the brand he writes and ships under.

## Context

Birdcar is one person with two adjacent identities:

1. **Product engineer / technical writer.** Currently a solutions engineer at WorkOS. Writes about dev tooling, AI, and solutions engineering for a small, technical audience.
2. **Local consultant.** Building a part-time consulting practice for small Fort Worth businesses ($1–20M revenue) — internal tools, automations, back-office systems. Project-based, 4–12 weeks, fixed scope.

"Birdcar" is a long-standing personal nickname; it is the brand.

### Primary audience

A non-technical small-business owner, CPA, mortgage broker, or realtor who **just got a referral** and is checking that Birdcar is real and competent. They will not read long copy. They need to feel: *this person is legitimate, careful, and sends emails back.*

### Secondary audience

Peers in dev / AI / support-engineering who already read the writing. They should feel at home — the technical content should reinforce credibility for the primary audience without alienating them.

### Tone in one line

> Competent operator who writes well.

Not corporate. Not startup-bro. Not "developer brand" cutesy. Clear sentences, real opinions, no hype.

## Sources used to build this system

The user provided a written brief only — **no codebase, Figma, screenshots, or existing assets were attached.** The brand was derived from the tone and audience description, working in the tradition of editorial personal sites (Craig Mod, Robin Sloan, Simon Willison, Nat Eliason, Pinboard).

If/when assets exist, drop them in `assets/` and update this file.

---

## Content fundamentals

**Voice.** First person singular. "I build internal tools for small businesses." Never "we." No royal we. No third-person bio voice on the site itself (that's for someone else's blog post about you).

**Address.** Direct second person when talking to the reader. "You probably got my name from someone." "Email me." Not "users," not "clients," not "folks."

**Casing.** Sentence case for everything — headings, buttons, navigation, page titles. Title Case Looks Like A Brochure. The exception is proper nouns and the brand mark `Birdcar` (capital B).

**Sentence shape.** Short. Then occasionally a longer one that earns the space because it's making a real point. Cut every adverb you don't need. Cut "really," "very," "just," "simply." Cut "I think" unless the disclaimer is doing real work.

**No hype words.** Banned: *leverage, empower, unlock, transform, seamless, robust, cutting-edge, innovative, AI-powered, world-class, best-in-class, solutions, synergy.* If a sentence stops working when you remove the hype word, the sentence wasn't working.

**Concrete over abstract.** Not "I help businesses streamline operations." Yes "I built a CPA firm a tool that pulls 80 client returns out of Drake into a single review queue." Always reach for the specific example.

**Opinions, lightly held.** Have a take. "Most small-business software is sold by salespeople to people who don't have time to evaluate it. The result is usually fine and rarely good." Don't hedge into nothing.

**Dryness.** A small amount of dry humor is on-brand. *"I am, against my better judgment, on LinkedIn."* It should never be the point of the sentence — it's seasoning, not the meal.

**Emoji.** No. Not in copy, not in headings, not in nav. The one allowable exception is a literal use inside a code block or a quoted message.

**Metadata is content.** Post dates are visible and matter. "Updated" dates matter. Reading time is fine if it's honest. "5 min read" not "a quick read."

**Examples**

> ✅ I build internal tools and automations for small businesses in and around Fort Worth.
>
> ❌ I empower local SMBs to unlock operational excellence through bespoke automation solutions.

> ✅ Project-based. Four to twelve weeks. Fixed scope, fixed price. I write the proposal; you approve it; I build the thing.
>
> ❌ Flexible engagement models tailored to your unique business needs.

> ✅ Recent writing
>
> ❌ Latest insights from the Birdcar blog 🚀

---

## Visual foundations

The look is **editorial, monochrome, warm.** Think a printed essay, not a SaaS landing page. The site should feel like it was designed once, carefully, and then left alone — not iterated into a Figma soup.

### Mood

- **Warm paper, not cold white.** Background is an off-white with a faint warm cast (`#F7F3EC`). Pure white reads sterile and corporate; we want lived-in.
- **High-contrast type.** Near-black ink on warm paper. No greige body copy.
- **One accent color, used sparingly.** A rust/clay red (`#B8442B`) for links, the brand mark, and one or two accents per page. It should feel like a stamp, not a paint job.
- **No gradients.** No drop-shadows on cards. No glass. No glow. No neumorphism.
- **No illustrations.** No spot illustrations of the author at a laptop. No abstract blobs. If imagery is needed, it's a real photograph (likely b&w or warm/desaturated) or it isn't there.

### Color

| Role | Token | Hex | Use |
|---|---|---|---|
| Background | `--paper` | `#F7F3EC` | Page background |
| Background, alt | `--paper-2` | `#EFE9DD` | Section breaks, code block bg |
| Ink | `--ink` | `#1A1814` | Body text, headings |
| Ink, soft | `--ink-2` | `#4A453D` | Secondary text |
| Ink, muted | `--ink-3` | `#7A7368` | Metadata, dates, captions |
| Rule | `--rule` | `#D9D1BE` | Hairlines, dividers, borders |
| Accent (clay) | `--clay` | `#B8442B` | Links, brand mark, key accents |
| Accent, hover | `--clay-2` | `#8E2F1B` | Link hover, pressed states |
| Selection | `--selection` | `#F2D8B6` | Text selection background |

Semantic states (used very rarely — this isn't an app):

| Role | Token | Hex |
|---|---|---|
| Success | `--ok` | `#3F6B43` |
| Warning | `--warn` | `#A8741A` |
| Danger | `--err` | `#8E2F1B` (same as clay-2) |

### Typography

A two-typeface system plus mono.

- **Display + body:** **Source Serif 4** — a contemporary literary serif with great mid-size legibility. Wide weight range; we use Regular (400), Semibold (600), and Bold (700).
- **UI / sans (sparingly — nav, metadata, captions):** **Inter Tight** — neutral, dense, gets out of the way. Used at small sizes only.
- **Mono (code, metadata, dates):** **JetBrains Mono** — code blocks, post dates, slugs. The dates being mono is intentional; it says "this is a record."

**Substitution flag:** No font files were provided. All three are Google Fonts and are loaded from there in `colors_and_type.css`. If you have specific licensed faces you want instead (e.g. Söhne, Tiempos, GT America), drop the files into `fonts/` and swap the `@font-face` declarations.

**Type scale** (1.250 / major third, anchored at 18px body):

| Token | Size | Use |
|---|---|---|
| `--t-display` | 56px / 1.05 | Home hero only |
| `--t-h1` | 40px / 1.1 | Page titles |
| `--t-h2` | 28px / 1.2 | Section heads |
| `--t-h3` | 22px / 1.3 | Sub-sections, post titles in lists |
| `--t-body` | 18px / 1.55 | Body copy |
| `--t-small` | 15px / 1.5 | Secondary copy |
| `--t-meta` | 13px / 1.4 | Dates, tags, captions (mono or sans) |

Body line-length is capped at **68ch** (roughly 620px at 18px). Long-form posts at **64ch**.

### Spacing & layout

A 4px base, used as 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 / 96 / 128. The home page is a single-column editorial layout, max-width 720px content / 960px page. No sidebars on the public site. No card grids on the home.

- Section rhythm: 96px between top-level sections on desktop, 64px on mobile.
- Paragraph rhythm: 1em (18px) between paragraphs in body copy.
- Lists: 8px between items.

### Borders, rules, corners

- **Hairlines, not boxes.** Section breaks are 1px horizontal rules in `--rule`, never bordered cards.
- **Corner radius is essentially 0.** Buttons and form fields use `2px`. Code blocks use `4px`. Nothing is pill-shaped. Nothing is `rounded-2xl`.
- **No drop shadows anywhere.** Elevation is communicated by typography and spacing, not z-axis.

### Backgrounds & imagery

- Page background: solid `--paper`. No textures, no patterns, no noise.
- A single optional motif: a **thin double-rule** (two 1px lines, 4px apart, both `--rule`) used to separate the masthead from content and to mark the footer. This is the only "ornament" in the system.
- Photography, if used, is warm-toned or black-and-white. Never cool blue. Never AI-generated.
- No author headshot illustration. If a photo is used on About, it is a real photograph, small (max 240px), and offset to one side — not centered like a corporate bio.

### Animation

- **Default to none.** Pages load and they're there.
- The two allowed motions:
  - Link underlines slide in/out on hover (`transition: text-underline-offset 120ms ease-out`).
  - The mobile nav drawer fades in over 160ms.
- No scroll-triggered fades. No parallax. No hero animations. No marquees.

### Hover & press states

- **Links:** underline thickens and offset reduces; color stays `--clay`. Visited links stay `--clay` (don't fade them).
- **Buttons (primary, ink-filled):** hover lightens to `--ink-2`; press shifts down 1px (no scale).
- **Buttons (secondary, outline):** hover fills background to `--paper-2`; press same 1px shift.
- **Nav items:** hover underlines, no color shift.
- No opacity-based hovers. No scale transforms.

### Transparency, blur, glass

None. The site is opaque. There is no fixed header that blurs the content behind it (in fact, the header is not fixed at all on desktop — it scrolls with the page).

### Cards

The system avoids cards. The blog index is a **vertically stacked list with hairline rules between entries**, not a card grid. The Work page is a stacked list of problem-pattern entries, not cards. If a card is unavoidable, it's `--paper-2` background, no border, no shadow, no radius beyond 2px.

### Layout rules

- Single column on the public site. The masthead is left-aligned, not centered.
- Nav is in the masthead; no sticky nav.
- Footer is plain text, left-aligned, separated by a double-rule.
- Forms are full-width within the content column, labels above fields, no floating labels.

---

## Iconography

**Birdcar uses almost no icons.**

The aesthetic is editorial. Icons in body copy or section headers would undermine the "competent operator who writes well" tone. The places icons *are* allowed:

- **External link arrows** — a simple `↗` (U+2197) appended to outbound links inline. Unicode, not SVG.
- **RSS** — the standard RSS glyph in the footer (Lucide `rss`, stroke 1.5px, `--ink-2`).
- **Email / GitHub / etc. in footer** — Lucide stroke icons, 18px, `--ink-2`, never filled.
- **Form field affordances** — Lucide icons inside form fields where genuinely useful (search icon in a search input, etc.).

**Icon library:** [Phosphor](https://phosphoricons.com) via the official web font. **Regular** weight only — Bold reads too heavy, Light disappears on warm paper, Duotone is off-brand. Always monochrome `--ink-2`. Never colored. Never filled (no `ph-fill`).

```html
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<i class="ph ph-rss"></i>
```

Phosphor was chosen over Lucide for slightly more character in the strokes — the editorial system can carry it without becoming decorative.

**Emoji:** Not used in UI. The only place an emoji can appear is inside the body of a blog post if the post itself is quoting or about emoji.

**Logo / wordmark:** Birdcar uses a **wordmark only**, set in Source Serif 4 Bold, often with an em-dash or a small mono tag (`birdcar.dev`) underneath. There is no bird icon, no car icon, no combined mark. Resist the temptation. See `assets/wordmark.svg`.

---

## Index

Files in this design system:

| File | What's in it |
|---|---|
| `README.md` | This file — context, tone, visual foundations, iconography (kept as historical record; the working contract is the repo's `PRODUCT.md` + `DESIGN.md`) |
| `SKILL.md` | Agent-skill manifest from claude.ai/design |
| `assets/` | Wordmark SVGs (design source; production renders the wordmark in CSS) |
| `preview/` | Per-token preview cards |
| `ui_kits/birdcar-dev/` | JSX UI kit (the design source for the implemented Astro pages under `src/pages/`) |

**Relocated since export.** The original bundle also shipped `colors_and_type.css` (now superseded by `src/styles/global.css`), `WRITING.md` (now at `docs/writing/WRITING.md`), and a `screenshots/` directory (verification only, not committed).

### UI kits

- **birdcar-dev** — the marketing/personal site. Home, About, Work, Writing (blog index), individual post, Contact.

### Caveats / open questions

- **No real assets were provided.** The wordmark is constructed in CSS/SVG; there is no logotype file from the user. If one exists, replace `assets/wordmark.svg`.
- **Fonts are Google Fonts substitutions.** All three faces (Source Serif 4, Inter Tight, JetBrains Mono) are licensed for web use via Google Fonts. If you have a different licensed display face you want to use, drop the files into `fonts/` and edit `colors_and_type.css`.
- **No real blog posts.** The UI kit uses plausible placeholder titles and dates in the established voice. Replace with real content.
