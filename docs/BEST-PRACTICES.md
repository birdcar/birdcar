# Birdcar.dev — Cloudflare + Astro Best Practices Audit

**Audit date**: 2026-05-05
**Audit scope**: Phase 5 of the Holistic Hardening project
**Stack at audit time**: Astro 6.1.9, `@astrojs/cloudflare` 13.2.1, `agents` 0.11.6, `wrangler` 4.87, `compatibility_date: 2026-04-15`
**Source of truth**: `cloudflare/*` skills (workers-best-practices, agents-sdk, durable-objects), `mcp__astro-docs`, primary docs at `developers.cloudflare.com` and `docs.astro.build`

This is a verification report. It enumerates every convention checked, the evidence in the repo, the verdict, and (when applicable) the action taken. Future audits should diff against this document so drift is visible.

## Summary

| Verdict | Count |
|---|---|
| Conformant | 42 |
| Conformant w/ caveat | 4 |
| Remediated | 0 |
| Deferred | 2 |

Total: 48 items (36 from the spec checklists + 12 bonus items caught during the audit).

The Phase 3 sweep already addressed the heavy-hitters (single env loader, structured event logging, idempotent claim patterns, extracted repo). Phase 5 caught zero must-fix gaps. The two deferred items are tracked under "Deferred items" at the bottom.

## Cloudflare Workers

### Bindings & runtime

