# Lead Triage Agent Contract

**Created**: 2026-04-27
**Confidence Score**: 95/100
**Status**: Draft
**Supersedes**: None (the prior `lead-intake-pipeline` project is abandoned, not superseded — its shipped code remains, but its contract and specs no longer represent the active design)

## Problem Statement

The contact form on birdcar.dev/contact captures leads durably to D1 (shipped in commit b9556ad). What's missing is the rest of the loop: triaging the lead with AI, drafting a response in Nick's voice, surfacing it for approval, and sending the reply. The first attempt at that loop was going to use n8n + Discord — a stack of three external services (n8n machine on the tailnet, Discord application + bot, Cloudflare Tunnel for either or both) that added operational surface without commensurate value. The reason the n8n design existed at all was that Cloudflare's primitives weren't yet rich enough; that's no longer true.

The new direction collapses every external service into native Cloudflare primitives plus one identity provider (WorkOS, which Nick works on and gets passkey auth from). The Lead Triage Agent — a Durable Object subclass of the Cloudflare Agents SDK — orchestrates per-lead triage workflows that run as durable Cloudflare Workflows with checkpointed AI calls. When a workflow needs human approval, it pauses via `waitForApproval()`. Nick gets an email notification (Cloudflare's `send_email` binding, free for outbound from his own domain), taps a link to `/admin/leads`, and approves/edits/discards from a tiny web dashboard that's connected to the agent over WebSocket. The dashboard is gated by WorkOS AuthKit with passkeys.

Everything ships in one repo, on one runtime, with one auth surface and one deploy pipeline. The pain solved: today's contact form silently triages nothing — leads sit in D1 as `status='pending'` with no AI processing and no human notification path. The cost of solving it the n8n way was a tailnet machine, three Cloudflare Tunnel + Access apps, and a Discord application. The cost of solving it this way is roughly nothing operationally.

## Goals

1. **Closed-loop lead handling.** Form submit → AI triage (classify, qualify if relevant, score, draft) → Nick gets an email → he approves/edits/discards from `/admin/leads` → reply email goes out (or doesn't) → outcome recorded. End-to-end, no manual hand-off. Verifiable by submitting a form, receiving a notification email, approving, and confirming a reply lands in the lead's inbox.
2. **Native HITL via the Cloudflare Agents framework.** No Discord, no n8n, no third-party workflow tool. The agent + workflow + admin dashboard pattern lives entirely in code. Verifiable by `grep -r "discord\|n8n" src/` returning zero hits in the v1 deliverable.
3. **First-class agent observability.** Every workflow run, AI call, retry, and outcome is recorded in the agent's SQLite state and visible in the admin dashboard. Verifiable by submitting 3 leads and seeing 3 workflow runs (with classify/qualify/draft step-level metrics) on `/admin/activity`.
4. **WorkOS AuthKit gates the admin surface.** All `/admin/*` and `/agents/*` (WebSocket upgrade) routes require a valid sealed WorkOS session cookie. Passkeys enabled. Verifiable by hitting `/admin/leads` without auth and getting redirected to AuthKit, then completing a passkey flow and landing on the page.
5. **The shipped foundation is reused, not rewritten.** D1 leads schema, Drizzle helpers, contact action, `/api/leads/[id]` PATCH endpoint all stay. The Astro Cloudflare adapter, mobile UI, design system, OG images all stay. Verifiable by zero changes to `src/db/schema.ts`, `src/lib/leads.ts` `insertLead` helper, or `src/actions/index.ts` validation logic.

## Success Criteria

- [ ] `LeadTriageAgent` class extending `Agent<Env, AgentState>` from the `agents` SDK, bound as a Durable Object in `wrangler.jsonc`
- [ ] `LeadTriageWorkflow` class extending `AgentWorkflow<LeadTriageAgent, { leadId: string }>`, registered as a workflow binding
- [ ] `wrangler.jsonc` updated with `ai` binding, `durable_objects.bindings` for `LEAD_TRIAGE`, `workflows[]` entry for `LEAD_TRIAGE_WORKFLOW`, `send_email[]` for `EMAIL`, and `migrations` block for the new SQLite-backed DO class
- [ ] Form submit writes lead to D1 (already shipped) AND fires `agent.queueLead(leadId)` to start triage
- [ ] Workflow steps execute durably with explicit retry/timeout config: classify (3 retries, 30s timeout), qualify (3 retries, 30s timeout, conditional), score (deterministic, no retries), draft (2 retries, 2min timeout)
- [ ] After draft + persist, workflow calls `env.EMAIL.send()` to email `hi@birdcar.dev` with a link to `/admin/leads/[id]`, then pauses at `waitForApproval(step, { timeout: '7 days' })`
- [ ] Workflow's `onWorkflowProgress()` updates `agent.state.pendingApprovals` so connected dashboard clients see the new pending lead in real-time
- [ ] WorkOS AuthKit middleware gates `/admin/*` and `/agents/*` — unauthenticated requests redirect to AuthKit hosted login (or 401 for WebSocket upgrades)
- [ ] `/admin/login`, `/admin/callback`, `/admin/logout` routes implemented; sealed `wos-session` cookie set/cleared correctly
- [ ] `/admin/leads` (list view) and `/admin/leads/[id]` (detail view) Astro pages render server-side, hydrate client-side via `AgentClient` from `agents/client`, and update in real-time as agent state changes
- [ ] Approve/Edit/Discard buttons on the detail view call `agent.approveLead(id, edited?)` / `agent.discardLead(id, reason?)` callable methods over WebSocket → workflow resumes → reply email sent (or not, for discard) → lead marked with `outcome` in D1
- [ ] `/admin/activity` view shows recent workflow runs with per-step latency, retry counts, and outcomes
- [ ] Agent records every workflow step start/success/failure in its own SQLite table (`agent_activity`), queried by the dashboard
- [ ] `agent.schedule("*/5 * * * *", "sweepStuckRows")` runs every 5 minutes; resets leads in `status='processing'` for >10 minutes back to `status='pending'` for retry
- [ ] `bun run check` 0 errors; `bun run build:ci` produces a valid worker bundle
- [ ] Manual test: submit a real form → email arrives within 30s → click link → log in via passkey → approve → reply email lands in lead's inbox within 30s; full activity row visible on `/admin/activity`
- [ ] Polling endpoint `/api/leads/pending` deleted (no longer used; was an n8n contract). PATCH `/api/leads/[id]` kept as debug surface

## Scope Boundaries

### In Scope

- `src/agents/lead-triage-agent.ts` — Agent class with state, callable methods, scheduling, lifecycle hooks
- `src/workflows/lead-triage-workflow.ts` — Durable workflow with the 7 steps + waitForApproval
- `src/lib/prompts.ts` — Classify, qualify, draft prompt templates as exported TS string functions
- `src/lib/ai-types.ts` — Zod schemas for structured AI outputs (parsed inside workflow steps)
- `src/lib/workos.ts` — Thin wrapper for WorkOS Node SDK: instantiate, helpers for getAuthorizationUrl/authenticateWithCode/loadSealedSession
- `src/middleware.ts` — Extend the existing markdown content-negotiation middleware to gate `/admin/*` and `/agents/*` with WorkOS session validation
- `src/pages/admin/login.astro` — Redirect to AuthKit hosted login URL
- `src/pages/admin/callback.ts` — Exchange `?code=` for sealed session, set cookie, redirect to `state` param
- `src/pages/admin/logout.ts` — Get logout URL, clear cookie, redirect
- `src/pages/admin/leads/index.astro` — Pending approvals list (server-rendered, hydrated client-side)
- `src/pages/admin/leads/[id].astro` — Single lead detail with full draft + Approve/Edit/Discard buttons
- `src/pages/admin/activity/index.astro` — Recent workflow activity table
- `src/scripts/admin-client.ts` — Shared vanilla JS using `AgentClient` to subscribe to agent state
- `src/scripts/admin-leads-list.ts` — List view client (renders pending approvals from agent state)
- `src/scripts/admin-lead-detail.ts` — Detail view client (Approve/Edit/Discard buttons fire callable methods)
- `src/scripts/admin-activity.ts` — Activity view client (queries agent for recent workflow runs)
- `src/styles/admin.css` — Small additions to the existing design system for admin-only chrome (status pills, activity table)
- `src/actions/index.ts` — One-line addition: after `insertLead`, call `agent.queueLead(id)`
- `wrangler.jsonc` — Add bindings: `ai`, `durable_objects` (LEAD_TRIAGE), `workflows` (LEAD_TRIAGE_WORKFLOW), `send_email` (EMAIL), `migrations` block for the new DO class
- `package.json` — Add deps: `agents`, `@workos-inc/node`, `ai`, `workers-ai-provider`
- `.env.example` — Add four `WORKOS_*` env vars with usage notes
- Activity tracking baked into every workflow step from day one (no separate phase)
- Stuck-row sweep (cron-scheduled on the agent)
- WebSocket auth via the same middleware that handles HTTP auth

### Out of Scope

- **Discord integration of any kind.** No webhook, no application, no bot.
- **n8n.** The whole product is gone.
- **Cloudflare Tunnel + Access.** Replaced by WorkOS AuthKit at the application layer.
- **`onEmail()` lead intake.** Deferred to v1.5. The agent will gain a 30-line `onEmail()` method later that creates a lead row and runs the same workflow, but the operational setup (DNS records, Email Routing config) isn't part of v1.
- **Resend or other transactional email provider.** v1 uses Cloudflare's `send_email` binding (free, native). If reply emails start landing in spam folders for real leads, swap to Resend in a follow-up — both are env-binding-shaped, the swap is two-line.
- **Multi-user / multi-tenant.** WorkOS supports orgs; v1 is hardcoded to Nick's WorkOS user ID. The admin pages don't have a "switch org" surface or per-user filtering.
- **Prompt versioning, A/B testing, RAG over past leads, scheduled digest emails, audit log export.** All explicitly deferred — they're real features, just not v1.
- **`/api/leads/pending` polling endpoint.** Deleted. Was an n8n contract; nothing consumes it now.
- **Migrating the leads schema.** Drizzle table stays as-is. v1 doesn't add columns to `leads` — the new state lives on the agent's own SQLite.
- **Mobile-specific admin UI.** The admin pages should *work* on mobile (they inherit the existing responsive design system) but no special tablet/phone optimizations are part of v1.
- **React.** Vanilla JS + `AgentClient` is sufficient for the dashboard's needs. Adding React just for admin/* would be a separate decision.

### Future Considerations

- `onEmail()` integration with Cloudflare Email Routing → second lead source (v1.5)
- Resend or other email provider for outbound reply deliverability (if CF Email shows spam-folder issues)
- Per-org multi-tenancy via WorkOS organizations (when Nick's consulting practice grows beyond himself)
- Prompt versioning + A/B with `agent.state.activePromptVersion` field
- Scheduled weekly digest email summarizing the week's leads + outcomes
- RAG over past leads for "this looks similar to the X engagement" signal during triage
- A "now" or "status" page that surfaces public-facing metrics (e.g., current capacity from agent state) on the homepage

## Open Decisions Resolved

| Decision | Resolution |
|---|---|
| Workflow tool: n8n vs Cloudflare Workflows in-code | **Cloudflare Workflows.** Single deploy lifecycle, type-safe RPC between agent and workflow, durable per-step checkpointing. |
| HITL surface: Discord buttons vs admin web dashboard | **Admin web dashboard.** Lives in your own product, voiced like the rest of birdcar.dev, no Discord-specific code paths. |
| Auth provider: Cloudflare Access vs WorkOS AuthKit | **WorkOS AuthKit.** Passkeys (better UX), real user/org model (portable to multi-user later), dogfooding (you work at WorkOS). |
| Notification mechanism: Discord ping vs email | **Email.** Cloudflare `send_email` binding, free for outbound from your own domain. iOS Mail push notifications work fine. |
| Outbound email provider | **Cloudflare `send_email` (env.EMAIL).** Free, native. Swap to Resend if deliverability issues appear. |
| Dashboard rendering | **Vanilla JS + `AgentClient` from `agents/client`.** No new framework dependency. |
| WebSocket auth pattern | **Cookie-on-upgrade.** The `wos-session` cookie travels with the WebSocket upgrade request and the same Astro middleware validates it before the upgrade reaches the Durable Object. |
| Activity tracking storage | **Agent's own SQLite (via `this.sql`).** It's the agent's observability surface; doesn't pollute the leads schema. |
| Stuck-row sweep | **Yes — `agent.schedule("*/5 * * * *", "sweepStuckRows")`.** Belt-and-suspenders for workflows that crash mid-step. |
| Project name | **`lead-triage-agent`** — describes the central architectural primitive. |
| onEmail in v1 | **Deferred to v1.5** — operational setup (DNS, Email Routing rules) isn't part of v1's "approvals + activity tracking" focus. |
| Polling endpoint fate | **Deleted.** Was n8n's contract; nothing consumes it. |

## Open Decisions Deferred

These don't block specs and can be answered during execution or after v1 lands:

| Decision | Default until decided |
|---|---|
| Sealed cookie name (`wos-session` is the WorkOS convention) | `wos-session` |
| WorkOS session length | Whatever AuthKit dashboard default is; revisit if friction |
| Activity dashboard granularity (per-step rows vs per-workflow-run aggregates) | Per-step rows in v1; aggregate views are dashboard-side query, not schema |
| Whether to surface workflow-level error messages to the lead in the reply email | No — internal failures stay internal; lead never sees "Workflow LEAD_TRIAGE failed at step 4" |
| Edit modal max body length on mobile | 8000 chars matching the D1 column max; UI doesn't enforce until then |
| Whether `discardLead` sends any email at all | No — discard is silent, just records `outcome=discarded` |

## Execution Plan

### Dependency Graph

```
Phase 1: Triage agent + workflow + email backend
  └── Phase 2: WorkOS AuthKit + admin dashboard  (blocked by Phase 1)
```

Phase 2 depends on Phase 1 because the dashboard renders agent state, calls agent RPC methods, and reads `agent.getRecentActivity()` — all of which need the agent class + workflow + activity SQLite to exist. Trying to ship them in parallel would mean Phase 2 mocks every agent integration point and rewrites against the real ones later. Sequential is the right shape.

### Execution Steps

**Strategy**: Sequential

1. **Phase 1** — Triage agent + workflow + email backend _(blocking)_
   ```bash
   /execute-spec docs/ideation/lead-triage-agent/spec-phase-1.md
   ```

   After this lands, validate end-to-end via `wrangler tail` while submitting a real contact form: workflow steps fire, notification email arrives, `agent.approveLead(workflowId)` called manually from `wrangler dev`'s shell advances the workflow to send-reply.

2. **Phase 2** — WorkOS AuthKit + admin dashboard
   ```bash
   /execute-spec docs/ideation/lead-triage-agent/spec-phase-2.md
   ```

   After this lands, end-to-end test is: submit form → email lands → tap link → passkey login → see lead in dashboard → approve → reply email lands.

### Operational steps between phases (not code)

After Phase 1 ships:
- `bun add agents ai workers-ai-provider`
- Update `wrangler.jsonc` with `ai` binding, `durable_objects.bindings`, `workflows[]`, `send_email[]`, `migrations` block
- Cloudflare Email Routing: configure outbound from `birdcar.dev` (the `send_email` binding requires the `from` domain to be on Cloudflare)
- Verify in dashboard that the LEAD_TRIAGE Durable Object is bound to the worker
- `bun run build && git push` (deploy via the existing GitHub → Cloudflare hook)

Before Phase 2:
- Create the WorkOS AuthKit application in the dashboard
- Set redirect URI to `https://birdcar.dev/admin/callback` AND `http://localhost:4321/admin/callback` for dev
- Enable passkeys
- `bunx wrangler secret put WORKOS_API_KEY` (and the other three: `WORKOS_CLIENT_ID`, `WORKOS_REDIRECT_URI`, `WORKOS_COOKIE_PASSWORD`)
- Generate cookie password: `openssl rand -base64 32`

### Why no agent team prompt

Both phases are sequential and authored by the same developer (Nick). No parallelizable work; an agent team would be overhead.

