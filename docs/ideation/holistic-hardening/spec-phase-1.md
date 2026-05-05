# Implementation Spec: Holistic Hardening - Phase 1

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Stand up a Workers-native test harness using `@cloudflare/vitest-pool-workers` so every later phase has a single `bun x vitest run` command that exercises the actual `workerd` runtime — including D1, KV, Workers AI, Durable Objects, Workflows, Queues, and Email send. This phase ships **no behavior changes** to `src/*`; it produces test infrastructure plus one canary test that proves the harness works end-to-end.

The pool-workers approach is non-negotiable: jest/vitest with miniflare-the-library has been removed in favor of `vitest-pool-workers`, and the alternatives (raw miniflare, hand-rolled mocks) cannot exercise Durable Object isolates or Workflow `step.do` semantics correctly. We accept the constraint that tests run inside `workerd`, which means Node-only APIs (e.g. `node:fs` used by rehype plugins) only work in tests if the test file itself imports them under `nodejs_compat` — this is fine because rehype plugins are invoked at build time, not at runtime, so their unit tests can run via plain `bun test` outside the pool. We will keep both: `vitest-pool-workers` for runtime code, `bun test` for build-time/pure-logic code.

Existing test (`src/plugins/rehype-image-cdn.test.ts`) stays on `bun test` and is invoked by the same `bun run test` umbrella script. CI gains an `astro check` + `bun run test` job on `pull_request` and `push`; the existing deploy job uses `needs:` to require it.

## Feedback Strategy

**Inner-loop command**: `bun run test`

**Playground**: Vitest watch mode (`bun x vitest`) for the pool-workers tests and a separate `bun test --watch src/plugins/` for the rehype unit tests during this phase. Both finish in under 5 seconds on a warm cache.

**Why this approach**: This phase is plumbing — the only validation that matters is "does `bun run test` exit 0 against a canary test that touches every binding?"

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `vitest.config.ts` | Configures `@cloudflare/vitest-pool-workers` with bindings mirroring `wrangler.jsonc` (D1 `LEADS_DB`, KV `SESSION`, AI, DO `LEAD_TRIAGE_AGENT`, Workflow `LEAD_TRIAGE_WORKFLOW`, Queue `LEAD_TRIAGE_QUEUE`, Email `EMAIL`) plus test-only `WORKOS_*` env vars. Loads `wrangler.jsonc` via `defineWorkersConfig({ wrangler: { configPath: './wrangler.jsonc' } })`. |
| `tests/setup.ts` | Global setup for pool-workers tests: applies D1 migrations from `./migrations` to the test DB before the suite runs, resets fixtures between tests, and exposes a typed `env` helper. |
| `tests/fixtures/leads.ts` | Builders for canonical lead rows (pending, processing, awaiting-approval, done) consumed by Phase 2 tests. |
| `tests/mocks/workers-ai.ts` | Drop-in mock for `createWorkersAI` that returns deterministic `ClassifyOutput` / `QualifyOutput` JSON for fixed prompts. Used by Phase 2 workflow tests. |
| `tests/mocks/workos.ts` | Mock for `@workos-inc/node`'s `userManagement` surface: `getAuthorizationUrl`, `authenticateWithCode`, `loadSealedSession().authenticate()`, `loadSealedSession().refresh()`, `loadSealedSession().getLogoutUrl()`. Returns shaped responses without hitting workos.com. |
| `tests/canary.spec.ts` | Smoke test asserting every binding resolves and `D1` returns rows after migrations apply. |
| `.github/workflows/test.yml` *or modifications to deploy.yml* | New `test` job running `astro check` + `bun run test` on `push` and `pull_request`. `deploy` job adds `needs: test`. |

### Modified Files

| File Path | Changes |
|---|---|
| `package.json` | Add devDeps: `@cloudflare/vitest-pool-workers`, `vitest`. Add scripts: `"test": "bun x vitest run && bun test src/plugins/"`, `"test:watch": "bun x vitest"`, `"test:rehype": "bun test src/plugins/"`. |
| `tsconfig.json` | Add `"types": ["@cloudflare/workers-types", "@cloudflare/vitest-pool-workers"]` to compilerOptions so test files type-check. |
| `.github/workflows/deploy.yml` | Add `test` job above `deploy`; gate `deploy` with `needs: test`. Trigger expanded to include `pull_request`. |

