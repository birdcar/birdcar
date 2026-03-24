# SEO & AEO Optimization Contract

**Created**: 2026-03-24
**Confidence Score**: 96/100
**Status**: Approved

## Problem Statement

birdcar.dev is a well-built Astro static blog but has nearly zero SEO/AEO infrastructure. The site is missing Open Graph tags, Twitter cards, canonical URLs, structured data (JSON-LD), a robots.txt, and a sitemap. Blog post pages don't even render `<meta name="description">` because `Post.astro` never forwards the description to `Base.astro`. The RSS feed contains only titles and summaries — no rendered HTML content — and has no XSL stylesheet for human-readable browser viewing.

This means search engines and AI answer engines have minimal structured signals to index against, social shares produce blank previews, and RSS readers show empty entries.

## Goals

1. **Complete meta tag coverage** — every page renders OG, Twitter Card, canonical URL, and description meta tags
2. **Full structured data** — WebSite, Person, and Article JSON-LD schemas present on appropriate pages for AI/answer engine discoverability
3. **Proper crawl guidance** — robots.txt with sitemap reference + @astrojs/sitemap generating sitemap.xml
4. **Rich RSS feed** — full rendered HTML content in feed items with XSL stylesheet for browser viewing
5. **Per-post OG image support** — optional `image` field in blog schema with site-wide fallback default

## Success Criteria

- [ ] Every page has `og:title`, `og:description`, `og:url`, `og:type`, `og:image`, `og:locale` meta tags
- [ ] Every page has `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` meta tags
- [ ] Every page has `<link rel="canonical">` pointing to its full URL
- [ ] Blog posts render `<meta name="description">` from frontmatter
- [ ] Blog posts include Article JSON-LD with author, datePublished, dateModified, tags
- [ ] Site-wide WebSite and Person JSON-LD schemas are present
- [ ] `robots.txt` exists with appropriate directives and sitemap reference
- [ ] `sitemap.xml` is generated on build via @astrojs/sitemap
- [ ] RSS feed contains full rendered HTML in `<content:encoded>` for each post
- [ ] RSS feed has XSL stylesheet for styled browser viewing
- [ ] Blog post schema accepts optional `image` field; pages fall back to site default when absent
- [ ] `astro build` succeeds with all new integrations

## Scope Boundaries

### In Scope

- SEO component in `Base.astro` `<head>` (OG, Twitter, canonical, description)
- JSON-LD structured data (WebSite, Person, Article schemas)
- `robots.txt` in `public/`
- `@astrojs/sitemap` integration
- RSS feed with full rendered content and XSL stylesheet
- Blog post schema update (optional `image` field)
- Default OG image in `public/`
- Passing `description` from `Post.astro` to `Base.astro`

### Out of Scope

- OG image generation (dynamic image creation) — complexity not justified for current post volume
- Analytics integration — separate concern
- Performance optimization (Core Web Vitals) — separate concern
- Content changes to existing blog posts — infrastructure only

### Future Considerations

- Dynamic OG image generation per post using `@vercel/og` or `satori`
- Breadcrumb JSON-LD for navigation hierarchy
- FAQ/HowTo structured data for specific post types
- Analytics and search console integration

## Execution Plan

### Dependency Graph

```
Phase 1: SEO Infrastructure (meta, OG, Twitter, canonical, JSON-LD, sitemap, robots.txt)
  └── Phase 2: RSS Feed Enhancement (content rendering, XSL stylesheet)
```

Phase 2 depends on Phase 1 because the schema change (adding `image` field) and the SEO component patterns established in Phase 1 inform how RSS constructs its item metadata.

### Execution Steps

**Strategy**: Sequential

1. **Phase 1** — SEO Infrastructure _(blocking)_
   ```bash
   /execute-spec docs/ideation/seo-aeo-optimization/spec-phase-1.md
   ```

2. **Phase 2** — RSS Feed Enhancement
   ```bash
   /execute-spec docs/ideation/seo-aeo-optimization/spec-phase-2.md
   ```

---

_This contract was generated from brain dump input. Review and approve before proceeding to specification._
