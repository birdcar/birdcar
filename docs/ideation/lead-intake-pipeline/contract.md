# Lead Intake Pipeline Contract

**Created**: 2026-04-27
**Confidence Score**: 88/100
**Status**: Draft
**Supersedes**: None

## Problem Statement

The `birdcar.dev/contact` form currently posts to a `mailto:`-replaced Astro Action that forwards directly to a `CONTACT_WEBHOOK_URL` n8n endpoint. That endpoint isn't yet provisioned, but more importantly the entire push-based design assumes n8n is publicly reachable — which means a Cloudflare Tunnel + Access on top of the tailnet host. That's heavier infrastructure than this use case justifies.

The new shape: leads get captured durably the moment Megan taps Send. n8n stays purely on the Tailscale tailnet, never exposes inbound, and **pulls** pending leads from a Worker-hosted endpoint on a schedule. The Worker stores leads in D1 the moment they arrive. n8n picks them up at its leisure, runs an AI triage flow (classify, qualify, draft), and patches the row when done. The result is **lossless capture even when n8n is down**, **zero new public surface for the tailnet**, and a clean source-of-truth in D1 that doubles as a CRM artifact.

The pain solved: today, the contact form would silently swallow leads if n8n were unreachable. The trust audience is a CPA referral checking that Birdcar is real — losing one lead is permanently bad. Pull + D1 makes that impossible.

## Goals

1. **Lossless lead capture.** Every form submit results in a row in D1 with `status='pending'`, even if n8n is unavailable for hours or days. Verifiable by simulating an n8n outage and confirming no leads are lost.
2. **Zero new public surface for the tailnet.** No Cloudflare Tunnel, no Access app, no public DNS for n8n. Verifiable by `curl https://n8n.birdcar.dev` returning DNS NXDOMAIN (the hostname never exists).
3. **AI triage runs end-to-end on n8n + Workers AI.** Each pending lead is classified, qualified (if consulting-fit), and a draft reply is generated. The classification + draft + score lands in Discord as a notification. Verifiable by submitting a sample form and seeing the Discord post within ~60s.
4. **Phase 1 ships with notification-only Discord.** Phase 2 adds interactive buttons (approve/edit/discard) via a Discord application + Worker-receiver pattern. Verifiable by Phase 1 working in production before Phase 2 work begins.
5. **D1 becomes the CRM source of truth.** All leads, classifications, drafts, eventual outcomes, and response timestamps live in one queryable place. Verifiable via `wrangler d1 execute birdcar-leads --command "SELECT * FROM leads"`.

## Success Criteria

