# Implementation Spec: Birdcar Blog - Phase 2

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Phase 2 builds all the content pages on top of the Phase 1 foundation: home page, paginated blog index, about page, tag index, tag detail pages, and RSS feed. All pages use the content collection API and `extractMetadata` to query and display posts.

Tags are merged from two sources: frontmatter `tags` array and inline `#hashtags` extracted by `extractMetadata`. This gives a unified tag system where authors can tag via frontmatter or inline — both are indexed.

Pagination uses Astro's built-in `paginate()` function. RSS uses the `@astrojs/rss` package. All pages extend the `Base.astro` layout from Phase 1.

## Feedback Strategy

**Inner-loop command**: `bunx astro dev` (then navigate to pages as they're built)

**Playground**: Dev server — every component in this phase is a page. Visual confirmation that content renders, pagination works, tags link correctly.

**Why this approach**: All changes are page-level. The dev server provides instant feedback on layout and content queries.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `src/lib/posts.ts` | Shared post querying helpers (get all posts, get tags, filter by tag, sort, merge metadata tags) |
| `src/pages/index.astro` | Home page — recent posts, brief intro |
| `src/pages/blog/index.astro` | Blog index — paginated post list |
| `src/pages/about.astro` | Static about/bio page |
| `src/pages/tags/index.astro` | Tag index — list all tags with post counts |
| `src/pages/tags/[tag].astro` | Tag detail — posts filtered by tag |
| `src/pages/rss.xml.ts` | RSS feed endpoint |
| `src/components/PostList.astro` | Reusable post list component (title, date, description, tags) |
| `src/components/Pagination.astro` | Pagination navigation component |
| `src/components/TagList.astro` | Reusable tag chip list |
| `src/content/blog/second-post.md` | Second test post for pagination and tag testing |

### Modified Files

| File Path | Changes |
|-----------|---------|
| `src/layouts/Base.astro` | Add site-wide nav (Home, Blog, About, Tags) |

## Implementation Details

### Post Query Helpers

**Overview**: Centralize post querying logic — draft filtering, sorting, tag merging.

```typescript
// src/lib/posts.ts
import { getCollection } from 'astro:content';
import { getPostMetadata } from './markdown';

export async function getPublishedPosts() {
  const posts = await getCollection('blog', ({ data }) => {
    return import.meta.env.PROD ? !data.draft : true;
  });

  const postsWithMeta = await Promise.all(
    posts.map(async (post) => {
      const metadata = await getPostMetadata(post.body!);
      const allTags = [...new Set([
        ...post.data.tags,
        ...(metadata.computed?.tags ?? []),
      ])];
      return { ...post, metadata, allTags };
    })
  );

  return postsWithMeta.sort(
    (a, b) => b.data.date.getTime() - a.data.date.getTime()
  );
}

export async function getAllTags() {
  const posts = await getPublishedPosts();
  const tagCounts = new Map<string, number>();
  for (const post of posts) {
    for (const tag of post.allTags) {
      tagCounts.set(tag, (tagCounts.get(tag) ?? 0) + 1);
    }
  }
  return tagCounts;
}
```

**Key decisions**:
- Tag merging happens once at query time, not stored separately
- `Set` deduplication handles tags that appear in both frontmatter and inline
- Tags normalized to lowercase (handled by `extractMetadata` already)

**Feedback loop**:
- **Playground**: Dev server, check pages that consume these helpers
- **Experiment**: Add posts with overlapping frontmatter tags and inline hashtags. Verify deduplication works. Verify sorting is newest-first.
- **Check command**: `bunx astro build` (build fails if queries are broken)

### Home Page

**Overview**: Landing page showing recent posts and a brief intro.

```astro
---
// src/pages/index.astro
import Base from '../layouts/Base.astro';
import PostList from '../components/PostList.astro';
import { getPublishedPosts } from '../lib/posts';

const posts = await getPublishedPosts();
const recentPosts = posts.slice(0, 5);
---
<Base title="birdcar">
  <main>
    <h1>birdcar</h1>
    <p>Developer blog.</p>
    <h2>Recent Posts</h2>
    <PostList posts={recentPosts} />
    <a href="/blog">All posts →</a>
  </main>
</Base>
```

No feedback loop — simple page composition.

### Blog Index (Paginated)

**Overview**: Paginated list of all blog posts.

```astro
---
// src/pages/blog/index.astro
import type { GetStaticPaths } from 'astro';
import Base from '../../layouts/Base.astro';
import PostList from '../../components/PostList.astro';
import Pagination from '../../components/Pagination.astro';
import { getPublishedPosts } from '../../lib/posts';

export const getStaticPaths = (async ({ paginate }) => {
  const posts = await getPublishedPosts();
  return paginate(posts, { pageSize: 10 });
}) satisfies GetStaticPaths;

const { page } = Astro.props;
---
<Base title="Blog — birdcar">
  <main>
    <h1>Blog</h1>
    <PostList posts={page.data} />
    <Pagination page={page} />
  </main>
</Base>
```

**Key decisions**:
- 10 posts per page — reasonable default
- Astro's `paginate()` generates `/blog`, `/blog/2`, `/blog/3`, etc.

**Feedback loop**:
- **Playground**: Dev server, navigate to `/blog`
- **Experiment**: Add enough test posts to trigger pagination (11+ posts). Verify page 2 link works. Verify page 1 shows newest posts.
- **Check command**: `bunx astro build && ls dist/blog/` (verify paginated directories exist)

### Tag Pages

**Overview**: Tag index lists all tags with counts; tag detail shows posts for a specific tag.

```astro
---
// src/pages/tags/[tag].astro
import type { GetStaticPaths } from 'astro';
import Base from '../../layouts/Base.astro';
import PostList from '../../components/PostList.astro';
import { getPublishedPosts } from '../../lib/posts';

export const getStaticPaths = (async () => {
  const posts = await getPublishedPosts();
  const tags = new Set(posts.flatMap((p) => p.allTags));
  return [...tags].map((tag) => ({
    params: { tag },
    props: {
      tag,
      posts: posts.filter((p) => p.allTags.includes(tag)),
    },
  }));
}) satisfies GetStaticPaths;

const { tag, posts } = Astro.props;
---
<Base title={`#${tag} — birdcar`}>
  <main>
    <h1>#{tag}</h1>
    <PostList posts={posts} />
  </main>
</Base>
```

**Feedback loop**:
- **Playground**: Dev server, navigate to `/tags` and `/tags/{tag-name}`
- **Experiment**: Create posts with shared tags. Verify tag index shows correct counts. Verify tag detail pages list the right posts. Verify inline `#hashtag` in post body appears as a tag.
- **Check command**: `bunx astro build && ls dist/tags/`

### RSS Feed

**Overview**: RSS 2.0 feed using `@astrojs/rss`.

```typescript
// src/pages/rss.xml.ts
import rss from '@astrojs/rss';
import type { APIContext } from 'astro';
import { getPublishedPosts } from '../lib/posts';

export async function GET(context: APIContext) {
  const posts = await getPublishedPosts();
  return rss({
    title: 'birdcar',
    description: 'Developer blog',
    site: context.site!,
    items: posts.map((post) => ({
      title: post.data.title,
      pubDate: post.data.date,
      description: post.data.description ?? '',
      link: `/blog/${post.id}/`,
    })),
  });
}
```

**Feedback loop**:
- **Playground**: Dev server, navigate to `/rss.xml`
- **Experiment**: Verify XML is well-formed. Verify items match published posts. Verify draft posts are excluded.
- **Check command**: `curl -s http://localhost:4321/rss.xml | head -20`

### Site Navigation

**Overview**: Add persistent nav to `Base.astro` layout.

Simple `<nav>` with links to Home, Blog, About, Tags. No feedback loop — trivial change.

### Components (PostList, Pagination, TagList)

**Overview**: Shared components for rendering post lists, pagination controls, and tag chips.

`PostList`: Takes array of posts, renders title (linked), date, description, tags.
`Pagination`: Takes Astro's page object, renders prev/next links with page numbers.
`TagList`: Takes array of tag strings, renders as linked chips to `/tags/{tag}`.

No dedicated feedback loop — validated through the pages that use them.

## Testing Requirements

### Manual Testing

- [ ] Home page renders at `/` with recent posts
- [ ] Blog index renders at `/blog` with paginated post list
- [ ] Pagination links work (next/prev)
- [ ] About page renders at `/about`
- [ ] Tag index at `/tags` shows all tags with counts
- [ ] Tag detail at `/tags/{tag}` shows filtered posts
- [ ] Tags from frontmatter and inline hashtags both appear in tag system
- [ ] RSS feed at `/rss.xml` is valid XML with correct items
- [ ] Draft posts excluded from all pages in production build
- [ ] Draft posts included in dev server
- [ ] Navigation links work across all pages

## Error Handling

| Error Scenario | Handling Strategy |
|---------------|-------------------|
| No published posts | Pages render with empty state (no posts yet) |
| Post with no tags | Post appears in index and blog but not in any tag page — expected |
| RSS with no site URL configured | Astro build fails with clear error — `site` required in config |

## Validation Commands

```bash
# Type checking
bunx astro check

# Build
bunx astro build

# Dev server
bunx astro dev

# Verify RSS
curl -s http://localhost:4321/rss.xml | head -20
```

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
