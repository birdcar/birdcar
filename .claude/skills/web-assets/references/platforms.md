# Platform Output Specifications

Per-platform size, format, and file naming requirements for all generated web assets.

## Favicon Set

| File | Size | Format | Platform |
|---|---|---|---|
| `favicon-16x16.png` | 16×16 | PNG | Browser tab (small) |
| `favicon-32x32.png` | 32×32 | PNG | Browser tab (standard) |
| `favicon-96x96.png` | 96×96 | PNG | Desktop shortcut |
| `apple-touch-icon.png` | 180×180 | PNG | iOS home screen |
| `android-chrome-192x192.png` | 192×192 | PNG | Android home screen |
| `android-chrome-512x512.png` | 512×512 | PNG | Android splash screen |
| `favicon.ico` | 16+32+48 | ICO (multi-res) | Legacy browsers, Windows |

All favicon PNGs: RGB or RGBA, no transparency required (brand uses solid background).

ICO file must embed 16×16, 32×32, and 48×48 layers in a single file.

## OG / Social Images

| File | Size | Aspect ratio | Platform |
|---|---|---|---|
| `og-image.png` | 1200×630 | ~1.91:1 | Facebook, LinkedIn, generic OpenGraph |
| `twitter-image.png` | 1200×675 | ~1.78:1 | Twitter/X summary_large_image card |
| `og-square.png` | 1200×1200 | 1:1 | Instagram, Discord, fallback |

Format: PNG, sRGB color space, no alpha channel. Max file size target: 200KB per image.

### Per-Post OG Images

| File | Size | Location |
|---|---|---|
| `public/og/{slug}.png` | 1200×630 | Per post, slug derived from filename |

Slug: lowercase filename without `.md` extension (e.g., `my-post-title.md` → `public/og/my-post-title.png`).

## HTML Meta Tag Snippets

After generation, emit these tags for placement in Astro layout (`src/layouts/BaseLayout.astro`):

### Favicon tags

```html
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
<link rel="icon" href="/favicon.ico" sizes="any" />
<link rel="manifest" href="/manifest.json" />
```

### Site-wide OG tags

```html
<meta property="og:image" content="/og-image.png" />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:image" content="/twitter-image.png" />
```

### Per-post OG tags (dynamic — Astro frontmatter)

```astro
---
// In post layout
const { slug } = Astro.params;
const ogImage = `/og/${slug}.png`;
---
<meta property="og:image" content={ogImage} />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:image" content={ogImage} />
```

## PWA Manifest

File: `public/manifest.json`

Required fields:

| Field | Value |
|---|---|
| `name` | `birdcar.dev` |
| `short_name` | `birdcar` |
| `display` | `standalone` |
| `theme_color` | `#74c7ec` (sapphire) |
| `background_color` | `#1e1e2e` (base) |
| `start_url` | `/` |
| `icons` | References android-chrome-192 and android-chrome-512 |

## File Placement

All generated assets default to `public/` at the project root (Astro static output dir).

| Asset type | Default output dir |
|---|---|
| Favicons | `public/` |
| Site OG images | `public/` |
| Per-post OG images | `public/og/` |
| PWA manifest | `public/` |

Override with `--output-dir` flag on any script.
