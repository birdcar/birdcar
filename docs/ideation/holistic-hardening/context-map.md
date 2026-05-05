# Context Map: holistic-hardening

**Phase**: 1
**Scout Confidence**: 84/100
**Verdict**: GO

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 18/20 | Spec lists 7 new files and 3 modified files with concrete content. Spec offers `.github/workflows/test.yml` *or* modifying `deploy.yml` — modifying `deploy.yml` is correct because the existing schedule trigger lives there. Phase 2 fixtures/mocks (`tests/fixtures/leads.ts`, `tests/mocks/workers-ai.ts`, `tests/mocks/workos.ts`) are scaffolded here; only the canary test is asserted in Phase 1 testing requirements. |
| Pattern familiarity | 16/20 | `src/plugins/rehype-image-cdn.test.ts` shows `bun:test` AAA conventions; `extractJson` ("pattern to follow" for AI mocks) at `src/workflows/lead-triage-workflow.ts:357`. No existing vitest infrastructure — first stand-up. `@cloudflare/vitest-pool-workers` API drift is the unknown. |
| Dependency awareness | 17/20 | Bindings declared in `wrangler.jsonc` are consumed by `src/types.ts:51-67`, `src/worker.ts`, `src/agents/lead-triage-agent.ts`, `src/workflows/lead-triage-workflow.ts`, `src/lib/leads.ts`, `src/db/client.ts`, `src/lib/workos.ts`, `src/middleware.ts`. Phase 1 changes touch zero of these — additive only. The deploy workflow change has no dependents. |
| Edge case coverage | 16/20 | Spec's Failure Modes table is thorough. Additional edges: (a) DO `LeadTriageAgent` migration (`new_sqlite_classes`) must flow from `wrangler.jsonc` to pool-workers; (b) `nodejs_compat` flag flows from `wrangler.jsonc:17-19`; (c) `send_email` binding has `remote: true` — likely cannot resolve in miniflare without explicit local stub; canary should assert `env.EMAIL` exists, not invoke. |
| Test strategy | 17/20 | Clear: `bun x vitest run` for pool-workers + `bun test src/plugins/` for rehype, umbrella'd as `bun run test`. Migrations live in `/migrations/` (4 SQL files); `tests/setup.ts` reads journal, applies in order against `:memory:` D1. CI command set explicit. |

## Key Patterns

- `src/plugins/rehype-image-cdn.test.ts` — `bun:test` style: `import { describe, test, expect } from 'bun:test'`. Stays on `bun test`; vitest tests live under `tests/` with `.spec.ts` extension to disambiguate runners.
- `src/workflows/lead-triage-workflow.ts:357` (`extractJson`) — pattern referenced for the workers-ai mock: deterministic prompt-prefix → fixed JSON output. `generateText` call sites at lines 56-69, 97-102, 138 — Phase 2 mock target is the `ai` package's `generateText`.
- `src/types.ts:49-69` — `Cloudflare.Env` augmentation defines exact binding shape pool-workers must produce. `SESSION` is optional; the canary asserts binding presence accordingly.
- `src/lib/leads.ts:33-55` — Drizzle insert pattern the canary mirrors for the round-trip insert+select check.
- `wrangler.jsonc` — single source of truth for bindings; `compatibility_date: 2026-04-15`, `compatibility_flags: ["nodejs_compat"]`, `migrations_dir: ./migrations`, DO `migrations: [{ tag: "v1", new_sqlite_classes: ["LeadTriageAgent"] }]`.

## Dependencies

Phase 1 is purely additive — no production source files modified. Touched files:

- `package.json` — adds devDeps + 3 scripts. No consumer impact.
- `tsconfig.json` — spec asks to add `compilerOptions.types`; **scout flags this as risky** (would shrink auto-included `@types/*`). Resolution: omit the root tsconfig change; vitest-pool-workers' `cloudflare:test` virtual module is typed via its own `package.json#types` and does not need a `types` array entry. If `astro check` complains, add `tests/tsconfig.json` extending root with the array.
- `.github/workflows/deploy.yml` — currently 45 lines, single `deploy` job, triggers `push: main`, `schedule`, `workflow_dispatch`. Add `pull_request` trigger and `test` job; `deploy` requires it via `needs:`. Make the `if:` on deploy explicit.
- `migrations/` — four SQL files. **Scout flags**: `migrations/meta/_journal.json` enumerates only `0000_woozy_bishop`; `0001`-`0003` were hand-authored. Setup must `glob + sort migrations/*.sql` lex order, not trust the journal.

## Conventions

- **Naming**: kebab-case file names. Test files colocated as `*.test.ts` for `bun:test`; pool-workers tests under `tests/` with `.spec.ts`.
- **Imports**: NodeNext-style; type-only imports use `import type`. `@cloudflare/workers-types` is canonical type provider; `cloudflare:workers` is dynamic-imported in `lib/leads.ts:29` and `middleware.ts:33` to keep it out of the prerender bundle.
- **Error handling**: Throw at boundaries; structured `errorFields(err)` (`src/lib/log.ts:24`) for telemetry. JSON objects with stable `event` keys.
- **Types**: Strict (Astro's strict tsconfig). Discriminated unions and zod schemas. `Cloudflare.Env` augmented in `src/types.ts`.
- **Testing**: `bun:test` colocated with source. Phase 1 introduces vitest under `tests/`; runners are kept disjoint by directory.
- **Package manager**: `bun` everywhere; `bun x vitest` per spec.

## Risks

1. **Root `tsconfig.json compilerOptions.types`** — explicit array shrinks auto-included `@types/*`. **Mitigation**: omit unless `astro check` fails; fall back to `tests/tsconfig.json` extending root.
2. **Migrations journal incompleteness** — `_journal.json` lists only `0000`. **Mitigation**: glob+sort `migrations/*.sql` rather than read journal.
3. **`send_email` `remote: true`** — miniflare may not resolve to a usable stub. **Mitigation**: canary asserts `env.EMAIL` exists, never invokes `.send()`.
4. **DO `new_sqlite_classes` migration tag** — `wrangler.jsonc:110-115` declares it. Pool-workers should auto-honor via `configPath`. **Mitigation**: canary doesn't instantiate the DO in this phase, so this doesn't matter for Phase 1; revisit in Phase 2.
5. **Umbrella test script `&&` short-circuit** — vitest failure means rehype tests don't run locally. Acceptable for now; document.
6. **`@cloudflare/vitest-pool-workers` version pinning** — repo has no `wrangler` direct devDep. **Mitigation**: add `wrangler` as a pinned devDep alongside pool-workers.
7. **`pull_request` deploy gate `if:`** — make explicit: `if: github.event_name == 'push' || github.event_name == 'schedule' || github.event_name == 'workflow_dispatch'`.