1. ✅ **`compatibility_date`** (`wrangler.jsonc:20`) — `2026-04-15`. Three weeks before the audit date and well within the recommended 12-month freshness window. Source: [Workers compatibility flags](https://developers.cloudflare.com/workers/configuration/compatibility-flags/). **Conformant.**

2. ✅ **`compatibility_flags: ["nodejs_compat"]`** (`wrangler.jsonc:17-19`) — Per current docs, `nodejs_compat` automatically enables `nodejs_compat_v2` for any compatibility date ≥ 2024-09-23. There is no superseding flag to add. **Conformant.**

3. ⚠️ **Observability sampling at 100%** (`wrangler.jsonc:26-29`) — `head_sampling_rate: 1` is documented as appropriate for low-traffic workers where every event matters; the inline comment captures the cost trade-off. If lead volume crosses ~10k/day this should drop to ~0.1, with `console.error` paths kept at 100% via a separate sampler. **Conformant w/ caveat — re-audit when traffic grows.**

4. ✅ **Source map upload** (`wrangler.jsonc:34`) — `upload_source_maps: true` plus `vite.build.sourcemap: true` in `astro.config.ts:88-90` give production stack traces back at zero hot-path cost. **Conformant.**

5. ✅ **D1 migrations directory** (`wrangler.jsonc:54-58` + `migrations/`) — `migrations_dir: "./migrations"` matches drizzle-kit's emit path. The `tests/setup.ts:17-49` migration loader globs the same directory, so prod and test schemas can't drift. **Conformant.**

6. ✅ **Smart placement** — explicitly **not** configured. Per [Smart Placement docs](https://developers.cloudflare.com/workers/configuration/smart-placement/), it benefits workers that hit external origin services (legacy databases, third-party APIs). This worker uses only Cloudflare-native bindings (D1, KV, AI, Queue, DO, Workflow, EMAIL) which are already edge-optimized. Adding `placement: { mode: 'smart' }` would offer "minimal benefit" per the docs. **Conformant by absence.**

### Queue + Workflow

7. ✅ **Queue consumer config** (`wrangler.jsonc:96-101`) — `max_batch_size: 5`, `max_batch_timeout: 5`, `max_retries: 3`. Cloudflare's defaults are 10/5/3; smaller batch is appropriate for low-volume lead traffic where each lead does meaningful AI work and batching by 10 risks tail latency on the slowest message. **Conformant.**

8. ⚠️ **Dead-letter queue** (`wrangler.jsonc:96-101`) — not configured. Cloudflare's docs note: "If a `dead_letter_queue` is not defined, messages that repeatedly fail processing will eventually be discarded." The cron sweep at `lead-triage-agent.ts:238-262` is the current safety net — it picks up `pending` rows older than `STUCK_ROW_THRESHOLD_MINUTES` regardless of why the queue path failed, which covers the failure mode a DLQ would otherwise catch. A DLQ would still be belt-and-suspenders (rows that hit `max_retries: 3` get visibility instead of silently re-queueing forever). **Deferred** — file as follow-up.

9. ✅ **`step.do()` retry shape** (`src/lib/triage-config.ts:13-20`) — `{ retries: { limit, delay: '5 seconds', backoff: 'exponential' }, timeout: '30 seconds' }` matches the documented `WorkflowStepConfig` shape from [Workflows: sleeping and retrying](https://developers.cloudflare.com/workflows/build/sleeping-and-retrying/). `delay` accepts both numeric ms and human-readable strings; we use the latter for readability. **Conformant.**

10. ✅ **`waitForApproval` timeout semantics** (`src/workflows/lead-triage-workflow.ts:243-263`) — the SDK's `WaitForApprovalOptions.timeout` is documented as a `WorkflowSleepDuration`; on timeout the call throws (verified against `node_modules/agents/dist/workflow-types-*.d.ts`). The workflow's try/catch correctly distinguishes `WorkflowRejectedError` from timeout/unexpected errors. The existing inline comment is accurate. **Conformant.**

11. ✅ **No floating workflow promises** — every `await step.do(...)` in `lead-triage-workflow.ts` is awaited; no `void`-returned step calls. Matches the [Rules of Workflows](https://developers.cloudflare.com/workflows/build/rules-of-workflows/) "always await your steps" rule. **Conformant.**

12. ✅ **Step idempotency** — `claimNotificationSlot` and `claimResponseSlot` (`src/lib/leads-repo.ts:55-82`) use atomic conditional updates; the workflow's `notify-nick` and `send-reply` steps wrap the email send in try/catch with explicit `releaseNotificationSlot` / `releaseResponseSlot` rollback (`lead-triage-workflow.ts:191-231` + `:267-295`). Matches the "steps should ideally be idempotent" rule. **Conformant.**

13. ✅ **Deterministic step naming** — every `step.do(name, ...)` call uses a literal string (`'load-lead'`, `'classify'`, `'notify-nick'`, etc.), never a templated name. Matches the rule. **Conformant.**

### Durable Objects + Agents

14. ✅ **`static options = { hibernate: true }`** (`src/agents/lead-triage-agent.ts:63-65`) — verified against the Agents SDK type definition (`node_modules/agents/dist/index-BM7Nk0QD.d.ts:DEFAULT_AGENT_STATIC_OPTIONS`). `hibernate` defaults to `true`, so the explicit setting is redundant but documents intent. **Conformant.**

15. ✅ **DO migration `new_sqlite_classes`** (`wrangler.jsonc:110-114`) — `tag: "v1"` with `new_sqlite_classes: ["LeadTriageAgent"]` is the current pattern for SQLite-backed DOs. **Conformant.**

16. ✅ **`onStart` table creation idempotency** (`lead-triage-agent.ts:67-87`) — `CREATE TABLE IF NOT EXISTS` + `CREATE INDEX IF NOT EXISTS` is correct for `onStart`, which can fire multiple times across DO instantiations. **Conformant.**

17. ✅ **Cron + alarm dual safety net** — worker-level cron in `wrangler.jsonc:41-43` triggers `src/worker.ts:scheduled` which calls `stub.sweepStuckRows()`; the agent's own `this.schedule(SWEEP_CRON, 'sweepStuckRows', {})` in `onStart` registers the DO-internal alarm. The inline comment in `src/worker.ts:18-23` documents the chicken-and-egg rationale: the DO alarm only registers after the first request reaches the DO, so the worker cron is the bootstrap mechanism. **Conformant.**

18. ✅ **`@callable()` decorators on RPC methods** (`lead-triage-agent.ts:93,118,130,215`) — `queueLead`, `approveLead`, `discardLead`, `getRecentActivity` are decorated; `recordActivity` and `sweepStuckRows` correctly are not (only invoked from the workflow / cron, not from clients). The `vite.esbuild.supported.decorators: false` lowering in `astro.config.ts:79-83` is required because workerd's parser doesn't accept TC39 stage-3 decorator syntax — the inline comment captures this. **Conformant.**

19. ✅ **`extends Agent`, not `implements`** (`lead-triage-agent.ts:52`) — `class LeadTriageAgent extends Agent<Env, AgentState>` is correct. `implements` would lose `this.ctx` / `this.env` per the workers-best-practices skill. **Conformant.**

20. ✅ **`this.env`, not bare `env`** — every binding access in the agent uses `this.env.LEADS_DB` / `this.env.LEAD_TRIAGE_WORKFLOW`; no module-level `env` references. **Conformant.**

### Email send

21. ✅ **`send_email` binding** (`wrangler.jsonc:104-108`) — `name: "EMAIL"`, `remote: true`. The `remote: true` flag enables the binding in local dev. The workflow accesses it as `this.env.EMAIL.send(...)`. **Conformant.**

22. ✅ **Send error → release-claim pattern** (`lead-triage-workflow.ts:204-207` + `:285-288`) — try/catch around `EMAIL.send()` releases the `notified_at` / `responded_at` claim before re-throwing, so a transient email failure leaves the slot reclaimable on retry. Phase 3 documented this; it remains correct. **Conformant.**

### Auth + sessions

23. ✅ **Cookie flags** (`src/lib/workos.ts:116-124`) — `HttpOnly; SameSite=Lax; Max-Age=2592000` always; `Secure` is conditional on the URL being https or non-localhost. The conditional is required because Chrome refuses to round-trip a `Secure` cookie over plain http on `wrangler dev` localhost. **Conformant.**

24. ✅ **Session cookie name** — `wos-session` (`src/lib/workos.ts:8`). Single hardcoded constant; no scattered string literals. **Conformant.**

25. ✅ **CSRF posture** — Astro Actions enforce same-origin form POST by default; the `state` parameter on the OAuth roundtrip is sanitized via `safeReturnPath` (`src/lib/workos.ts:131-142`), which rejects protocol-relative paths, backslash variants, and CR/LF header-injection attempts. Verified by the new tests in `src/lib/workos.test.ts:7-58`. **Conformant.**

26. ⚠️ **Sealed-session cookie password rotation** — `WORKOS_COOKIE_PASSWORD` is a single static secret with no rotation mechanism. WorkOS's sealed-session encryption depends on it; rotating means invalidating every active session. Acceptable for a single-admin auth surface; the operational risk grows if the user base expands. **Deferred** — file as follow-up.

27. ✅ **WebSocket auth gate at the worker entry** (`src/worker.ts:48-54`) — `routeAgentRequest` runs ahead of Astro middleware, so the middleware's auth check can't protect WS upgrades to the DO. The worker entry checks `requireSession` and returns 401 before the SDK accepts the upgrade. The inline comment captures the rationale. **Conformant.**

### Logging + observability

28. ✅ **Structured event logging** (`src/lib/event-log.ts:16-38`) — `logEvent`, `logWarn`, `logError` always emit objects (never strings), preserving the `event` key as a queryable field in Workers Logs. The convention `<surface>.<action>[.<outcome>]` is documented in `src/lib/log.ts:14-17`. Phase 4 added structural tests at `src/lib/event-log.test.ts`. **Conformant.**

29. ✅ **Error unpacking via `errorFields`** (`src/lib/log.ts:24-36`) — `Error` instances are unpacked to `{ name, message, stack? }` because their own properties are non-enumerable and don't survive default JSON serialization. Used uniformly by `logWarn` / `logError`. **Conformant.**

30. ⚠️ **`onWorkflowError` bypasses `errorFields`** (`lead-triage-agent.ts:176-181`) — the SDK passes a pre-formatted string, so the agent emits it as a literal `error` field rather than running it through `errorFields`. The inline comment documents the divergence. The current contract works; if the SDK ever changes to pass an `Error` object, this branch will need a `typeof err === 'string'` guard. **Conformant w/ caveat.**

### Build + deploy

31. ✅ **`build:ci` skips image sync** (`package.json:9`) — CI doesn't need the S3 sync because the prod CDN already has the images from the most recent local build. The dev `build` script (`package.json:8`) does run it. **Conformant.**

32. ✅ **Deploy from `dist/server/wrangler.json`** (`.github/workflows/deploy.yml`) — the Astro Cloudflare adapter emits a merged manifest at `dist/server/wrangler.json`; `wrangler deploy --config dist/server/wrangler.json` is the documented pattern. **Conformant.**

33. ✅ **Frozen lockfile in CI** (`.github/workflows/deploy.yml`) — `bun install --frozen-lockfile` in both the `test` and `deploy` jobs. **Conformant.**

## Astro 6

34. ✅ **`output: 'static'` with adapter** (`astro.config.ts:23-40`) — admin and contact-action routes opt into on-demand rendering; the rest prerender. The adapter at v13.2.1 is the current major for Astro 6 (latest release v13.3.1 is an in-major patch; no breaking changes). **Conformant.**

35. ✅ **`prerenderEnvironment: 'node'`** (`astro.config.ts:39`) — added in `@astrojs/cloudflare@13.1.0`, current. The inline comment correctly explains why workerd prerendering fails in CI when `remoteBindings` can't proxy. Per the [adapter docs](https://docs.astro.build/en/guides/integrations-guide/cloudflare/#prerenderenvironment), this is the recommended escape hatch when prerendered pages depend on Node.js APIs (our OG generator uses `@resvg/resvg-js`, satisfying the requirement). **Conformant.**

36. ✅ **`remoteBindings: process.env.CI ? false : undefined`** (`astro.config.ts:31`) — the AI binding is implicitly remote; CI without a `wrangler login` session would crash starting the proxy. Disabling in CI only is the correct narrowing. **Conformant.**

37. ✅ **Astro Actions error semantics** (`src/actions/index.ts:42-49`) — uses `ActionError` with `code: 'INTERNAL_SERVER_ERROR'` and a user-facing `message`. The `wrapAction` helper preserves pre-shaped `ActionError` throws and wraps unexpected errors uniformly. Matches the [Actions API reference](https://docs.astro.build/en/reference/modules/astro-actions/#actionerror). **Conformant.**

38. ✅ **`astro:middleware` `defineMiddleware` signature** (`src/middleware.ts:34`) — `defineMiddleware(async (context, next) => ...)` matches the [Astro 6 middleware reference](https://docs.astro.build/en/reference/modules/astro-middleware/#definemiddleware). **Conformant.**

39. ✅ **`context.isPrerendered` skip mechanism** (`src/middleware.ts:40-42`) — added in Astro 5.0. The docs explicitly call out this property as the recommended way to "avoid accessing headers in prerendered pages." Our middleware short-circuits early to dodge the `headers not available` warning. **Conformant.**

40. ✅ **Content collections schema** (`src/content.config.ts:1-20`) — `defineCollection({ loader: glob({ pattern: '**/*.md', base: './src/content/blog' }), schema: z.object({...}) })` with `astro/zod` is the current Astro 6 pattern. The `astro:content` `z` re-export is deprecated; we already use `astro/zod`. **Conformant.**

41. ✅ **`astro/zod` import** (`src/actions/index.ts:4`, `src/content.config.ts:4`) — confirmed in current Astro 6 docs as the canonical replacement for both `import { z } from 'astro:content'` and `import { z } from 'astro:schema'`. The inline comments capture the rationale. **Conformant.**

42. ✅ **`Astro.locals.runtime.env` not used** — Astro 6 removed it. The codebase uses `import { env } from 'cloudflare:workers'` lazily through `getCloudflareEnv()` in `src/lib/env.ts:22-29`. **Conformant.**

43. ✅ **`getStaticPaths` shape in `[slug].astro`** (`src/pages/writing/[slug].astro`, `src/pages/tags/[tag].astro`, `src/pages/writing/[...page].astro`) — each returns `Array<{ params, props }>`, matching the [routing reference](https://docs.astro.build/en/reference/routing-reference/#getstaticpaths). **Conformant.**

44. ✅ **Sitemap integration** (`astro.config.ts:41`) — `sitemap()` from `@astrojs/sitemap` 3.7.2; no custom config needed for our routing. **Conformant.**

45. ✅ **RSS** (`src/pages/rss.xml.ts`) — uses `@astrojs/rss` with `context.site` from `APIContext`, custom `xmlns` for `content:encoded` and Atom self-link, and a stylesheet. Shape matches the integration's current API. **Conformant.**

### Unrelated to numbered checklist (caught during audit)

46. ⚠️ **`vite.optimizeDeps.exclude: ['@workos-inc/node']`** (`astro.config.ts:103-105`) — workaround for a Vite dep-optimizer interaction with `@workos-inc/node`'s CJS+ESM hybrid + subpath imports. The inline comment is detailed and accurate. No documented replacement exists; this stays as the canonical fix. **Conformant w/ caveat — re-test on every WorkOS SDK major bump.**

47. ⚠️ **`vite.ssr.external: ['@resvg/resvg-js', 'satori', '@aws-sdk/client-s3']`** (`astro.config.ts:106-110`) — keeps native bindings + Node-only deps out of the worker bundle. These modules are only used by prerendered routes (OG generator + image sync script). Required because workerd doesn't support native bindings. **Conformant w/ caveat — review when Cloudflare adds workerd image gen support.**

48. ✅ **`crypto.randomUUID()` for lead IDs** (`src/actions/index.ts:51`) — Web Crypto, not `Math.random()`. Matches the security rule from the workers-best-practices skill. **Conformant.**

## Remediations applied this phase

None. Every item is either conformant, conformant with a caveat, or deferred. The Phase 3 sweep already addressed the structural gaps; the audit found nothing whose blast radius justified mid-phase remediation.

## Deferred items

1. **Dead-letter queue for `lead-triage`** (item 8) — cron sweep is the current safety net. A DLQ would add visibility into permanent failures (rows that hit `max_retries: 3`) without changing recoverability. Low priority; file as follow-up.

2. **Sealed-session cookie password rotation** (item 26) — single static `WORKOS_COOKIE_PASSWORD`. Acceptable while admin auth has one user; revisit when scope expands to multiple admins or a periodic rotation schedule is required by policy.

(Item 3 — observability sampling — is tagged conformant w/ caveat in the body, not deferred. It's a re-audit trigger, not a follow-up task.)

## Re-audit cadence

This document captures conventions as of **2026-05-05**. Cloudflare and Astro both ship breaking conventions on a quarterly-ish cadence. Recommended re-audit triggers:

- Astro major bump (Astro 7)
- `@astrojs/cloudflare` major bump (v14)
- `agents` SDK major bump
- `compatibility_date` bump that crosses 12 months (next bump-by date: **2027-04-15**)
- Annually on the audit anniversary regardless of bumps
