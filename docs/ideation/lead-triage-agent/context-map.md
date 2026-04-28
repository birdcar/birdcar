# Context Map: lead-triage-agent

**Phase**: 1
**Scout Confidence**: 78/100
**Verdict**: GO (with documented gaps — see Risks)

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 17/20 | All 6 new files, 6 modified files, and 1 deleted file are identified. Spec contains near-complete code for each. Two ambiguities: (1) the `worker-entry` re-export shape is left as "validate during implementation" and (2) `.env.example` says drop `WORKER_PULL_TOKEN` but `src/pages/api/leads/[id].ts` still consumes it (kept as debug surface). |
| Pattern familiarity | 16/20 | Read all extant pattern files: `src/lib/leads.ts`, `src/db/client.ts`, `src/db/schema.ts`, `src/actions/index.ts`. Patterns are clear: named exports, no barrels, `drizzle-orm/d1` queries, `astro:schema` Zod, `getEnv(locals)` helper for runtime env. The Agents/Workflows SDK patterns are net-new (no existing analogue in the repo). |
| Dependency awareness | 16/20 | `claimPendingLeads` only consumed by `src/pages/api/leads/pending.ts` (being deleted) — safe to remove. `WORKER_PULL_TOKEN` consumed by `pending.ts` (deleted) AND `[id].ts` (kept). `CloudflareEnv` only defined/used inside `src/lib/leads.ts`; needs consolidation in new `src/types.ts`. `getDb` signature confirmed: `(d1: D1Database) => DrizzleD1Database<typeof schema>`. |
| Edge case coverage | 15/20 | Spec documents failure modes per step. Edge cases identified: spam short-circuit, approval timeout, malformed AI JSON, sweep race window, send_email retry behavior. Gap: behavior when `agent.queueLead` succeeds but the workflow boots before D1 commit propagates (D1 read-after-write semantics on Workers — unaddressed). |
| Test strategy | 14/20 | No test framework in repo. Spec explicitly chooses no unit tests; validation path is `bun run check` + `wrangler dev --remote` + `wrangler tail` + manual form submit. Validation steps are concrete and reproducible. |

**Total: 78/100 — GO**

## Key Patterns

- `src/lib/leads.ts` — named exports, `CloudflareEnv` interface, `getEnv(locals)` helper that pulls runtime env from `App.Locals`. Drizzle queries returning typed `Lead`/`NewLead` rows. `patchLead` returns `Lead | null`. The `LeadPatch` type uses `Partial<Pick<Lead, ...>>`. No default exports anywhere.
- `src/db/client.ts` — `getDb(d1: D1Database): Database` with `Database = DrizzleD1Database<typeof schema>`. Logger off. Canonical entry for new agent + workflow Drizzle calls.
- `src/db/schema.ts` — `leads` table already has every column the spec writes to: `category` (with the same enum), `qualification` (text, nullable — JSON stringified), `score` (integer), `draft` (text), `status` (with `processing`/`done` already in the enum), `outcome` (with `approved`/`edited`/`discarded`), `respondedAt` (text). The schema field is `respondedAt` (camelCase) but the DB column is `responded_at` — drizzle handles the mapping.
- `src/actions/index.ts` — uses `astro:actions` `defineAction({ accept: 'form', input: z.object(...), handler: async (input, ctx) => ... })`. Env access via `getEnv(ctx.locals)`. Throws `ActionError` on failure. Try/catch wraps `insertLead`. New `agent.queueLead(id)` call follows this pattern.
- `src/middleware.ts` — runtime env is read as `(context.locals as { runtime?: { env?: ... } }).runtime?.env`.
- `wrangler.jsonc` — flat JSONC structure. Existing bindings: `kv_namespaces` (SESSION), `d1_databases` (LEADS_DB). `nodejs_compat` flag is on, `compatibility_date` is `2026-04-15`. The Astro Cloudflare adapter merges this into `dist/server/wrangler.json` at build time.
- `astro.config.ts` — `output: 'static'` with `adapter: cloudflare({ imageService: 'compile' })`. No worker-entry hook is currently configured. The Vite SSR `external` block excludes `@resvg/resvg-js`, `satori`, `@aws-sdk/client-s3`.
- `tsconfig.json` — extends `astro/tsconfigs/strict`. JSX configured for React. Strict mode active.

## Dependencies

