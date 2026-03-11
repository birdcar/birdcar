# Context Map: birdcar

**Phase**: 2
**Scout Confidence**: 82/100
**Verdict**: GO

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 18/20 | All 11 new files and 1 modified file are specified. Implementation details include full code bodies. Only gap: `src/pages/about.astro` has no spec template, but it's trivially static. |
| Pattern familiarity | 18/20 | Read `Base.astro`, `Post.astro`, `[slug].astro`, `index.astro`, `markdown.ts`, `content.config.ts`. Conventions are clear. |
| Dependency awareness | 16/20 | `Base.astro` is consumed by `Post.astro`, `[slug].astro`, and the existing `index.astro`. All Phase 2 pages will also consume it. Adding a `<nav>` is additive and non-breaking. |
| Edge case coverage | 15/20 | Key edge cases identified: `@astrojs/rss` not installed, `post.body` is `string | undefined` (uses `!` assertion), `blog/index.astro` path conflict with existing `[slug].astro` pagination, `index.astro` already exists and must be replaced. |
| Test strategy | 15/20 | No automated test infra — all manual. Validation commands are clear: `bunx astro check`, `bunx astro build`, `bunx astro dev`. The existing test post exercises most tag/content features already. |

## Key Patterns

- `src/layouts/Base.astro` — Props interface with `title: string` and optional `description`. Standard HTML shell with `<slot />` in `<body>`. No `<nav>` yet. Modification is additive: insert `<nav>` between opening `<body>` and `<slot />`.

- `src/layouts/Post.astro` — Pattern for wrapping `Base.astro`. Shows how to define `Props`, destructure, and pass `title` down to `Base`. Components follow the same frontmatter-script/template split.

- `src/pages/blog/[slug].astro` — Pattern for content collection pages: `getCollection` with draft filter, `getStaticPaths`, `render()`, `getPostMetadata()`. Uses `import.meta.env.PROD` for draft filtering. `post.body!` non-null assertion is the established pattern.

- `src/pages/index.astro` — **This file already exists** and must be **replaced** by the Phase 2 version.

- `src/lib/markdown.ts` — `getPostMetadata(rawContent: string): Promise<DocumentMetadata>` is the only export. `metadata.computed.tags: string[]`.

- `src/content.config.ts` — Blog collection uses Astro's `glob` loader (Astro 5+ content layer API). Posts have `title`, `description?`, `date`, `updated?`, `tags` (default `[]`), `draft` (default `false`).

## Dependencies

- `src/layouts/Base.astro` — consumed by → `src/layouts/Post.astro`, `src/pages/index.astro`, `src/pages/blog/[slug].astro`, and all Phase 2 pages.
- `src/lib/markdown.ts` — consumed by → `src/pages/blog/[slug].astro`, `src/layouts/Post.astro` (type import). Phase 2 `src/lib/posts.ts` adds a third consumer.
- `src/pages/index.astro` — must be replaced, not created fresh.

## Conventions

- **Naming**: Files use kebab-case. Components are PascalCase (`.astro`). Lib files are camelCase (`.ts`). Content collection files are kebab-case (`.md`).
- **Imports**: Relative paths. `astro:content` for collection APIs. No barrel exports.
- **Error handling**: Non-null assertion (`post.body!`) for `post.body`. No try/catch in page code.
- **Types**: Inline `interface Props` in `.astro` frontmatter. `satisfies GetStaticPaths` pattern.
- **Draft filtering**: `import.meta.env.PROD ? !data.draft : true` is the established pattern.
- **Date formatting**: `toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })` (Post.astro style).

## Risks

- **`@astrojs/rss` is not installed.** Must `bun add @astrojs/rss` before implementing RSS feed. The `site` field is already set in `astro.config.ts` (`https://birdcar.dev`).
- **`src/pages/index.astro` already exists** with a different implementation. Must replace it.
- **`post.body` type**: `string | undefined`. `post.body!` assertion is consistent with existing usage.
- **No automated tests** — all validation is manual via dev server and build commands.
