# Implementation Spec: Holistic Hardening - Phase 2

**Contract**: ./contract.md
**Estimated Effort**: L
**Blocked by**: Phase 1

## Technical Approach

Lock in current runtime behavior with integration tests **before** the Phase 3 refactor touches anything. Every test in this phase exercises the real `workerd` runtime via `@cloudflare/vitest-pool-workers` from Phase 1: the contact-form Astro Action submits, the queue handler dispatches to the agent, the workflow runs end-to-end, the agent emits state, the cron sweeps stuck rows. WorkOS HTTP and Workers AI are mocked at the module boundary; everything else is real.

These tests serve a dual purpose: they document the current behavior contract (so reviewers can spot semantic drift in Phase 3 PRs) and they form the regression net for the comprehensive DRY sweep. **No production code changes in this phase** — if a test reveals a bug, it gets a `test.todo` or `test.fails` annotation referencing a follow-up issue, not a fix here.

We use `SELF.fetch(...)` for HTTP-level scenarios (action submit, admin auth flow, agent gating), and direct module imports for queue/scheduled/agent-RPC scenarios. Workflow tests dispatch via the agent's `queueLead` and assert downstream effects (D1 row state + agent state + EMAIL.send call counts) rather than instrumenting `step.do` internals.

## Feedback Strategy

**Inner-loop command**: `bun x vitest run tests/integration/`

**Playground**: `bun x vitest tests/integration/contact-flow.spec.ts` in watch mode while writing each scenario; the pool-workers test runner gives sub-second feedback per file.

**Why this approach**: Integration tests through the real runtime are slower than units, so a focused single-file watch keeps the loop tight. Each new test file gets watched in isolation while writing; full suite runs at PR time.

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `tests/integration/contact-flow.spec.ts` | Action submit → D1 insert → queue.send → queue handler → agent.queueLead → workflow runs → notify-nick email → agent state has pending approval |
| `tests/integration/workflow-paths.spec.ts` | Workflow: spam short-circuit, consulting-fit happy path, off-fit no-qualify, reject (WorkflowRejectedError), approval timeout, edited-body branch |
| `tests/integration/worker-handlers.spec.ts` | `fetch` agent-path gating (401 unauth, 200 with valid session); `queue` retry on agent throw, ack on success; `scheduled` invokes `sweepStuckRows` |
| `tests/integration/agent-rpc.spec.ts` | `queueLead`, `approveLead`, `discardLead`, `getRecentActivity`, `sweepStuckRows` (resets stale `processing` rows, retriggers stale `pending` rows, idempotent), `recordActivity` (logs to `agent_activity`, swallows errors) |
| `tests/integration/middleware.spec.ts` | Admin path auth gate (302 to login when unauth); WS upgrade returns 401 not 302; markdown negotiation serves `.md` from ASSETS; prerender skip; `/admin/login` and `/admin/callback` allowlisted; refreshedCookie appended to response |
| `tests/integration/workos-session.spec.ts` | `validateSession` happy path / refresh path / unauthenticated fallback / refresh failure → null; `safeReturnPath` rejects `//`, `/\\`, full URLs, control chars; cookie roundtrip via `buildSessionCookie` + `readSessionCookie`; `Secure` flag flips on http localhost vs https prod |
| `tests/integration/leads-db.spec.ts` | `insertLead` defaults source; `getLeadById` returns null for missing; `patchLead` updates `updatedAt`; status enum violations rejected at SQL boundary |
| `tests/helpers/auth.ts` | Build a sealed `wos-session` cookie value the WorkOS mock recognizes; helper to attach `Cookie` header to a `SELF.fetch` request |
| `tests/helpers/workflow.ts` | `waitForLeadStatus(env, id, status, timeoutMs)` — polls D1 until the workflow advances, with a default 5s ceiling so tests fail loud instead of hanging |
| `tests/helpers/email.ts` | Stub `env.EMAIL.send` and capture calls so tests can assert subject/from/body |

### Modified Files

| File Path | Changes |
|---|---|
| `tests/setup.ts` (from Phase 1) | Reset D1 between tests via the per-suite `beforeEach` so workflow tests don't leak state |
| `tests/mocks/workers-ai.ts` (from Phase 1) | Add `mockClassifyDecision(category, confidence)` and `mockQualifyDecision(industry, geography, sizeSignal, problemShape, urgencySignal)` builders; tests pick a plan and the mock dispatches accordingly |