## Implementation Details

### Vitest pool-workers configuration

**Pattern to follow**: official Cloudflare docs example for `defineWorkersConfig`. Read latest from the `cloudflare:cloudflare` skill or `mcp__context7__query-docs` for `@cloudflare/vitest-pool-workers` if the API has shifted since 2026-01.

**Overview**: One config block points vitest at `wrangler.jsonc` so bindings auto-derive; we override only `migrations` (point at `./migrations`) and inject test secrets.

```ts
// vitest.config.ts
import { defineWorkersConfig } from '@cloudflare/vitest-pool-workers/config';
export default defineWorkersConfig({
  test: {
    globalSetup: ['./tests/setup.ts'],
    poolOptions: {
      workers: {
        wrangler: { configPath: './wrangler.jsonc' },
        miniflare: {
          d1Databases: { LEADS_DB: ':memory:' },
          bindings: {
            WORKOS_API_KEY: 'test_api_key',
            WORKOS_CLIENT_ID: 'client_test',
            WORKOS_REDIRECT_URI: 'http://localhost:4321/admin/callback',
            WORKOS_COOKIE_PASSWORD: 'a'.repeat(32),
          },
        },
      },
    },
  },
});
```

**Key decisions**:

- **In-memory D1 per test run** — fast, isolated. Migrations applied in `globalSetup`. Worker env's `LEADS_DB` binding still resolves; only the underlying SQLite is in-memory.
- **WorkOS secrets are dummy strings** — real workos.com is mocked at the SDK boundary in Phase 2.
- **Reuse `wrangler.jsonc`** — anything declared there (DO, Workflow, Queue, AI, Email) flows through automatically. No duplicate binding list to drift.

**Implementation steps**:

1. Install: `bun add -d @cloudflare/vitest-pool-workers vitest`.
2. Write `vitest.config.ts` per snippet above.
3. Write `tests/setup.ts` that imports the migrations from `./migrations/0000_*.sql`, executes them against `env.LEADS_DB`, and exports a fixture reset helper.
4. Write `tests/canary.spec.ts`:
   - asserts `env.LEADS_DB`, `env.SESSION`, `env.AI`, `env.LEAD_TRIAGE_AGENT`, `env.LEAD_TRIAGE_WORKFLOW`, `env.LEAD_TRIAGE_QUEUE`, `env.EMAIL` are all defined
   - inserts a row via Drizzle, queries it back, asserts shape
5. Add `package.json` scripts.
6. Run `bun run test` locally — green is the gate.

**Feedback loop**:
- **Playground**: `bun x vitest` in watch mode while building.
- **Experiment**: Toggle one binding at a time in `vitest.config.ts` to confirm canary fails when expected.
- **Check command**: `bun run test`

### Mock harnesses (workers-ai, workos)

**Pattern to follow**: existing inline JSON parsing in `src/workflows/lead-triage-workflow.ts:357 extractJson` for deterministic prompt → output mapping.

**Overview**: Tests in Phase 2 will need to assert on AI-driven decisions without flaky model output. Mocks return canned `ClassifyOutput` / `QualifyOutput` keyed by a fixture's known prompt fragment.

```ts
// tests/mocks/workers-ai.ts
import type { ClassifyOutput, QualifyOutput } from '../../src/lib/ai-types';
export interface MockAIPlan {
  classify?: ClassifyOutput;
  qualify?: QualifyOutput;
  draft?: string;
}
export function mockWorkersAI(plan: MockAIPlan) {
  return () => () => async ({ prompt }: { prompt: string }) => {
    if (prompt.includes('classify')) return { text: JSON.stringify(plan.classify) };
    if (prompt.includes('qualify'))  return { text: JSON.stringify(plan.qualify) };
    return { text: plan.draft ?? 'mock draft body' };
  };
}
```

