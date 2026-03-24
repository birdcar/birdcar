# Implementation Spec: SEO & AEO Optimization - Phase 2

**Contract**: ./contract.md
**Estimated Effort**: S

## Technical Approach

Phase 2 enhances the RSS feed in two ways: (1) include full rendered HTML content in each feed item so RSS readers display complete posts, and (2) add an XSL stylesheet so the raw XML renders as a styled, human-readable page in browsers.

`@astrojs/rss` supports a `content` field per item that maps to `<content:encoded>`. To populate it, we need to render each post's markdown to HTML at feed generation time. Astro's `render()` function returns a component, not raw HTML, so we'll use the `@birdcar/markdown` processor (already in the project) to render post bodies to HTML strings directly.

For the XSL stylesheet, we'll create a static `.xsl` file in `public/` and reference it via `customData` in the RSS config to inject the `<?xml-stylesheet?>` processing instruction.

## Feedback Strategy

**Inner-loop command**: `bun run build:ci && cat dist/rss.xml | head -50`

**Playground**: Build output — inspect the generated `dist/rss.xml` to verify content and stylesheet reference.

**Why this approach**: RSS output is only available after build. Checking the first 50 lines catches both the stylesheet PI and the content structure.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `public/rss-styles.xsl` | XSL stylesheet for human-readable RSS feed in browsers |

### Modified Files

| File Path | Changes |
|-----------|---------|
| `src/pages/rss.xml.ts` | Add rendered HTML content to feed items; add stylesheet processing instruction |
| `src/lib/markdown.ts` | Export a function to render markdown to HTML string (may already exist — check) |

## Implementation Details

### 1. Markdown-to-HTML Rendering

**Pattern to follow**: `src/lib/markdown.ts` (existing `getPostMetadata` function)

**Overview**: We need a function that takes a post's markdown body and returns an HTML string. Check if `getPostMetadata` or the `@birdcar/markdown` processor already produces HTML — if so, expose it. If not, add a rendering step.

**Implementation steps**:
1. Read `src/lib/markdown.ts` to understand the existing processing pipeline
2. If the processor already generates HTML (likely via the unified/remark pipeline), export a `renderPostToHtml(body: string): Promise<string>` function
3. If not, create one using the same remark/rehype plugins configured in `astro.config.ts` to ensure consistency between the site and RSS output
4. The function should apply the BFM remark plugins and rehype plugins so that custom directives, hashtags, math, etc. render correctly in the feed

**Key decisions**:
- Reuse the existing markdown processing pipeline rather than duplicating plugin configuration
- Accept that some interactive elements (GSAP animations, rough-notation) won't work in RSS — that's expected
- The image CDN rehype plugin should be applied if `CDN_BASE_URL` is set, so RSS images also point to the CDN

### 2. RSS Feed Enhancement

**Pattern to follow**: Current `src/pages/rss.xml.ts`

**Overview**: Add full HTML content to each feed item and inject the XSL stylesheet reference.

```typescript
import rss from '@astrojs/rss';
import type { APIContext } from 'astro';
import { getPublishedPosts } from '../lib/posts';
import { renderPostToHtml } from '../lib/markdown';

export async function GET(context: APIContext) {
  const posts = await getPublishedPosts();

  const items = await Promise.all(
    posts.map(async (post) => {
      const html = await renderPostToHtml(post.body!);
      return {
        title: post.data.title,
        pubDate: post.data.date,
        description: post.data.description ?? '',
        link: `/blog/${post.id}/`,
        content: html,
      };
    })
  );

  return rss({
    title: 'birdcar',
    description: 'Developer blog by Nick Cannariato. Writing about software, systems, and the craft of building things.',
    site: context.site!,
    items,
    xmlns: {
      content: 'http://purl.org/rss/1.0/modules/content/',
      atom: 'http://www.w3.org/2005/Atom',
    },
    customData: [
      '<language>en-us</language>',
      `<atom:link href="${context.site}rss.xml" rel="self" type="application/rss+xml" />`,
    ].join('\n'),
    stylesheet: '/rss-styles.xsl',
  });
}
```

**Key decisions**:
- Use `@astrojs/rss`'s built-in `stylesheet` option (added in v4) rather than manually injecting the PI
- Add `xmlns` for `content:encoded` and `atom:link` (self-referencing for feed validators)
- Add `<language>` and Atom self-link as `customData` for feed best practices
- Upgrade RSS description to be more descriptive for feed readers

**Feedback loop**:
- **Playground**: Build the site and inspect `dist/rss.xml`
- **Experiment**: Verify first item has `<content:encoded>` with HTML; verify `<?xml-stylesheet?>` PI is at top; open `dist/rss.xml` in a browser to see styled output
- **Check command**: `bun run build:ci && grep -c 'content:encoded' dist/rss.xml`

### 3. XSL Stylesheet

**Overview**: Create a clean, readable XSL stylesheet that transforms the RSS XML into a styled HTML page when viewed in a browser. Matches the birdcar.dev aesthetic.

**Implementation steps**:
1. Create `public/rss-styles.xsl` with XSLT 1.0 (broadest browser support)
2. The stylesheet should render:
   - A header explaining "This is an RSS feed" with subscribe instructions
   - The feed title and description
   - Each item as a card with title (linked), date, description, and a "Read more" link
3. Style inline using the site's color palette (dark background, light text, accent colors from the CSS custom properties)
4. Keep it self-contained — no external CSS or JS dependencies

```xml
<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:atom="http://www.w3.org/2005/Atom"
  xmlns:content="http://purl.org/rss/1.0/modules/content/"
  exclude-result-prefixes="atom content">

  <xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>

  <xsl:template match="/">
    <html lang="en">
      <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title><xsl:value-of select="/rss/channel/title"/> — RSS Feed</title>
        <style>
          /* Inline styles matching birdcar.dev aesthetic */
          /* Dark bg, light text, accent colors, Archivo/Space Grotesk fonts */
        </style>
      </head>
      <body>
        <header>
          <h1>RSS Feed</h1>
          <p>This is the RSS feed for <strong><xsl:value-of select="/rss/channel/title"/></strong>.
          Copy the URL into your RSS reader to subscribe.</p>
        </header>
        <main>
          <xsl:for-each select="/rss/channel/item">
            <article>
              <h2><a><xsl:attribute name="href"><xsl:value-of select="link"/></xsl:attribute>
                <xsl:value-of select="title"/></a></h2>
              <time><xsl:value-of select="pubDate"/></time>
              <p><xsl:value-of select="description"/></p>
            </article>
          </xsl:for-each>
        </main>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
```

**Key decisions**:
- XSLT 1.0 for maximum browser compatibility
- Self-contained inline styles — no external dependencies
- Show item summaries (not full content) in the styled view — full content is for feed readers
- Match site's dark aesthetic with Google Fonts reference for consistency

## Validation Commands

```bash
# Build
bun run build:ci

# Verify content:encoded exists in RSS
grep -c 'content:encoded' dist/rss.xml

# Verify stylesheet PI
head -5 dist/rss.xml | grep 'xml-stylesheet'

# Verify XSL file is in output
ls dist/rss-styles.xsl

# Verify atom:link self-reference
grep 'atom:link' dist/rss.xml
```

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
