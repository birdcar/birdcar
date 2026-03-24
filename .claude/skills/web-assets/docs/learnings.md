# web-assets — Learnings

Accumulated observations from skill usage. Updated manually or by retrospective review.

## Edge Cases to Watch

- Font download failures: `urllib.request` has no retry logic. If Google Fonts or GitHub raw URLs become unavailable, scripts will fail at the font step. Consider adding retry/fallback logic if this becomes a recurring issue.
- ICO generation: The manual ICO builder in `generate_favicons.py` uses PNG layers (modern ICO format). Some very old tools expect BMP-encoded ICO layers. Has not been an issue in practice.
- Emoji rendering: System emoji font path differs between macOS (`/System/Library/Fonts/Apple Color Emoji.ttc`) and Linux (`/usr/share/fonts/truetype/noto/NotoColorEmoji.ttf`). Script tries both and falls back to PIL default. Verify emoji output on Linux if deploying there.
- Per-post OG: Posts with titles longer than ~50 characters will get text wrapped to 2 lines. This is intentional per the layout spec. Very long titles are truncated at word boundaries at line 2.
- Script must be run from project root: `src/content/blog/` and `public/` are relative to CWD. Absolute paths via `--content-dir` / `--output-dir` override this requirement.
