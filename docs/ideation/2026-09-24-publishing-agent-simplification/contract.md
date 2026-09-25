# Publishing Agent Simplification Contract

**Created**: 2026-09-24
**Readiness**: All 5 gates ready
**Status**: Approved
**Approval**: Express — single consolidated confirmation, no per-artifact review
**Supersedes**: None

## Problem Statement

The owner wants to develop and publish articles, not maintain model endpoint prices, token-cost units, provider names, allowance balances and reservation ledgers. The current config/publishing_agents.php and .env.example delegate these application decisions to deployment setup, while RunEditorialActivity passes a global/default-or-premium model over the nine existing SDK role agents.

The dedicated OpenRouter key already has an owner-managed spending limit. Current per-attempt allowances and pre-call reservations duplicate that control, and successful generation can pause merely because billing metadata is missing. This costs setup and operational effort without serving the owner's desired workflow.

Cost still matters when choosing models: prefer cost-effective, task-suited candidates, including available DeepSeek/open-weight and Gemini Flash-class models where evidence supports them. Do not confuse cost-aware design-time recommendations with an application billing subsystem. Actual editorial quality must be evaluated, not inferred from a model name or advertised capability list.

## Goals

1. Every concrete editorial agent owns an explicit recommended OpenRouter model and appropriate native SDK execution settings; running publishing requires no model/provider/price environment-variable matrix.
2. An authorized owner can change or reset a curated model override for each agent and pause agent requests from Publishing settings, with changes visible to subsequent requests/jobs without redeployment.
3. Remove all application allowances, top-ups, reservations, price ceilings and billing-dependent execution gates, including obsolete schema and UI, while preserving articles, revisions, approvals, conversations and native SDK usage data.
4. Preserve authorization, ownership, frozen-input/revision checks, human approval boundaries and bounded request/recovery behavior while allowing valid results to complete without cost metadata.
5. Choose recommendations from current capability, quality, latency and price research and validate the resulting editorial workflow with a small synthetic live trial and owner quality review. Support OpenRouter Auto Router as an explicit choice without promising a cheapest-model or quality guarantee.

## Success Criteria

