---
paths:
  - '**'
---

# General

## Commit target
This repository is a full Laravel rewrite of the former Astro marketing site. Marketing-site work commits directly to `main` (owner decision, 2026-09-14): this is the owner's personal site with no migrations or production data to protect yet.

## Local development server
The owner develops on macOS with Herd and on Linux with lerd (`.lerd.yaml`); use whichever manager serves the checkout and never start a competing PHP server with `php artisan serve` or `php artisan dev`. Marketing is birdcar.test and Admin is admin.birdcar.test; discover the actual scheme/port through the manager and Boost's `get-absolute-url` instead of assuming them, and set the nonsecret BIRDCAR_ADMIN_URL separately from APP_URL so Admin authentication preserves its origin. Keep Folio marketing-only and Admin routes explicit. Build changed frontend assets with bun run build or an existing asset worker. `PostHogService` throws in debug mode when `POSTHOG_PROJECT_TOKEN` is blank; set it or `POSTHOG_DISABLED=true` in `.env` before booting.

## Linear Git history
Keep repository history linear. Before integrating a feature branch, rebase it onto the intended target when the target has advanced, then update the target with `git merge --ff-only`; do not create merge commits unless explicitly requested.
