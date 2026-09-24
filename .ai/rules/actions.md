---
paths:
  - 'app/Actions/**'
---

# Actions

## Database-only public writing
The owner removed file-serving compatibility (2026-09-24). Public writing pages, RSS, and sitemap must read only immutable published database releases through the article's published pointer; never add a file-mode flag, fallback, or file-backed production reader. Preserve the original archive files only as explicit import/parity inputs. Markdown rendering for archive conversion and local specimens is not a public reader.
