# Context Map: lead-triage-agent

**Phase**: 2 (extends Phase 1)
**Scout Confidence (Phase 2)**: 73/100
**Verdict (Phase 2)**: GO (with documented gaps — see Phase 2 Risks)

## Phase 1 Sections (retained)

### Phase 1 Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 17/20 | All 6 new files, 6 modified files, and 1 deleted file are identified. Spec contains near-complete code for each. Two ambiguities: (1) the `worker-entry` re-export shape is left as "validate during implementation" and (2) `.env.example` says drop `WORKER_PULL_TOKEN` but `src/pages/api/leads/[id].ts` still consumes it (kept as debug surface). |
| Pattern familiarity | 16/20 | Read all extant pattern files: `src/lib/leads.ts`, `src/db/client.ts`, `src/db/schema.ts`, `src/actions/index.ts`. Patterns are clear: named exports, no barrels, `drizzle-orm/d1` queries, `astro:schema` Zod, `getEnv(locals)` helper for runtime env. The Agents/Workflows SDK patterns are net-new (no existing analogue in the repo). |
| Dependency awareness | 16/20 | `claimPendingLeads` only consumed by `src/pages/api/leads/pending.ts` (being deleted) — safe to remove. `WORKER_PULL_TOKEN` consumed by `pending.ts` (deleted) AND `[id].ts` (kept). `CloudflareEnv` only defined/used inside `src/lib/leads.ts`; needs consolidation in new `src/types.ts`. `getDb` signature confirmed: `(d1: D1Database) => DrizzleD1Database<typeof schema>`. |
| Edge case coverage | 15/20 | Spec documents failure modes per step. Edge cases identified: spam short-circuit, approval timeout, malformed AI JSON, sweep race window, send_email retry behavior. Gap: behavior when `agent.queueLead` succeeds but the workflow boots before D1 commit propagates (D1 read-after-write semantics on Workers — unaddressed). |
| Test strategy | 14/20 | No test framework in repo. Spec explicitly chooses no unit tests; validation path is `bun run check` + `wrangler dev --remote` + `wrangler tail` + manual form submit. Validation steps are concrete and reproducible. |

**Total: 78/100 — GO**

### Phase 1 Key Patterns

- `src/lib/leads.ts` — named exports, `CloudflareEnv` interface, `getEnv(locals)` helper that pulls runtime env from `App.Locals`. Drizzle queries returning typed `Lead`/`NewLead` rows. `patchLead` returns `Lead | null`. The `LeadPatch` type uses `Partial<Pick<Lead, ...>>`. No default exports anywhere.
- `src/db/client.ts` — `getDb(d1: D1Database): Database` with `Database = DrizzleD1Database<typeof schema>`. Logger off. Canonical entry for new agent + workflow Drizzle calls.
- `src/db/schema.ts` — `leads` table already has every column the spec writes to: `category`, `qualification`, `score`, `draft`, `status`, `outcome`, `respondedAt`. Status enum now `pending|processing|awaiting-approval|done|discarded` (Phase 1 added `awaiting-approval`).
- `src/actions/index.ts` — `astro:actions` `defineAction` pattern. Env access via `getEnv(ctx.locals)`. Throws `ActionError` on failure.
- `src/middleware.ts` — runtime env read as `(context.locals as { runtime?: { env?: ... } }).runtime?.env`. Markdown content-negotiation branch is the only existing logic.
- `wrangler.jsonc` — flat JSONC. KV (SESSION), D1 (LEADS_DB), AI, DO (LEAD_TRIAGE_AGENT), Workflow, Queue, send_email all bound. `nodejs_compat`, `compatibility_date` 2026-04-15.
- `astro.config.ts` — `output: 'static'`, adapter cloudflare, vite esbuild target sets `decorators: false`. SSR external excludes resvg/satori/aws-sdk.
- `tsconfig.json` — `astro/tsconfigs/strict`, JSX configured for React.

### Phase 1 Dependencies

- `src/lib/leads.ts:claimPendingLeads` — consumed only by deleted `src/pages/api/leads/pending.ts`. Safe to remove.
- `src/lib/leads.ts:CloudflareEnv` — replaced by `src/types.ts:Env`. Now consolidated in `src/types.ts` after Phase 1.
- `src/lib/leads.ts:WORKER_PULL_TOKEN` field — still consumed by `src/pages/api/leads/[id].ts:44`. Kept on `Env`.
- `src/db/schema.ts:leads` — new consumers (now landed): `src/agents/lead-triage-agent.ts`, `src/workflows/lead-triage-workflow.ts`.
- `src/actions/index.ts:server.contact.send` — adds `agent.queueLead(id)` (non-breaking, try/catch).

### Phase 1 Conventions

