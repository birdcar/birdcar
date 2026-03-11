# Context Map: birdcar

**Phase**: 3
**Scout Confidence**: 83/100
**Verdict**: GO

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 17/20 | All 5 new files and 3 modified files specified. One open item: dev mode image serving approach. |
| Pattern familiarity | 18/20 | Rehype plugin pattern clear from `src/lib/rehype/`. Plugin registration in `astro.config.ts` well understood. |
| Dependency awareness | 17/20 | All changes are additive. No existing consumers break. |
| Edge case coverage | 16/20 | Error scenarios documented. Dev image serving uncertain. Build script change needs credential guard. |
| Test strategy | 15/20 | First test in project. `bun test` works natively. All test cases specified. |

## Key Patterns

- `src/lib/rehype/include.ts` — Plugin with options: `export function rehypeBfmInclude(options: IncludeOptions)` returning closure. Direct pattern for `rehypeImageCdn`.
- `astro.config.ts` lines 27-32 — Plugin registration: tuple `[fn, options]` form, array cast `as any`.
- `src/lib/rehype/math.ts` — Rehype plugin: `Root`/`Element` from `hast`, `visit` from `unist-util-visit`.

## Dependencies

- `astro.config.ts` — additive modification (new plugin + vite config)
- `src/lib/s3.ts` (new) → consumed by `scripts/sync-images.ts`
- `src/plugins/rehype-image-cdn.ts` (new) → consumed by `astro.config.ts`

## Conventions

- Rehype plugins: `export function rehypeName(options)` returning `(tree: Root) => void`
- Node builtins: `node:` protocol prefix
- `as any` casts on plugin arrays in astro config
- No test infra exists yet — `bun test` is the runner

## Risks

- `@aws-sdk/client-s3` and `mime-types` not installed
- Dev mode image serving unresolved — investigate Vite alias or symlink
- First test in project — infra untested
- Build script needs sync → CI/CD without S3 creds will fail
- `CDN_BASE_URL` conditional: use `process.env` not `import.meta.env` in config
- `images/` .gitignore needs `!images/.gitkeep` syntax
