# Implementation Spec: Birdcar Blog - Phase 3

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Phase 3 adds image management: a local `images/` directory for authoring, an S3 sync step at build time, and a rehype plugin that rewrites local image paths to S3 CDN URLs in production builds.

The S3 integration is provider-agnostic via `@aws-sdk/client-s3`. Configuration (bucket, region, endpoint, CDN base URL) comes from environment variables, making it work with AWS S3, Cloudflare R2, MinIO, or any S3-compatible service.

The workflow: author writes `![alt](./images/my-post/hero.png)`, images live in `images/` at the project root (gitignored). At build time, a script syncs new/changed images to the bucket, then a rehype plugin rewrites `./images/...` paths to `{CDN_BASE_URL}/images/...` in the HTML output. In dev mode, images are served locally via Astro's `public/` symlink or static file serving.

## Feedback Strategy

**Inner-loop command**: `bunx astro build` (verify image URLs in output HTML)

**Playground**: Build output inspection + dev server. Build-time sync and URL rewriting are verified by checking the generated HTML. Dev-time local serving is verified by the dev server.

**Why this approach**: The image pipeline is a build-time transformation. The dev server confirms local paths work, and build output confirms rewriting works. S3 sync is tested against a local MinIO or with dry-run mode.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `images/.gitkeep` | Local images directory (contents gitignored) |
| `scripts/sync-images.ts` | S3 sync script — uploads new/changed images to bucket |
| `src/plugins/rehype-image-cdn.ts` | Rehype plugin — rewrites `./images/` paths to CDN URLs in production |
| `src/lib/s3.ts` | S3 client factory (provider-agnostic configuration) |
| `.env.example` | Example env vars for S3 configuration |

### Modified Files

| File Path | Changes |
|-----------|---------|
| `astro.config.ts` | Add `rehypeImageCdn` plugin to rehype config; configure static file serving for `images/` in dev |
| `.gitignore` | Add `images/**` (except `.gitkeep`), `.env` |
| `package.json` | Add `@aws-sdk/client-s3`, `mime-types`, `sync-images` script |

## Implementation Details

### S3 Client Factory

**Overview**: Provider-agnostic S3 client configured via environment variables.

```typescript
// src/lib/s3.ts
import { S3Client } from '@aws-sdk/client-s3';

export function createS3Client() {
  return new S3Client({
    region: process.env.S3_REGION ?? 'auto',
    endpoint: process.env.S3_ENDPOINT, // undefined for AWS, set for R2/MinIO
    credentials: {
      accessKeyId: process.env.S3_ACCESS_KEY_ID!,
      secretAccessKey: process.env.S3_SECRET_ACCESS_KEY!,
    },
    forcePathStyle: !!process.env.S3_FORCE_PATH_STYLE, // true for MinIO
  });
}

export const S3_BUCKET = process.env.S3_BUCKET!;
export const CDN_BASE_URL = process.env.CDN_BASE_URL!; // e.g., https://images.birdcar.dev
```

**Key decisions**:
- `forcePathStyle` needed for MinIO but not AWS/R2 — configurable via env
- `region: 'auto'` is Cloudflare R2's convention; works fine for AWS too when overridden
- No defaults for credentials — fail fast if not configured

### Image Sync Script

**Overview**: Walks `images/` directory, compares with bucket contents, uploads new/changed files.

```typescript
// scripts/sync-images.ts
/// <reference types="bun-types" />
import { S3Client, PutObjectCommand, ListObjectsV2Command } from '@aws-sdk/client-s3';
import { createS3Client, S3_BUCKET } from '../src/lib/s3';
import { lookup } from 'mime-types';
import { readdir, stat } from 'node:fs/promises';
import { join, relative } from 'node:path';

// Walk images/, list remote objects, diff, upload missing/changed
```

**Key decisions**:
- Uses file modification time + size for change detection (not content hashing — simpler, fast enough)
- Preserves directory structure in bucket: `images/post-slug/hero.png` → `s3://{bucket}/images/post-slug/hero.png`
- Sets `Content-Type` from file extension via `mime-types`
- Runs as a bun script: `bun run scripts/sync-images.ts`

**Implementation steps**:
1. Create S3 client via factory
2. Walk local `images/` directory recursively
3. List existing objects in bucket under `images/` prefix
4. Compare local files against remote (by key existence and size)
5. Upload new/changed files with correct Content-Type
6. Log each upload (path, size)
7. Summary at end (N uploaded, N skipped, N total)

**Feedback loop**:
- **Playground**: Create a test image in `images/test/sample.png`. Run sync against a local MinIO or with `--dry-run` flag.
- **Experiment**: Sync with empty bucket (all files upload). Run again (all files skipped). Add a new file, run again (only new file uploads). Modify a file, run again (only changed file uploads).
- **Check command**: `bun run scripts/sync-images.ts --dry-run`

### Rehype Image CDN Plugin

**Overview**: At build time, rewrites `./images/...` src attributes to CDN URLs.

```typescript
// src/plugins/rehype-image-cdn.ts
import type { Root, Element } from 'hast';
import { visit } from 'unist-util-visit';

interface Options {
  cdnBaseUrl: string;
}

export function rehypeImageCdn(options: Options) {
  const { cdnBaseUrl } = options;

  return (tree: Root) => {
    visit(tree, 'element', (node: Element) => {
      if (node.tagName === 'img' && typeof node.properties?.src === 'string') {
        const src = node.properties.src;
        if (src.startsWith('./images/') || src.startsWith('images/')) {
          const cleanPath = src.replace(/^\.\//, '');
          node.properties.src = `${cdnBaseUrl}/${cleanPath}`;
        }
      }
    });
  };
}
```

