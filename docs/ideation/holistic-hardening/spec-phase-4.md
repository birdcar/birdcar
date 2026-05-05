# Implementation Spec: Holistic Hardening - Phase 4

**Contract**: ./contract.md
**Estimated Effort**: M
**Blocked by**: Phase 3 (covers both pre-existing and newly-extracted modules)
**Parallelizable with**: Phase 5

## Technical Approach

Round out the test suite with **unit tests for every pure-logic module** in `src/lib/*` and the **5 untested rehype plugins** (`chart`, `embed`, `include`, `math`, `query`; `rehype-image-cdn` already has `src/plugins/rehype-image-cdn.test.ts`). After Phase 3, this also covers the newly-extracted modules: `env.ts`, `session.ts`, `event-log.ts`, `rehype/utils.ts`, `leads-repo.ts`, `with-action-error.ts`, `admin-controller.ts`. Together with Phase 2 integration tests, this drives total test count above 30 and gives every duplicated-then-extracted pattern its own dedicated unit coverage.

Two test runners coexist: `vitest-pool-workers` for tests that need bindings (e.g. `LeadsRepo` against D1), `bun test` for tests of pure logic with no runtime needs (e.g. `safeReturnPath`, `extractText`, `errorFields`). The split is mechanical — anything that imports `cloudflare:workers` or uses Drizzle live needs vitest; anything else uses `bun test` for speed. The umbrella `bun run test` from Phase 1 runs both.

Rehype plugin tests follow a single repeatable pattern: build a hast tree fixture, run the plugin, assert the resulting tree shape. We extract a `tests/helpers/rehype.ts` with `applyPlugin(plugin, html, options?)` that converts an HTML string to hast, runs the plugin transformer, and returns the result for assertions. Existing `rehype-image-cdn.test.ts` style is the reference.

## Feedback Strategy

**Inner-loop command**: `bun test src/ tests/unit/`

**Playground**: `bun test --watch src/lib/workos.test.ts` (or whichever file is being written) for sub-second feedback on pure-logic units; `bun x vitest tests/unit/` for tests that need bindings.

