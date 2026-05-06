# Context Map: holistic-hardening

**Phase**: 4
**Scout Confidence**: 86/100
**Verdict**: GO

(Phase 3 map preserved under "Prior Phases". Phase 4 is purely additive — it creates new test files, no source modifications beyond `package.json` devDeps.)

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 18/20 | All 22+ test files enumerated. Open items: `agent-stub.ts` is just a 9-line `getTriageAgent` factory that requires `Env` + DO bindings — testable surface is thin, fits naturally in `bun test` with a stubbed `env` object asserting `idFromName('global')` is called and the returned stub is forwarded. `markdown.ts` runs `unified()` pipelines — synchronous `bun test` works; no Astro pipeline needed. |
| Pattern familiarity | 18/20 | `src/plugins/rehype-image-cdn.test.ts` is the canonical reference: builds a `Root` literal, calls the plugin factory with options, asserts on the mutated tree. Diverges from spec's proposed `applyPlugin(html, ...)` helper — the existing pattern is **hast-tree-as-fixture** (no HTML round-trip needed). Spec's `applyPlugin` is fine for plugins that need parser-derived `class`/`data-*` shape (chart, embed, query) but unnecessary for math (works on bare elements). |
| Dependency awareness | 17/20 | Verified: only `env.ts` imports `cloudflare:workers` (lazy dynamic); only `leads-repo.ts` imports drizzle live. `with-action-error.ts` imports `astro:actions` — needs the same `vi.mock('astro:actions', ...)` shim used in middleware tests OR move to vitest with a fake module. `posts.ts` imports `astro:content` — testable only under vitest with a getCollection mock; recommend skipping or deferring to integration. |
| Edge case coverage | 17/20 | All edge cases in spec land. Open: chart plugin `niceTicks(min===max)` returns `[min]`; renderer divides by `xCount-1` so single-row data path needs explicit fixture. `extractText` already migrated to `utils.ts`; embed.ts compensates with explicit `.trim()`. Math test "invalid LaTeX produces error span" — `katex.renderToString` is called with `throwOnError: false`, so the catch block leaves raw text. Test should assert the no-op behavior, not error span. |
| Test strategy | 16/20 | Two-runner split is mechanical and clear. Risk: `package.json` script `test` is currently `bun test src/plugins/ && bun x vitest run` — Phase 4 needs to widen the bun pattern to `bun test src/` (or `src/lib/ src/plugins/ src/scripts/`) to pick up new pure tests. Inner-loop command `bun test src/ tests/unit/` from spec assumes `tests/unit/` exists; spec only creates files in `src/lib/`, `src/lib/rehype/`, `src/scripts/` and `tests/helpers/`. There is **no `tests/unit/`** directory created — the spec's feedback command is misleading. |

## Key Patterns

