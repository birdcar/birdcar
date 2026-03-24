# Brand System — birdcar.dev

Catppuccin Mocha design system constants. All scripts and layouts reference these values directly.

## Color Palette

| Token | Hex | Usage |
|---|---|---|
| `base` | `#1e1e2e` | Primary background — OG images, favicon bg |
| `mantle` | `#181825` | Secondary background, elevated surfaces |
| `crust` | `#11111b` | Deepest background, borders |
| `text` | `#cdd6f4` | Primary body text |
| `subtext1` | `#bac2de` | Secondary text, descriptions, captions |
| `surface0` | `#313244` | Subtle cards, code block backgrounds |
| `surface1` | `#45475a` | Borders, dividers |
| `surface2` | `#585b70` | Muted UI elements |
| `sapphire` | `#74c7ec` | Primary accent — strips, highlights, `theme_color` |
| `blue` | `#89b4fa` | Links, secondary accents |
| `lavender` | `#b4befe` | Tertiary accent |
| `mauve` | `#cba6f7` | Special callouts |
| `red` | `#f38ba8` | Errors, warnings |
| `green` | `#a6e3a1` | Success states |
| `peach` | `#fab387` | Warm accent |

## Font Stack

| Role | Family | Weight | Use |
|---|---|---|---|
| Heading | Archivo | 700 | Titles, post names, site name |
| Body | Space Grotesk | 400 | Descriptions, subtitles, meta text |
| Code | JetBrains Mono | 400 | Code snippets, monospace elements |

### Google Fonts Download URLs

Scripts cache fonts to `~/.cache/birdcar-fonts/` on first run.

```
Archivo 700:
https://fonts.gstatic.com/s/archivo/v19/k3kPo8UDI-1M0wlSV9XAw6lQkqWY8Q02mqr1fqODH-GXQdaB.woff2

Space Grotesk 400:
https://fonts.gstatic.com/s/spacegrotesk/v16/V8mQoQDjQSkFtoMM3T6r8E7mF71Q-gowFXNvNA.woff2

JetBrains Mono 400:
https://fonts.gstatic.com/s/jetbrainsmono/v18/tDbY2o-flEEny0FZhsfKu5WU4xD-IQ-PuZJJXxfpAO-Lf1OYBU5.woff2
```

## OG Image Layout Spec

Canvas: 1200x630 (standard), 1200x675 (Twitter), 1200x1200 (square)

```
┌─────────────────────────────────────────────────┐
│ [sapphire accent strip — 8px height]            │
│                                                 │
│  ╔═══════════════════════════════════════════╗  │
│  ║  [left margin: 80px]                      ║  │
│  ║                                           ║  │
│  ║  TITLE                                    ║  │
│  ║  Archivo 700, text (#cdd6f4)              ║  │
│  ║  font-size: 64px, max 2 lines             ║  │
│  ║                                           ║  │
│  ║  Subtitle / description                   ║  │
│  ║  Space Grotesk 400, subtext1 (#bac2de)    ║  │
│  ║  font-size: 28px, max 3 lines             ║  │
│  ║                                           ║  │
│  ║  [bottom: site name — birdcar.dev]        ║  │
│  ║  Space Grotesk 400, surface2 (#585b70)    ║  │
│  ║  font-size: 22px                          ║  │
│  ╚═══════════════════════════════════════════╝  │
└─────────────────────────────────────────────────┘
Background: base (#1e1e2e)
```

### Layout Constants

| Element | Value |
|---|---|
| Accent strip height | 8px |
| Horizontal margin | 80px |
| Vertical padding (top) | 80px |
| Title font size | 64px |
| Subtitle font size | 28px |
| Site label font size | 22px |
| Title / description gap | 32px |
| Max title lines | 2 |
| Max subtitle lines | 3 |

## Color Usage Rules

- Backgrounds: always `base` (#1e1e2e) for image surfaces
- Accent strip: always `sapphire` (#74c7ec) — do not swap
- Primary text: `text` (#cdd6f4) for titles and headings
- Secondary text: `subtext1` (#bac2de) for descriptions and body
- Site watermark: `surface2` (#585b70) — subtle, not competing with content
- Favicon background: `base` (#1e1e2e) — unless `--bg-color` override supplied
- PWA `theme_color`: `sapphire` (#74c7ec)
- PWA `background_color`: `base` (#1e1e2e)