- [ ] Every supported editorial role uses its own explicit SDK recommendation without a global default/premium route; allowlisted per-agent overrides and reset-to-recommended behavior are exercised through actual fake HTTP request bodies. Legacy publishing model/provider/price configuration is not required. — check: `php artisan test --compact tests/Feature/Publishing/EditorialAgentsTest.php tests/Feature/Publishing/PublishingAgentSettingsTest.php` → exits 0; these tests are extended/created by Phase 1 and cover all concrete editorial roles, defaults, overrides, invalid selections and absence of the legacy route/price inputs
- [ ] Named models and openrouter/auto produce capability-compatible structured-output/tool requests, with no explicit upstream provider pin or price ceiling. Optional reasoning settings are not sent blindly to incompatible overrides; requested and returned model identities remain distinguishable where the provider returns them. — check: `php artisan test --compact tests/Feature/Publishing/EditorialAgentsTest.php tests/Feature/Publishing/EditorialToolApprovalTest.php tests/Feature/Publishing/EvidenceResearchTest.php` → exits 0; request-body and response handling tests cover Auto Router, require_parameters, AskAuthor, research evidence and capability-appropriate options
- [ ] A valid editorial response completes when billing metadata is absent or unavailable; normal execution and recovery never need a generation-cost lookup or application allowance. Explicit provider rejection pauses with an actionable reason, and uncertain in-flight failures do not cause blind automatic regeneration. — check: `php artisan test --compact tests/Feature/Publishing/AgentBudgetTest.php tests/Feature/Publishing/EditorialWorkflowTest.php` → exits 0; existing budget-specific assertions are replaced with budget-free execution, missing-cost, 401/402/429, timeout and recovery cases while preserving unrelated safety coverage
- [ ] Fresh installation and upgrade migrations create initialized Spatie settings and remove obsolete allowance/reservation schema without losing articles, revisions, approvals, conversations or their existing SDK usage data. Obsolete budget-only pauses do not leave work permanently stranded or silently restart uncertain work. — check: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php tests/Feature/Publishing/PublishingBudgetRemovalTest.php` → exits 0; Phase 1 adds fresh/upgrade migration fixtures, preserved-content assertions and explicit legacy-pause transition coverage
- [ ] The app-wide pause switch blocks new model requests from start, stage transitions, native-tool resume, already-queued jobs and recovery; in-flight requests may finish. Re-enabling agents does not blindly replay uncertain or approval-blocked work, and queued-but-unstarted work remains recoverable. — check: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php tests/Feature/Publishing/EditorialWorkflowTest.php tests/Feature/Publishing/EditorialToolApprovalTest.php` → exits 0; pause/unpause is tested at each dispatch and execution boundary, including settings changes between jobs in a warm worker
- [ ] An already-started activity and its native human-approval continuation retain their captured execution choice when settings change. Auto-routed approval continuations pin the returned concrete model when available rather than silently selecting another model after approval. — check: `php artisan test --compact tests/Feature/Publishing/EditorialToolApprovalTest.php tests/Feature/Publishing/PublishingAgentSettingsTest.php` → exits 0; settings-change and approval-resume fixtures prove model identity and frozen-input stability, including missing returned-model handling
- [ ] Only users with the dedicated publishing configuration capability can read or mutate Publishing settings, including forged Livewire actions. Admin admission alone is insufficient; existing ownership and cross-user denial boundaries remain intact. — check: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php tests/Feature/Publishing/PublishingAuthorizationTest.php tests/Feature/Authorization` → exits 0; covers guest, Admin-only, publishing-without-configuration permission, authorized owner, revoked permission and cross-user denial; catalogs use authorization:sync, not boot-time grants
- [ ] The full editorial journey still requires explicit human angle, plan and release approval, rejects stale inputs and wrong-owner continuations, and never turns native tool approval into publication authority. — check: `php artisan test --compact tests/Feature/Publishing/EditorialToolApprovalTest.php tests/Feature/Publishing/PublishingJourneyTest.php tests/Feature/Publishing/EditorialReviewTest.php tests/Feature/Publishing/ReleaseIntegrityTest.php` → exits 0 with provider HTTP fakes; no test suite execution makes paid calls
- [ ] Publishing settings persists curated per-agent overrides, rejects unknown selections, resets to code recommendations and reports missing credentials without exposing keys. Workspace and Admin summaries render and operate without allowance/top-up/reservation dependencies. — check: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php tests/Feature/Publishing/AdminPublishingWorkspaceTest.php tests/Feature/Publishing/AdminPublishingEditorTest.php tests/Feature/Publishing/PublishingSessionTest.php` → exits 0; route, Livewire mutation, persistence, reset, secret-redaction and budget-free workspace assertions exercise rendered behavior
- [ ] Publishing JavaScript guards, PHP static analysis and production assets remain valid after removal of budget controls and addition of settings. — check: `bun test resources/js/admin/publishing && composer types:check && bun run build` → exits 0; existing editor/motion tests pass, Larastan passes and Vite builds the Admin assets
- [ ] The owner accepts the recommended agents on a synthetic editorial sample: interviewing asks rather than invents missing facts, research quotes accessible evidence, the plan and draft preserve the brief, and reviews identify seeded factual, voice and buyer issues without self-approval. A dated recommendation rationale compares relevant cost-effective candidates using current sources and clearly labels live observations versus vendor claims. — judgment call: Owner reviews Phase 3's source-linked model rationale and the actual live sample outputs, including all editorial roles and an AskAuthor continuation, then explicitly accepts them or requests targeted changes. No benchmark or capability metadata is presented as proof of editorial quality.
- [ ] The settings screen is usable within the incumbent Flux Pro/Inter Admin surface in light and dark themes at desktop and mobile widths, with keyboard-accessible save/reset/pause controls and understandable success, validation and provider-error states. — judgment call: Implementer exercises the authenticated rendered settings and publishing workspace at 1440px and 390px in light/dark themes and with keyboard navigation; owner reviews the results. Static HTML assertions or build success alone do not count as browser evidence.
- [ ] Active configuration and setup instructions contain no legacy PUBLISHING_AGENTS environment-variable matrix. Behavioral settings tests separately prove that the replacement configuration actually works. — check: `python3 -c 'import re; from pathlib import Path; files = [Path(p) for p in [".env.example", "config/publishing_agents.php", "docs/development-setup.md", "docs/production-setup.md"]]; stale = [str(p) for p in files if re.search(r"PUBLISHING_AGENTS_[A-Z0-9_]+", p.read_text())]; assert not stale, stale'` → exits 0; missing required files fail rather than producing a vacuous negative match
- [ ] Live validation evidence exists as structured, nonsecret data with source-linked recommendations and actual input/output records for each selected role/model, a native AskAuthor continuation and a separate Auto Router probe. This validates completeness only; owner quality acceptance remains separate. — check: `python3 -c 'import json; from pathlib import Path; d=json.loads(Path("docs/ideation/2026-09-24-publishing-agent-simplification/model-validation.json").read_text()); roles={p.stem for p in Path("app/Ai/Agents").glob("*.php") if p.stem != "EditorialAgent"}; assert d["schema_version"] == 1 and d["recorded_at"]; recommendations=d["recommendations"]; assert roles == {r["agent"] for r in recommendations}; assert all(r["model"] and r["rationale"] and r["sources"] for r in recommendations); completed=[r for r in d["live_trials"] if r["status"] == "completed" and all(r.get(k) for k in ["scenario","agent","requested_model","returned_model","input","output"])]; assert all(any(t["scenario"] == "initial" and t["agent"] == r["agent"] and t["requested_model"] == r["model"] for t in completed) for r in recommendations); assert any(r["scenario"] == "ask_author_continuation" and r["agent"] == "Interviewer" for r in completed); assert any(r["scenario"] == "auto_probe" and r["requested_model"] == "openrouter/auto" and r["returned_model"] != "openrouter/auto" for r in completed)'` → exits 0 after Phase 3 records real trial evidence; it makes no network request and does not establish prose quality or owner approval
- [ ] Authoritative guidance no longer instructs operators or future agents to configure publishing price matrices or preserve the removed budget system. — judgment call: Reviewer compares the implemented behavior with .ai/rules/ai.md updated through record-rule, generated agent guidance, relevant existing development/production setup instructions and the affected Admin design/surface records, resolving contradictory guidance rather than merely appending a new rule.