**Why this approach**: Unit tests are the fastest tests. A focused watch on the file under construction trumps full-suite runs. CI runs everything.

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `tests/helpers/rehype.ts` | `applyPlugin(plugin, html, options?)` — hast fixture builder + plugin runner |
| `src/lib/workos.test.ts` | `safeReturnPath`, `readSessionCookie`, `buildSessionCookie`, `buildClearedSessionCookie`, `shouldUseSecure` (via cookie inspection) — pure-logic; `bun test` |
| `src/lib/session.test.ts` | `requireSession` (depends on workos mock), `attachRefreshedSession`, `clearSession` — vitest-pool (needs `Env` type but no real bindings; can be `bun test` with a `Env`-shaped object) |
| `src/lib/env.test.ts` | `getCloudflareEnv` memoization, error-as-null fallback, `__resetEnvCacheForTests` clears state — vitest-pool |
| `src/lib/event-log.test.ts` | `logEvent`, `logWarn`, `logError` payload shapes; `errorFields` already covered transitively but add an explicit test — `bun test` with `console.*` spies |
| `src/lib/log.test.ts` | `errorFields` for `Error`, non-Error, undefined, circular — `bun test` |
| `src/lib/with-action-error.test.ts` | Wrapper passes `ActionError` through; wraps non-ActionError into `INTERNAL_SERVER_ERROR`; logs via `event-log` — `bun test` |
| `src/lib/leads-repo.test.ts` | Every method against an in-memory D1; claim/release roundtrip; idempotency of `resetStaleProcessing` — vitest-pool |
| `src/lib/markdown.test.ts` | Existing markdown helpers (read source first; tests vary by what's exposed) |
| `src/lib/posts.test.ts` | `getAllPosts`, `getPostBySlug`, sort order, draft filtering — `bun test` |
| `src/lib/jsonld.test.ts` | JSON-LD output for blog post / about page / index — `bun test` |
| `src/lib/triage-config.test.ts` | Config invariants only (no scoring tested — that's product) — `bun test`. Asserts SCORE_RULES arrays are non-empty, MODELS map has classify/qualify/draft, STEP_RETRY entries have valid retry shape. |
| `src/lib/ai-types.test.ts` | `ClassifyOutput.parse(valid)` succeeds; `parse(missing field)` fails; same for `QualifyOutput`; round-trip via JSON.stringify/parse — `bun test` |
| `src/lib/prompts.test.ts` | `classifyPrompt`, `qualifyPrompt`, `draftPrompt` produce strings containing the lead's name + email; system prompts are static; user prompts include the message verbatim — `bun test` |
| `src/lib/agent-stub.test.ts` | If this module exposes anything testable (read source first); skip if just a re-export |
| `src/lib/bfm-handlers.test.ts` | `mention`, `hashtag`, `directiveBlock` mdast → hast handlers produce expected shapes — `bun test` |
| `src/lib/rehype/utils.test.ts` | `extractText`, `getDataAttr` (kebab + camel + missing), `buildErrorElement` — `bun test` |
| `src/lib/rehype/chart.test.ts` | Renders line + bar from a known data fixture; missing data file produces error figure; preserves `figcaption`; series limit (4) — `bun test` |
| `src/lib/rehype/embed.test.ts` | Recognized embed types render; unrecognized → no-op — `bun test` |
| `src/lib/rehype/include.test.ts` | Existing file content included; missing file produces error node; section extraction by heading — `bun test` |
| `src/lib/rehype/math.test.ts` | KaTeX renders inline + block math; invalid LaTeX produces error span — `bun test` |
| `src/lib/rehype/query.test.ts` | Query resolver returns expected slugs; tag filter — `bun test` |
| `src/lib/rehype/figure-src.test.ts` | Figure src resolution rules; relative vs absolute — `bun test` |
| `src/scripts/admin-controller.test.ts` | DOM helpers `setButtonsDisabled`, `showNotice` — `bun test` with happy-dom or jsdom |

### Modified Files

| File Path | Changes |
|---|---|
| `package.json` | Add devDeps if absent: `happy-dom` (for admin-controller tests) — `bun add -d happy-dom` |

## Implementation Details

### Rehype test helper

**Pattern to follow**: existing `src/plugins/rehype-image-cdn.test.ts`.

```ts
// tests/helpers/rehype.ts
import { unified } from 'unified';
import rehypeParse from 'rehype-parse';
import rehypeStringify from 'rehype-stringify';
import type { Plugin } from 'unified';
import type { Root } from 'hast';

export async function applyPlugin(
  plugin: Plugin<any[], Root>,
  html: string,
  options?: any,
): Promise<{ tree: Root; html: string }> {
  const processor = unified()
    .use(rehypeParse, { fragment: true })
    .use(plugin, options)
    .use(rehypeStringify);
  const file = await processor.process(html);
  const tree = processor.parse(html) as Root;
  await processor.run(tree);
  return { tree, html: String(file) };
}
```

**Implementation steps**:

1. Add `rehype-parse` + `rehype-stringify` if not in deps (likely transitively present via Astro's pipeline; add explicit if needed).
2. Each plugin test imports `applyPlugin` and feeds an HTML fragment.

### Per-rehype-plugin test pattern

Every rehype plugin test follows the same template:

```ts
// src/lib/rehype/chart.test.ts
import { describe, it, expect } from 'bun:test';
import { applyPlugin } from '../../../tests/helpers/rehype';
import { rehypeBfmChart } from './chart';

describe('rehypeBfmChart', () => {
  it('renders a line chart from a JSON fixture', async () => {
    const html = `<figure class="bfm-figure" data-kind="chart" data-src="./test-data.json" data-type="line"></figure>`;
    const { html: out } = await applyPlugin(rehypeBfmChart, html, { basePath: __dirname + '/__fixtures__' });
    expect(out).toContain('<svg');
    expect(out).toContain('class="bfm-chart-svg"');
  });
  it('renders an error message when the data file is missing', async () => { /* ... */ });
  it('preserves figcaption', async () => { /* ... */ });
});
```

**Key decisions**:

- **Co-locate fixtures** in `src/lib/rehype/__fixtures__/` — keeps tests near the data they need
- **Test the rendered shape, not pixel-perfect SVG** — assert `<svg`, class names, and that the data values appear; don't assert exact path coordinates (brittle)

### `LeadsRepo` test (vitest-pool-workers)

```ts
// src/lib/leads-repo.test.ts
import { env } from 'cloudflare:test';
import { it, expect, beforeEach } from 'vitest';
import { LeadsRepo } from './leads-repo';
import { resetD1 } from '../../tests/setup';

beforeEach(resetD1);

it('claim/release notification slot is atomic and idempotent', async () => {
  const repo = new LeadsRepo(env.LEADS_DB);
  await repo.insert({ id: 'L1', submittedAt: '2026-01-01T00:00:00Z', name: 'A', email: 'a@b', message: 'hi', userAgent: null, source: 'test' });
  expect(await repo.claimNotificationSlot('L1')).toBe(true);
  expect(await repo.claimNotificationSlot('L1')).toBe(false); // second claim fails
  await repo.releaseNotificationSlot('L1');
  expect(await repo.claimNotificationSlot('L1')).toBe(true); // can re-claim after release
});
```

### Pure-logic units (`bun test`)

```ts
// src/lib/workos.test.ts
import { describe, it, expect } from 'bun:test';
import { safeReturnPath, readSessionCookie, buildSessionCookie } from './workos';

describe('safeReturnPath', () => {
  it('passes a clean absolute path', () => expect(safeReturnPath('/admin/leads')).toBe('/admin/leads'));
  it('rejects protocol-relative', () => expect(safeReturnPath('//evil.com')).toBe('/admin/leads'));
  it('rejects backslash variants', () => expect(safeReturnPath('/\\evil.com')).toBe('/admin/leads'));
  it('rejects header injection', () => expect(safeReturnPath('/admin\r\nLocation: x')).toBe('/admin/leads'));
  it('falls back on null', () => expect(safeReturnPath(null)).toBe('/admin/leads'));
});

describe('readSessionCookie', () => {
  it('extracts wos-session from a multi-cookie header', () => {
    const req = new Request('http://x', { headers: { cookie: 'foo=1; wos-session=ABC; bar=2' } });
    expect(readSessionCookie(req)).toBe('ABC');
  });
  it('returns undefined when missing', () => {
    const req = new Request('http://x');
    expect(readSessionCookie(req)).toBeUndefined();
  });
});

describe('buildSessionCookie Secure flag', () => {
  it('omits Secure on http localhost', () => {
    expect(buildSessionCookie('v', new URL('http://localhost:4321'))).not.toContain('Secure');
  });
  it('includes Secure on https', () => {
    expect(buildSessionCookie('v', new URL('https://birdcar.dev'))).toContain('Secure');
  });
});
```

## Testing Requirements

### Unit Tests

| Test File | Coverage |
|---|---|
| `src/lib/workos.test.ts` | safeReturnPath edge cases, cookie helpers, Secure flag rules |
| `src/lib/session.test.ts` | requireSession composition, refresh attachment |
| `src/lib/env.test.ts` | Memoization + null fallback |
| `src/lib/event-log.test.ts` | Event payload shape per level |
| `src/lib/log.test.ts` | errorFields all input types |
| `src/lib/with-action-error.test.ts` | Pass-through + wrap behavior |
| `src/lib/leads-repo.test.ts` | All 11 repo methods + idempotency |
| `src/lib/markdown.test.ts` | Whatever the module exposes |
| `src/lib/posts.test.ts` | Listing, sorting, draft filter |
| `src/lib/jsonld.test.ts` | Per-page JSON-LD shape |
| `src/lib/triage-config.test.ts` | Config invariants (not product logic) |
| `src/lib/ai-types.test.ts` | Zod schemas validate + reject |
| `src/lib/prompts.test.ts` | Prompt content includes inputs |
| `src/lib/bfm-handlers.test.ts` | mdast→hast handler shapes |
| `src/lib/rehype/utils.test.ts` | All three helpers |
| `src/lib/rehype/{chart,embed,include,math,query,figure-src}.test.ts` | One file per plugin, ≥3 cases each |
| `src/scripts/admin-controller.test.ts` | DOM helpers |

**Target**: ≥18 new test files; combined with Phase 2's 7 integration test files, the project ships ~25 test files. Total individual `it()` cases ≥40, well above the contract's 30+ goal.

### Manual Testing

- [ ] `bun run test` green
- [ ] CI green on PR
- [ ] No flaky tests across 3 sequential local runs

## Error Handling

| Scenario | Strategy |
|---|---|
| Module exposes no testable surface (e.g. `agent-stub.ts` is just a type alias) | Skip with a one-line comment in this spec; don't force a contrived test |
| Test pulls a Node-only API into a vitest-pool file | Move to `bun test` or vice versa; the runner choice follows the imports |
| Rehype plugin test needs Astro's `process.cwd()` | Pass `basePath` explicitly via plugin options; tests don't rely on cwd |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| Rehype helper `applyPlugin` | hast tree mutation observed in returned `tree` differs from `html` round-trip | `rehype-stringify` reformats whitespace | Test asserts on `html` but tree differs | Always assert on `html` for whole-document checks; assert on `tree` for property-level checks |
| `leads-repo.test.ts` | D1 in-memory schema drifts from migrations | Direct CREATE TABLE in test instead of migration apply | Repo behavior diverges from prod | Reuse Phase 1's migration apply in `beforeEach`; never CREATE TABLE inline |
| Pure-logic test | Imports a module that transitively pulls `cloudflare:workers` | Forgetting to break the chain | `bun test` crashes on virtual module | Either move to vitest-pool or refactor the dependency chain so the pure module doesn't pull runtime |
| `markdown.test.ts` | Tests product-specific frontmatter shape | Coupling tests to current blog content | Fails when an existing post is edited | Use a synthetic frontmatter fixture, not real `src/content/blog/*.md` files |
| `triage-config.test.ts` | Tests scoring values | Caller treats it as product logic | Tests block product changes | Asserts only structural invariants (non-empty arrays, present keys) |

## Validation Commands

```bash
bun run check
bun run test
bun test src/lib/         # pure-logic only
bun x vitest run tests/unit/   # bindings-required units
```

## Rollout Considerations

- **Feature flag**: none
- **Monitoring**: none — tests don't ship
- **Rollback plan**: drop test files individually; nothing else affected

## Open Items

- [ ] Confirm `happy-dom` is acceptable for `admin-controller.test.ts` vs jsdom (recommendation: happy-dom — lighter, fast under bun)
- [ ] Decide whether to fold `lib/markdown.test.ts` into integration tests if the module's behavior depends on the full Astro pipeline
- [ ] Verify `src/lib/agent-stub.ts` has any testable surface; if not, document as "no test" with a comment in the file
