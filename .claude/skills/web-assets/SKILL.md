---
name: web-assets
description: >-
  Generates branded web assets (favicons, OG images, PWA manifest) for
  birdcar.dev using the Catppuccin Mocha design system. Use when the user
  asks to "generate favicons", "create OG images", "make social sharing
  images", "generate a PWA manifest", or "create branded web assets".
  Do NOT use for editing existing blog posts, modifying site layouts, or
  CSS changes.
allowed-tools: AskUserQuestion, Bash, Read
metadata:
  version: 1.0.0
  author: birdcar
  tags: [web, assets, favicons, og-images, pwa, catppuccin]
---

# Web Assets

Generates the complete birdcar.dev branded asset set: favicons, site-wide OG images, per-post OG images, and PWA manifest — all using the Catppuccin Mocha design system.

## Critical Rules

- Run scripts via Bash — do not read script source into context. Scripts print JSON summaries; act on that output.
- Check the JSON `errors` array after every script run. If non-empty, report failures before proceeding. Do not silently continue past script errors.
- All scripts must be run from the project root so relative paths (`src/content/blog/`, `public/`) resolve correctly.
- Do not modify brand hex values. They are intentionally hardcoded in each script to match `references/brand.md`. Changes require updating both locations.
- Use AskUserQuestion for all asset-type selection and input collection — do not guess the user's intent.

## Workflow

### Step 1: Select asset type

Use AskUserQuestion with these options:

```
What would you like to generate?
  1. Favicons — complete favicon set (16px through 512px + ICO)
  2. Site OG images — og-image.png, twitter-image.png, og-square.png
  3. Per-post OG images — batch generate from blog frontmatter
  4. PWA manifest — manifest.json
  5. All — full set (favicons + site OG + per-post OG + manifest)
```

For options 1, 2, and 5, collect required inputs before running scripts (see decision table below).

### Step 2: Collect inputs (per asset type)

| Asset type | Required inputs | Optional inputs |
|---|---|---|
| Favicons | Source mode (svg/text/emoji/image) + source value | `--bg-color`, `--fg-color`, `--output-dir` |
| Site OG | `--title` text | `--subtitle` text, `--output-dir` |
| Per-post OG | None (reads `src/content/blog/*.md`) | `--force`, `--content-dir`, `--output-dir` |
| Manifest | None | `--site-name`, `--short-name`, `--description`, `--output-dir` |

For favicon mode, use AskUserQuestion to ask which source mode they want: SVG file, text character, emoji, or existing image.

### Step 3: Run script(s)

Execute scripts from the project root. See invocation patterns below.

If the script exits non-zero, show the `errors` array from its JSON output and stop. Do not proceed to the next script in an "all" run.

### Step 4: Report results and emit meta tags

Parse the JSON output from each script. Report:

1. List of generated files with their paths
2. Any skipped items (per-post OG) with reasons
3. HTML meta tag snippet to add to Astro layout

For the full meta tag snippet, see `${CLAUDE_SKILL_DIR}/references/platforms.md`.

## Script Invocation Patterns

### Favicons

```bash
# SVG source
python ${CLAUDE_SKILL_DIR}/scripts/generate_favicons.py --svg path/to/icon.svg

# Text character (brand font, brand colors)
python ${CLAUDE_SKILL_DIR}/scripts/generate_favicons.py --text "B"

# Emoji
python ${CLAUDE_SKILL_DIR}/scripts/generate_favicons.py --emoji "🐦"

# Existing raster image
python ${CLAUDE_SKILL_DIR}/scripts/generate_favicons.py --image path/to/icon.png

# Override background color
python ${CLAUDE_SKILL_DIR}/scripts/generate_favicons.py --text "B" --bg-color "#313244"

# Custom output directory
python ${CLAUDE_SKILL_DIR}/scripts/generate_favicons.py --text "B" --output-dir dist/
```

### Site OG images

```bash
# Title only
python ${CLAUDE_SKILL_DIR}/scripts/generate_og.py --title "birdcar.dev"

# Title + subtitle
python ${CLAUDE_SKILL_DIR}/scripts/generate_og.py \
  --title "birdcar.dev" \
  --subtitle "Engineering, products, and systems thinking"

# Custom output directory
python ${CLAUDE_SKILL_DIR}/scripts/generate_og.py \
  --title "birdcar.dev" \
  --subtitle "Engineering, products, and systems thinking" \
  --output-dir dist/
```

### Per-post OG images

```bash
# Generate for all posts without an `image` frontmatter field
python ${CLAUDE_SKILL_DIR}/scripts/generate_post_og.py

# Force regeneration even if image field is set
python ${CLAUDE_SKILL_DIR}/scripts/generate_post_og.py --force

# Custom content or output directories
python ${CLAUDE_SKILL_DIR}/scripts/generate_post_og.py \
  --content-dir src/content/blog \
  --output-dir public/og
```

### PWA manifest

```bash
# Defaults (outputs public/manifest.json)
python ${CLAUDE_SKILL_DIR}/scripts/generate_manifest.py

# Custom output directory
python ${CLAUDE_SKILL_DIR}/scripts/generate_manifest.py --output-dir dist/
```

## Examples

### Example 1: Generate favicons from a text initial

**Input**: "Generate favicons using the letter B"
**Process**: Ask if they want any color overrides. Run `generate_favicons.py --text "B"`. Parse JSON output. Report all 7 generated files.
**Output**: Lists `public/favicon-16x16.png` through `public/android-chrome-512x512.png` and `public/favicon.ico`. Emits the favicon HTML meta tag snippet.

### Example 2: Batch generate OG images for all blog posts

**Input**: "Create OG images for all my blog posts"
**Process**: Run `generate_post_og.py` from project root. Parse JSON for `generated`, `skipped`, and `errors`.
**Output**: Reports how many posts got images, which were skipped (had existing `image` field), and the per-post OG Astro tag pattern to add to the post layout.

### Example 3: Full asset generation run

**Input**: "Generate all web assets for the site"
**Process**: Collect favicon source mode and site title via AskUserQuestion. Run all four scripts in sequence. Stop if any script reports errors.
**Output**: Summary of all generated files grouped by type, plus the complete HTML meta tag block for Astro.

## References

- `${CLAUDE_SKILL_DIR}/references/brand.md` — Catppuccin Mocha palette, font stack, OG layout spec
- `${CLAUDE_SKILL_DIR}/references/platforms.md` — Per-platform sizes, formats, HTML meta tag snippets
