# Implementation Spec: Birdcar Blog - Phase 1

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Phase 1 scaffolds the Astro project with a fully working BFM rendering pipeline. The core deliverable is: write a `.md` file with BFM syntax, run `astro dev`, and see it rendered correctly as HTML.

Astro's content collections provide type-safe frontmatter validation and build-time content querying. We'll define a `blog` collection with a Zod schema matching BFM frontmatter conventions. The remark pipeline chains `remark-parse` → `remark-gfm` → `remarkBfm` → `remark-rehype` → `rehype-stringify`, configured in `astro.config.ts`. We'll also integrate `extractMetadata` from `@birdcar/markdown/metadata` to surface reading time, word count, and tags at the collection level.

Draft support is handled by filtering on `draft: true` in frontmatter — included in dev, excluded in production builds. A single post page (`/blog/[slug]`) validates the full pipeline end-to-end.

## Feedback Strategy

**Inner-loop command**: `bunx astro dev` (then check `http://localhost:4321/blog/test-post`)

**Playground**: Dev server — the primary deliverable is rendered HTML from BFM markdown. Visual confirmation that directives, footnotes, mentions, and hashtags render correctly.

**Why this approach**: This phase is about pipeline integration. The dev server with a test post exercises the full chain from markdown to rendered page.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `package.json` | Project manifest with Astro, @birdcar/markdown, and related deps |
| `astro.config.ts` | Astro config with remark/rehype pipeline, site URL, output: static |
| `tsconfig.json` | TypeScript config (strict, ES2022, NodeNext) |
| `src/content.config.ts` | Content collection definitions with Zod schema for blog posts |
| `src/lib/markdown.ts` | Shared remark/unified processor and `extractMetadata` helper |
| `src/pages/blog/[slug].astro` | Individual blog post page |
| `src/layouts/Base.astro` | Minimal HTML shell layout (head, body, slot) |
| `src/layouts/Post.astro` | Blog post layout (title, date, reading time, content slot) |
| `src/content/blog/test-post.md` | Test post exercising BFM features (directives, footnotes, mentions, hashtags, tasks) |
| `.gitignore` | Standard Astro gitignore (node_modules, dist, .astro) |

### Modified Files

None — greenfield project.

## Implementation Details

### Astro Project Scaffold

**Overview**: Initialize the project with Bun, install deps, configure TypeScript.

**Implementation steps**:
1. Create `package.json` with dependencies:
   - `astro` (latest)
   - `@birdcar/markdown` (latest — provides `remarkBfm`, `extractMetadata`)
   - `remark-parse`, `remark-gfm`, `remark-rehype`, `rehype-stringify` (peer deps for the pipeline)
   - `unified` (pipeline orchestration)
2. Run `bun install`
3. Create `tsconfig.json` extending Astro's strict preset
4. Create `.gitignore`

### Remark Pipeline Integration

**Overview**: Configure the full BFM → HTML pipeline as Astro's markdown processor.

```typescript
// astro.config.ts
import { defineConfig } from 'astro/config';
import remarkGfm from 'remark-gfm';
import { remarkBfm } from '@birdcar/markdown';

export default defineConfig({
  site: 'https://birdcar.dev', // placeholder, update for CF Pages
  output: 'static',
  markdown: {
    remarkPlugins: [remarkGfm, remarkBfm],
    shikiConfig: {
      theme: 'github-dark',
    },
  },
});
```

**Key decisions**:
- `remarkGfm` before `remarkBfm` — BFM is a superset of GFM, so GFM tables/strikethrough are handled first
- Astro handles the `remark-rehype` + `rehype-stringify` step internally, so we only configure remark plugins
- Shiki for code highlighting (built into Astro)

**Implementation steps**:
1. Create `astro.config.ts` with the remark plugin chain
2. Verify the pipeline works by running dev server with the test post

**Feedback loop**:
- **Playground**: Start dev server (`bunx astro dev`), navigate to `/blog/test-post`
- **Experiment**: Verify each BFM feature renders: `@callout` blocks, `[^footnotes]`, `@mentions`, `#hashtags`, task lists with extended states, `@details` blocks, code blocks with syntax highlighting
- **Check command**: `bunx astro dev` then `curl -s http://localhost:4321/blog/test-post | grep -c '<div'` (verify HTML elements are present)

### Content Collection Schema

**Overview**: Define the blog collection with Zod schema matching BFM frontmatter conventions.

```typescript
// src/content.config.ts
import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

const blog = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './src/content/blog' }),
  schema: z.object({
    title: z.string(),
    description: z.string().optional(),
    date: z.coerce.date(),
    updated: z.coerce.date().optional(),
    tags: z.array(z.string()).default([]),
    draft: z.boolean().default(false),
  }),
});

export const collections = { blog };
```

