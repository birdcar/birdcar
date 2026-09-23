---
paths:
  - '**'
---

# General

## Commit target
This repository work is a full Laravel rewrite of the former Astro marketing site. Marketing-site work commits directly to `main` (owner decision, 2026-09-14): this is the owner's personal site with no migrations or production data to protect yet. The former `laravel/site-rewrite` merge target no longer applies.

## Local development server
Start the app with `php artisan dev` (or `composer dev`), which runs the FrankenPHP server on the configured `APP_URL` host plus Vite and the other dev processes. Do not use `php artisan serve`. Folio marketing routes are mounted on the `APP_URL` host (`localhost`), so probe `http://localhost:8000`, not `127.0.0.1`. `PostHogService` throws in debug mode when `POSTHOG_PROJECT_TOKEN` is blank; set it or `POSTHOG_DISABLED=true` in `.env` before booting.

## Linear Git history
Keep repository history linear. Before integrating a feature branch, rebase it onto the intended target when the target has advanced, then update the target with `git merge --ff-only`; do not create merge commits unless explicitly requested.

## Greenfield implementation authority
The user confirms the existing application code is disposable boilerplate and need not be preserved. Treat this work as greenfield: boilerplate does not constrain structure, copy, design, or component behavior. Follow the explicit product, visual, and project requirements recorded for the new work.

## Local development server
Use the already-running Herd sites for local development; do not start a competing PHP server with Artisan dev/serve. The owner confirmed birdcar.test for marketing and admin.birdcar.test for Admin. Discover actual scheme/port through Herd and Boost instead of assuming them; set the nonsecret ADMIN_URL separately from APP_URL so Admin authentication preserves its origin. Keep Folio marketing-only and Admin routes explicit. Build changed frontend assets with bun run build or an existing asset worker. This replaces the earlier localhost/FrankenPHP startup guidance.
