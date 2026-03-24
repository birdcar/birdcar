# Implementation Spec: SEO & AEO Optimization - Phase 1

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Phase 1 establishes the complete SEO/AEO infrastructure for birdcar.dev. The approach is:

1. **Expand `Base.astro` props and `<head>`** to accept all meta-relevant data (description, image, url, type, dates, tags, author) and render OG, Twitter Card, canonical, and JSON-LD tags. This centralizes all SEO concerns in one place rather than creating a separate component.
2. **Update `Post.astro`** to forward description, image, dates, and tags to `Base.astro`.
3. **Add `@astrojs/sitemap`** integration to `astro.config.ts`.
4. **Add `robots.txt`** and a default OG image to `public/`.
5. **Extend the blog content schema** with an optional `image` field.

The key architectural decision is keeping SEO logic in `Base.astro` rather than extracting a `<SEO />` component. The site has a single layout — a separate component adds indirection without benefit. If layouts multiply later, extraction is trivial.

## Feedback Strategy

**Inner-loop command**: `bun run build`

**Playground**: Build output — inspect generated HTML in `dist/` to verify meta tags, JSON-LD, and sitemap presence.

**Why this approach**: SEO tags are static HTML output. The build is the fastest way to verify they render correctly. No runtime behavior to test.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `public/robots.txt` | Crawl directives for search engines and AI bots |
| `public/og-default.png` | Default Open Graph image (1200x630) placeholder |

### Modified Files

| File Path | Changes |
|-----------|---------|
| `src/content.config.ts` | Add optional `image` field to blog schema |
| `src/layouts/Base.astro` | Expand Props interface; add OG, Twitter, canonical, JSON-LD to `<head>` |
| `src/layouts/Post.astro` | Forward description, image, dates, tags to Base |
| `src/pages/blog/[slug].astro` | Pass description and image from post data to Post layout |
| `src/pages/index.astro` | Pass structured props to Base for homepage JSON-LD |
| `astro.config.ts` | Add `@astrojs/sitemap` integration |
| `package.json` | Add `@astrojs/sitemap` dependency |

## Implementation Details

### 1. Blog Schema Update

**Overview**: Add optional `image` field to content collection schema.

```typescript
// src/content.config.ts — add to schema
image: z.string().optional(), // relative path or URL to post hero/OG image
```

### 2. robots.txt

**Overview**: Comprehensive robots.txt with AI bot directives for AEO control.

```
# birdcar.dev robots.txt

User-agent: *
Allow: /

# Sitemap
Sitemap: https://birdcar.dev/sitemap-index.xml

# Crawl-delay for polite bots
User-agent: GPTBot
Allow: /

User-agent: Google-Extended
Allow: /

User-agent: ChatGPT-User
Allow: /

User-agent: Claude-Web
Allow: /

User-agent: Applebot-Extended
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: Bytespider
Disallow: /

User-agent: CCBot
Allow: /

User-agent: anthropic-ai
Allow: /

User-agent: Amazonbot
Allow: /

User-agent: FacebookExternalHit
Allow: /

User-agent: Twitterbot
Allow: /
```

