# Implementation Spec: Holistic Hardening - Phase 5

**Contract**: ./contract.md
**Estimated Effort**: M
**Blocked by**: Phase 3
**Parallelizable with**: Phase 4

## Technical Approach

Audit the application against current Cloudflare Workers and Astro 6 best practices, document findings in `docs/BEST-PRACTICES.md`, and apply any remediations whose blast radius is small enough to land in this phase. Each best-practice gets one of four verdicts: **Conformant** (no action), **Conformant with caveat** (note in audit, no code change), **Remediate** (code change applied this phase), or **Defer** (file as future-considerations follow-up).

This is a **verification phase**, not a refactor phase. The Phase 3 sweep already addresses many best-practice concerns (single env loader, structured event logging, idempotent claim patterns). Phase 5's job is to scan the remaining surface for gaps the user-facing brain dump didn't enumerate, with fresh research from Cloudflare and Astro docs to catch convention drift since the code was written. Use the `cloudflare:cloudflare` skill, `cloudflare:workers-best-practices` skill, and `mcp__astro-docs__search_astro_docs` MCP to ground each verdict in a current docs reference.

We deliberately keep remediations narrow: anything beyond ~50 lines or touching a hot path stays in `BEST-PRACTICES.md` as a tracked finding rather than ballooning this phase. The audit report is a deliverable on its own — even with zero remediations, the documentation closes the regression-safety loop by enumerating what was checked and what was decided.

## Feedback Strategy

**Inner-loop command**: `bun run test && bun run check && bun run build:ci`

**Playground**: For each best-practice cluster, read the relevant skill / docs, then a single test run + build to confirm no behavior or build regression. The audit document itself is the product; the test suite catches code-change regressions.

**Why this approach**: An audit is read-driven, not code-driven. Validation matters only when a finding triggers a remediation; for those, Phase 2's integration tests are still the regression net.

## File Changes

### New Files

| File Path | Purpose |
|---|---|
| `docs/BEST-PRACTICES.md` | Canonical audit report. Each section: convention checked, evidence in code, verdict, action taken or deferred. Becomes a reference doc for future contributors. |

### Modified Files

| File Path | Changes |
|---|---|
| `wrangler.jsonc` | Possible: add `placement: { mode: 'smart' }` if not present; add `compatibility_flags` updates if 2026-04-15 has shifted; tighten `queues.consumers[0]` settings (max_batch_size / timeout / dead-letter queue) if findings warrant. |
| `astro.config.ts` | Possible: explicit `image.service` config if Cloudflare adapter exposes a better default; remove the `vite.optimizeDeps.exclude` hack for `@workos-inc/node` if a documented fix exists. |
| `src/worker.ts` | Possible: replace ad-hoc `MessageBatch.attempts` retry logic with `msg.retry({ delaySeconds })` consistency check; confirm `console.error` paths produce a thrown error so Workers Logs captures the stack. |
| `src/agents/lead-triage-agent.ts` | Possible: review `static options = { hibernate: true }` against current Agents SDK guidance. |
| `src/workflows/lead-triage-workflow.ts` | Possible: review `STEP_RETRY` retry shape against `agents/workflows` 2026 docs; confirm `step.do` config matches current schema. |
| `src/middleware.ts` | Possible: confirm `context.isPrerendered` is the recommended skip mechanism; verify no replacement API. |

(Modifications listed are the **maximum** scope. The audit may produce zero code changes if everything is conformant.)

## Implementation Details

### Audit checklist — Cloudflare Workers

Each item: **what to check**, **where to look in the repo**, **how to verdict**.

#### Bindings & runtime

