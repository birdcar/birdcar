#!/usr/bin/env -S uv run --script
# /// script
# requires-python = ">=3.11"
# dependencies = [
#   "pillow>=10.0",
#   "pyyaml>=6.0",
#   "requests>=2.31",
# ]
# ///
"""
Batch generate per-post OG images from blog frontmatter for birdcar.dev.

Reads all .md files in src/content/blog/, extracts title and description
from YAML frontmatter, generates public/og/{slug}.png (1200x630) per post.

Skips posts that already have an `image` field in frontmatter, unless --force.

Prints JSON summary to stdout for Claude to parse.
"""

import argparse
import json
import re
import sys
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
    "https://fonts.gstatic.com/s/archivo/v25/"
    "k3k6o8UDI-1M0wlSV9XAw6lQkqWY8Q82sJaRE-NWIDdgffTT0zRp8A.ttf"
)
SPACE_GROTESK_TTF_URL = (
    "https://fonts.gstatic.com/s/spacegrotesk/v22/"
    "V8mQoQDjQSkFtoMM3T6r8E7mF71Q-gOoraIAEj7oUUsj.ttf"
)

# Layout constants (mirrors references/brand.md OG layout spec)
ACCENT_STRIP_HEIGHT = 8
H_MARGIN = 80
V_PADDING_TOP = 80
TITLE_FONT_SIZE = 64
SUBTITLE_FONT_SIZE = 28
SITE_LABEL_FONT_SIZE = 22
TITLE_SUBTITLE_GAP = 32
SITE_NAME = "birdcar.dev"
OG_WIDTH = 1200
OG_HEIGHT = 630


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


def parse_frontmatter(content: str) -> dict:
    """Extract YAML frontmatter from a markdown file."""
    import yaml

    match = re.match(r"^---\s*\n(.*?)\n---\s*\n", content, re.DOTALL)
    if not match:
        return {}
    try:
        return yaml.safe_load(match.group(1)) or {}
    except yaml.YAMLError:
        return {}


def wrap_text(text: str, font: object, draw: object, max_width: int, max_lines: int) -> list[str]:
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


def render_post_og(
    title: str,
    description: str | None,
    output_path: Path,
    archivo_path: Path,
    space_grotesk_path: Path,
) -> None:
    from PIL import Image, ImageDraw, ImageFont

    img = Image.new("RGB", (OG_WIDTH, OG_HEIGHT), hex_to_rgb(BRAND_BASE))
    draw = ImageDraw.Draw(img)

    # Sapphire accent strip across the top
    draw.rectangle(
        [(0, 0), (OG_WIDTH, ACCENT_STRIP_HEIGHT)],
        fill=hex_to_rgb(BRAND_SAPPHIRE),
    )

    title_font = ImageFont.truetype(str(archivo_path), TITLE_FONT_SIZE)
    subtitle_font = ImageFont.truetype(str(space_grotesk_path), SUBTITLE_FONT_SIZE)
    site_font = ImageFont.truetype(str(space_grotesk_path), SITE_LABEL_FONT_SIZE)

    max_text_width = OG_WIDTH - (H_MARGIN * 2)
    y = ACCENT_STRIP_HEIGHT + V_PADDING_TOP

    # Title — up to 2 lines
    title_lines = wrap_text(title, title_font, draw, max_text_width, max_lines=2)
    for line in title_lines:
        draw.text((H_MARGIN, y), line, fill=hex_to_rgb(BRAND_TEXT), font=title_font)
        bbox = draw.textbbox((H_MARGIN, y), line, font=title_font)
        y += bbox[3] - bbox[1] + 8

    y += TITLE_SUBTITLE_GAP

    # Description — up to 3 lines
    if description:
        desc_lines = wrap_text(
            str(description), subtitle_font, draw, max_text_width, max_lines=3
        )
        for line in desc_lines:
            draw.text(
                (H_MARGIN, y), line, fill=hex_to_rgb(BRAND_SUBTEXT1), font=subtitle_font
            )
            bbox = draw.textbbox((H_MARGIN, y), line, font=subtitle_font)
            y += bbox[3] - bbox[1] + 6

    # Site label near bottom
    site_y = OG_HEIGHT - 60
    draw.text(
        (H_MARGIN, site_y),
        SITE_NAME,
        fill=hex_to_rgb(BRAND_SURFACE2),
        font=site_font,
    )

    output_path.parent.mkdir(parents=True, exist_ok=True)
    img.save(str(output_path), format="PNG")


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Batch generate per-post OG images from blog frontmatter"
    )
    parser.add_argument(
        "--content-dir",
        type=Path,
        default=Path("src/content/blog"),
        help="Blog content directory (default: src/content/blog)",
    )
    parser.add_argument(
        "--output-dir",
        type=Path,
        default=Path("public/og"),
        help="Output directory for OG images (default: public/og/)",
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="Regenerate even if the post already has an image field in frontmatter",
    )
    args = parser.parse_args()

    content_dir = args.content_dir
    if not content_dir.exists():
        print(
            json.dumps({
                "error": f"Content directory not found: {content_dir}",
                "generated": [],
                "skipped": [],
                "errors": [],
            })
        )
        sys.exit(1)

    md_files = list(content_dir.glob("*.md"))
    if not md_files:
        print(
            json.dumps({
                "message": f"No .md files found in {content_dir}",
                "generated": [],
                "skipped": [],
                "errors": [],
            })
        )
        sys.exit(0)

    archivo_path = ensure_font(ARCHIVO_TTF_URL, "Archivo-Bold.ttf")
    space_grotesk_path = ensure_font(SPACE_GROTESK_TTF_URL, "SpaceGrotesk-Regular.ttf")

    generated = []
    skipped = []
    errors = []

    for md_file in sorted(md_files):
        slug = md_file.stem
        output_path = args.output_dir / f"{slug}.png"

        try:
            content = md_file.read_text(encoding="utf-8")
            fm = parse_frontmatter(content)

            title = fm.get("title")
            if not title:
                skipped.append({"slug": slug, "reason": "no title in frontmatter"})
                continue

            # Skip if post already has an explicit image field, unless --force
            if fm.get("image") and not args.force:
                skipped.append({"slug": slug, "reason": "has image field (use --force to override)"})
                continue

            description = fm.get("description") or fm.get("excerpt") or None

            render_post_og(
                title=str(title),
                description=str(description) if description else None,
                output_path=output_path,
                archivo_path=archivo_path,
                space_grotesk_path=space_grotesk_path,
            )
            generated.append({"slug": slug, "file": str(output_path)})

        except Exception as e:
            errors.append({"slug": slug, "file": str(output_path), "error": str(e)})

    summary = {
        "generated": generated,
        "skipped": skipped,
        "errors": errors,
        "content_dir": str(content_dir),
        "output_dir": str(args.output_dir),
        "total_posts": len(md_files),
    }
    print(json.dumps(summary, indent=2))

    if errors:
        sys.exit(1)


if __name__ == "__main__":
    main()