## Implementation Details

### Contact-flow integration test

**Pattern to follow**: Cloudflare Workers test docs `SELF.fetch` + `runInDurableObject` examples. Confirm via `cloudflare:cloudflare` skill if API names shifted.

**Overview**: One end-to-end test that submits a form-encoded body to the action endpoint and asserts every downstream effect.

```ts
// tests/integration/contact-flow.spec.ts
import { env, SELF } from 'cloudflare:test';
import { it, expect, vi } from 'vitest';
import { mockWorkersAI } from '../mocks/workers-ai';
import { resetD1, captureEmail, waitForLeadStatus } from '../helpers';

vi.mock('ai', mockWorkersAI({
  classify: { category: 'consulting-fit', confidence: 0.9, reason: 'fits' },
  qualify:  { industry: 'b2b-saas', geography: 'usa', size_signal: 'mid-market', problem_shape: 'support-ops', urgency_signal: 'in-pain' },
  draft: 'Hey — happy to chat about this.',
}));

beforeEach(resetD1);

it('contact form lands a consulting-fit lead and surfaces it for approval', async () => {
  const emails = captureEmail(env);
  const res = await SELF.fetch('http://test/_actions/contact.send', {
    method: 'POST',
    body: new URLSearchParams({ name: 'A', email: 'a@b.co', message: 'I need help with X.' }),
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  expect(res.status).toBe(200);
  const body = await res.json();
  expect(body.delivered).toBe(true);

  // D1 row materialized
  const row = await env.LEADS_DB.prepare('SELECT * FROM leads WHERE id = ?').bind(body.id).first();
  expect(row.status).toBe('pending');

  // Workflow runs and reaches awaiting-approval (queue handler is in-process)
  await waitForLeadStatus(env, body.id, 'awaiting-approval');

  // Email sent to Nick
  expect(emails).toHaveLength(1);
  expect(emails[0].subject).toMatch(/^New lead:/);

  // Agent state has the approval
  const stub = env.LEAD_TRIAGE_AGENT.get(env.LEAD_TRIAGE_AGENT.idFromName('global'));
  // (use runInDurableObject if state inspection requires direct access)
});
```

**Key decisions**:

- **One test asserts the full chain** — fragmenting it into "action calls insertLead" / "queue dispatches" / "workflow notifies" leaves seams the integration test is meant to seal
- **Module-mock `ai` package** — workflow imports `generateText` from `ai`; mocking that single export covers all three step calls (classify, qualify, draft)
- **Capture emails by stubbing `env.EMAIL.send`** — `runInDurableObject` exposes the binding, but for the worker entry's `queue` handler, monkey-patch the binding before invoking

**Implementation steps**:

1. Write `tests/helpers/email.ts` with `captureEmail(env)` returning the array, replacing `env.EMAIL.send`.
2. Write `tests/helpers/workflow.ts` with the polling helper.
3. Write `tests/helpers/auth.ts` with `signedSessionCookie('user_test')` that returns a string the workos mock recognizes.
4. Write the `contact-flow.spec.ts` happy path.
5. Add: spam path (mock returns `category: 'spam'`, assert `status === 'done'` + `outcome === 'discarded'` + zero emails).
6. Add: queue.send failure path (monkey-patch `env.LEAD_TRIAGE_QUEUE.send` to throw, assert action still returns `delivered: true` and the cron sweep recovers the row).

**Feedback loop**:
- **Playground**: `bun x vitest tests/integration/contact-flow.spec.ts`
- **Experiment**: Vary the mock's classify decision (`spam`, `consulting-fit`, `recruiting`) and assert downstream effects diverge correctly
- **Check command**: `bun x vitest run tests/integration/contact-flow.spec.ts`

### Workflow-paths test

**Pattern to follow**: agent's `runWorkflow` invocation in `src/workflows/lead-triage-workflow.ts`; SDK's workflow test patterns from `agents/workflows`.

**Overview**: Six scenarios that drive the workflow through each branch and assert the final D1 state.

**Key decisions**:

- **Drive via `agent.queueLead(leadId)`** — the public RPC surface; do not invoke `LeadTriageWorkflow.run` directly
- **For approval timeout**, dispatch a workflow then call `runInDurableObject` on the agent to wait through the workflow's wall-clock timeout. Use `vi.useFakeTimers()` with `vi.advanceTimersByTime` if the SDK timer is mockable; otherwise run with a small `APPROVAL_TIMEOUT` override via env-conditional config (acceptable for tests only)
- **Reject path** uses `agent.discardLead(workflowId)` after the workflow surfaces the approval; assert `status === 'done'` + `outcome === 'discarded'`
- **Edit path** calls `agent.approveLead(workflowId, 'edited body')` and asserts `outcome === 'edited'` and the email body equals the edit + `\n\n- Nick`

**Implementation steps**:

1. Reuse `waitForLeadStatus` to gate each scenario.
2. For each path, set up the mock plan, drive the action or call `agent.queueLead` directly, then approve/reject/wait, then assert D1 + emails.
3. Document **observed bugs** as `it.fails` with a comment "see issue #N" if any surface — do not fix here.

### Worker-handlers test

**Pattern to follow**: `src/worker.ts` re-exports `default` with `fetch`, `queue`, `scheduled`. Import the default via `import worker from '../../src/worker'` inside the test, then call `worker.queue(batch, env)` directly.

**Overview**: Three handler types, each with one happy and one failure case.

**Key decisions**:

- **Construct a `MessageBatch` by hand** — don't drive through the real queue infra in tests, it adds nondeterminism. The handler signature accepts any `MessageBatch<TriageMessage>` shape with `messages: { body, attempts, ack(), retry() }[]`
- **For `scheduled`**, build a fake `ScheduledController` with a `cron: '*/5 * * * *'` literal
- **For `fetch`**, use `SELF.fetch('/agents/lead-triage-agent/global')` — the Agents SDK matches this path; assert 401 without cookie, then attach a sealed cookie, assert 200 (or 426 if WS upgrade required — confirm with sdk)

### Middleware test

**Pattern to follow**: `src/middleware.ts:onRequest` exported via `defineMiddleware`. Call `SELF.fetch` against gated paths.

**Key cases**:

- `/admin/leads` no cookie → 302 with `Location` matching `/admin/login?return=/admin/leads`
- `/admin/leads` valid cookie → 200, `set-cookie` absent (no refresh needed)
- `/admin/leads` cookie that triggers refresh → 200, `set-cookie` present with new sealed value
- `/admin/leads` `upgrade: websocket` no cookie → 401 (not 302)
- `/admin/login` no cookie → 302 to WorkOS (allowlisted, doesn't loop)
- `/contact` `Accept: text/markdown` → 200 with `Content-Type: text/markdown` and body from `ASSETS`
- `/contact` no markdown header → 200 with html
- Prerendered route via `context.isPrerendered` → middleware short-circuits (asserted by mocking the context flag if SELF.fetch can't reproduce it; otherwise document as covered transitively)

### WorkOS session test

**Pattern to follow**: existing `src/lib/workos.ts` is already pure-ish — direct unit tests work without running the worker.

**Key cases**:

- `safeReturnPath('/admin/leads')` → `/admin/leads`
- `safeReturnPath('//evil.com/path')` → `/admin/leads`
- `safeReturnPath('/admin\rInjected: x')` → `/admin/leads`
- `readSessionCookie` correctly extracts when cookie has multiple entries
- `buildSessionCookie(value, https://birdcar.dev)` → contains `Secure`
- `buildSessionCookie(value, http://localhost)` → does not contain `Secure`
- `validateSession` happy path returns `{ user }`
- `validateSession` access-token-expired triggers refresh, returns `{ user, refreshedCookie }`
- `validateSession` refresh fails → returns `null`

### Agent RPC test

**Pattern to follow**: SDK's `runInDurableObject` for direct agent invocation.

**Key cases**:

- `queueLead(id)` returns `{ workflowId }`, increments `metrics.leadsProcessed`
- `approveLead(workflowId)` resolves the waiting workflow with metadata, clears `pendingApprovals`
- `discardLead(workflowId)` rejects the workflow, clears `pendingApprovals`
- `sweepStuckRows()`:
  - resets `processing` rows older than `STUCK_ROW_THRESHOLD_MINUTES` to `pending`
  - retriggers `pending` rows older than threshold
  - is idempotent — running it twice in a row produces no double workflows (verified by counting `agent_activity` `load-lead` rows)
  - skips `awaiting-approval` rows (deliberate dwell state)
- `getRecentActivity(limit)` returns rows in `ts DESC` order, respects limit
- `recordActivity` insert failure does not throw to caller (best-effort)

### Leads-DB test

**Pattern to follow**: existing `src/lib/leads.ts` Drizzle calls.

**Key cases**:

- `insertLead` populates default `source` when omitted
- `getLeadById` returns `null` for missing id (not throws)
- `patchLead` updates `updatedAt`; concurrent patches both succeed (last-write-wins)
- Status enum violation (`status: 'invalid'`) rejected at the Drizzle layer — confirms the schema enum is enforced

## Testing Requirements

### Integration Tests

| Test File | Coverage |
|---|---|
| `tests/integration/contact-flow.spec.ts` | Form submit → workflow → approval (3 scenarios) |
| `tests/integration/workflow-paths.spec.ts` | 6 workflow branches |
| `tests/integration/worker-handlers.spec.ts` | fetch routing, queue retry, scheduled cron |
| `tests/integration/agent-rpc.spec.ts` | All 6 callable methods + sweep idempotency |
| `tests/integration/middleware.spec.ts` | Auth gate, WS upgrade, markdown negotiation, allowlist |
| `tests/integration/workos-session.spec.ts` | Session lifecycle + safeReturnPath edge cases |
| `tests/integration/leads-db.spec.ts` | CRUD + enum enforcement |

**Target**: ≥20 integration tests in this phase. Combined with Phase 4 unit tests (≥10) the contract's 30+ goal is met.

### Manual Testing

- [ ] After all tests written, run `bun run test` — full green
- [ ] Re-run on a clean clone to confirm the test suite is hermetic
- [ ] Confirm CI from Phase 1 runs them on PR

## Error Handling

| Scenario | Strategy |
|---|---|
| Test reveals a real bug | Annotate as `it.fails` + open a tracked issue + reference in spec; do not fix in this phase |
| Mock drift after future SDK update | Mocks live in `tests/mocks/`, single source of truth — update once |
| Flaky workflow timing | `waitForLeadStatus` with bounded poll; if flaky, raise the timeout once and document; never silence |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| `waitForLeadStatus` | Test hangs forever | Workflow throws and never advances row | CI runs to timeout | Bound timeout to 5s default; throw with last-seen row state |
| `captureEmail` | Mutates shared env across tests | Forgetting to restore in `afterEach` | Cross-test pollution | Restore in `afterEach` always; gate with `beforeEach(resetEmail)` |
| Mock `ai` module | Real workflow adds a step the mock doesn't anticipate | Future product addition | Mock returns wrong shape, opaque failure | Mock throws on unrecognized prompt prefix |
| Agent `queueLead` test | Real workflow registration races with test setup | Workflow binding not yet warm | First test in suite fails intermittently | Pre-warm in `beforeAll` by invoking once with a known id |
| WS upgrade test | `SELF.fetch` cannot send `upgrade: websocket` headers in pool-workers | Test framework limitation | WS branch uncovered | Drop to direct `worker.fetch(request, env, ctx)` invocation with hand-built Request including upgrade header |

## Validation Commands

```bash
bun run check                                # astro check
bun run test                                 # full suite
bun x vitest run tests/integration/          # this phase only
bun x vitest --reporter verbose tests/integration/contact-flow.spec.ts
```

## Rollout Considerations

- **Feature flag**: none — tests don't ship to runtime
- **Monitoring**: CI test job duration; if >2 min, parallelize via `vitest --shard` or split workflow scenarios into a separate job
- **Rollback plan**: tests can be removed; they don't affect production

## Open Items

- [ ] Confirm `vitest-pool-workers` supports calling Astro Action endpoints via `SELF.fetch` (the `_actions/contact.send` route is generated by Astro at build) — if not, dispatch via direct module import of the action handler
- [ ] Decide pattern for testing the workflow's `APPROVAL_TIMEOUT` without burning real wall time — fake timers vs env override
