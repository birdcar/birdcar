#!/usr/bin/env -S uv run --script
# /// script
# requires-python = ">=3.11"
# dependencies = [
#   "pillow>=10.0",
#   "cairosvg>=2.7",
#   "requests>=2.31",
# ]
# ///
"""
Generate complete favicon set for birdcar.dev.

Modes:
  --svg <path>    Rasterize an SVG file to all favicon sizes
  --text <char>   Render a single character/letter (brand font)
  --emoji <char>  Render an emoji (system font fallback)
  --image <path>  Resize an existing raster image

Output: favicon-16x16.png, favicon-32x32.png, favicon-96x96.png,
        apple-touch-icon.png (180x180), android-chrome-192x192.png,
        android-chrome-512x512.png, favicon.ico (multi-res: 16+32+48)

Prints JSON summary to stdout for Claude to parse.
"""

import argparse
import io
import json
import os
import struct
import sys
import urllib.request
from pathlib import Path

# --- Brand constants (mirrors references/brand.md) ---
BRAND_BASE = "#1e1e2e"
BRAND_TEXT = "#cdd6f4"
BRAND_SAPPHIRE = "#74c7ec"
FONT_CACHE_DIR = Path.home() / ".cache" / "birdcar-fonts"
ARCHIVO_URL = (
    "https://fonts.gstatic.com/s/archivo/v19/"
    "k3kPo8UDI-1M0wlSV9XAw6lQkqWY8Q02mqr1fqODH-GXQdaB.woff2"
)
# We need a TTF/OTF for Pillow; using a fallback TTF bundle approach below.
ARCHIVO_TTF_URL = (
    "https://github.com/Omnibus-Type/Archivo/raw/master/fonts/ttf/"
    "Archivo-Bold.ttf"
)

SIZES = [
    (16, "favicon-16x16.png"),
    (32, "favicon-32x32.png"),
    (48, None),  # ICO layer only
    (96, "favicon-96x96.png"),
    (180, "apple-touch-icon.png"),
    (192, "android-chrome-192x192.png"),
    (512, "android-chrome-512x512.png"),
]
ICO_SIZES = [16, 32, 48]


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


def build_ico(images_by_size: dict[int, "Image.Image"]) -> bytes:
    """Build a minimal ICO file from a dict of {size: PIL Image}."""
    from PIL import Image  # local import after dep install

    entries = []
    image_data_list = []
    for sz in ICO_SIZES:
        img = images_by_size[sz].convert("RGBA")
        buf = io.BytesIO()
        img.save(buf, format="PNG")
        data = buf.getvalue()
        entries.append((sz, data))
        image_data_list.append(data)

    # ICO header: RESERVED(2) + TYPE(2) + COUNT(2)
    header = struct.pack("<HHH", 0, 1, len(entries))
    # Each directory entry: width(1) height(1) colorcount(1) reserved(1)
    #   planes(2) bitcount(2) bytesinres(4) imageoffset(4)
    dir_size = 16 * len(entries)
    offset = 6 + dir_size  # header + all directory entries
    directory = b""
    for sz, data in entries:
        w = sz if sz < 256 else 0
        h = sz if sz < 256 else 0
        directory += struct.pack("<BBBBHHII", w, h, 0, 0, 1, 32, len(data), offset)
        offset += len(data)

    return header + directory + b"".join(image_data_list)


def rasterize_svg(svg_path: Path, size: int) -> "Image.Image":
    import cairosvg
    from PIL import Image

    png_bytes = cairosvg.svg2png(
        url=str(svg_path), output_width=size, output_height=size
    )
    return Image.open(io.BytesIO(png_bytes)).convert("RGBA")


def render_text(char: str, size: int, bg_color: str, fg_color: str) -> "Image.Image":
    from PIL import Image, ImageDraw, ImageFont

    font_path = ensure_font(ARCHIVO_TTF_URL, "Archivo-Bold.ttf")
    img = Image.new("RGB", (size, size), hex_to_rgb(bg_color))
    draw = ImageDraw.Draw(img)
    # Scale font to ~65% of canvas size
    font_size = int(size * 0.65)
    try:
        font = ImageFont.truetype(str(font_path), font_size)
    except Exception:
        font = ImageFont.load_default()
    bbox = draw.textbbox((0, 0), char, font=font)
    text_w = bbox[2] - bbox[0]
    text_h = bbox[3] - bbox[1]
    x = (size - text_w) // 2 - bbox[0]
    y = (size - text_h) // 2 - bbox[1]
    draw.text((x, y), char, fill=hex_to_rgb(fg_color), font=font)
    return img.convert("RGBA")