## Scope Boundaries

### In Scope

- Native per-agent recommendations and curated overrides, including explicit OpenRouter Auto Router support — The existing nine role agents already provide the correct configuration boundary; SDK attributes/methods and call overrides are sufficient. A small code-owned allowlist serves validation and UI, not a new routing framework.
- Typed database-backed Publishing settings and a small permission-protected Admin screen — Use installed spatie/laravel-settings 3.9 with migrations/defaults and fresh per-job reads. Expose only a pause switch and optional model override/reset per agent under Publishing; credentials remain deployment secrets.
- Remove the entire obsolete application budgeting path and schema — The owner explicitly approved removing allowances, top-ups, reservations and their storage. Remove budget-only services/models/factories, price matrices, billing reconciliation and UI; preserve native SDK usage without building new cost telemetry. Existing budget-specific tests may be replaced, not unrelated tests.
- Preserve editorial execution safety independently from billing — Ownership, permissions, stale-input/revision checks, native approval integrity, explicit publication approval, bounded loops, actionable provider failures and non-blind recovery remain functional requirements even without budgets.
- Current per-role model research and a bounded synthetic live sample trial — The owner requested task-suited, cost-effective recommendations and approved paid validation. Research available DeepSeek/open-weight, Gemini Flash-class and other justified candidates; do not assume an example name is an available exact model ID. Validate capability compatibility offline before the live trial and obtain owner judgment on quality.
- Focused regression coverage and operational/design guidance reconciliation — Changes cross settings, queues, approvals, migrations, authorization and existing UI. Tests and existing runbooks/shared rules must describe the same simplified system; preserve the existing publishing UI work, now committed and fast-forwarded into main.

### Out of Scope

- Application budget gates, reservations, price ceilings or remote key-cap management — OpenRouter owns spend enforcement; selecting models economically is a design-time activity, not a per-request billing concern. Do not edit OpenRouter keys, limits or account settings.
- Per-article cost display, new cost ledgers or billing reconciliation — The owner explicitly called cost display a nice-to-have. Preserve native SDK data already produced, but do not require or reconstruct cost metadata.
- Automatic model optimization, application-built routers or an evaluation platform — Use researched code recommendations and OpenRouter's existing router. The live trial is a bounded acceptance exercise, not a new product subsystem.
- Global default/premium model tiers, arbitrary model/provider forms, and API-key editing in the UI — These recreate the rejected configuration burden. Each agent is explicit; overrides are curated and credentials stay in environment-backed secret configuration.
- Generic Admin settings infrastructure, tenant settings or new identity models — This project needs one app-wide Publishing settings group using existing global authorization and Admin conventions.
- Publishing workflow redesign, automatic publication or removal of human/editorial protections — Removing budget machinery does not grant agents new authority or invalidate the current editorial workflow.
- General research-tool migration or dependency upgrades — Keep the existing working evidence flow. Only a minimal SDK-compatible adjustment necessary to pass the selected models' research trial belongs here; unrelated provider/search modernization is a separate project. No new package is needed.
- Production deployment, real-article publication, broad visual redesign or unrelated working-tree cleanup — Acceptance uses isolated synthetic data and the existing Admin design. Existing user changes must not be overwritten, staged or reverted incidentally.

