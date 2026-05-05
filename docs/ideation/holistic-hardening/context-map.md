# Context Map: holistic-hardening

**Phase**: 3
**Scout Confidence**: 86/100
**Verdict**: GO

(Phase 1 map archived in commit 90f999f; Phase 2 map replaced by this one. Prior phase notes preserved in "Prior Phases" at the bottom.)

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 18/20 | All 8 hotspots verified. Open item: lib/leads.ts deletion vs. transitional alias. **5** admin .astro files call `getEnv` (not 3 listed in spec). |
| Pattern familiarity | 18/20 | Patterns confirmed verbatim. `getDataAttr` already exists locally in query.ts:145-150 — returns `string \| undefined`, divergence from spec's `string`. |
| Dependency awareness | 17/20 | Phase 2 tests cover every target. `tests/integration/leads-db.spec.ts` directly imports `insertLead`/`getLeadById`/`patchLead` — must keep working through migration. |
| Edge case coverage | 17/20 | Spec failure modes thorough. `loadEnv` returns `EnvWithAssets`; `getCloudflareEnv` must preserve. `extractText` semantics differ (embed.ts trims, math.ts doesn't). |
| Test strategy | 16/20 | Inner-loop `bun x vitest run tests/integration/`. **Risk**: log event names not asserted in tests — preservation depends on manual diffs. |

## Key Patterns

- `src/middleware.ts:31-38` — `loadEnv()` returns `EnvWithAssets | null` (with optional ASSETS).
- `src/lib/leads.ts:28-31` — `getEnv(_locals)` body to delegate.
- `src/lib/log.ts:24-36` — `errorFields(err)` shape `{name, message, stack?}`.
- `src/lib/rehype/chart.ts:138-149` — `extractCaptionText` calls local recursive `collect`. Caller does `.trim()` after.
- `src/lib/rehype/embed.ts:101-105` — local `extractText` includes `.trim()` on recursion result. Migration: shared utility doesn't trim; call site needs explicit `.trim()`.
- `src/lib/rehype/math.ts:52-56` — local `extractText` no trim. Caller already trims (line 11). Shared utility works.
- `src/lib/rehype/query.ts:145-150` — `getDataAttr` returns `string | undefined`; callers (32-37) check truthy. Shared `string` return preserves semantics (empty string equally falsy).
- `src/lib/rehype/figure-src.ts:48-57` — error elements identical across chart/include/figure-src with different `class` values. `buildErrorElement(message, className?)` accepts override.
- `src/scripts/admin-client.ts:22-33` — existing `connectAgent`. New `AgentController` class replaces.
- `src/scripts/admin-lead-detail.ts:52-65` — local `setButtonsDisabled(disabled)` closes over `root`. Shared `(scope, disabled)` requires explicit pass.
- `src/lib/workos.ts:64-94, 116-124, 144-151` — low-level helpers. `session.ts` composes; do not delete the low-level ones.
- `src/workflows/lead-triage-workflow.ts:191-231, 295-331` — claim/release patterns for notify-nick and send-reply with rollback on email send failure.
- `src/agents/lead-triage-agent.ts:238-277` — `sweepStuckRows` D1 reads/writes for `findStaleProcessing` / `resetStaleProcessing` / `findStalePending`.
- `src/actions/index.ts:42-59` — outer try/catch around insertLead. Inner try/catch around `LEAD_TRIAGE_QUEUE.send` at 65-70 STAYS (different semantics — non-blocking).

## Dependencies

| Modified file | Consumed by |
|---|---|
| `src/middleware.ts` | tests/integration/middleware.spec.ts |
| `src/lib/leads.ts:getEnv` | actions/index.ts; pages/admin/login.ts, callback.ts, logout.ts; **pages/admin/leads/[id].astro, leads/index.astro, activity/index.astro** (5 callers, not 3) |
| `src/lib/leads.ts:insertLead/getLeadById/patchLead` | actions/index.ts; **tests/integration/leads-db.spec.ts:5,9,17,22,...** (test depends on direct exports — keep transitional thin wrappers) |
| `src/lib/workos.ts:readSessionCookie/validateSession/buildSessionCookie/buildClearedSessionCookie` | middleware.ts; worker.ts; admin/callback.ts, admin/logout.ts; tests/integration/workos-session.spec.ts (and others transitively) |
| `src/worker.ts` | tests/integration/worker-handlers.spec.ts; tests/integration/contact-flow.spec.ts |
| `src/agents/lead-triage-agent.ts` | agent-rpc.spec.ts, workflow-paths.spec.ts, contact-flow.spec.ts |
| `src/workflows/lead-triage-workflow.ts` | workflow-paths.spec.ts |
| `src/scripts/admin-leads-list.ts`, `admin-lead-detail.ts` | Browser bundle only — `bun run build:ci` validates. |
| `src/lib/rehype/*.ts` | Astro markdown pipeline only — `bun run build:ci` validates. |
| `src/actions/index.ts` | tests/integration/contact-flow.spec.ts (imports `server` and calls `server.contact.send`) |

## Conventions

- **Naming**: `src/lib/` for shared modules. Class names PascalCase (`LeadsRepo`, `AgentController`). Functions camelCase.
- **Imports**: `import type` for type-only. No barrel files. Lazy `import('cloudflare:workers')` (not static).
- **Error handling**: `errorFields(err)` for log payloads. Throw `ActionError` at action boundary; let other errors propagate.
- **Types**: `Env` from `src/types`; `Lead`/`NewLead` from `src/db/schema`; `Database` from `src/db/client`.
- **Testing**: NO new tests in Phase 3. Phase 2 suite is the regression net. Inner loop: `bun x vitest run tests/integration/`. Full: `bun run test`.
- **Logging contract**: Workers Logs at 100% sampling. **DO NOT rename event names** — they're operational contract: `lead.received`, `lead.persisted`, `lead.persist.failed`, `lead.enqueued`, `lead.enqueue.failed`, `queue.batch.received`, `queue.dispatching`, `queue.dispatched`, `queue.dispatch.failed`, `cron.scheduled.fired/ok/failed`, `agent.queueLead.start/ok/failed`, `agent.workflow.complete`, `agent.workflow.failed`, `agent.activity.insert.failed`, `sweep.processing.reset`, `sweep.pending.retriggering`, `sweep.pending.retriggered`, `sweep.pending.retrigger.failed`, `notify-nick.skipped`, `send-reply.skipped`, `admin.callback.failed`, `admin.activity.load.failed`.

## Risks

1. **`getCloudflareEnv` return type drops `ASSETS`** — `loadEnv` returns `EnvWithAssets`. `getCloudflareEnv` signature `Promise<Env | null>` would force a cast at the markdown branch. Mitigation: include `ASSETS?: AssetsBinding` in the returned type, or use `EnvWithAssets`.
2. **Memoization across worker isolates** — pool-workers may persist memoized null between tests. Add `__resetEnvCacheForTests()` to test setup OR don't memoize.
3. **`extractText` trim semantics differ** — embed.ts call site needs explicit `.trim()` after migration to shared utility.
4. **`getDataAttr` return type narrowing** — keep `string`; truthy guards at call sites preserve semantics.
5. **`AgentController.call` Promise typing** — `as Promise<T>` unavoidable; AgentClient.call returns `unknown`.
6. **LeadsRepo class instance** — drizzle-orm/d1 stateless; safe to construct per call.
7. **`wrapAction` ctx type** — use `ActionAPIContext` from `astro:actions` (mocked in test as identity-on-handler).
8. **`leads-db.spec.ts` direct imports** — keep `lib/leads.ts` thin wrappers in Phase 3; defer deletion to Phase 4.
9. **5 admin .astro pages call `getEnv`** — `pages/admin/leads/[id].astro`, `leads/index.astro`, `activity/index.astro` plus 3 .ts auth-flow files.
10. **Workflow notify-nick claim/release** — preserve try/catch around email send with explicit rollback.
11. **Mock initialization order** — keep transitional re-exports until Phase 4 to avoid vitest hoisting surprises.

## Prior Phases

**Phase 1** (commit 90f999f): canary spec only. Pool-workers + cloudflare:test API surface confirmed.

**Phase 2** (merged): 7 spec files + 3 helpers. 69 passing + 1 expected fail + 4 todos. Mock convention for AI: dispatch on `system`. Per-test agent IDs (not `'global'`) avoid DO state pollution. `vi.mock('ai', ...)` uses async dynamic import to dodge the hoisting hazard.
