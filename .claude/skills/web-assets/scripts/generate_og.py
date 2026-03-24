#!/usr/bin/env -S uv run --script
# /// script
# requires-python = ">=3.11"
# dependencies = [
#   "pillow>=10.0",
#   "requests>=2.31",
# ]
# ///
"""
Generate site-wide OG images for birdcar.dev.

Outputs:
  og-image.png       1200x630  (Facebook, LinkedIn, generic OpenGraph)
  twitter-image.png  1200x675  (Twitter/X summary_large_image)
  og-square.png      1200x1200 (Instagram, Discord, fallback)

Prints JSON summary to stdout for Claude to parse.
"""

import argparse
import json
import sys
import textwrap
import urllib.request
from pathlib import Path

# --- Brand constants (mirrors references/brand.md) ---
BRAND_BASE = "#1e1e2e"
BRAND_TEXT = "#cdd6f4"
BRAND_SUBTEXT1 = "#bac2de"
BRAND_SURFACE2 = "#585b70"
BRAND_SAPPHIRE = "#74c7ec"

FONT_CACHE_DIR = Path.home() / ".cache" / "birdcar-fonts"
ARCHIVO_TTF_URL = (
    "https://github.com/Omnibus-Type/Archivo/raw/master/fonts/ttf/Archivo-Bold.ttf"
)
SPACE_GROTESK_TTF_URL = (
    "https://github.com/floriankarsten/space-grotesk/raw/master/fonts/ttf/"
    "SpaceGrotesk-Regular.ttf"
)

# Layout constants (mirrors references/brand.md OG layout spec)
ACCENT_STRIP_HEIGHT = 8   # px — sapphire strip at top
H_MARGIN = 80             # px — left/right margins
V_PADDING_TOP = 80        # px — space below accent strip to title
TITLE_FONT_SIZE = 64      # px
SUBTITLE_FONT_SIZE = 28   # px
SITE_LABEL_FONT_SIZE = 22 # px
TITLE_SUBTITLE_GAP = 32   # px — vertical space between title and subtitle
SITE_NAME = "birdcar.dev"

OUTPUTS = [
    ("og-image.png", 1200, 630),
    ("twitter-image.png", 1200, 675),
    ("og-square.png", 1200, 1200),
]


def hex_to_rgb(hex_color: str) -> tuple[int, int, int]:
    h = hex_color.lstrip("#")
    return int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16)


def ensure_font(url: str, filename: str) -> Path:
    FONT_CACHE_DIR.mkdir(parents=True, exist_ok=True)
    dest = FONT_CACHE_DIR / filename
    if not dest.exists():
        print(f"[info] Downloading font to {dest}", file=sys.stderr)
        urllib.request.urlretrieve(url, dest)
    return dest


def wrap_text(text: str, font: object, draw: object, max_width: int, max_lines: int) -> list[str]:
    """Wrap text to fit max_width, returning at most max_lines lines."""
    from PIL import ImageFont

    words = text.split()
    lines: list[str] = []
    current = ""
    for word in words:
        candidate = f"{current} {word}".strip()
        bbox = draw.textbbox((0, 0), candidate, font=font)
        if bbox[2] - bbox[0] > max_width and current:
            lines.append(current)
            current = word
            if len(lines) >= max_lines:
                break
        else:
            current = candidate
    if current and len(lines) < max_lines:
        lines.append(current)
    return lines


def render_og(
    title: str,
    subtitle: str | None,
    width: int,
    height: int,
    output_path: Path,
    archivo_path: Path,
    space_grotesk_path: Path,
) -> None:
    from PIL import Image, ImageDraw, ImageFont

    img = Image.new("RGB", (width, height), hex_to_rgb(BRAND_BASE))
    draw = ImageDraw.Draw(img)

    # Sapphire accent strip across the top
    draw.rectangle([(0, 0), (width, ACCENT_STRIP_HEIGHT)], fill=hex_to_rgb(BRAND_SAPPHIRE))

    title_font = ImageFont.truetype(str(archivo_path), TITLE_FONT_SIZE)
    subtitle_font = ImageFont.truetype(str(space_grotesk_path), SUBTITLE_FONT_SIZE)
    site_font = ImageFont.truetype(str(space_grotesk_path), SITE_LABEL_FONT_SIZE)

    max_text_width = width - (H_MARGIN * 2)
    y = ACCENT_STRIP_HEIGHT + V_PADDING_TOP

    # Title — up to 2 lines
    title_lines = wrap_text(title, title_font, draw, max_text_width, max_lines=2)
    for line in title_lines:
        draw.text((H_MARGIN, y), line, fill=hex_to_rgb(BRAND_TEXT), font=title_font)
        bbox = draw.textbbox((H_MARGIN, y), line, font=title_font)
        y += bbox[3] - bbox[1] + 8  # 8px line gap

    y += TITLE_SUBTITLE_GAP

    # Subtitle — up to 3 lines
    if subtitle:
        sub_lines = wrap_text(subtitle, subtitle_font, draw, max_text_width, max_lines=3)
        for line in sub_lines:
            draw.text((H_MARGIN, y), line, fill=hex_to_rgb(BRAND_SUBTEXT1), font=subtitle_font)
            bbox = draw.textbbox((H_MARGIN, y), line, font=subtitle_font)
            y += bbox[3] - bbox[1] + 6  # 6px line gap

    # Site label anchored near bottom
    site_bbox = draw.textbbox((0, 0), SITE_NAME, font=site_font)
    site_y = height - 60
    draw.text((H_MARGIN, site_y), SITE_NAME, fill=hex_to_rgb(BRAND_SURFACE2), font=site_font)

    img.save(str(output_path), format="PNG")


def main() -> None:
    parser = argparse.ArgumentParser(description="Generate site-wide OG images for birdcar.dev")
    parser.add_argument("--title", required=True, help="Main title text")
    parser.add_argument("--subtitle", default=None, help="Optional subtitle / tagline")
    parser.add_argument(
        "--output-dir",
        type=Path,
        default=Path("public"),
        help="Output directory (default: public/)",
    )
    args = parser.parse_args()

    output_dir = args.output_dir
    output_dir.mkdir(parents=True, exist_ok=True)

    archivo_path = ensure_font(ARCHIVO_TTF_URL, "Archivo-Bold.ttf")
    space_grotesk_path = ensure_font(SPACE_GROTESK_TTF_URL, "SpaceGrotesk-Regular.ttf")

    generated = []
    errors = []

    for filename, width, height in OUTPUTS:
        dest = output_dir / filename
        try:
            render_og(
                title=args.title,
                subtitle=args.subtitle,
                width=width,
                height=height,
                output_path=dest,
                archivo_path=archivo_path,
                space_grotesk_path=space_grotesk_path,
            )
            generated.append(str(dest))
        except Exception as e:
            errors.append({"file": filename, "error": str(e)})

    summary = {
        "generated": generated,
        "errors": errors,
        "output_dir": str(output_dir),
        "title": args.title,
        "subtitle": args.subtitle,
    }
    print(json.dumps(summary, indent=2))

    if errors:
        sys.exit(1)


if __name__ == "__main__":
    main()
