# Holistic Hardening Contract

**Created**: 2026-05-05
**Confidence Score**: 95/100 (after clarifying questions)
**Status**: Draft
**Supersedes**: None

## Problem Statement

`birdcar.dev` has grown from a static blog into a non-trivial Cloudflare Workers application: an Astro 6 site with a contact form that fans into a Durable Object agent (`LeadTriageAgent`), a 7-step `LeadTriageWorkflow` calling Workers AI, a queue with a cron sweep safety net, WorkOS AuthKit on `/admin/*` and `/agents/*`, D1 + Drizzle, and six rehype plugins powering custom markdown. The runtime surface is sophisticated, but maintenance ergonomics and regression safety have not kept pace with feature growth.

Concretely: session/cookie/auth logic is duplicated across 5+ files (middleware, worker, login, callback, logout), making any auth change a multi-file edit with no test coverage to catch drift. Rehype plugins redefine `extractText` and dual-format `data-attr` access independently. Admin scripts repeat the agent-subscription pattern. The `cloudflare:workers` env import is inlined in three places. Most critically, the entire runtime surface — workflow steps, agent RPC, worker queue/scheduled handlers, WorkOS session lifecycle, D1 lead CRUD — has zero automated tests, with a single rehype unit test (`rehype-image-cdn.test.ts`) as the only existing test infrastructure. CI builds and deploys but never validates behavior.

The cost compounds: every refactor is high-risk because regressions only surface on production traffic; every duplication invites inconsistency drift (e.g., one session-handling site adding `Secure` checks, another not); future contributors (or future me with a 6-month gap) lack a feedback loop tighter than "deploy and watch logs." Hardening means: extracting the duplicated patterns into single-source-of-truth modules, validating against current Cloudflare/Astro best practices, and standing up a test harness that exercises the actual runtime (Workers + D1 + DO + Workflow) so future changes have a regression net.

## Goals

1. **Eliminate the 8 identified DRY violations** — session/cookie helpers, env loader, structured event logging, admin agent controller, rehype utilities, Drizzle query patterns, and Astro Action validation wrapping consolidated into single-source-of-truth modules. Build-script CLI helpers explicitly excluded.
2. **Validate against Cloudflare Workers + Astro 6 best practices** — Workers retry/timeout configs, Durable Object hibernation, Workflow `step.do` retry shapes, queue idempotency, observability (Workers Logs sampling, source maps), `cloudflare:workers` virtual-module usage, Astro Action error semantics, middleware prerender skip — each documented as conformant or remediated.
3. **Stand up `@cloudflare/vitest-pool-workers` test infrastructure** with 30+ tests covering: critical-path integration (action → queue → workflow → approval), WorkOS session lifecycle, worker handlers (fetch/queue/scheduled), agent RPC, D1 layer, **and unit tests for every `lib/*` function plus the 5 untested rehype plugins**. Existing `bun:test` rehype-image-cdn test stays.
4. **Block deploys on test or typecheck failure** — `.github/workflows/deploy.yml` runs `astro check` and the new test suite on push and pull_request; deploy step gated on green CI.
5. **Result**: every duplicated pattern has one definition; every runtime entry point has a test; every PR runs typecheck + tests before merge or deploy.

## Success Criteria