- `src/lib/leads.ts:claimPendingLeads` — consumed only by `src/pages/api/leads/pending.ts:31`. Safe to remove with that route.
- `src/lib/leads.ts:CloudflareEnv` — currently the only env interface in the repo. Will be replaced/extended by `src/types.ts:Env`. `src/lib/leads.ts:getEnv` and `src/middleware.ts` both inline-cast `locals` rather than importing this interface, so the rename has minimal blast radius.
- `src/lib/leads.ts:WORKER_PULL_TOKEN` field on `CloudflareEnv` — consumed by `src/pages/api/leads/[id].ts:44`. The spec keeps this route. **Resolution: keep the field on `Env`** so the debug surface continues to work; revisit when Phase 2 lands the WorkOS gate.
- `src/lib/leads.ts:insertLead` — consumed by `src/actions/index.ts:39`. Unchanged in Phase 1.
- `src/lib/leads.ts:patchLead` — consumed by `src/pages/api/leads/[id].ts:61`. Unchanged in Phase 1.
- `src/db/schema.ts:leads` — new consumers: `src/agents/lead-triage-agent.ts` (sweep query), `src/workflows/lead-triage-workflow.ts` (load + update).
- `src/actions/index.ts:server.contact.send` — consumed implicitly by `/contact` page via `astro:actions`. Adding `agent.queueLead(id)` is non-breaking (try/catch).

## Conventions

- **Naming**: camelCase variables/functions, PascalCase types/interfaces/classes. Files kebab-case. Schema columns snake_case in SQL but camelCase via drizzle column mapping.
- **Imports**: relative paths only (no path aliases). Type-only imports use `import type`. Cloudflare types from `@cloudflare/workers-types`. **Zod**: Astro files use `astro:schema`; agent/workflow files (bundled into the worker outside Astro module graph) use `zod` directly. Need to add `zod` to deps.
- **Error handling**: throw `ActionError` from action handlers; throw plain `Error` deeper. Try/catch at boundaries. Console.warn for non-blocking failures.
- **Types**: `interface` for object shapes; `type` for unions/intersections. No barrels. `Lead`/`NewLead` from drizzle's `$inferSelect`/`$inferInsert`.
- **Env access**: `getEnv(locals)` in actions/routes; `this.env.X` in agent/workflow.
- **Testing**: no test framework; validation is `bun run check` + dev server.
- **Drizzle**: `eq`, `inArray`, `asc`, `and`, `lt` from `drizzle-orm`. Returning rows via `.returning()`.

## Risks

1. **`N8N_AI_FLOW.md` is external (gist), not in the repo.** The spec twice references it as the source of truth for prompts. **Mitigation**: hand-author the prompts from the spec's sketches plus voice rules now in `PRODUCT.md`. Output: minimum-viable prompts covering classify / qualify / draft, with explicit `// TODO: tune` markers for further iteration. Flag in completion report.

2. **`WORKER_PULL_TOKEN` removal is inconsistent.** Spec says drop it from `.env.example` but `src/pages/api/leads/[id].ts:44` still reads `env.WORKER_PULL_TOKEN`. **Resolution**: keep the field on `Env` and the entry in `.env.example`. The debug-surface route keeps working until Phase 2 replaces it with a WorkOS gate.

3. **Worker entry / DO + Workflow class re-export shape is unverified.** Spec admits this with "validate during implementation." **Mitigation**: fetch `@astrojs/cloudflare` v13 docs and `agents` SDK "add to existing project" guide via Context7 before writing the worker-entry file.

4. **`astro:schema` Zod compatibility with `agents` SDK.** The agent + workflow are bundled into the worker entry, not the Astro action runtime. **Resolution**: use plain `zod` (add as dep) in `src/lib/ai-types.ts`. Astro action files keep `astro:schema`.

5. **Drizzle sweep query needs `and(eq, lt)`.** The schema uses `text('updated_at')` storing ISO strings. **Resolution**: use `and(eq(leads.status, 'processing'), lt(leads.updatedAt, cutoffISO))` — Drizzle handles ISO 8601 string comparison correctly.

6. **D1 read-after-write timing for `agent.queueLead`.** The workflow runs in a different DO context from the action that just inserted the row. **Resolution**: rely on `STEP_RETRY.persist` (3 retries, 2s exponential backoff) on the `load-lead` step. If `Lead ${id} not found` happens, retry covers it.

7. **`agents` SDK API surface assumed but not verified.** Spec was authored against an iterating SDK. **Mitigation**: fetch current `agents` SDK docs via Context7 before writing agent / workflow files; verify each method signature (`Agent.options.hibernate`, `this.sql`, `this.schedule`, `this.runWorkflow`, `this.approveWorkflow`, `AgentWorkflow`, `step.do`, etc.) against current docs.