**Key decisions**:
- Only rewrites paths starting with `./images/` or `images/` — leaves absolute URLs and external images untouched
- Only active in production builds (configured in `astro.config.ts` with `import.meta.env.PROD` check)
- Operates at the rehype (HAST) level, after markdown→HTML conversion, so it catches images from all sources (standard markdown `![]()` and BFM `@figure` directives)

**Implementation steps**:
1. Create the rehype plugin
2. Add to `astro.config.ts` rehype plugins array, conditionally for production
3. Test by running `bunx astro build` and checking output HTML for rewritten URLs

**Feedback loop**:
- **Playground**: Add an image reference in the test post (`![Test](./images/test/sample.png)`), build the site
- **Experiment**: In dev mode, verify image src stays as `./images/test/sample.png`. In production build, verify src becomes `{CDN_BASE_URL}/images/test/sample.png`. Test with both `./images/` and `images/` prefix formats.
- **Check command**: `CDN_BASE_URL=https://images.example.com bunx astro build && grep -r 'images.example.com' dist/`

### Dev Mode Local Image Serving

**Overview**: In development, images need to resolve from the local `images/` directory.

**Implementation steps**:
1. Add `images/` as a Vite static asset directory in `astro.config.ts`:

```typescript
// in astro.config.ts
export default defineConfig({
  // ...
  vite: {
    server: {
      fs: {
        allow: ['images'],
      },
    },
  },
});
```

2. Or alternatively, use Astro's `public/` directory with a symlink created by a setup script. The simpler approach: configure Vite to serve `images/` as a static directory.

**Key decisions**:
- Vite's `fs.allow` + a simple alias is the lightest touch — no symlinks, no copy step
- In dev, `./images/foo.png` in markdown resolves relative to the page — we may need a Vite plugin or base path config to make this work. If relative paths don't resolve, fall back to the symlink approach.

### Environment Configuration

**Overview**: `.env.example` documents required environment variables.

```
# S3 Configuration (works with AWS S3, Cloudflare R2, MinIO, etc.)
S3_REGION=auto
S3_ENDPOINT=                    # Leave empty for AWS S3; set for R2/MinIO
S3_ACCESS_KEY_ID=
S3_SECRET_ACCESS_KEY=
S3_BUCKET=birdcar-images
S3_FORCE_PATH_STYLE=            # Set to "true" for MinIO
CDN_BASE_URL=https://images.birdcar.dev
```

No feedback loop — config file.

### Build Integration

**Overview**: Wire sync + build together in `package.json` scripts.

```json
{
  "scripts": {
    "dev": "astro dev",
    "build": "bun run scripts/sync-images.ts && astro build",
    "preview": "astro preview",
    "sync-images": "bun run scripts/sync-images.ts"
  }
}
```

**Key decisions**:
- `build` runs sync first, then Astro build — images are uploaded before URLs are rewritten
- `sync-images` also available standalone for manual syncing
- `dev` does NOT sync — local images served locally

## Testing Requirements

### Unit Tests

| Test File | Coverage |
|-----------|----------|
| `src/plugins/rehype-image-cdn.test.ts` | URL rewriting logic |

**Key test cases**:
- `./images/foo.png` → `{CDN_BASE_URL}/images/foo.png`
- `images/foo.png` (no `./`) → `{CDN_BASE_URL}/images/foo.png`
- `https://example.com/photo.png` → unchanged (external URL)
- `/assets/logo.png` → unchanged (absolute path, not images dir)
- Image inside `@figure` directive → rewritten correctly

### Manual Testing

- [ ] `bun run sync-images` uploads files to configured S3 bucket
- [ ] Running sync twice doesn't re-upload unchanged files
- [ ] `bunx astro dev` serves local images at `./images/` paths
- [ ] `bunx astro build` rewrites image URLs to CDN base URL
- [ ] Images referenced in markdown render in both dev and production
- [ ] `.env.example` documents all required variables
- [ ] `images/` directory contents are gitignored

## Error Handling

| Error Scenario | Handling Strategy |
|---------------|-------------------|
| S3 credentials not configured | Sync script fails with clear error: "Missing S3_ACCESS_KEY_ID" |
| CDN_BASE_URL not set during build | Rehype plugin is a no-op (skipped) — images keep local paths. Log a warning. |
| Image referenced in markdown doesn't exist locally | Normal broken image behavior — browser shows alt text. No build failure. |
| S3 upload fails for a single file | Log error, continue with remaining files, exit with non-zero code |
| Bucket doesn't exist | S3 SDK throws — caught and logged with helpful message |

## Validation Commands

```bash
# Type checking
bunx astro check

# Unit tests for rehype plugin
bun test src/plugins/rehype-image-cdn.test.ts

# Sync images (dry run)
bun run scripts/sync-images.ts --dry-run

# Build (includes sync + astro build)
bun run build

# Verify rewritten URLs in output
grep -r 'CDN_BASE_URL_VALUE' dist/ | head -5
```

## Open Items

- [ ] Dev mode image serving: verify relative `./images/` paths resolve in Astro dev server. May need Vite alias config or a symlink to `public/images`. Investigate during implementation.

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