- `src/plugins/rehype-image-cdn.test.ts:1-92` — **Reference pattern**. `import { describe, test, expect } from 'bun:test'`. Builds `Root` literal directly (no HTML parse step). Calls plugin factory: `rehypeImageCdn({...})(tree)`. Asserts on `tree.children[0]` properties.
- `src/plugins/rehype-image-cdn.test.ts:5-17` — `makeTree(imgSrc)` helper inlines the fixture builder — preferred over a shared `applyPlugin` for simple element shapes.
- `tests/setup.ts:17-49` — `applyMigrations()` reads `migrations/*.sql` via `import.meta.glob` and runs each statement. The `afterEach` truncates `leads`. **Available to import from new vitest tests** via `setupFiles` (auto-loaded for any `tests/**/*.spec.ts`). `LeadsRepo` test should be `tests/unit/leads-repo.spec.ts` (NOT `src/lib/leads-repo.test.ts`) so vitest's `include: ['tests/**/*.spec.ts']` picks it up and the setup file fires.
- `tests/integration/middleware.spec.ts:1-40` — Pattern for mocking `astro:middleware` and `@workos-inc/node`. Same approach reused for `astro:actions` / `astro:content`.
- `src/lib/workos.ts:131-142, 144-151, 110-114, 116-119, 121-124` — `safeReturnPath`, `readSessionCookie`, `shouldUseSecure` (private), `buildSessionCookie`, `buildClearedSessionCookie`. `shouldUseSecure` not exported — test it indirectly via `buildSessionCookie('v', new URL('http://localhost:4321'))`-style assertions on Secure presence.
- `src/lib/env.ts:22-29` — `getCloudflareEnv()` is **NOT memoized** (comment line 19-21 explicitly explains why). Spec's claim of `__resetEnvCacheForTests` is **outdated** — the symbol does not exist. Test should assert (a) returns truthy under vitest-pool, (b) catches dynamic-import failure → null. **Spec correction needed**: drop `__resetEnvCacheForTests` test case, drop "memoization" assertion. Phase 3 context map already flagged this risk (#2).
- `src/lib/event-log.ts:16-38` — Three exports: `logEvent` (console.log), `logWarn` (console.warn, error optional), `logError` (console.error, error required). Test by spying on `console.*` and asserting payload shape `{event, ...ctx, error?}`.
- `src/lib/log.ts:24-36` — `errorFields(err)`: Error instance → `{name, message, stack}`; non-Error → `{name: 'Unknown', message: String(err)}`. **No circular handling** — `String(circular)` on a plain object returns `'[object Object]'`, no crash. Spec's "circular" case asserts that contract.
- `src/lib/with-action-error.ts:18-34` — `wrapAction({event, userMessage}, fn)`. `ActionError` re-thrown unchanged; non-`ActionError` → `logError` then throw new `ActionError({code: 'INTERNAL_SERVER_ERROR', ...})`. Test imports `ActionError` from `astro:actions` — needs vi.mock or a stub module.
- `src/lib/leads-repo.ts:15-107` — 11 methods: `insert`, `findById`, `patch`, `markStatus`, `claimNotificationSlot`, `releaseNotificationSlot`, `claimResponseSlot`, `releaseResponseSlot`, `resetStaleProcessing`, `findStalePending`. Constructor takes `D1Database`. **Test file goes in `tests/unit/leads-repo.spec.ts`** to ride the existing `setupFiles: ['./tests/setup.ts']` auto-migration.
- `src/lib/triage-config.ts:6-37` — `MODELS`, `STEP_RETRY`, `APPROVAL_TIMEOUT`, `SCORE_RULES`, `STUCK_ROW_THRESHOLD_MINUTES`, `SWEEP_CRON`, `NOTIFY_TO`, `NOTIFY_FROM`, `REPLY_FROM`. All `as const`. Test invariants: `STEP_RETRY` keys cover `classify/qualify/score/draft/persist/notify`; `MODELS` has `classify/qualify/draft`; `SCORE_RULES.industryFit/geoStrong/geoOk/sizeFit/problemRecognized` all non-empty arrays.
- `src/lib/ai-types.ts:3-52` — Two zod schemas: `ClassifyOutput` (category enum, confidence 0-1, reasoning ≤500 chars), `QualifyOutput` (5 enum fields). Use `.parse()` for happy path, `.safeParse()` to assert failure shape.
- `src/lib/prompts.ts:41-152` — Three exports: `classifyPrompt(lead)`, `qualifyPrompt(lead)`, `draftPrompt(input)`. Each returns `{system, user}`. Assert `user` contains `lead.name`, `lead.email`, and message wrapped in `<message>...</message>` fence (line 38).
- `src/lib/agent-stub.ts:5-8` — `getTriageAgent(env)` calls `env.LEAD_TRIAGE_AGENT.idFromName('global')` then `.get(id)`. Testable with a stubbed env: spy on both calls, assert `'global'` literal. **No DO needed** — pure delegation.
- `src/lib/bfm-handlers.ts:25-189, 204-258` — Three exports: `directiveBlock`, `mention`, `hashtag`. Pure functions — input is mdast-style node, output is hast element literal. Easy `bun test`. Many internal branches: callout/aside/details/figure/embed/tabs/tab/toc/endnotes for directiveBlock; platform routing (github/twitter/x/bluesky/mastodon/npm/linkedin) for mention.
- `src/lib/rehype/utils.ts:8-51` — `extractText` (recursive, no trim), `getDataAttr` (kebab + camel + ''empty fallback), `buildErrorElement(message, className='bfm-figure-error')`.
- `src/lib/rehype/chart.ts:75-125` — Plugin reads `data-kind="chart"` figure, JSON-fetches data via `fs.readFileSync`, renders SVG inline. Test by writing a fixture JSON to `__fixtures__/`, passing `basePath` option. Error figure when `JSON.parse` throws (`buildErrorElement`).
- `src/lib/rehype/embed.ts:24-70` — **Async** plugin (returns async function). Reads `class="embed"` divs. Calls `fetchOEmbed` which hits the network. **Test must mock `fetch`** with `bun:test`'s `mock.module` or pass through unrecognized hosts to assert fallback path.
- `src/lib/rehype/include.ts:11-53` — Reads `.include` divs with `data-path`. `fs.existsSync` + `fs.readFileSync`. Section extraction via `data-heading`. Errors → `buildErrorElement(msg, 'include include--error')`.
- `src/lib/rehype/math.ts:7-42` — Reads `.math` elements. Uses `katex.renderToString({throwOnError: false})` so invalid input renders as KaTeX error markup (not exception). Plugin only catches if extractText is empty (line 12: `if (!latex.trim()) return;`).
- `src/lib/rehype/query.ts:25-148` — **Async** plugin, takes `{resolver}` option. Resolver is the pure injection point — pass a stub returning fixed `QueryResult[]`.
- `src/lib/rehype/figure-src.ts:28-88` — Plugin handles non-chart `bfm-figure` elements with `data-src`. SVG inlined; raster → `<img>`. `fs.readFileSync` for SVG; `toPublicUrl` for raster.
- `src/scripts/admin-controller.ts:7-50` — `AgentController` class (constructor takes onState callback), `setButtonsDisabled(scope, disabled)`, `showNotice(scope, text, id?)`. The DOM helpers are pure, but rely on `document` global → need happy-dom or jsdom. `AgentController` instantiates `AgentClient` immediately in constructor → **untestable without mocking `agents/client`** (network call). Recommendation: test `setButtonsDisabled` and `showNotice` only; leave `AgentController` covered transitively by the integration tests.

