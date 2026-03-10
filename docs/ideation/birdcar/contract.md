# Birdcar Developer Blog Contract

**Created**: 2026-03-10
**Confidence Score**: 96/100
**Status**: Approved

## Problem Statement

Writing and publishing a developer blog on GitHub comes with two persistent friction points. First, using a custom markdown flavor (BFM) for authoring means most static site generators won't render content correctly out of the box — you need a build pipeline that integrates the `@birdcar/markdown` remark plugin. Second, managing images in a git-hosted blog is painful: binary assets bloat the repository, and there's no clean workflow for referencing images locally during writing while serving them from a CDN in production.

The result is that blog posts either don't get written (too much friction) or get published with compromises (standard markdown, no images, or a bloated repo).

## Goals

1. **Publish BFM-authored posts as a static site** — write posts in Birdcar Flavored Markdown, build with Astro + `@birdcar/markdown`, deploy to Cloudflare Pages
2. **Automate image management** — reference images locally during authoring (`./images/post-slug/hero.png`), sync to S3-compatible storage and rewrite URLs at build time
3. **Ship a complete blog with standard pages** — home, blog index (paginated), individual posts, about page, tag index, tag detail pages, and RSS feed
4. **Support drafts** — `draft: true` in frontmatter hides posts from production builds but shows them in dev
5. **Enable future extensibility** — architecture supports adding interactive widgets (Astro islands), scrobbling integrations, and art-directed posts without structural rewrites

## Success Criteria

- [ ] Posts written in BFM (frontmatter, directives, mentions, hashtags, footnotes) render correctly as HTML
- [ ] `astro dev` serves the site locally with hot reload; draft posts are visible
- [ ] `astro build` produces a static site deployable to Cloudflare Pages; drafts are excluded
- [ ] Images referenced as `./images/{slug}/{file}` resolve locally in dev and rewrite to S3 CDN URLs in production builds
- [ ] A build-time step syncs local `images/` directory to configured S3-compatible bucket
- [ ] Home page, blog index (paginated), post pages, about page, tag index, tag pages, and RSS feed all render correctly
- [ ] Tag pages aggregate posts by tag (combining frontmatter tags and inline hashtags via `extractMetadata`)
- [ ] RSS feed validates against RSS 2.0 or Atom spec
- [ ] Site deploys successfully to Cloudflare Pages via `wrangler pages deploy` or GitHub integration

## Scope Boundaries

### In Scope

- Astro project scaffolding with TypeScript, Bun
- Remark pipeline integration: `remark-parse` → `remark-gfm` → `remarkBfm` → `remark-rehype` → `rehype-stringify`
- Content collection for blog posts with BFM frontmatter schema validation
- All pages: home, blog index (paginated), post detail, about, tag index, tag detail, RSS/Atom feed
- Image management: local `images/` directory, S3 sync script, build-time URL rewriting
- Draft support via `draft: true` frontmatter
- `extractMetadata` integration for reading time, word count, tags, and task aggregation
- Cloudflare Pages deployment configuration
- Minimal functional styling (unstyled or basic utility classes — enough to be usable, not designed)

### Out of Scope

- Visual design, branding, custom typography — explicitly deferred by user
- Interactive widgets / Astro islands — future extensibility, not implemented now
- Scrobbling integrations (Last.fm, reading habits) — future feature
- Art-directed post layouts — future feature
- Comments system — not requested
- Analytics — not requested
- Search — not requested
- CMS or admin interface — posts are authored as files in the repo

### Future Considerations

- Interactive Astro islands for embedded demos, code playgrounds
- Scrobbling widgets (Last.fm, Literal, etc.)
- Art-directed post templates with per-post CSS/layout overrides
- Full-text search (Pagefind or similar)
- Comments (giscus or similar)
- `@include` and `@query` directive resolution at build time

## Execution Plan

### Dependency Graph

```
Phase 1: Astro + BFM Pipeline ─┐
                                 ├── Phase 2: Pages & Content
                                 └── Phase 3: Image Management
```

### Execution Steps

**Strategy**: Hybrid (Phase 1 sequential, then Phases 2 & 3 parallel)

1. **Phase 1 — Astro + BFM Pipeline** _(blocking, must complete first)_
   ```
   /execute-spec docs/ideation/birdcar/spec-phase-1.md
   ```

2. **Phases 2 & 3 — parallel after Phase 1**

   Start one Claude Code session, enter delegate mode (Shift+Tab), paste the agent team prompt below.

   Or run sequentially:
   ```
   /execute-spec docs/ideation/birdcar/spec-phase-2.md
   /execute-spec docs/ideation/birdcar/spec-phase-3.md
   ```

### Agent Team Prompt

```
Phase 1 (Astro + BFM Pipeline) is complete. Create an agent team to
implement 2 remaining phases in parallel. Each phase is independent.

Spawn 2 teammates with plan approval required. Each teammate should:
1. Read their assigned spec file
2. Explore the codebase for relevant patterns before planning
3. Plan their implementation approach and wait for approval
4. Implement following spec and codebase patterns
5. Run validation commands from their spec after implementation

Coordinate on shared files (astro.config.ts, package.json, .gitignore) to
avoid merge conflicts — only one teammate should modify a shared file at a time.

Teammates:

1. "Pages & Content" — docs/ideation/birdcar/spec-phase-2.md
   Home page, paginated blog index, about page, tag index, tag detail pages,
   RSS feed, post query helpers, shared components.

2. "Image Management" — docs/ideation/birdcar/spec-phase-3.md
   S3 sync script, rehype image CDN plugin, dev mode image serving,
   environment configuration.
```