- [ ] `grep -r "import('cloudflare:workers')"` returns exactly one site (`src/lib/env.ts` or equivalent), not three
- [ ] `readSessionCookie` / `validateSession` / `buildSessionCookie` / `buildClearedSessionCookie` invoked exclusively through a single `requireSession()` / `clearSession()` API; raw cookie helpers no longer imported by route files
- [ ] All 6 rehype plugins import `extractText`, `getDataAttr`, `buildErrorElement` from `src/lib/rehype/utils.ts`; no local redefinition
- [ ] `src/scripts/admin-leads-list.ts` and `admin-lead-detail.ts` share an `AgentController` abstraction; DOM helpers (`setButtonsDisabled`, `showNotice`) extracted
- [ ] Astro Actions validate-then-throw pattern wrapped in a single `withActionErrorHandling` helper; `src/actions/index.ts` shows the pattern in use
- [ ] `lib/leads.ts` query operations expressed via a typed `LeadsRepo` (or equivalent) instead of raw inline `db.select(...).from(...).where(...)` at call sites
- [ ] `vitest.config.ts` configured with `@cloudflare/vitest-pool-workers`; `bun run test` (or `bun x vitest run`) passes locally and in CI
- [ ] Test files exist and pass for: `actions/index.ts`, `worker.ts` (fetch + queue + scheduled), `agents/lead-triage-agent.ts` (queueLead, approveLead, discardLead, sweepStuckRows, recordActivity), `workflows/lead-triage-workflow.ts` (happy path + spam short-circuit + reject + timeout), `lib/workos.ts` (validate, refresh, logout, safeReturnPath, cookie builders), `middleware.ts` (auth gate, WS upgrade, markdown negotiation, prerender skip), `lib/leads.ts`, every `lib/rehype/*.ts` plugin
- [ ] Total test count ≥ 30; critical-path integration tests cover the action → queue → workflow → approval roundtrip end-to-end
- [ ] `.github/workflows/deploy.yml` has a `test` job (or step) running `astro check` + the test suite; deploy job uses `needs:` to gate on test success; equivalent `pull_request` trigger added
- [ ] A `BEST-PRACTICES.md` audit report exists in `docs/` enumerating each Cloudflare/Astro convention checked, the verdict, and any deltas applied
- [ ] All existing functionality unchanged — manual smoke test of `/contact` form submission produces a lead in D1, fires a workflow, and surfaces an approval in `/admin/leads`

## Scope Boundaries

### In Scope

- Refactor of all 8 DRY hotspots: session/auth helpers, env loader, structured logging, admin script controller, rehype utilities, Drizzle repository pattern, Astro Action error wrapper, admin DOM helpers
- New test infrastructure: `@cloudflare/vitest-pool-workers` setup, miniflare bindings for D1/KV/AI/DO/Queue/Workflow/Email
- Critical-path integration tests through the contact-form pipeline
- Unit tests for every `src/lib/*` function and every rehype plugin
- WorkOS session lifecycle tests (validate, refresh, logout, cookie roundtrip, `safeReturnPath`)
- Worker entry tests (fetch routing, queue handler retry, scheduled cron, agent gating)
- DO agent + workflow tests (RPC, state mutations, sweepStuckRows idempotency, workflow happy path, spam short-circuit, reject, timeout)
- D1 lead CRUD layer tests
- CI: `.github/workflows/deploy.yml` updated to run `astro check` + tests on push and pull_request, deploy gated on success
- Best-practices audit report (`docs/BEST-PRACTICES.md`) cross-referencing Cloudflare Workers + Astro 6 conventions against current code
- Mocking strategy for Workers AI (`createWorkersAI`) and WorkOS HTTP calls

### Out of Scope

- **Build scripts** (`scripts/cf-bootstrap.ts`, `cf-create-admin.ts`, `generate-og.ts`, `sync-images.ts`, `post-build.ts`, `update-readme.ts`) — explicitly excluded by user; these run at build/setup time and aren't part of the runtime regression surface. CLI helper duplication between `cf-bootstrap` and `cf-create-admin` is parked.
- New features or product changes — this is hardening, not feature work
- Visual/CSS changes to `.astro` pages or `src/styles/*.css`
- Changes to AI prompts (`src/lib/prompts.ts`), AI types (`src/lib/ai-types.ts`), or scoring rules (`src/lib/triage-config.ts`) — these encode product decisions
- Migrating away from Drizzle, the Agents SDK, or Workers AI — the stack is the constraint, not the variable
- E2E browser testing (Playwright); the test harness targets the Workers runtime, not the browser UI
- Performance benchmarking or load testing
- Content authoring changes (markdown files in `src/content/`)

### Future Considerations