## Dependencies

| Modified file | Consumed by |
|---|---|
| `package.json` (devDeps) | Add `happy-dom` (for admin-controller DOM tests). `rehype-parse` already present at top-level via the `rehype-stringify` family; safe to import directly in tests. |
| `package.json` `scripts.test` | Currently `bun test src/plugins/ && bun x vitest run`. Phase 4 must widen to e.g. `bun test src/ && bun x vitest run` (so all new `*.test.ts` under `src/lib/` and `src/scripts/` are picked up). Without this, CI ignores the new tests. |
| `tests/helpers/rehype.ts` | new file — consumed by chart/embed/include/math/query/figure-src tests. |
| `tests/unit/leads-repo.spec.ts` | new file (vitest) — rides `setupFiles: ['./tests/setup.ts']`. **Note**: vitest config `include` is `tests/**/*.spec.ts`. Naming as `.spec.ts` (not `.test.ts`) and putting under `tests/unit/` matches the existing config. |
| All other new `src/**/*.test.ts` | Picked up by `bun test src/` (after script widening). Bun and vitest discriminate purely by filename pattern + import — `bun:test` vs `vitest` imports tell the runner apart. |

## Conventions

- **Test file naming**:
  - Co-located pure tests: `src/**/*.test.ts` with `import { describe, test|it, expect } from 'bun:test'`.
  - vitest tests: `tests/**/*.spec.ts` with `import {...} from 'vitest'` and optional `import { env } from 'cloudflare:workers'`.
- **Imports in tests**:
  - bun: `import { describe, test, expect } from 'bun:test'` (existing rehype-image-cdn uses `test`, not `it`; either works).
  - vitest: `import { describe, it, expect, beforeEach } from 'vitest'`.