**Key decisions**:
- `date` uses `z.coerce.date()` to handle both `2026-03-10` and `2026-03-10T12:00:00Z` formats
- `tags` defaults to empty array — inline `#hashtags` from `extractMetadata` will be merged at query time
- `draft` defaults to `false` so published posts don't need to declare it

### Metadata Helper

**Overview**: Wrap `extractMetadata` for use in Astro pages to surface reading time, word count, and merged tags.

```typescript
// src/lib/markdown.ts
import { unified } from 'unified';
import remarkParse from 'remark-parse';
import remarkGfm from 'remark-gfm';
import { remarkBfm } from '@birdcar/markdown';
import { extractMetadata } from '@birdcar/markdown/metadata';

export async function getPostMetadata(rawContent: string) {
  const processor = unified().use(remarkParse).use(remarkGfm).use(remarkBfm);
  const tree = processor.parse(rawContent);
  const transformed = await processor.run(tree);
  return extractMetadata(transformed);
}
```

**Key decisions**:
- Separate processor instance (not Astro's) because we need the MDAST tree, not HTML output
- This is called per-post at build time — acceptable performance for a static site

### Blog Post Page

**Overview**: Dynamic route that renders a single blog post with full BFM HTML.

```astro
---
// src/pages/blog/[slug].astro
import { getCollection } from 'astro:content';
import { render } from 'astro:content';
import Post from '../../layouts/Post.astro';
import { getPostMetadata } from '../../lib/markdown';

export async function getStaticPaths() {
  const posts = await getCollection('blog', ({ data }) => {
    return import.meta.env.PROD ? !data.draft : true;
  });
  return posts.map((post) => ({
    params: { slug: post.id },
    props: { post },
  }));
}

const { post } = Astro.props;
const { Content } = await render(post);
const metadata = await getPostMetadata(post.body!);
---
<Post title={post.data.title} date={post.data.date} metadata={metadata}>
  <Content />
</Post>
```

**Key decisions**:
- Draft filtering at `getStaticPaths` level — drafts never generate pages in production
- `import.meta.env.PROD` is Astro's built-in env flag
- `getPostMetadata` runs on the raw body to extract reading time, word count

**Feedback loop**:
- **Playground**: Dev server running, navigate to `/blog/test-post`
- **Experiment**: Check that the page renders with title, date, reading time displayed. Verify BFM content (callouts, footnotes, etc.) appears correctly. Check that adding `draft: true` to the test post hides it from build output but not dev.
- **Check command**: `bunx astro build && ls dist/blog/` (verify test-post exists or doesn't based on draft flag)

### Layouts

**Overview**: Minimal functional layouts — Base provides the HTML shell, Post wraps blog content.

```astro
---
// src/layouts/Base.astro
interface Props { title: string; description?: string; }
const { title, description } = Astro.props;
---
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{title}</title>
  {description && <meta name="description" content={description} />}
</head>
<body>
  <slot />
</body>
</html>
```

No feedback loop — trivial layout components.

### Test Post

**Overview**: A markdown file that exercises all major BFM features for pipeline validation.

The test post should include:
- YAML frontmatter (title, date, tags, description)
- Headings, paragraphs, links, images
- GFM tables and strikethrough
- BFM `@callout` directive
- BFM `@details` directive
- Footnotes (`[^1]`)
- Inline mentions (`@birdcar`)
- Inline hashtags (`#testing`)
- Extended task list (`[ ]`, `[x]`, `[>]`, `[-]`)
- Code blocks with language annotation

## Testing Requirements

### Unit Tests

No unit tests for Phase 1 — the pipeline is validated by the dev server and build output. The `@birdcar/markdown` library has its own test suite.

### Manual Testing

- [ ] `bunx astro dev` starts without errors
- [ ] `/blog/test-post` renders with all BFM features visible
- [ ] `@callout` renders as a styled block element
- [ ] Footnotes render with reference links and definition section
- [ ] Mentions and hashtags render as inline elements
- [ ] Extended task states render (open, done, scheduled, migrated, irrelevant, event, priority)
- [ ] Code blocks have syntax highlighting
- [ ] `bunx astro build` succeeds and produces `dist/` output
- [ ] Draft post excluded from `bunx astro build` output
- [ ] Draft post included in `bunx astro dev` output

## Error Handling

| Error Scenario | Handling Strategy |
|---------------|-------------------|
| Invalid frontmatter | Astro's content collection validation fails at build time with clear error message |
| BFM syntax error in markdown | `@birdcar/markdown` gracefully degrades — unrecognized directives pass through as plain text |
| Missing `@birdcar/markdown` dep | Build fails immediately — hard dependency |

## Validation Commands

```bash
# Type checking
bunx astro check

# Build
bunx astro build

# Dev server
bunx astro dev
```

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