```ts
// tests/mocks/workos.ts — module mock injected per-test via vi.mock('@workos-inc/node', ...)
export const buildWorkOSMock = (overrides: Partial<MockUser> = {}) => { /* ... */ };
```

**Key decisions**:

- **Mock the `ai` package's `generateText`, not `createWorkersAI`** — easier surface, tests just `vi.mock('ai')` once per workflow test.
- **Mock `@workos-inc/node` as a module** so any code path that imports it gets the mock; no surgery to `src/lib/workos.ts`.

### CI gate

**Modified file**: `.github/workflows/deploy.yml`

Add the `test` job before `deploy`:

```yaml
on:
  push:
    branches: [main]
  pull_request:
  schedule:
    - cron: '0 14 * * *'
  workflow_dispatch:

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6
      - uses: oven-sh/setup-bun@v2
        with: { bun-version: latest }
      - run: bun install --frozen-lockfile
      - run: bun run check        # astro check
      - run: bun run test         # vitest + bun test umbrella

  deploy:
    needs: test
    if: github.event_name != 'pull_request'
    # ... existing job ...
```

**Key decisions**:

- **PRs run tests but never deploy** — `if: github.event_name != 'pull_request'` on deploy.
- **Schedule run still goes** — daily future-dated post publishing keeps working; tests still gate it.

## Testing Requirements

### Unit Tests

| Test File | Coverage |
|---|---|
| `tests/canary.spec.ts` | All bindings resolve; D1 migrations apply; one round-trip insert+select succeeds |

**Key test cases**:

- Canary asserts every binding listed in `wrangler.jsonc` is present on `env`
- Canary inserts a `leads` row via Drizzle, selects it back, asserts default `status === 'pending'` and `created_at` is non-null

### Manual Testing

- [ ] `bun run test` exits 0 locally
- [ ] Open a draft PR; confirm `test` job runs and `deploy` job is skipped
- [ ] Push to `main`; confirm `test` runs first, `deploy` waits for it, deploy succeeds

## Error Handling

| Scenario | Strategy |
|---|---|
| Migration apply fails in `tests/setup.ts` | Throw with the SQL line number — test suite aborts, exit 1 |
| Binding missing in `wrangler.jsonc` but referenced in test | `defineWorkersConfig` surfaces a typed error at vitest startup |
| `bun run check` fails | CI fails before tests run; fast-fail signal |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| `vitest.config.ts` | Pool-workers version mismatch with `wrangler` major | Updating one without the other | Tests fail to start with cryptic compat error | Pin both in `package.json` and bump together |
| `tests/setup.ts` | Migrations file format changes | Drizzle-kit emits a non-SQL artifact | Setup can't apply migrations | Read `migrations/meta/_journal.json` to enumerate apply order; fail loud |
| `tests/mocks/workers-ai.ts` | Real workflow code paths bypass the mock | A new `step.do` calls a different model API | Mock returns nothing, test fails with unhelpful "undefined.text" | Throw from the catch-all branch with the prompt prefix to make new paths visible |
| Canary test | Binding present but unusable in `workerd` | Test pool runs older `compatibility_date` | One binding shows as `undefined` | Mirror `compatibility_date` from `wrangler.jsonc` in vitest config |

## Validation Commands

```bash
bun install --frozen-lockfile
bun run check          # astro check — no type errors
bun run test           # vitest pool-workers + bun test umbrella
bun x vitest --reporter verbose tests/canary.spec.ts
```

## Rollout Considerations

- **Feature flag**: none — test infrastructure only
- **Monitoring**: GitHub Actions run history; first PR after merge confirms PR gating works
- **Rollback plan**: Revert the workflow file edits and remove the `test` job; tests stay in the repo for local use

## Open Items

- [ ] Confirm `@cloudflare/vitest-pool-workers` supports `compatibility_flags: ["nodejs_compat"]` automatically from `wrangler.jsonc` (expected yes; verify in canary)
- [ ] Decide whether to migrate `src/plugins/rehype-image-cdn.test.ts` to vitest now or leave on `bun test` (recommendation: leave — rehype runs at build time, not in workerd)