- CLI helper extraction across `scripts/cf-bootstrap.ts` and `scripts/cf-create-admin.ts` (deferred with build scripts)
- Playwright/browser-level e2e test for the admin dashboard
- Bundle size budget enforcement
- Linting (Biome / ESLint) added to CI
- Migration of `/admin` rendering from inline `.astro` + vanilla TS to a more structured client (Phase 2 dashboard work referenced in the agent's `approveLead` `editedBody` parameter)
- Alarm-based replacement for the worker-level cron once DO bootstrap reliability is verified

## Execution Plan

### Dependency Graph

```
Phase 1: Test Infrastructure Foundation
  └── Phase 2: Critical-Path Integration Tests
        └── Phase 3: DRY Refactor (Comprehensive Sweep)
              ├── Phase 4: Unit Tests for lib/* + Rehype Plugins   (parallel with 5)
              └── Phase 5: Best-Practices Audit + Remediation       (parallel with 4)
```

Sequential through Phase 3 — each phase establishes a guarantee the next depends on. Phase 4 and Phase 5 fan out from Phase 3 with low file-overlap risk.

### Execution Steps

**Strategy**: Hybrid — sequential for phases 1→2→3, agent team for phases 4+5.

1. **Phase 1** — Test Infrastructure Foundation (blocking)
   ```bash
   /execute-spec docs/ideation/holistic-hardening/spec-phase-1.md
   ```
   Done when: `bun run test` exits 0 against the canary; PR runs `test` job; `deploy` blocked on it.

2. **Phase 2** — Critical-Path Integration Tests (blocking)
   ```bash
   /execute-spec docs/ideation/holistic-hardening/spec-phase-2.md
   ```
   Done when: ≥20 integration tests cover the action → queue → workflow → approval pipeline + middleware + agent RPC + workos lifecycle.

3. **Phase 3** — DRY Refactor, Comprehensive Sweep (blocking)
   ```bash
   /execute-spec docs/ideation/holistic-hardening/spec-phase-3.md
   ```
   Done when: all 8 DRY hotspots eliminated, every Phase 2 integration test still green, `bun run build:ci` succeeds.

4. **Phases 4 & 5 in parallel** — see Agent Team Prompt below, or run sequentially:
   ```bash
   /execute-spec docs/ideation/holistic-hardening/spec-phase-4.md
   /execute-spec docs/ideation/holistic-hardening/spec-phase-5.md
   ```

### Agent Team Prompt

Use after Phase 3 ships. Start a new Claude Code session, enter delegate mode (Shift+Tab), and paste the block below. The lead spawns two teammates to run Phase 4 and Phase 5 concurrently, monitors progress, and synthesizes results.

```
You are the lead for the Holistic Hardening project (./docs/ideation/holistic-hardening/contract.md). Phase 3 is complete and merged. Spawn two teammates and coordinate Phases 4 and 5 in parallel.

Teammate "tester":
  Task: execute ./docs/ideation/holistic-hardening/spec-phase-4.md
  Goal: ship unit tests for every src/lib/* module and the 5 untested rehype plugins; cover newly-extracted modules from Phase 3 (env, session, event-log, rehype/utils, leads-repo, with-action-error, admin-controller).
  Definition of done: ≥18 new test files, `bun run test` green, no flaky runs across 3 attempts.

Teammate "auditor":
  Task: execute ./docs/ideation/holistic-hardening/spec-phase-5.md
  Goal: produce docs/BEST-PRACTICES.md with a verdict for every checklist item; apply remediations whose blast radius is <50 lines and not on the workflow/agent hot path.
  Definition of done: BEST-PRACTICES.md exists with the documented structure, all 36 checklist items have verdicts, `bun run test && bun run check && bun run build:ci` green.

Coordinate on shared files — only one teammate should modify a shared file at a time:
  - package.json (tester adds happy-dom; auditor may bump a Cloudflare devDep)
  - src/lib/triage-config.ts (tester adds triage-config.test.ts; auditor may update STEP_RETRY shape)
  - src/lib/ai-types.ts (tester adds ai-types.test.ts; auditor unlikely to touch but possible)
  Resolution: tester writes test files first; auditor pulls before any source-file remediation. If a conflict surfaces, auditor pauses, lets tester land their commit, then rebases.

Both teammates work on the main branch via separate commits — no shared branches needed; tests and audit doc are additive and orthogonal in practice.

Synthesize: when both teammates report done, run `bun run test && bun run check && bun run build:ci` end-to-end; verify total test count ≥30 and BEST-PRACTICES.md is complete. Report final state to me.
```

### Notes

- Each phase commits independently — no monolithic PR
- Phases 1 and 2 build the safety net; Phase 3 cashes it; Phases 4 and 5 round out coverage and validation
- After Phase 3, agent teams require `CLAUDE_CODE_EXPERIMENTAL_AGENT_TEAMS=1` in `.claude/settings.json` or `~/.claude/settings.json`
- A user wanting fully sequential execution can ignore the agent team prompt and run the two `/execute-spec` commands one after the other
