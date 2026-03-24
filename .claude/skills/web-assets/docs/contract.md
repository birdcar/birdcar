# web-assets — Contract

## Problem Statement

birdcar.dev has a plain dark rectangle as its OG image and a basic SVG favicon. Generating branded web assets (favicons at multiple sizes, OG images with site typography, per-post OG images) requires manual image editing or ad-hoc scripting each time. The site's Catppuccin Mocha design system, Archivo/Space Grotesk/JetBrains Mono font stack, and specific color palette should be consistently applied to all generated assets without re-specifying brand details each time.

## Goals

1. Generate a complete favicon set (16/32/96/180/192/512 + ICO) from SVG, text, emoji, or image input — all using brand colors
2. Generate branded site-wide OG images (1200x630, 1200x675, 1200x1200) with correct typography
3. Generate per-post OG images from blog frontmatter (title + description on branded background)
4. Generate a PWA `manifest.json` with correct icon references and brand colors
5. Output Astro-ready HTML meta tag snippets for immediate integration

## Success Criteria

- [ ] Favicon set generates all 7 sizes + ICO from each input type (SVG, text, emoji, image)
- [ ] OG images use Archivo for headings, Space Grotesk for body, Catppuccin Mocha palette
- [ ] Per-post OG generation reads frontmatter from `src/content/blog/*.md` and produces images in `public/og/`
- [ ] PWA manifest references generated icons with correct paths and theme colors
- [ ] All scripts are self-contained uv Python scripts with embedded dependencies
- [ ] Generated HTML snippets are valid Astro component syntax

## Scope Boundaries

### In Scope

- Favicon generation (SVG rasterization, text-based, emoji-based, image-based)
- Site-wide OG image generation (text/logo variants)
- Per-post OG image generation (batch from frontmatter)
- PWA manifest.json generation
- HTML meta tag snippet output
- Catppuccin Mocha brand system baked into scripts

### Out of Scope

- Dynamic OG generation at build time (Astro integration) — separate concern, would require `@vercel/og` or `satori`
- Light theme / Catppuccin Latte variants — dark mode only for now
- Animated favicons or SVG favicons beyond the existing one
- Social media platform API integration (uploading images, etc.)

### Future Considerations

- Astro integration component that auto-generates OG images during build
- Support for Catppuccin Latte (light) theme variant
- WCAG contrast validation on generated images

## Design Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Script runtime | uv Python scripts with embedded deps | Pillow is the most mature image library; uv scripts are self-contained with no global install; matches user's Python tooling preference |
| Brand system | Hardcoded in reference doc, imported by scripts | Catppuccin palette is stable; avoids config overhead; single source of truth in `references/brand.md` |
| Font loading | Download Google Fonts at script runtime if missing | Avoids committing font files; fonts cached after first run |
| Output paths | `public/` for web assets, stdout for HTML snippets | Matches Astro static asset conventions |
| Per-post naming | `public/og/{post-slug}.png` | Maps 1:1 to blog post URLs; easy to reference in frontmatter |
