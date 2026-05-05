# Implementation Spec: Holistic Hardening - Phase 3

**Contract**: ./contract.md
**Estimated Effort**: L
**Blocked by**: Phase 2 (regression net must exist before refactoring)

## Technical Approach

Eliminate all 8 DRY hotspots identified during exploration in a single-phase comprehensive sweep. Each refactor follows the same shape: identify the duplicated pattern, extract a single-source-of-truth module under `src/lib/` (or `src/scripts/` for browser code), replace every call site, run Phase 2 tests, commit. Phase 2 tests are the safety net — if a Phase 2 test fails after a refactor, the refactor changed semantics and must be revised.

The refactors are **independent** of each other (the env loader doesn't depend on the session module, the rehype utils are unrelated to admin scripts), so they can ship in any order. Order them by **blast radius**: lowest-blast (rehype utils, CLI helpers) first to build confidence, highest-blast (session module — touches auth) last so a regression caught by tests has minimum churn to undo.

We deliberately **do not** introduce new abstractions where a duplicate exists only twice (e.g., the dynamic `import('cloudflare:workers')` appears in 3 places — qualifies; `getDb(d1)` appears at every call site but `getDb` is already the abstraction — does not qualify). The CLI helper extraction in `scripts/cf-bootstrap.ts` and `scripts/cf-create-admin.ts` is **out of scope** per the contract.

## Feedback Strategy

**Inner-loop command**: `bun x vitest run tests/integration/`

**Playground**: After each extraction, run the integration test file most relevant to the refactored surface (e.g., `workos-session.spec.ts` after the session module extraction) in watch mode while updating call sites. Full integration suite on each commit.

**Why this approach**: The refactor is mechanical — extract, replace, validate. Tight feedback against the integration suite from Phase 2 catches semantic drift; type errors catch shape drift. Storybook/dev-server playgrounds aren't useful for a refactor with no UI.

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `src/lib/env.ts` | Memoized `getCloudflareEnv()` wrapping the dynamic `import('cloudflare:workers')`; single import site for the runtime env. |
| `src/lib/session.ts` | High-level auth API: `requireSession(request, env)`, `clearSession(url)`, `attachRefreshedSession(response, refreshedCookie, url)`. Wraps the existing low-level helpers. |
| `src/lib/event-log.ts` | `logEvent(event, data, error?)` and `logError(event, data, error)` — single shape for `console.log` / `console.error` payloads with `errorFields` baked in. |
| `src/lib/rehype/utils.ts` | Shared `extractText(node)`, `getDataAttr(node, name)` (handles dual `data-foo`/`dataFoo` keys), `buildErrorElement(message, className?)`. |
| `src/scripts/admin-controller.ts` | Browser-side `AgentController` class wrapping the `connectAgent` subscription pattern + `setButtonsDisabled(scope, disabled)`, `showNotice(scope, text)` DOM helpers. |
| `src/lib/leads-repo.ts` | Typed repository: `LeadsRepo(d1)` with `insert(input)`, `findById(id)`, `patch(id, patch)`, `claimNotificationSlot(id)`, `releaseNotificationSlot(id)`, `claimResponseSlot(id)`, `releaseResponseSlot(id)`, `markStatus(id, status, outcome?)`, `findStaleProcessing(cutoff)`, `findStalePending(cutoff)`. |
| `src/lib/with-action-error.ts` | `wrapAction(handler)` translates thrown internal errors into `ActionError({ code: 'INTERNAL_SERVER_ERROR', message })` with consistent log shape. |

### Modified Files

| File Path | Changes |
|---|---|
| `src/lib/leads.ts` | Replace `getEnv()` body with `getCloudflareEnv(_locals)`. Remove inline `import('cloudflare:workers')`. Re-export `LeadsRepo` so the action's existing import path works during transition (delete after one cycle). |
| `src/middleware.ts` | Replace `loadEnv()` with `getCloudflareEnv()`. Replace the auth gate's manual `readSessionCookie` + `validateSession` + `buildSessionCookie` chain with `requireSession(request, env)` + `attachRefreshedSession(response, ...)`. |
| `src/worker.ts` | Replace agent-path gate with `requireSession`. Replace structured logs with `logEvent` / `logError`. |
| `src/pages/admin/login.ts` | Use `getCloudflareEnv()` instead of `getEnv()`; behavior unchanged |
| `src/pages/admin/callback.ts` | Same. Use `attachRefreshedSession` only if the post-auth flow ever needs it (currently only sets initial cookie — leave inline for clarity, but route through `session.ts` helpers) |
| `src/pages/admin/logout.ts` | Use `clearSession(url)` to build the cleared cookie |
| `src/actions/index.ts` | Wrap `contact.send` handler with `wrapAction`; remove inline try/catch around `insertLead` (let it throw — wrapper logs + maps to `ActionError`). Replace `console.log/error` with `logEvent/logError`. |
| `src/lib/rehype/chart.ts` | Replace local `extractCaptionText` (and `extractText` patterns) with shared `extractText`; replace dual-prop `dataSrc \|\| data-src` accesses with `getDataAttr(node, 'src')` |
| `src/lib/rehype/embed.ts` | Same |
| `src/lib/rehype/include.ts` | Same; reuse `buildErrorElement('Include not found: ...')` instead of inlined error node |
| `src/lib/rehype/math.ts` | Same |
| `src/lib/rehype/figure-src.ts` | Same |
| `src/lib/rehype/query.ts` | Same |
| `src/scripts/admin-leads-list.ts` | Use `AgentController` + shared DOM helpers |
| `src/scripts/admin-lead-detail.ts` | Same; remove local `setButtonsDisabled` / `showNotice` definitions |
| `src/agents/lead-triage-agent.ts` | Replace D1 access with `LeadsRepo`. Replace structured logs with `logEvent`. |
| `src/workflows/lead-triage-workflow.ts` | Replace inline `db.update().set({notifiedAt: ...}).where(...)` claim pattern with `repo.claimNotificationSlot(leadId)` etc. Replace D1 reads with repo methods. |

### Deleted Files

None — all extractions add new files, modifications stay in place.

## Implementation Details

### 1. `src/lib/env.ts` — env loader (lowest blast radius)

**Pattern to follow**: existing `loadEnv()` in `src/middleware.ts:31-38` and `getEnv()` in `src/lib/leads.ts:28-31`.

```ts
// src/lib/env.ts
import type { Env } from '../types';

let cached: Env | null | undefined;

/**
 * Resolve the Cloudflare runtime env. Astro 6 removed `Astro.locals.runtime.env`;
 * the supported pattern is `import { env } from 'cloudflare:workers'`. Imported
 * lazily so the virtual module stays out of the Node prerender bundle.
 *
 * Memoized after first successful resolve — the module is import-once anyway,
 * but the memo also turns failure (Node context) into a fast `null` instead
 * of repeating the throw.
 */
export async function getCloudflareEnv(): Promise<Env | null> {
  if (cached !== undefined) return cached;
  try {
    const { env } = await import('cloudflare:workers');
    cached = (env as Env) ?? null;
  } catch {
    cached = null;
  }
  return cached;
}

/** Test-only reset. Do not call from production code. */
export function __resetEnvCacheForTests(): void { cached = undefined; }
```

**Key decisions**:

- **Memoize on success only** — if Node context returns null on first call (during prerender) but later worker context could resolve, the memo is harmless because each isolate is a fresh module instance
- **`__resetEnvCacheForTests` is dunder-prefixed** — signals "do not import in production"; tests reset between runs

**Implementation steps**:

1. Create the file.
2. Update `src/lib/leads.ts:getEnv` to delegate (`return getCloudflareEnv()`); keep the export name as a thin alias. Mark deprecated in a comment.
3. Update `src/middleware.ts` to call `getCloudflareEnv()` directly; delete local `loadEnv`.
4. Run `tests/integration/middleware.spec.ts` — must stay green.
5. Update other callers (admin/login.ts, callback.ts, logout.ts) to use `getCloudflareEnv()` directly.
6. Once all callers are migrated, **delete** `getEnv` from `lib/leads.ts` (it's just an alias now).

**Feedback loop**:
- **Playground**: `bun x vitest tests/integration/middleware.spec.ts`
- **Experiment**: Add a test asserting `getCloudflareEnv()` returns the same reference twice in a row (memoization)
- **Check command**: `bun x vitest run tests/integration/`

### 2. `src/lib/rehype/utils.ts` — rehype helpers (low blast)

**Pattern to follow**: existing `extractCaptionText` in `src/lib/rehype/chart.ts:138`; existing dual-prop access in chart, embed, include.

```ts
// src/lib/rehype/utils.ts
import type { Element, Node } from 'hast';

export function extractText(node: Node): string {
  if (node.type === 'text') return (node as { value: string }).value || '';
  if ('children' in node && Array.isArray((node as Element).children)) {
    return (node as Element).children.map(extractText).join('');
  }
  return '';
}

export function getDataAttr(node: Element, name: string): string {
  // hast normalizes data-* attributes to camelCase but the original kebab-case
  // also appears in some pipelines. Read both, prefer kebab form.
  const props = node.properties ?? {};
  const kebab = props[`data-${name}`];
  const camel = props[`data${name[0].toUpperCase()}${name.slice(1)}`];
  return String(kebab ?? camel ?? '');
}

export function buildErrorElement(message: string, className = 'bfm-error'): Element {
  return {
    type: 'element',
    tagName: 'div',
    properties: { class: className },
    children: [{ type: 'text', value: message }],
  };
}
```

**Implementation steps**:

1. Create the file.
2. In each of the 6 rehype plugins, import and replace the local definitions / inline accesses.
3. The existing `src/plugins/rehype-image-cdn.test.ts` should continue to pass — it doesn't import from `lib/rehype/` so it's unaffected.

### 3. `src/lib/event-log.ts` — structured logging

**Pattern to follow**: existing `errorFields` in `src/lib/log.ts`.

```ts
// src/lib/event-log.ts
import { errorFields } from './log';

type LogContext = Record<string, string | number | boolean | null | undefined>;

export function logEvent(event: string, ctx: LogContext = {}): void {
  console.log({ event, ...ctx });
}

export function logWarn(event: string, ctx: LogContext = {}, err?: unknown): void {
  console.warn({ event, ...ctx, ...(err ? { error: errorFields(err) } : {}) });
}

export function logError(event: string, ctx: LogContext, err: unknown): void {
  console.error({ event, ...ctx, error: errorFields(err) });
}
```

**Key decisions**:

- **Three explicit functions, not one `log(level, ...)`** — call sites are clearer; matches existing `console.log/warn/error` usage one-for-one
- **`logError` requires the error** — non-error errors get `logWarn(event, ctx, err)`. This forces call sites to think about which signal level matters
- **Context is `Record<string, primitive | null | undefined>`** — keeps payloads serializable for Workers Logs

**Implementation steps**:

1. Create the file.
2. Mechanically replace `console.log({ event: 'X', leadId: id })` with `logEvent('X', { leadId: id })` etc., in: `worker.ts`, `actions/index.ts`, `agents/lead-triage-agent.ts`, `workflows/lead-triage-workflow.ts`, `pages/admin/callback.ts`.
3. **Keep raw `console.*` calls** in modules that don't follow the structured-event convention (e.g. browser scripts) — only refactor the structured ones.

### 4. `src/scripts/admin-controller.ts` — browser-side controller

**Pattern to follow**: existing inline `connectAgent` calls in `admin-leads-list.ts` and `admin-lead-detail.ts`.

```ts
// src/scripts/admin-controller.ts
import { AgentClient } from 'agents/client';
import type { AgentStateView, PendingApprovalView } from './admin-client';

export class AgentController {
  private client: AgentClient;
  constructor(onState: (state: AgentStateView) => void) {
    this.client = new AgentClient({
      agent: 'lead-triage-agent',
      name: 'global',
      host: window.location.host,
      onStateUpdate: (state) => onState(state as AgentStateView),
    });
  }
  async call<T>(method: string, args: unknown[] = []): Promise<T> {
    return this.client.call(method, args) as Promise<T>;
  }
}

export function setButtonsDisabled(scope: HTMLElement, disabled: boolean): void {
  scope.querySelectorAll('button').forEach((b) => { (b as HTMLButtonElement).disabled = disabled; });
}

export function showNotice(scope: HTMLElement, text: string, id = 'lead-status-notice'): void {
  if (document.getElementById(id)) return;
  const notice = document.createElement('p');
  notice.id = id;
  notice.className = 'bc-admin-notice';
  notice.textContent = text;
  scope.parentElement?.insertBefore(notice, scope);
}
```

**Implementation steps**:

1. Create file.
2. `admin-leads-list.ts`: replace `connectAgent(...)` with `new AgentController(...)`.
3. `admin-lead-detail.ts`: replace inline DOM helpers and the agent client; pass `root` to `setButtonsDisabled` / `showNotice`.

### 5. `src/lib/session.ts` — high-level session API (highest blast — last)

**Pattern to follow**: existing helpers in `src/lib/workos.ts`. This module composes them; it does not replace them.

```ts
// src/lib/session.ts
import type { Env } from '../types';
import {
  buildClearedSessionCookie,
  buildSessionCookie,
  readSessionCookie,
  validateSession,
  type SessionUser,
  type ValidatedSession,
} from './workos';

export interface SessionResult {
  session: ValidatedSession | null;
  user: SessionUser | null;
}

export async function requireSession(request: Request, env: Env): Promise<SessionResult> {
  const cookie = readSessionCookie(request);
  const session = await validateSession(env, cookie);
  return { session, user: session?.user ?? null };
}

export function attachRefreshedSession(response: Response, refreshedCookie: string, url: URL): Response {
  response.headers.append('Set-Cookie', buildSessionCookie(refreshedCookie, url));
  return response;
}

export function clearSession(url: URL): string {
  return buildClearedSessionCookie(url);
}
```

**Implementation steps**:

1. Create file.
2. Update `src/middleware.ts`: replace the manual chain in the auth gate with `requireSession` + `attachRefreshedSession`. Behavior unchanged.
3. Update `src/worker.ts`: replace the agent-path gate's `readSessionCookie` + `validateSession` chain with `requireSession`.
4. Update `src/pages/admin/logout.ts` to call `clearSession(new URL(request.url))` and `getLogoutUrl` from `lib/workos.ts` (the logout URL helper stays in workos.ts — it's a one-liner already).
5. Run `tests/integration/middleware.spec.ts`, `tests/integration/workos-session.spec.ts`, `tests/integration/worker-handlers.spec.ts` — must all stay green.

**Feedback loop**:
- **Playground**: `bun x vitest tests/integration/middleware.spec.ts tests/integration/workos-session.spec.ts`
- **Experiment**: Run all integration tests; any failure is a behavior regression
- **Check command**: `bun x vitest run`

### 6. `src/lib/leads-repo.ts` — typed repository

**Pattern to follow**: existing query patterns scattered across `lib/leads.ts`, `agents/lead-triage-agent.ts`, `workflows/lead-triage-workflow.ts`.

```ts
// src/lib/leads-repo.ts
import { and, eq, isNull, lt, sql } from 'drizzle-orm';
import { getDb, type Database } from '../db/client';
import { leads, type Lead, type NewLead } from '../db/schema';
import type { D1Database } from '@cloudflare/workers-types';

export class LeadsRepo {
  private db: Database;
  constructor(d1: D1Database) { this.db = getDb(d1); }

  async insert(input: NewLead): Promise<void> {
    await this.db.insert(leads).values(input);
  }
  async findById(id: string): Promise<Lead | null> {
    const [row] = await this.db.select().from(leads).where(eq(leads.id, id)).limit(1);
    return row ?? null;
  }
  async patch(id: string, patch: Partial<Lead>): Promise<Lead | null> {
    const [updated] = await this.db.update(leads)
      .set({ ...patch, updatedAt: sql`(datetime('now'))` })
      .where(eq(leads.id, id))
      .returning();
    return updated ?? null;
  }
  async markStatus(id: string, status: Lead['status'], outcome?: Lead['outcome']): Promise<void> {
    await this.db.update(leads).set({ status, ...(outcome ? { outcome } : {}) }).where(eq(leads.id, id));
  }
  async claimNotificationSlot(id: string): Promise<boolean> {
    const claim = await this.db.update(leads)
      .set({ notifiedAt: sql`(datetime('now'))` })
      .where(and(eq(leads.id, id), isNull(leads.notifiedAt)))
      .returning({ id: leads.id });
    return claim.length > 0;
  }
  async releaseNotificationSlot(id: string): Promise<void> {
    await this.db.update(leads).set({ notifiedAt: null }).where(eq(leads.id, id));
  }
  async claimResponseSlot(id: string): Promise<boolean> { /* same shape with respondedAt */ }
  async releaseResponseSlot(id: string): Promise<void> { /* same shape */ }
  async findStaleProcessing(cutoffIso: string): Promise<Pick<Lead, 'id'>[]> { /* status='processing' AND updatedAt<cutoff */ }
  async findStalePending(cutoffIso: string): Promise<Pick<Lead, 'id'>[]> { /* status='pending' AND submittedAt<cutoff */ }
  async resetStaleProcessing(cutoffIso: string): Promise<{ id: string }[]> { /* update where … returning id */ }
}
```

**Key decisions**:

- **Class, not functions** — instance carries the `db`; one construction per request avoids passing `d1` everywhere
- **`claim*Slot` returns boolean** — caller decides whether to send the email; `release*Slot` is the rollback. Encodes the workflow's atomic CAS pattern as a method
- **Keep `lib/leads.ts` insertLead / patchLead / getLeadById** as thin wrappers calling into `LeadsRepo` for one transition cycle, then delete

**Implementation steps**:

1. Create the file.
2. Add tests for each method (Phase 4 will cover, but a smoke test here is fine).
3. Migrate `actions/index.ts` to `new LeadsRepo(env.LEADS_DB).insert(...)`.
4. Migrate `agents/lead-triage-agent.ts:sweepStuckRows` to use `findStaleProcessing` / `resetStaleProcessing` / `findStalePending`.
5. Migrate `workflows/lead-triage-workflow.ts` `notify-nick` and `send-reply` claim/release to `claimNotificationSlot` / `releaseNotificationSlot` / `claimResponseSlot` / `releaseResponseSlot`.
6. Delete `lib/leads.ts` re-exports once no caller remains.

### 7. `src/lib/with-action-error.ts` — Astro Action wrapper

**Pattern to follow**: existing try/catch in `actions/index.ts:42-59`.

```ts
// src/lib/with-action-error.ts
import { ActionError } from 'astro:actions';
import { logError } from './event-log';

export interface WrapOptions {
  event: string;
  userMessage: string;
}

export function wrapAction<I, O>(
  opts: WrapOptions,
  fn: (input: I, ctx: any) => Promise<O>,
): (input: I, ctx: any) => Promise<O> {
  return async (input, ctx) => {
    try {
      return await fn(input, ctx);
    } catch (err) {
      if (err instanceof ActionError) throw err; // pre-shaped errors pass through
      logError(opts.event, {}, err);
      throw new ActionError({ code: 'INTERNAL_SERVER_ERROR', message: opts.userMessage });
    }
  };
}
```

**Implementation steps**:

1. Create file.
2. In `actions/index.ts`, wrap `contact.send`'s handler:
   ```ts
   handler: wrapAction(
     { event: 'lead.action.failed', userMessage: "I couldn't save that. Email hi@birdcar.dev directly and I'll see it." },
     async (input, ctx) => { /* existing body without the outer try/catch */ }
   )
   ```
3. Inner try/catch around `LEAD_TRIAGE_QUEUE.send` stays — non-blocking semantics differ from the persist failure.

### 8. Re-run integration suite per refactor

After each numbered extraction, run `bun x vitest run tests/integration/`. Commit only when green. If a test fails, the refactor changed semantics — either the refactor is wrong (likely) or the test was wrong (rare; revisit). **Don't update tests in this phase to make refactors pass** — that defeats the safety net.

## Testing Requirements

This phase **adds no new tests**. It validates against Phase 2's tests. New unit tests for the extracted modules land in Phase 4.

### Manual Testing

- [ ] After each extraction commit, `bun run test` is green
- [ ] `bun run check` (astro check) green
- [ ] `bun run build:ci` succeeds
- [ ] Manual smoke: submit `/contact` form locally with `astro dev` → lead in D1, workflow runs, approval surfaces in `/admin/leads` (sanity check the integration tests didn't lie)

## Error Handling

| Scenario | Strategy |
|---|---|
| Phase 2 test fails after a refactor | Revert the refactor commit; investigate; reshape extraction; retry |
| Type error from new module signature | Fix in the new module; do not loosen call-site types |
| `cloudflare:workers` import refuses to resolve in Node prerender path post-refactor | Restore lazy import; assert via `bun run build:ci` |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| `getCloudflareEnv` | Memoized null persists across worker isolate reuse | Subtle if Cloudflare ever runs multiple requests through the same module instance with different env handles | Stale env reference | Module reload per isolate is the platform contract; document the assumption in a comment |
| `requireSession` | Returns `{ session: null }` for both unauth and validation error | Tests can't distinguish | Auth bug looks like missing cookie | Add a `session: null, error: 'invalid'` discriminator only if a real test needs it; YAGNI otherwise |
| `LeadsRepo.claim*Slot` | Race between two workflow attempts | Same lead enqueued twice (queue + cron sweep) | One legit attempt loses the claim | Existing `notifiedAt IS NULL` guard handles it; preserved in repo method |
| `wrapAction` | Swallows a real client-facing error from inside the handler | Inner code throws something the user should see | User sees generic "couldn't save" | Pre-shape known errors as `ActionError` inside the handler; wrapper passes those through |
| `event-log` `logEvent` ctx | Drops error details if caller passes Error in ctx | Calling `logEvent('x', { error: err })` | Workers Logs entry has un-serialized object | Lint/comment that errors must go through `logError` |
| Rehype utils `getDataAttr` | Returns empty string for present-but-empty attribute | Author writes `data-foo=""` | Indistinguishable from missing | Existing call sites already treat empty as missing — no behavior change |

## Validation Commands

```bash
bun run check
bun run test
bun run build:ci          # confirms prerender path still works
git diff --stat src/      # eyeball: every refactor's diff is mechanical
```

## Rollout Considerations

- **Feature flag**: none
- **Monitoring**: Workers Logs after deploy — confirm event names unchanged so existing log queries continue to work. The structured event keys (`lead.received`, `queue.dispatched`, etc.) are part of the contract; do not rename them in this phase.
- **Rollback plan**: Each extraction is one commit; revert that commit. `bun run test` must pass post-revert.

## Open Items

- [ ] Decide whether `lib/leads.ts` stays as a deprecated alias module or gets deleted in this phase (recommendation: delete in this phase; the contract calls it a comprehensive sweep)
- [ ] Confirm `wrapAction`'s `any` for `ctx` doesn't trigger lint errors — if so, import `ActionAPIContext` from `astro:actions`
- [ ] Decide whether to refactor the `pages/admin/leads/index.astro` and `pages/admin/leads/[id].astro` `getEnv()` access — they are .astro files and not in the DRY hotspot list, but consistency suggests yes (low cost, runs the same `getCloudflareEnv()`)