- camelCase variables/functions, PascalCase types/interfaces/classes. Files kebab-case.
- Imports relative; type-only via `import type`. Cloudflare types from `@cloudflare/workers-types`. Astro files use `astro:schema`; agent/workflow files use `zod` directly.
- Throw `ActionError` from action handlers; throw plain `Error` deeper. Try/catch at boundaries. Console.warn for non-blocking failures.
- `interface` for object shapes; `type` for unions. No barrels.
- Env access: `getEnv(locals)` in actions/routes; `this.env.X` in agent/workflow.
- No tests. `bun run check` + `wrangler dev --remote` + `wrangler tail` + manual.
- Drizzle: `eq`, `inArray`, `asc`, `and`, `lt` from `drizzle-orm`. `.returning()` for RETURNING rows.

### Phase 1 Risks (status update)

1. `N8N_AI_FLOW.md` external — accepted, prompts hand-authored in Phase 1.
2. `WORKER_PULL_TOKEN` kept — still unresolved in Phase 2 spec. Phase 2 wraps `/admin/*` and `/agents/*` with auth; `/api/leads/[id]` is NOT covered by the gate. **Carry forward as Phase 2 risk.**
3. Worker entry / DO + Workflow re-export — landed in Phase 1 (`src/worker.ts` exports `LeadTriageAgent` and `LeadTriageWorkflow`). **However, `routeAgentRequest` is NOT yet wired** (see Phase 2 Risk #1).
4. `astro:schema` Zod compatibility — resolved in Phase 1 (Zod is a dep).
5. Drizzle sweep query — landed in `src/agents/lead-triage-agent.ts:238`.
6. D1 read-after-write — Phase 1 covers via `STEP_RETRY.persist`.
7. Agents SDK API surface — landed cleanly in Phase 1.

## Phase 2 Sections (new)

### Phase 2 Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 16/20 | All 12 new files, 5 modified files identified with code in spec. Two unknowns: (1) **`src/lib/agent-stub.ts` does not exist** (spec's `/admin/activity/index.astro` imports `getTriageAgent(env)` from it — must be created or activity page must inline the stub fetch); (2) **Worker entry needs `routeAgentRequest(request, env)` wired** — spec admits "validate during implementation" and the current `src/worker.ts:28` only delegates to `server.fetch`. Without this, `/agents/*` WebSocket upgrades return 404 from the Astro adapter. |
| Pattern familiarity | 14/20 | Read `src/middleware.ts` (markdown branch present, can interleave auth check ahead of it). Read `Base.astro` (admin pages slot in fine). Read `global.css` — design tokens confirmed: `--paper`, `--paper-2`, `--ink`, `--ink-2`, `--ink-3`, `--rule`, `--rule-strong`, `--clay`, `--clay-2`, plus `--mono`/`--serif`/`--sans` (NOT `--font-mono`/`--font-serif` as spec's `admin.css` writes — see Risks). `src/scripts/` directory exists but is empty — no precedent for client-bundled TS modules. The Agents SDK `AgentClient` API (auto-reconnect, `state` subscription, `stub.X` RPC over WS) is net-new. |
| Dependency awareness | 15/20 | Agent methods exist exactly as spec assumes (`approveLead`, `discardLead`, `getRecentActivity` are `@callable()`). State shape `pendingApprovals: PendingApproval[]` confirmed. New file `src/lib/agent-stub.ts` has zero existing consumers. Middleware extension is additive. WORKOS_* env vars only consumed by new `src/lib/workos.ts`. |
| Edge case coverage | 14/20 | Spec covers: WS upgrade 401, session refresh propagation, callback no-code, callback auth-failure, logout no-cookie, draft min-length client check, race when an approval is fulfilled mid-action, agent state arriving while a button is mid-click. Gaps: (1) `[id].astro` doesn't check `lead.status`; (2) Cookie `Secure` flag breaks local dev over `http://localhost:4321`; (3) `WORKER_PULL_TOKEN` route NOT auth-gated. |
| Test strategy | 14/20 | No new test infrastructure proposed. Validation steps are concrete: dev → AuthKit redirect → callback → dashboard → contact form → live update → click into lead → approve → email → activity table → logout. `bun run check` + `bun run build:ci` are the static gates. |

**Total: 73/100 — GO**

### Phase 2 Key Patterns

- `src/middleware.ts:29-77` — existing `defineMiddleware` pattern. Already has `context.isPrerendered` short-circuit at line 35. Phase 2 inserts the auth gate above the markdown branch. Runtime env extracted via the same `(context.locals as { runtime?: { env?: ... } }).runtime?.env` cast already used here.
- `src/types.ts:50-61` — `Cloudflare.Env` augmentation pattern. Phase 2 adds 4 WORKOS_* fields here.
- `src/agents/lead-triage-agent.ts:9-18` — `PendingApproval` interface (already exposed). Phase 2 client mirrors a subset (`workflowId`, `leadId`, `name`, `email`, `category`, `score`, `enqueuedAt`).
- `src/agents/lead-triage-agent.ts:120-136` — `@callable()` `approveLead(workflowId, editedBody?)` and `discardLead(workflowId, reason?)`. **Already supports the editedBody param.** No agent change needed.
- `src/agents/lead-triage-agent.ts:215-221` — `@callable()` `getRecentActivity(limit = 100)` returns `ActivityRow[]` with snake_case keys.
- `src/layouts/Base.astro` — accepts `title`/`description`/etc. Admin pages just need `title`.
- `src/styles/global.css` — design tokens: `--paper`, `--paper-2`, `--paper-3`, `--ink`, `--ink-2`, `--ink-3`, `--ink-4`, `--rule`, `--rule-strong`, `--clay`, `--clay-2`, `--clay-tint`, `--ok`, `--warn`, `--err`, `--mono`, `--serif`, `--sans` (NOT `--font-mono`/`--font-serif`), `--s-1`..`--s-10`, etc. `.bc-form-error` already exists at line 836.
- `src/pages/contact.astro:1-7` — server-render pattern with `prerender = false` and `Astro.locals` access.
- `src/pages/api/leads/[id].ts` — `APIRoute` pattern with `prerender = false`, runtime env, returns `Response`.
- `src/worker.ts:28-31` — current default export only forwards `server.fetch`. Phase 2 needs to wrap with `routeAgentRequest` ahead of `server.fetch`.

### Phase 2 Dependencies

- `src/middleware.ts:29` — extending; new code path for `/admin/*` and `/agents/*` runs ahead of markdown branch.
- `src/types.ts:51-60` — extending `Cloudflare.Env` interface with 4 WORKOS_* fields.
- `src/agents/lead-triage-agent.ts` — read-only; consumed by new admin pages and client scripts.
- `src/lib/agent-stub.ts` — does not exist yet; needed by activity page.
- `src/worker.ts:28-31` — needs `routeAgentRequest` import and wrapping call.
- `src/scripts/` — directory exists but empty. **No existing precedent for how client-side TS gets bundled and served at `/scripts/*.js`.** Need to verify the bundling approach during implementation.
- `package.json:dependencies` — adding `@workos-inc/node`. `agents/client` already available via `agents` dep.

### Phase 2 Conventions

- **Cookies**: `wos-session` name; `HttpOnly; Secure; SameSite=Lax; Max-Age=2592000` (30d). Cleared with `Max-Age=0`.
- **Auth gate**: pathname prefix on `/admin` and `/agents`, with allowlist for `/admin/login` and `/admin/callback`.
- **WS upgrade auth**: 401 plain Response (no redirect — browsers don't follow 302 on WS handshake).
- **Session refresh**: opportunistic — append `Set-Cookie` to wrapped response if refreshed.
- **Locals user**: `App.Locals.user?: SessionUser` global augmentation in middleware.
- **Client-side rendering**: forbid `innerHTML`/template-string interpolation. All user-facing text via `document.createElement` + `textContent`. URLs via `encodeURIComponent`.
- **Admin styles**: keep `admin.css` minimal; reuse global.css tokens.
- **Astro SSR opt-in**: every admin page sets `export const prerender = false`.

### Phase 2 Risks

1. **`routeAgentRequest` is not wired in `src/worker.ts`.** Phase 2's whole real-time UX depends on this. Mitigation: import `{ routeAgentRequest } from 'agents'`, call it before `server.fetch`. Validate against Agents SDK docs for v0.11.6 signature.

2. **`src/lib/agent-stub.ts` does not exist.** Spec's activity page imports `getTriageAgent` from this missing file. Add to file changes during execution.

3. **`src/scripts/` bundling is unverified.** Spec uses absolute `<script type="module" src="/scripts/admin-leads-list.js">`. Astro doesn't auto-emit standalone TS modules from `src/scripts/` to `/scripts/*.js`. Two viable approaches: (a) use Astro's inline `<script src="../../../scripts/admin-leads-list.ts">` (Vite-bundled — recommended); (b) put compiled JS in `public/scripts/`. Validate during implementation.

4. **CSS token name mismatch in spec.** `admin.css` example references `var(--font-mono)` and `var(--font-serif)` (lines 686, 705, 707), but actual tokens are `--mono` and `--serif`. Substitute correct names during write.

5. **`.bc-form-error` is duplicated.** Already exists in global.css. Drop the admin.css redefinition.

6. **Cookie `Secure` flag in dev.** Browsers may reject `Secure` cookies over `http://localhost`. If sessions break in dev, gate `Secure` on the request URL's scheme.

7. **`/api/leads/[id]` remains unauthed (debug surface).** Phase 2 doesn't gate it. Acceptable while WORKER_PULL_TOKEN stays a secret; mention in completion report.

8. **`[id].astro` renders action buttons regardless of `lead.status`.** Server-side check on `awaiting-approval` would improve clarity, but client hydration disables buttons when no matching pending approval. Acceptable.

9. **AuthKit redirect URI must be registered in WorkOS dashboard out-of-band.** Setup pre-req — note in completion report.

10. **Agents SDK kebab-case binding resolution.** `LEAD_TRIAGE_AGENT` collapses to `lead-triage-agent` via the SDK's `camelCaseToKebabCase`. Class name `LeadTriageAgent` → `lead-triage-agent`. Match confirmed; low risk.
