---
paths:
  - '**'
---

# General

## Rewrite branch target
This repository work is a full Laravel rewrite of the former Astro marketing site. Merge rewrite feature and ideation branches into `laravel/site-rewrite`, never directly into `main`.

## Linear Git history
Keep repository history linear. Before integrating a feature branch, rebase it onto the intended target when the target has advanced, then update the target with `git merge --ff-only`; do not create merge commits unless explicitly requested.