def render_emoji(char: str, size: int, bg_color: str) -> "Image.Image":
    from PIL import Image, ImageDraw, ImageFont

    img = Image.new("RGB", (size, size), hex_to_rgb(bg_color))
    draw = ImageDraw.Draw(img)
    font_size = int(size * 0.60)
    # Try system emoji font locations
    emoji_font_paths = [
        "/System/Library/Fonts/Apple Color Emoji.ttc",
        "/usr/share/fonts/truetype/noto/NotoColorEmoji.ttf",
    ]
    font = None
    for fp in emoji_font_paths:
        if Path(fp).exists():
            try:
                font = ImageFont.truetype(fp, font_size)
                break
            except Exception:
                continue
    if font is None:
        font = ImageFont.load_default()
    bbox = draw.textbbox((0, 0), char, font=font)
    text_w = bbox[2] - bbox[0]
    text_h = bbox[3] - bbox[1]
    x = (size - text_w) // 2 - bbox[0]
    y = (size - text_h) // 2 - bbox[1]
    draw.text((x, y), char, font=font)
    return img.convert("RGBA")


def resize_image(src_path: Path, size: int) -> "Image.Image":
    from PIL import Image

    img = Image.open(src_path).convert("RGBA")
    return img.resize((size, size), Image.LANCZOS)


def main() -> None:
    parser = argparse.ArgumentParser(description="Generate favicon set for birdcar.dev")
    mode_group = parser.add_mutually_exclusive_group(required=True)
    mode_group.add_argument("--svg", type=Path, help="Path to source SVG file")
    mode_group.add_argument("--text", type=str, help="Single character to render")
    mode_group.add_argument("--emoji", type=str, help="Emoji character to render")
    mode_group.add_argument("--image", type=Path, help="Path to source raster image")
    parser.add_argument(
        "--bg-color",
        default=BRAND_BASE,
        help=f"Background hex color (default: {BRAND_BASE})",
    )
    parser.add_argument(
        "--fg-color",
        default=BRAND_TEXT,
        help=f"Foreground/text hex color (default: {BRAND_TEXT})",
    )
    parser.add_argument(
        "--output-dir",
        type=Path,
        default=Path("public"),
        help="Output directory (default: public/)",
    )
    args = parser.parse_args()

    output_dir = args.output_dir
    output_dir.mkdir(parents=True, exist_ok=True)

    generated = []
    errors = []
    images_by_size: dict[int, object] = {}

    all_needed_sizes = {sz for sz, _ in SIZES}

    for size, filename in SIZES:
        try:
            if args.svg:
                img = rasterize_svg(args.svg, size)
            elif args.text:
                img = render_text(args.text, size, args.bg_color, args.fg_color)
            elif args.emoji:
                img = render_emoji(args.emoji, size, args.bg_color)
            else:
                img = resize_image(args.image, size)

            images_by_size[size] = img

            if filename is not None:
                dest = output_dir / filename
                img.save(str(dest), format="PNG")
                generated.append(str(dest))

        except Exception as e:
            errors.append({"size": size, "file": filename, "error": str(e)})

    # Generate favicon.ico (multi-res: 16 + 32 + 48)
    if all(s in images_by_size for s in ICO_SIZES):
        try:
            ico_bytes = build_ico(images_by_size)  # type: ignore[arg-type]
            ico_path = output_dir / "favicon.ico"
            ico_path.write_bytes(ico_bytes)
            generated.append(str(ico_path))
        except Exception as e:
            errors.append({"size": "ico", "file": "favicon.ico", "error": str(e)})

    summary = {
        "generated": generated,
        "errors": errors,
        "output_dir": str(output_dir),
        "mode": (
            "svg"
            if args.svg
            else "text"
            if args.text
            else "emoji"
            if args.emoji
            else "image"
        ),
    }
    print(json.dumps(summary, indent=2))

    if errors:
        sys.exit(1)


if __name__ == "__main__":
    main()