- [ ] `wrangler d1 create birdcar-leads` provisioned and bound to the Worker as `LEADS_DB`
- [ ] Schema migration applied (table `leads` with all columns from the gist's schema spec)
- [ ] `/contact` form submission writes to D1 with `status='pending'` and returns success state to the user — verified by manual form submit + D1 query
- [ ] `GET /api/leads/pending` returns pending leads when called with `Authorization: Bearer $WORKER_PULL_TOKEN`, returns 401 without
- [ ] `GET /api/leads/pending` claims rows with `status='processing'` so a re-poll within the same minute doesn't return the same row twice
- [ ] `PATCH /api/leads/:id` updates status + AI metadata fields, returns 401 without bearer token
- [ ] n8n Schedule Trigger workflow polls `/api/leads/pending` every 60s, processes via Workers AI, posts Discord notification, PATCHes the lead — verified end-to-end
- [ ] Discord notification includes lead summary, AI classification + score, AI-drafted reply (Phase 1 only — no buttons)
- [ ] (Phase 2) Discord application + bot configured with Interactions Endpoint URL pointing to `/api/discord/interact`
- [ ] (Phase 2) Worker verifies Discord Ed25519 signature, ACKs within 3s, writes button click to `lead_actions` table
- [ ] (Phase 2) Modal-based "Edit" flow lets Nick edit the draft body before approval
- [ ] (Phase 2) Approve/Edit/Discard actions resolve to the right outcome (draft sent via Gmail, edited draft sent, no-op respectively) and patch `outcome` on the lead
- [ ] All tests pass; `astro check` 0/0; `bun run build:ci` produces a valid worker bundle
- [ ] No reintroduction of `CONTACT_WEBHOOK_URL`, `CF_ACCESS_CLIENT_ID`, `CF_ACCESS_CLIENT_SECRET`, or `cf-worker.js`-style configs

## Scope Boundaries

### In Scope

- D1 database `birdcar-leads` with `leads` table (and Phase 2: `lead_actions` table)
- Schema migration files under `migrations/`
- Rewrite of `src/actions/index.ts` `contact.send` — replaces webhook POST with D1 INSERT
- New SSR endpoints: `GET /api/leads/pending`, `PATCH /api/leads/:id` — both auth-gated
- New secret: `WORKER_PULL_TOKEN` (single shared bearer)
- Update `wrangler.jsonc` to declare the D1 binding alongside existing SESSION KV
- Update `.env.example` to document the new secret + remove the old ones
- Discord webhook URL stored as an n8n credential — Phase 1 notification path
- (Phase 2) Discord application registration + Interactions Endpoint Worker route
- (Phase 2) Ed25519 signature verification using Discord's public key
- (Phase 2) `lead_actions` table to record button clicks for n8n to poll
- Documentation: `N8N_AI_FLOW.md` added to the existing gist describing the n8n-side workflow
- Operational notes added to README (or a CLAUDE.md/AGENTS.md entry) about migrations and secrets

### Out of Scope

- **The n8n workflow itself.** Workflow JSON, node configurations, prompt tuning happen on the n8n machine — this contract covers the codebase work that supports it. The `N8N_AI_FLOW.md` gist file is the bridge.
- **Rebuilding the contact form UI.** The form (and its progressive enhancement) is already shipped. Only the action's body changes.
- **Removing Astro Actions / SESSION KV.** SESSION still backs the form's success-state redirect. Actions architecture stays.
- **CRM dashboard / admin UI for D1.** Reading leads from D1 happens via `wrangler d1 execute` queries or Notion (if Nick wires that as a downstream sink). No web UI in this project.
- **Backpressure / circuit breaker UI.** If pending count grows unbounded, that's an n8n-side alerting concern, not codebase work.
- **Multi-tenant / multi-source leads.** Schema is friendly to it (`source` column) but ingest from anything other than the contact form is deferred.
- **Tunnel + Access infrastructure.** Explicitly removed. Don't reintroduce for any reason without revisiting this contract.

### Future Considerations

- **Cloudflare Queues** as a more formal job queue if D1 polling proves too lossy or noisy at higher volumes.
- **Durable Objects** for per-lead state machines if retry logic gets more sophisticated.
- **Cron Trigger** for weekly miscategorized-lead audit (compares AI's category vs. Nick's eventual outcome).
- **Multi-source ingest**: Twitter DMs, email-into-`hi@birdcar.dev`, Mastodon — all writing to the same `leads` table.
- **Lead dashboard** (D1 Studio-like view, or a tiny Astro admin route at `/admin/leads`) once volume justifies it.
- **Prompt versioning + A/B**: store `model_version` + `prompt_version` per lead, generate weekly diff reports.

## Open Decisions Resolved

| Decision | Resolution |
|---|---|
| Push (webhook + Tunnel + Access) vs. pull (D1 + n8n schedule) | **Pull.** No new public surface, lossless by default, simpler auth. |
| Slack vs Discord for the human-in-the-loop notification | **Discord.** Nick prefers it; webhook is trivial for Phase 1, application + Worker-receiver fits the same pattern as Slack would for Phase 2. |
| Phasing | **Two phases.** Phase 1 = D1 + write + read endpoints + Discord notification. Phase 2 = Discord application + interactions + buttons + modal. |
| Concurrency claim pattern (gist Open Q #2) | **Claim-on-read with a single SQL UPDATE.** Single n8n instance means no real race, but `UPDATE leads SET status='processing' WHERE id IN (...) AND status='pending'` makes the intent explicit and is safe under future scale. |
| Poll interval default | **60s.** Configurable on the n8n side; codebase doesn't care. |
| Lead retention | **Keep forever.** D1 storage is generous; the archive is a feature. |
| ORM / migration tooling | **Drizzle ORM + drizzle-kit.** Schema in `src/db/schema.ts` is the single source of truth. `drizzle-kit generate` writes SQL migrations to `migrations/`. `wrangler d1 migrations apply` runs them. Type-safe queries replace hand-built SQL strings. |

## Open Decisions Deferred

These don't block specs and can be answered during execution or after Phase 2 lands:

| Decision | Default until decided |
|---|---|
| Discord channel vs DM for notifications | Private channel `#leads` with Nick as the only member |
| Token rotation for `WORKER_PULL_TOKEN` | Annual, manual; calendar reminder |
| Whether to log every Discord button click or only "approve" outcomes | Log every click; cheap and useful for audit |
| Edit modal pre-fill behavior on iOS Discord | Test during Phase 2; if broken, fall back to "reply in thread" UX |
| Whether to use n8n's Wait-node HITL primitive (single workflow, end-to-end) instead of two polling workflows | **Defer to post-Phase 1 review.** Wait node is structurally cleaner but requires resurrecting Tunnel + Access for n8n's resume webhook URL. After running Phase 1 in production for a few weeks, decide if the operational simplicity of two polling workflows still feels right or if a single tunnelled path for callbacks is worth the elegance. Switching to Wait-node-based HITL doesn't change the schema or the Phase 1 contract — only the Phase 2 design. |

## Execution Plan

### Dependency Graph

```
Phase 1: D1 + write side + pull endpoints + Discord webhook notification
  └── Phase 2: Discord application + interactions endpoint + lead_actions
              (blocked by Phase 1 — both shipping the schema and exercising
               the pull pattern de-risk Phase 2)
```

### Execution Steps

**Strategy**: Sequential

1. **Phase 1** — D1 + write + pull endpoints _(blocking)_
   ```bash
   /execute-spec docs/ideation/lead-intake-pipeline/spec-phase-1.md
   ```

   After this lands and is verified in production (one real form submit produces a Discord notification via n8n), proceed to Phase 2.

2. **Phase 2** — Discord interactions
   ```bash
   /execute-spec docs/ideation/lead-intake-pipeline/spec-phase-2.md
   ```

### Why sequential

Phase 2 depends on the `leads` schema, the `getEnv` + `CloudflareEnv` helpers, the bearer-auth pattern, and the `claim-on-read` SQL idiom from Phase 1. Trying to land them as parallel agent-team work would mean both phases inventing the same scaffolding twice and reconciling later. Sequential is the right answer.

### Operational steps between phases (not code)

After Phase 1 ships:
- `bun run db:migrate` against production D1
- `bunx wrangler secret put WORKER_PULL_TOKEN`
- Set up the n8n workflow per the `N8N_AI_FLOW.md` gist — Schedule Trigger, Workers AI calls, Discord webhook post
- Submit a real form, verify the lead lands in D1, n8n picks it up, Discord notification posts

Before Phase 2:
- Register a Discord application at https://discord.com/developers/applications
- Create a bot user; copy bot token into n8n's Discord credential
- Configure the application's Interactions Endpoint URL — Discord won't validate it until Phase 2 ships, so plan to do this step *during* Phase 2 deploy

