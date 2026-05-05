# Context Map: holistic-hardening

**Phase**: 2
**Scout Confidence**: 82/100
**Verdict**: GO

(Phase 1's map is in commit 90f999f if needed — this file is now the Phase 2 map.)

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 17/20 | 7 spec files + 3 helpers + 2 mock extensions. Two open items: (a) Astro Action route shape — confirmed `/_actions/contact.send`; (b) `APPROVAL_TIMEOUT` test approach — pool-workers ships `introspectWorkflowInstance(...).modify(m => m.forceEventTimeout(...))` (no env-override needed). |
| Pattern familiarity | 17/20 | Phase 1's `canary.spec.ts` is the in-repo reference. `cloudflare:test` API confirmed via package types. Mock skeletons exist; need extension. |
| Dependency awareness | 17/20 | Purely additive; no production code modified. Tests consume worker, agent, workflow, middleware, workos, leads modules at module-import or `SELF.fetch` boundaries. |
| Edge case coverage | 16/20 | Spec failure modes good. Phase-2-specific edges: WS upgrade via SELF.fetch, send_email remote: true invocation, DO setState async-write timing, queue handler via createMessageBatch + getQueueResult helpers (canonical). |
| Test strategy | 15/20 | Pool-workers + vitest 4 confirmed. **Gap**: `vi.mock('ai', ...)` first usage at scale — canary doesn't exercise. Workflow passes verb in `system`, not `prompt` — current mock dispatches on `prompt` regex, must switch to `system`. |

## Key Patterns

- **`tests/canary.spec.ts`** — Phase 1 reference. `import { env } from 'cloudflare:workers'`, AAA blocks, Drizzle round-trip via `getDb(env.LEADS_DB)`.
- **`tests/setup.ts`** — `beforeAll` applies migrations lex-sorted; `afterEach` runs `DELETE FROM leads`. May need extension for DO `agent_activity` cleanup and `abortAllDurableObjects()`.
- **`@cloudflare/vitest-pool-workers/types/cloudflare-test.d.ts`** — confirms: `SELF: Fetcher`, `runInDurableObject<O,R>(stub, callback)`, `createScheduledController({ cron })`, `createMessageBatch<Body>(queueName, messages)`, `getQueueResult(batch, ctx)`, `introspectWorkflowInstance(workflow, instanceId)` with `.modify(m => m.disableSleeps()/forceEventTimeout())`, `applyD1Migrations`, `reset()`, `abortAllDurableObjects()`. Both `env`/`SELF` here are deprecated in favor of `cloudflare:workers` `env` / `exports.default.fetch()` — but functional. Use `env` from `cloudflare:workers`; pull `SELF` and helpers from `cloudflare:test`.
- **`src/workflows/lead-triage-workflow.ts:69-73,97-102,138-142`** — three `generateText({ model, system, prompt })` call sites. Mock target. **Workflow passes verb in `system`** (`classifyPrompt`/`qualifyPrompt` outputs distinct system strings), not `prompt`. Current mock dispatches on `prompt` regex — **must switch to `system`**.
- **`src/actions/index.ts`** — Astro Action `contact.send` accepts `accept: 'form'`. Astro 6 generates `/_actions/[...path]` routes per `node_modules/astro/dist/actions/consts.js:13`. So `POST /_actions/contact.send` form-encoded is correct.
- **`src/lib/triage-config.ts`** — `APPROVAL_TIMEOUT = '7 days'`, `STEP_RETRY` shape, `STUCK_ROW_THRESHOLD_MINUTES = 10`. Test approach for timeout: `introspectWorkflowInstance(env.LEAD_TRIAGE_WORKFLOW, workflowId).modify(m => m.forceEventTimeout(...))`.
- **`src/db/schema.ts`** — status enum: `['pending','processing','awaiting-approval','done','discarded']`. The legacy `'discarded'` status entry is unused by current workflow paths (which use `status='done', outcome='discarded'`); enum-violation test should target `'invalid'`, not `'discarded'`.

## Dependencies

Phase 2 is purely additive. Test files exercise:

- `src/actions/index.ts` ← `contact-flow.spec.ts` (via SELF.fetch)
- `src/worker.ts` (default fetch/queue/scheduled) ← `worker-handlers.spec.ts` (direct module + SELF.fetch)
- `src/agents/lead-triage-agent.ts` ← `agent-rpc.spec.ts` (via runInDurableObject)
- `src/workflows/lead-triage-workflow.ts` ← `workflow-paths.spec.ts` (via agent.queueLead, observed via D1 + EMAIL.send capture + introspectWorkflowInstance)
- `src/middleware.ts` ← `middleware.spec.ts` (via SELF.fetch)
- `src/lib/workos.ts` ← `workos-session.spec.ts` (direct + vi.mock('@workos-inc/node'))
- `src/lib/leads.ts` ← `leads-db.spec.ts` (direct against env.LEADS_DB)

Mock seams: `vi.mock('ai', ...)` and `vi.mock('@workos-inc/node', ...)`.

## Conventions

- **Naming**: `tests/integration/*.spec.ts` for new files; helpers `tests/helpers/*.ts`.
- **Imports**: `import { env } from 'cloudflare:workers'`; `import { SELF, runInDurableObject, createMessageBatch, createScheduledController, createExecutionContext, getQueueResult, introspectWorkflowInstance } from 'cloudflare:test'`; vitest names from `vitest`.
- **Error handling**: tests assert behavior. Bug-revealing tests use `it.fails` or `it.todo` referencing follow-up — no source fixes in Phase 2.
- **Types**: `import type` for type-only.
- **Inner loop**: `bun x vitest run tests/integration/` (full phase) or `bun x vitest run tests/integration/<file>.spec.ts` (focused).

## Risks

1. **`vi.mock('ai', ...)` first usage at Phase 2 scale** — current mock dispatches on `prompt`, but verb is in `system`. **Mitigation**: update mock to dispatch on `system` *before* writing the first workflow test.
2. **`APPROVAL_TIMEOUT = '7 days'`** — use `introspectWorkflowInstance(...).modify(m => m.forceEventTimeout({ name: '<wait-for-approval-step-name>' }))`. Identify exact step name from `node_modules/agents/dist/workflows.js` if not obvious. Fallback: `disableSleeps()`.
3. **Queue handler test path** — prefer `createMessageBatch + createExecutionContext + getQueueResult` over hand-rolling `worker.queue(batch, env)`. Cleaner ack/retry assertions.
4. **WS upgrade test via `SELF.fetch`** — should accept `headers: { upgrade: 'websocket' }`. Fallback: direct `worker.fetch(request, env, ctx)` with hand-built `Request` if `SELF` blocks the upgrade header.
5. **`send_email` `remote: true`** — `captureEmail(env)` monkey-patches `env.EMAIL.send`. Confirm `env.EMAIL` is writable; fallback to `vi.spyOn(env.EMAIL, 'send').mockResolvedValue(undefined)`.
6. **Agent `setState` async timing** — `setState` writes are async-flushed. Inspecting `instance.state.pendingApprovals` immediately may read pre-flush. **Mitigation**: poll with bounded timeout (mirror `waitForLeadStatus`), or assert via `agent_activity` rows (sql-synchronous).
7. **`agent_activity` table accumulates across tests** — DO sqlite is not wiped by `afterEach DELETE FROM leads`. **Mitigation**: `abortAllDurableObjects()` in `afterEach`, or explicit `runInDurableObject(stub, (i) => i.sql\`DELETE FROM agent_activity\`)`.
8. **DO sqlite `agent_activity` not created until `onStart` fires** — first call to `agent.queueLead` triggers `onStart`. Tests that call `getRecentActivity` *first* will fail. **Mitigation**: prewarm in `beforeEach` via `runInDurableObject(stub, async () => {})`.
9. **Shared global env mutations** — pool-workers shares isolates across tests in a file. Use `afterEach` (not `afterAll`) and explicit restore.
