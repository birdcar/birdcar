---
name: birdcar-design
description: Use this skill to generate well-branded interfaces and assets for Birdcar (birdcar.dev — Nick Cannariato's personal site and Fort Worth-based consulting practice), either for production or throwaway prototypes/mocks/etc. Contains essential design guidelines, colors, type, fonts, assets, and UI kit components for prototyping.
user-invocable: true
---

Read the README.md file within this skill, and explore the other available files.

If creating visual artifacts (slides, mocks, throwaway prototypes, etc), copy assets out and create static HTML files for the user to view. If working on production code, you can copy assets and read the rules here to become an expert in designing with this brand.

If the user invokes this skill without any other guidance, ask them what they want to build or design, ask some questions, and act as an expert designer who outputs HTML artifacts _or_ production code, depending on the need.

## Quick orientation

- **Brand in one line:** "Competent operator who writes well." Editorial monochrome on warm paper, one clay accent. Not corporate, not startup-bro, not developer-cutesy.
- **Tokens:** `colors_and_type.css` — paper/ink neutrals, `--clay` accent, Source Serif 4 + Inter Tight + JetBrains Mono.
- **Wordmark:** `assets/wordmark.svg` (inline) and `assets/wordmark-stacked.svg`. **Wordmark only — no icon mark, ever.**
- **Components:** `ui_kits/birdcar-dev/` — Masthead, Footer, Button, PostListItem, plus full pages (Home, About, Work, Writing, Post, Contact). Load `colors_and_type.css` + `kit.css`, mount the JSX.

## Hard rules

- Sentence case, first person ("I"), second person to reader ("you"). Never "we."
- No emoji in UI. No hype words (leverage, unlock, transform, seamless, etc.).
- No gradients, no drop shadows, no glassmorphism, no scroll animations.
- Corner radius is essentially 0 (2px on buttons/fields, 4px on code blocks). No pills.
- One accent color (`--clay #B8442B`). Used like a stamp, not paint.
- Icons: Lucide stroke-1.5px, monochrome `--ink-2`. Used very sparingly. Never inline in body copy.
- The blog index is a hairline-ruled list, not a card grid. Resist cards.