- **Test runner split rule** (mechanical):
  - Pure logic + `unified`/`hast`/`katex`/`fs`/zod → `bun test`. (workos, log, event-log, ai-types, prompts, triage-config, jsonld, bfm-handlers, all rehype/* + utils, agent-stub via stub env)
  - `cloudflare:workers` import or D1/Drizzle live → vitest-pool (`tests/unit/*.spec.ts`). (env, leads-repo)
  - `astro:actions` / `astro:content` import → vitest with `vi.mock` shim (with-action-error, posts, markdown if its imports cascade).
  - DOM globals → bun + happy-dom registered as the test environment in those specific files (admin-controller).
- **Fixtures**: Co-locate under `src/lib/rehype/__fixtures__/<plugin>/` per spec.
- **No tests for product values** (triage scoring weights, blog content shape) — assert structure only, per spec.
- **Naming `it()` cases**: read as specifications (`returns null when row is missing`, not `test patchLead null case`) — per `~/.claude/rules/testing.md`.
- **Mocking philosophy**: prefer real dependencies. Mock `fetch` for embed; mock `astro:actions`/`astro:content` virtual modules (no real implementation in test env).

## Risks

1. **Spec drift: `__resetEnvCacheForTests` does not exist.** `env.ts` is intentionally not memoized (lines 19-21 comment). Drop that test case from `env.test.ts`; assert non-null happy path under vitest-pool + null fallback when import fails (hard to trigger in vitest-pool — may need to live with one test).
2. **Spec drift: `tests/unit/` directory doesn't exist.** Spec's feedback command `bun test src/ tests/unit/` and the `vitest run tests/unit/` invocation both require the directory. Either create it (with `leads-repo.spec.ts` and `env.spec.ts`) or update the spec to put binding-required tests under `tests/integration/` next to the existing leads-db.spec.ts.
3. **`package.json` `test` script must be widened.** Currently `bun test src/plugins/` — add new test files won't run in CI unless changed to `bun test src/` (or explicit `src/lib/ src/lib/rehype/ src/plugins/ src/scripts/`).
4. **`with-action-error.test.ts` needs `astro:actions` shim.** `bun test` has no virtual-module support — must either mock via `mock.module('astro:actions', ...)` or refactor `with-action-error.ts` to take `ActionError` as injection. Recommend: test under vitest with `vi.mock('astro:actions', () => ({ ActionError: class ActionError extends Error {...} }))`.
5. **`posts.test.ts` and `markdown.test.ts`.** `posts.ts` imports `astro:content` — only testable under vitest with a `getCollection` mock + a synthetic frontmatter fixture. **Recommendation**: defer `posts.test.ts` (mark Open Item) — it tests the Astro content API more than our logic. `markdown.test.ts` is fine — `unified()` is pure.
6. **`agent-stub.test.ts`.** Module is 9 lines; testable by passing a stubbed `Env` shape `{LEAD_TRIAGE_AGENT: {idFromName: spy, get: spy}}` and asserting both calls. Avoid the spec's "skip" path — it does have surface.
7. **`embed.test.ts` requires fetch mocking.** OEmbed providers list at lines 14-22; tests should assert (a) unrecognized hostname returns no-op (no fetch), (b) error path renders fallback `embed embed--fallback` figure. Bun supports `mock.module('node:fetch', ...)` but easier: pass a URL whose hostname isn't in the OEMBED_PROVIDERS map.
8. **Chart `niceTicks` and renderer assume ≥1 data row.** `xCount === 1` path (line 175) and `niceTicks(min===max)` (line 261) are corner cases worth a fixture.
9. **`include.test.ts` injects raw HTML node.** `(node.children = [{type: 'raw', value: ...}])` — `rehype-stringify` needs `allowDangerousHtml: true` to render raw nodes. If `applyPlugin` helper uses default stringify, the raw value is dropped and assertions on the included content fail. Either assert on the tree directly (`tree.children[0].children[0].type === 'raw'`) or configure stringify with `allowDangerousHtml: true`.
10. **Async rehype plugins (embed, query) need awaited transformer.** Plugin factory returns an async function; `applyPlugin` helper must `await processor.run(tree)` to let the transform complete.
11. **`admin-controller.test.ts` happy-dom registration.** With `bun test`, set `--preload` or use `// @bun-test happy-dom` directive to register DOM globals. `AgentController` instantiates `AgentClient` in constructor — its tests will fail without mocking `agents/client`. Scope tests to `setButtonsDisabled` + `showNotice` only.
12. **vitest config `include` excludes `src/**` tests.** `vitest.config.ts:38` restricts to `tests/**/*.spec.ts`. Vitest will not pick up co-located `src/lib/leads-repo.test.ts` — that's why the spec's note "vitest-pool" for leads-repo means **the test must live under `tests/unit/` or `tests/integration/`**, not in `src/lib/`.

## Prior Phases

**Phase 1** (commit 90f999f): canary spec only. Pool-workers + cloudflare:test API surface confirmed.

**Phase 2** (merged): 7 spec files + 3 helpers in `tests/integration/`. 69 passing + 1 expected fail + 4 todos. Mock convention for AI: dispatch on `system`. Per-test agent IDs (not `'global'`) avoid DO state pollution. `vi.mock('ai', ...)` uses async dynamic import to dodge the hoisting hazard.

**Phase 3** (commit 62a40d9): DRY sweep. Extracted `env.ts`, `session.ts`, `event-log.ts`, `with-action-error.ts`, `leads-repo.ts`, `rehype/utils.ts`, `admin-controller.ts`. Kept `lib/leads.ts` thin wrappers (`insertLead`/`getLeadById`/`patchLead`) for `tests/integration/leads-db.spec.ts` compatibility — Phase 4 implicitly inherits this constraint. Logging event names are operational contract — Phase 4 tests should assert event names exactly as listed in the Phase 3 risks (`lead.received`, `lead.persisted`, etc.). `getCloudflareEnv` deliberately not memoized.