### Future Considerations

- Optional per-article cost display from provider-reported usage, only if the owner later wants it.
- Revisit per-agent recommendations after real editorial use; model choices are evidence-backed starting points, not permanent quality guarantees.

## Decisions Considered and Rejected

- **Keep credentials in environment-backed secret configuration; move meaningful editorial preferences into Spatie settings.** — rejected: Keep all publishing choices in .env, or mirror every variable into a settings form.. Both rejected approaches require the operator to assemble application behavior and maintain unrelated endpoint/billing metadata.
- **Give each SDK role agent its own explicit recommendation with an optional curated override.** — rejected: A shared default/premium routing layer, including default-plus-fact-checker selectors.. The owner explicitly challenged the global default: tasks have distinct needs and the SDK already supports per-agent configuration.
- **OpenRouter is the sole spending authority; remove app allowances, reservations and price enforcement.** — rejected: Duplicate per-article budgeting and pre-call cost reservations.. The owner uses dedicated publishing keys with an approximately $50/month cap and accepts per-article variance.
- **Accept valid editorial results even without cost metadata and defer a cost display.** — rejected: Pause successful work until billing is reconciled or require cost telemetry as part of this feature.. The owner selected continue-with-unknown-cost, then clarified that cost should not be a runtime concern and display is optional.
- **Balance model quality, latency and price through current per-role research, favoring cost-effective models where suitable.** — rejected: Default every role to a premium model, or continuously police spend during generation.. The owner explicitly distinguished economical model selection from futile runtime cost checks, naming DeepSeek/open-weight and Gemini Flash-class models as candidates rather than mandated exact IDs.
- **Offer openrouter/auto as an explicit SDK model choice, with capability constraints and honest dynamic-model semantics.** — rejected: Build a custom model router or claim one model is always best for every prompt.. The owner requested OpenRouter routing where a suitable fixed choice is uncertain; native routing avoids a parallel implementation.
- **Require current-source research, offline integration tests and a small paid synthetic live trial with owner output review.** — rejected: Accept recommendations solely from research and mocked tests.. The owner explicitly chose research plus sample trial. Metadata and mocks cannot establish real editorial quality.
- **Remove obsolete budget storage and replace budget-specific tests with new execution-safety coverage.** — rejected: Retain a read-only legacy budget-history subsystem.. The owner explicitly approved schema removal; articles, revisions, approvals, conversations and native usage are retained.
- **Execute and commit directly on main after fast-forward-only integration of the existing publishing branch.** — rejected: Create an express-finish isolation branch or a merge commit.. After committing the prior work, the owner explicitly requested main-only development and a ff-only merge because there are no users. main was fast-forwarded from 72f7d8a to df1d96e with a clean tree; no push was performed. This overrides the skill's default isolation branch.
- **Mechanically verify removal of legacy environment configuration and completeness of structured live evidence, while keeping semantic guidance and editorial-quality reviews human.** — rejected: Use reviewer judgment alone to establish configuration cleanup and presence of trial evidence.. The success-criteria critic identified two independently checkable artifact requirements. The added checks perform no paid calls and do not pretend that artifact existence proves quality.
- **Generate phase specs after contract approval and before implementation; stop if required rule-recording tooling cannot be reached.** — rejected: Treat not-yet-generated specs as a preapproval dependency, or hand-edit shared rules when a child lacks record-rule.. The dependency review exposed a lifecycle ambiguity, not a missing implementation dependency. The ideation lifecycle intentionally generates specs after approval, and project rules forbid a direct-edit fallback.
- **Keep bounded desktop/mobile light/dark checks for the changed settings surface in MVP.** — rejected: Defer all responsive/browser checks as extra scope.. The scope critic's suggestion was declined: this is a narrow new Admin settings surface within established project-wide usability standards, not a broad visual redesign.
- **Use the repository's dated ideation directory convention and treat OpenRouter workspace readiness as a live-trial preflight.** — rejected: An undated project directory or assuming that a present API key proves the workspace is ready.. The owner requested naming consistency and confirmed a live key exists while noting that workspace configuration may still be necessary.
- **Finish and commit the approved contract and three standalone specs, then wait for the owner to invoke execution.** — rejected: Start the express implementation or paid trial immediately after artifact generation.. At the run-mode question, the owner explicitly chose artifact generation and commit only. This preserves express artifact approval and main-only execution semantics without starting any implementation. No new branch or push is authorized.
- **Require evidence for the selected recommended models, the native continuation and an independently recorded Auto probe.** — rejected: Let a different model's old trial, an empty Auto result, or a missing continuation satisfy the completeness check.. Spec self-review identified gaps in the initial evidence predicate. Scenario-tagged records tighten the existing approved validation scope without adding paid invocations or substituting mechanical checks for human quality judgment.
- **Skip mining the five older implementation-note files during this interview.** — rejected: Expand intake into a retrospective of publishing and marketing runs.. The owner selected skip; existing learnings and current-code evidence are sufficient for this focused change.