1. **`compatibility_date` recency** — `wrangler.jsonc:20`. Verdict: conformant if within 12 months of current date (2026-05-05); else recommend bump.
2. **`compatibility_flags` includes `nodejs_compat`** — `wrangler.jsonc:17-19`. Verdict: conformant. Confirm no superseding flag (`nodejs_compat_v2`, etc.) exists in current docs.
3. **Observability sampling** — `wrangler.jsonc:26-29`. 100% at low traffic is fine. Verdict: conformant; note the cost trade-off if traffic grows.
4. **Source map upload** — `wrangler.jsonc:34`. Verdict: conformant.
5. **D1 migrations directory** — `wrangler.jsonc:54-58` + `migrations/`. Verdict: conformant; confirm `migrations_dir` path matches drizzle-kit output.
6. **Smart placement** — not currently set. Verdict: conformant by default but recommend adding `"placement": { "mode": "smart" }` if the worker calls origin services (it doesn't heavily — defer to follow-up).

#### Queue + Workflow

7. **Queue retry config** — `wrangler.jsonc:96-101` (max_retries: 3, max_batch_size: 5). Verdict: check against current Cloudflare guidance for low-traffic hand-off queues. Likely conformant; document.
8. **Dead-letter queue** — not configured. Verdict: defer or remediate. Cron sweep is the current safety net for permanent failures; a DLQ would be belt-and-suspenders. Document the trade-off.
9. **`step.do` retry shape** — `src/lib/triage-config.ts:STEP_RETRY` (read content). Verify against current `agents/workflows` documented retry config schema. If the SDK changed (e.g. introduced `timeout: '30s'` shorthand), note the alignment.
10. **Workflow `waitForApproval` timeout** — `src/workflows/lead-triage-workflow.ts:264-265`. Verify the current SDK uses thrown timeout vs returned discriminator. Verdict: conformant per existing comment, but reconfirm.

#### Durable Objects + Agents

11. **Hibernation** — `src/agents/lead-triage-agent.ts:65-67` (`static options = { hibernate: true }`). Verdict: conformant.
12. **DO migration `new_sqlite_classes`** — `wrangler.jsonc:110-114`. Conformant.
13. **`onStart` table creation idempotency** — `lead-triage-agent.ts:69-89`. `CREATE TABLE IF NOT EXISTS` is correct.
14. **Cron + alarm dual safety net** — worker-level cron + DO `this.schedule`. Read current Agents SDK guidance on whether worker cron is still recommended for DO bootstrap. Verdict: likely conformant given the existing inline comment's reasoning, but confirm.

#### Email send

15. **`send_email` binding shape** — `wrangler.jsonc:104-108` (`name: EMAIL`, `remote: true`). Confirm against current docs.
16. **Send error → release-claim pattern** — `lead-triage-workflow.ts:217-223` and `:318-324`. Verdict: conformant; documented in spec-phase-3.

#### Auth + sessions

17. **`HttpOnly`, `SameSite=Lax`, `Secure` (conditional)** — `src/lib/workos.ts:116-124`. Verdict: conformant; the Secure-on-http-localhost branch has good rationale.
18. **Cookie name registered** — `wos-session`. Verdict: conformant.
19. **CSRF posture** — Astro Actions use form POST with same-origin enforcement; the `state` param is sanitized via `safeReturnPath`. Verdict: conformant; document.
20. **Sealed-session cookie password rotation** — no current rotation mechanism. Defer to follow-up.

#### Logging + observability

21. **Structured event names follow `<surface>.<action>[.<outcome>]`** — `src/lib/log.ts:14-17` documents the convention. Spot-check 5 random log call sites for compliance. Verdict: conformant after Phase 3 sweep.
22. **Errors logged via `errorFields`** — confirmed by Phase 3 `event-log.ts`. Verdict: conformant.

#### Build + deploy

23. **`build:ci` skips `sync-images`** — `package.json:8`. Conformant; rationale is CI doesn't need the S3 sync.
24. **Deploy from `dist/server/wrangler.json`** — `.github/workflows/deploy.yml`. Conformant; this is the Astro adapter's emitted manifest.
25. **`upload_source_maps: true`** — covered above.

### Audit checklist — Astro 6

26. **`output: 'static'` with adapter** — `astro.config.ts:23`. Conformant; the worker handles SSR-on-demand routes (admin, actions) while the rest prerender.
27. **`prerenderEnvironment: 'node'`** — `astro.config.ts:39`. The inline comment justifies it; verify against current adapter docs that this is still supported (vs deprecated in favor of a different flag).
28. **`remoteBindings: process.env.CI ? false : undefined`** — `astro.config.ts:31`. Confirm vs current adapter's preferred pattern.
29. **Astro Actions error semantics** — `src/actions/index.ts` uses `ActionError` with `code` and `message`. Verdict: conformant. Confirm the post-redirect session-storage gotcha (`.dev.vars` SESSION KV requirement) is still load-bearing.
30. **`astro:middleware` `defineMiddleware` signature** — `src/middleware.ts:60`. Conformant with Astro 6.
31. **Content collections schema** — read `src/content.config.ts`. Confirm `defineCollection({ loader: glob(...), schema: ... })` is current pattern.
32. **`astro/zod` import** — `src/actions/index.ts:4`. Conformant per inline comment; confirm in latest docs.
33. **`Astro.locals.runtime.env` removed** — already accounted for via `cloudflare:workers` import. Conformant.
34. **`getStaticPaths` shape** in `[slug].astro` — read each dynamic page; confirm shape.
35. **Sitemap integration** — `astro.config.ts:41`. Conformant.
36. **RSS** — `src/pages/rss.xml.ts` + `@astrojs/rss`. Read source; confirm shape.

### Reading + research workflow

For each numbered item:

1. Read the relevant source file(s) cited in the checklist.
2. Use `mcp__astro-docs__search_astro_docs` for Astro items, or the `cloudflare:cloudflare` / `cloudflare:workers-best-practices` skill for Cloudflare items.
3. Compare current convention to repo state.
4. Write the audit entry in `docs/BEST-PRACTICES.md` with the four-verdict tag.
5. If verdict is "Remediate," apply the change and run `bun run test && bun run check`.

### `BEST-PRACTICES.md` structure

```markdown
# Birdcar.dev — Cloudflare + Astro Best Practices Audit

**Audit date**: 2026-05-05
**Audit scope**: Phase 5 of the Holistic Hardening project
**Source of truth**: cloudflare/* skills, astro-docs MCP

## Summary

| Verdict | Count |
|---|---|
| Conformant | N |
| Conformant w/ caveat | N |
| Remediated | N |
| Deferred | N |

## Cloudflare Workers

### Bindings & runtime
- ✅ **compatibility_date** (`wrangler.jsonc:20`) — `2026-04-15`, within 30 days of current. Conformant.
- ✅ **nodejs_compat flag** — Conformant. Reference: [Cloudflare Workers compatibility flags].
- … (continue per checklist)

### Queue + Workflow
- ⚠️ **Dead-letter queue** — not configured. Cron sweep mitigates permanent failures via `sweepStuckRows`; a DLQ would add belt-and-suspenders. **Deferred** — file as follow-up.
- … (continue)

## Astro 6

- ✅ **Output: static + Cloudflare adapter** — Conformant.
- … (continue)

## Remediations applied this phase

1. {file:lines} — {one-line summary of what changed and why}
2. {file:lines} — …

## Deferred items

1. **Dead-letter queue for `lead-triage`** — captured as future work; cron sweep covers the current failure mode.
2. **Sealed-session cookie password rotation** — out of scope for this phase.
3. … (continue)
```

## Testing Requirements

This phase **adds no new tests**. Any remediation regressions are caught by Phase 2 + Phase 4 tests.

### Manual Testing

- [ ] `bun run test && bun run check && bun run build:ci` green after every remediation
- [ ] `BEST-PRACTICES.md` exists, follows the structure above, and has a verdict for every checklist item
- [ ] Each remediation has a one-line entry under "Remediations applied this phase"
- [ ] Each "Deferred" item has a clear reason

## Error Handling

| Scenario | Strategy |
|---|---|
| A best-practice docs reference returns no current guidance | Note "no current guidance found; conformant by absence" — don't invent rules |
| A remediation breaks a test | Revert; downgrade verdict to "Defer" with the failure noted; investigate as follow-up |
| Cloudflare/Astro version drift mid-audit | Lock the audit's stated version (e.g. "Astro 6.1.9, wrangler X") so the report is reproducible |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| Audit report | Verdicts go stale | Cloudflare/Astro update conventions later | Reference doc misleads future contributors | Date-stamp the report; recommend re-audit annually |
| Remediation in scope | One change cascades into many | Misjudged blast radius | Phase blows past M sizing | Hard rule: any change >50 lines or touching workflow/agent moves to "Defer" |
| `cloudflare:cloudflare` skill | Returns generic guidance, not platform-specific | Skill is broad-spectrum | Audit verdicts unsupported | Cross-check with `cloudflare-docs` MCP for primary source |
| Astro docs MCP | Returns docs for an older major (5.x) | Search query too generic | False conformance signal | Include "Astro 6" in queries; verify version in returned doc |

## Validation Commands

```bash
bun run check
bun run test
bun run build:ci
```

## Rollout Considerations

- **Feature flag**: none
- **Monitoring**: post-deploy log review confirms event-name continuity (existing log queries still work)
- **Rollback plan**: each remediation is one commit; revert that commit. The `BEST-PRACTICES.md` doc itself has no runtime effect.

## Open Items

- [ ] Decide if the audit should pin to specific Cloudflare/Astro versions in the doc header (recommendation: yes)
- [ ] If multiple "Defer" items accumulate, file each as a planning note for a future hardening pass
