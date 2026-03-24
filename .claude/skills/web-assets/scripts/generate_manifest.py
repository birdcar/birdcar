#!/usr/bin/env -S uv run --script
# /// script
# requires-python = ">=3.11"
# dependencies = []
# ///
"""
Generate PWA manifest.json for birdcar.dev.

References favicon paths produced by generate_favicons.py.
Outputs public/manifest.json by default.

Prints JSON summary to stdout for Claude to parse.
"""

import argparse
import json
import sys
from pathlib import Path

# --- Brand constants (mirrors references/brand.md) ---
BRAND_BASE = "#1e1e2e"
BRAND_SAPPHIRE = "#74c7ec"

SITE_NAME = "birdcar.dev"
SITE_SHORT_NAME = "birdcar"
SITE_START_URL = "/"
SITE_DISPLAY = "standalone"
SITE_DESCRIPTION = "Nick's writing on engineering, products, and systems thinking."


def build_manifest() -> dict:
    return {
        "name": SITE_NAME,
        "short_name": SITE_SHORT_NAME,
        "description": SITE_DESCRIPTION,
        "start_url": SITE_START_URL,
        "display": SITE_DISPLAY,
        "theme_color": BRAND_SAPPHIRE,
        "background_color": BRAND_BASE,
        "icons": [
            {
                "src": "/android-chrome-192x192.png",
                "sizes": "192x192",
                "type": "image/png",
                "purpose": "any maskable",
            },
            {
                "src": "/android-chrome-512x512.png",
                "sizes": "512x512",
                "type": "image/png",
                "purpose": "any maskable",
            },
        ],
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Generate PWA manifest.json for birdcar.dev")
    parser.add_argument(
        "--output-dir",
        type=Path,
        default=Path("public"),
        help="Output directory (default: public/)",
    )
    parser.add_argument(
        "--site-name",
        default=SITE_NAME,
        help=f"Site name (default: {SITE_NAME})",
    )
    parser.add_argument(
        "--short-name",
        default=SITE_SHORT_NAME,
        help=f"Short name (default: {SITE_SHORT_NAME})",
    )
    parser.add_argument(
        "--description",
        default=SITE_DESCRIPTION,
        help="Site description for manifest",
    )
    args = parser.parse_args()

    output_dir = args.output_dir
    output_dir.mkdir(parents=True, exist_ok=True)

    manifest = build_manifest()
    manifest["name"] = args.site_name
    manifest["short_name"] = args.short_name
    manifest["description"] = args.description

    dest = output_dir / "manifest.json"
    try:
        dest.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
        summary = {
            "generated": [str(dest)],
            "errors": [],
            "output_dir": str(output_dir),
            "theme_color": BRAND_SAPPHIRE,
            "background_color": BRAND_BASE,
        }
        print(json.dumps(summary, indent=2))
    except Exception as e:
        summary = {
            "generated": [],
            "errors": [{"file": str(dest), "error": str(e)}],
            "output_dir": str(output_dir),
        }
        print(json.dumps(summary, indent=2))
        sys.exit(1)


if __name__ == "__main__":
    main()