## Execution Plan

_Added during Phase 5 handoff. Pick up this contract cold and know exactly how to execute._

### Dependency Graph

```
Native agent configuration and budget-free execution
  └── Publishing settings and operational integration  (blocked by Native agent configuration and budget-free execution)
        └── Live model trial and recommendation validation  (blocked by Publishing settings and operational integration)
              └── Owner editorial acceptance  (blocked by Live model trial and recommendation validation)
```

### Execution Steps

**Run the project** (recommended) — autopilot reads this contract, plans dependency waves, runs independent phases in parallel, and gates on failure:

```bash
/ideation:autopilot docs/ideation/2026-09-24-publishing-agent-simplification/contract.md
```

**Or run it unattended** — a `/goal` is a durability wrapper around the same autopilot run: Claude re-checks the condition before it is allowed to stop, so failures get repaired and re-run. Generated by `contract-gen --print-goal`; this is the only copy of that string:

```
/goal Drive the Publishing Agent Simplification contract (2026-09-24-publishing-agent-simplification) to completion with /ideation:autopilot.

1. Run `/ideation:autopilot docs/ideation/2026-09-24-publishing-agent-simplification/contract.md`. All commits belong on branch main — switch to it before any run.
2. It dispatches a BACKGROUND workflow. Wait for the completion notification — never start a second autopilot run while one is in flight.
3. Then run the ideation plugin's `scripts/verify.mjs` against `docs/ideation/2026-09-24-publishing-agent-simplification/contract-data.json` and leave its VERIFY line in the conversation. Resolve the plugin's install directory first — `${CLAUDE_PLUGIN_ROOT}/scripts/verify.mjs` is a placeholder, not a shell variable, and bash will not expand it. That line is the only evidence this goal is judged on.
4. If anything failed, fix the spec or the implementation and go back to step 1. Autopilot skips phases that already have commits.

Done when the most recent VERIFY line reads fail=0 and commits=3/3 — or when two consecutive VERIFY lines are identical and still failing, in which case name the failing checks and stop, because a contract whose checks have rotted must not trap the run.
```

**Or run phases manually** in dependency order:

**Strategy**: Focused MVP, sequential: simplify execution/storage, expose settings, then perform the bounded live trial and owner acceptance. The three standalone specs are ready. Current handoff is artifacts and commit only: wait for the owner to invoke execution; do not start implementation or paid calls during this finish. Later execution commits directly on main per the owner's explicit override, with express/strict review semantics; do not create an isolation branch or merge commit, and do not push. If new unrelated uncommitted changes appear, stop rather than including them. Capture the starting main SHA for the eventual review diff. Dispatch only the three phases with specPath; Owner editorial acceptance is a non-dispatchable human checkpoint, not a builder phase or an automatic pass.

1. **Phase 1** — Native agent configuration and budget-free execution _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-24-publishing-agent-simplification/spec-phase-1.md
   ```

2. **Phase 2** — Publishing settings and operational integration _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-24-publishing-agent-simplification/spec-phase-2.md
   ```

3. **Phase 3** — Live model trial and recommendation validation _(blocking)_

   ```bash
   /ideation:execute-spec docs/ideation/2026-09-24-publishing-agent-simplification/spec-phase-3.md
   ```

4. **Phase 4** — Owner editorial acceptance _(blocking)_

   ```bash
   # Review: Owner editorial acceptance
   ```

---

_This contract was generated from brain dump input. Review and approve before proceeding to specification._