**Key decisions**:
- Explicitly allow major AI crawlers (GPTBot, Claude-Web, PerplexityBot, etc.) for AEO visibility
- Block Bytespider (TikTok's aggressive crawler) — high volume, low value
- Allow social media bots for link preview rendering
- Reference sitemap-index.xml (the format `@astrojs/sitemap` generates)

### 3. Base.astro SEO Enhancement

**Pattern to follow**: Current `src/layouts/Base.astro` props pattern

**Overview**: Expand the Props interface and `<head>` to render all SEO/AEO meta tags and JSON-LD.

```typescript
// Expanded Props interface
interface Props {
  title: string;
  description?: string;
  image?: string;
  canonicalURL?: URL | string;
  type?: 'website' | 'article';
  publishedDate?: Date;
  modifiedDate?: Date;
  tags?: string[];
  noindex?: boolean;
}
```

**Implementation steps**:

1. Expand `Props` interface with all SEO fields
2. Compute derived values in the frontmatter script:
   - `canonicalURL` defaults to `Astro.url` (full URL from `site` config)
   - `ogImage` resolves to absolute URL: post image > `/og-default.png`
   - `pageTitle` for OG (without the " — birdcar" suffix for blog posts, keep for others)
3. Add Open Graph meta tags after existing `<meta name="description">`:
   ```html
   <meta property="og:title" content={title} />
   <meta property="og:description" content={description || 'Developer blog by birdcar'} />
   <meta property="og:url" content={canonicalURL} />
   <meta property="og:type" content={type || 'website'} />
   <meta property="og:image" content={ogImage} />
   <meta property="og:locale" content="en_US" />
   <meta property="og:site_name" content="birdcar.dev" />
   ```
4. Add Twitter Card meta tags:
   ```html
   <meta name="twitter:card" content="summary_large_image" />
   <meta name="twitter:title" content={title} />
   <meta name="twitter:description" content={description || 'Developer blog by birdcar'} />
   <meta name="twitter:image" content={ogImage} />
   ```
5. Add canonical URL:
   ```html
   <link rel="canonical" href={canonicalURL} />
   ```
6. Add article-specific OG tags when `type === 'article'`:
   ```html
   {publishedDate && <meta property="article:published_time" content={publishedDate.toISOString()} />}
   {modifiedDate && <meta property="article:modified_time" content={modifiedDate.toISOString()} />}
   {tags?.map(tag => <meta property="article:tag" content={tag} />)}
   ```
7. Add JSON-LD structured data as a `<script type="application/ld+json">` block:
   - **All pages**: WebSite schema (name, url, description)
   - **All pages**: Person schema (author — name, url)
   - **Article pages** (`type === 'article'`): Article schema with headline, datePublished, dateModified, author, image, description, keywords

```typescript
// JSON-LD construction in frontmatter
const jsonLd: Record<string, any>[] = [
  {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: 'birdcar.dev',
    url: 'https://birdcar.dev',
    description: 'Developer blog by birdcar. Writing about software, systems, and the craft of building things.',
  },
  {
    '@context': 'https://schema.org',
    '@type': 'Person',
    name: 'Nick Cannariato',
    url: 'https://birdcar.dev',
    sameAs: [
      'https://github.com/birdcar',
    ],
  },
];

if (type === 'article') {
  jsonLd.push({
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: title,
    description: description,
    image: ogImage,
    datePublished: publishedDate?.toISOString(),
    dateModified: (modifiedDate ?? publishedDate)?.toISOString(),
    author: {
      '@type': 'Person',
      name: 'Nick Cannariato',
      url: 'https://birdcar.dev',
    },
    publisher: {
      '@type': 'Person',
      name: 'Nick Cannariato',
      url: 'https://birdcar.dev',
    },
    keywords: tags?.join(', '),
    mainEntityOfPage: {
      '@type': 'WebPage',
      '@id': canonicalURL,
    },
  });
}
```

8. Render JSON-LD in `<head>`:
   ```html
   {jsonLd.map(schema => (
     <script type="application/ld+json" set:html={JSON.stringify(schema)} />
   ))}
   ```

**Key decisions**:
- Use `set:html` for JSON-LD to avoid Astro HTML-escaping the JSON
- Default description fallback so OG tags always have content even when description prop is omitted
- Person schema uses real name for author attribution
- Article schema includes `mainEntityOfPage` for AEO — tells answer engines this is the canonical article

### 4. Post.astro Update

**Pattern to follow**: Current `src/layouts/Post.astro`

**Overview**: Forward all SEO-relevant data from post frontmatter down to Base.

```typescript
// Updated Props
interface Props {
  title: string;
  description?: string;
  image?: string;
  date: Date;
  updated?: Date;
  metadata: DocumentMetadata;
  tags?: string[];
}
```

**Implementation steps**:
1. Add `description`, `image`, and `updated` to Props interface
2. Pass all SEO props to Base:
   ```astro
   <Base
     title={`${title} — birdcar`}
     description={description}
     image={image}
     type="article"
     publishedDate={date}
     modifiedDate={updated}
     tags={tags}
   >
   ```

### 5. Blog Slug Page Update

**Pattern to follow**: Current `src/pages/blog/[slug].astro`

**Overview**: Pass description and image from post data to Post layout.

**Implementation steps**:
1. Destructure `description` and `image` from `post.data`
2. Forward to `<Post>` component:
   ```astro
   <Post
     title={post.data.title}
     description={post.data.description}
     image={post.data.image}
     date={post.data.date}
     updated={post.data.updated}
     metadata={metadata}
     tags={allTags}
   >
   ```

### 6. Sitemap Integration

**Overview**: Install and configure `@astrojs/sitemap`.

**Implementation steps**:
1. Install: `bun add @astrojs/sitemap`
2. Add to `astro.config.ts`:
   ```typescript
   import sitemap from '@astrojs/sitemap';

   export default defineConfig({
     site: 'https://birdcar.dev',
     output: 'static',
     integrations: [sitemap()],
     // ... rest of config
   });
   ```

### 7. Default OG Image

**Overview**: Create a minimal placeholder OG image. This should be a 1200x630 PNG with the site name/branding.

**Implementation steps**:
1. Create `public/og-default.png` — a simple 1200x630 image with "birdcar.dev" text. For now, generate a minimal SVG-based placeholder or use a solid color with text. The user can replace this with a designed version later.

## Validation Commands

```bash
# Install new dependency
bun add @astrojs/sitemap

# Type checking
bun run check

# Build
bun run build:ci

# Verify meta tags in built output
grep -l 'og:title' dist/index.html
grep -l 'application/ld+json' dist/index.html
grep -l 'og:type.*article' dist/blog/*/index.html | head -3

# Verify sitemap
cat dist/sitemap-index.xml

# Verify robots.txt
cat dist/robots.txt
```

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
