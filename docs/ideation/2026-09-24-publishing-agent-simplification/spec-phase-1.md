# Implementation Spec: Publishing Agent Simplification — Phase 1

**Contract**: ./contract.md
**Phase**: Native agent configuration and budget-free execution
**Estimated effort**: L
**Prerequisites**: Approved contract; current branch `main`; clean baseline apart from this project's artifacts.

## Technical Approach

Replace the publishing system's default/premium endpoint arrays and pre-call budgeting with the installed Laravel AI SDK's per-agent configuration. The application already has nine concrete agents, native durable conversations and approvable tools. Keep those boundaries. Each concrete agent declares an explicit recommended model and role-appropriate execution settings. A small code-owned model allowlist validates optional per-agent overrides stored by the already-installed Spatie settings package. It is not an endpoint registry, pricing service or model router.

Remove the application budgeting subsystem, including its schema, dependencies and immediate UI references, in this phase so the application remains usable between phases. Preserve transactional activity claiming, authorization and freshness checks independently from reservation creation. Continue accepting valid output when cost metadata is absent. Provider rejection and uncertain execution are execution failures, not accounting states.

Use existing conventions and installed APIs: PHP 8.5, Laravel 13.30.1, `laravel/ai` 0.11.2, `spatie/laravel-settings` 3.9.0. Reconfirm versions at implementation. No package installation, provider account modification, live inference or publication is authorized in this phase.

## Decisions Considered and Rejected

- **Explicit native configuration per agent** — rejected global default/premium routes and a shared model chosen by the caller for every role. The owner specifically requested agent-local model decisions.
- **Curated per-agent overrides** — rejected arbitrary provider/model/price forms and an environment-variable matrix. Secrets stay in environment-backed configuration.
- **OpenRouter owns spending limits** — rejected application allowances, top-ups, reservations, price ceilings and billing reconciliation. The owner's dedicated key already has an externally managed cap.
- **Model economics belong in research** — rejected both indiscriminate premium defaults and runtime cost policing. Prefer economical capable models; justify stronger choices by the role.
- **Missing cost never blocks usable output** — rejected pausing successful generation to settle billing. Cost display and new telemetry are deferred; native SDK usage remains intact.
- **Use OpenRouter Auto Router where appropriate** — rejected a home-grown routing framework. Auto is a supported explicit choice, not a promise of lowest price or best quality.
- **Remove obsolete budget storage** — rejected maintaining a legacy budget-history subsystem. The owner authorized loss of budget-only data, not article/conversation/approval data.
- **Retain editorial protections** — removing financial gates does not remove human approvals, evidence grounding, ownership, stale-input protection or bounded execution.
- **Commit directly on main** — the owner explicitly rejected an isolation branch and merge commits. The earlier branch was fast-forwarded to `main` at `df1d96e`; do not push.
- **Artifacts were generated without implementation** — the owner asked for committed artifacts, then a stop. This spec runs only when they later invoke the execution command.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/EditorialAgentsTest.php`

**Playground**: Existing Pest feature tests with SQLite in memory, model factories, `Queue::fake()`/`Http::fake()` as appropriate and `Http::preventStrayRequests()`.

**Why**: Fake HTTP request-body assertions verify the application's actual native SDK configuration without spending money; focused activity tests exercise the transactional execution boundary.

Run only the relevant file/filter while iterating. Run the complete publishing suite before completing the phase. Never substitute paid calls for offline regression checks.

## File Changes

Generate Laravel classes, migrations and tests using the appropriate Artisan generators with `--no-interaction`; inspect `--help` first. Publish the settings package's table migration/config through its provider. Use the filenames below for deterministic handoff, renaming freshly generated timestamped migrations if needed without duplicating them.

### New Files

| File Path | Purpose |
| --- | --- |
| `app/Settings/PublishingAgentSettings.php` | Typed app-wide paused flag and per-role model overrides. |
| `config/settings.php` | Package registration, database repository and settings migration discovery; no new environment knobs. |
| `database/migrations/2022_12_14_083707_create_settings_table.php` | Package-published settings storage migration. |
| `database/settings/2026_09_24_230000_create_publishing_agent_settings.php` | Initializes the publishing settings group. |
| `database/migrations/2026_09_24_230100_remove_publishing_budget_storage.php` | Forward migration removing obsolete financial state and preserving diagnostic/activity identity. |
| `tests/Feature/Publishing/PublishingAgentSettingsTest.php` | Defaults, overrides, reset, request/job freshness and later UI authorization coverage. |
| `tests/Feature/Publishing/PublishingBudgetRemovalTest.php` | Fresh/upgrade schema behavior and preservation of non-budget records. |

### Modified Files

| File Path | Changes |
| --- | --- |
| `app/Ai/Agents/EditorialAgent.php` | Remove priced endpoint coupling; common native provider configuration, optional selected model and captured execution options. |
| `app/Ai/Agents/Interviewer.php` | Explicit recommendation and role settings. |
| `app/Ai/Agents/Researcher.php` | Explicit recommendation; retain evidence/search behavior. |
| `app/Ai/Agents/Planner.php` | Explicit recommendation and role settings. |
| `app/Ai/Agents/Drafter.php` | Explicit recommendation and output allowance appropriate to document generation. |
| `app/Ai/Agents/FactReviewer.php` | Explicit factual-reasoning recommendation. |
| `app/Ai/Agents/VoiceReviewer.php` | Explicit editorial recommendation. |
| `app/Ai/Agents/BuyerReviewer.php` | Explicit recommendation for audience/objection analysis. |
| `app/Ai/Agents/ReviewReconciler.php` | Explicit recommendation for bounded grouping. |
| `app/Ai/Agents/RevisionRechecker.php` | Explicit recommendation for checking seeded findings. |
| `config/publishing_agents.php` | Retain only a small nonfinancial model allowlist and relevant code-owned safety limits. |
| `.env.example` | Remove every `PUBLISHING_AGENTS_*` variable; retain OpenRouter secret configuration. |
| `app/Actions/Publishing/RunEditorialActivity.php` | Budget-free claim/configuration/prompt/result/failure handling. |
| `app/Actions/Publishing/StartEditorialActivity.php` | Settings-aware dispatch; preserve durable pending work and frozen inputs. |
| `app/Actions/Publishing/ResumeEditorialActivity.php` | Pause check without losing pending approval decisions. |
| `app/Actions/Publishing/ApprovePublishingStage.php` | Replace environment enablement with current settings at paid-dispatch boundaries. |
| `app/Actions/Publishing/AdvancePublishingAttempt.php` | Remove allowance initialization and preserve explicit attempt pause/resume semantics. |
| `app/Console/Commands/RecoverEditorialActivities.php` | Recover eligible pending work without billing queries; pause ambiguous running work. |
| `app/Models/PublishingAttempt.php` | Remove financial fillable/casts/relation. |
| `app/Models/EditorialActivity.php` | Remove reservation relation; retain model snapshot, generation and conversation identity. |
| `database/factories/PublishingAttemptFactory.php` | Remove financial fields. |
| `app/Authorization/Publishing/Permission.php` | Remove obsolete Budget capability from code. |
| `app/Authorization/Publishing/Catalog.php` | Remove Budget from catalog/role definitions; normal sync handles assignments. |
| `app/Policies/ArticlePolicy.php` | Remove the obsolete budget authorization method. |
| `resources/views/components/admin/publishing/⚡article-workspace.blade.php` | Remove top-up state/actions and budget queries without disturbing editorial state or save guards. |
| `resources/views/components/admin/publishing/partials/activity.blade.php` | Retain activity history; remove allowance panel and financial controls. |
| `resources/views/components/admin/publishing/partials/develop.blade.php` | Remove allowance promises from start/approval copy. |
| `resources/views/components/admin/publishing/partials/session.blade.php` | Use execution-status wording without allowance references. |
| `resources/views/components/admin/publishing/partials/write.blade.php` | Remove remaining-allowance wording, preserve deliberate review-cycle action. |
| `tests/Feature/Publishing/AgentBudgetTest.php` | Replace obsolete financial assertions with execution, failure and recovery behavior; retain file to preserve acceptance commands. |
| `tests/Feature/Publishing/EditorialAgentsTest.php` | Native role defaults and request options. |
| `tests/Feature/Publishing/EditorialToolApprovalTest.php` | Budget-free native approval/resume and model pinning. |
| `tests/Feature/Publishing/EditorialWorkflowTest.php` | Dispatch/pause/freshness and review orchestration. |
| `tests/Feature/Publishing/EditorialReviewTest.php` | Replace route/price test setup; preserve review semantics. |
| `tests/Feature/Publishing/EvidenceResearchTest.php` | Replace setup; preserve grounding and safe fetching. |
| `tests/Feature/Publishing/PublishingJourneyTest.php` | Budget-free end-to-end flow with human approvals. |
| `tests/Feature/Publishing/PublishingAuthorizationTest.php` | Remove budget-only assertions without reducing ownership denials. |
| `tests/Feature/Publishing/AdminPublishingEditorTest.php` | Settings-backed test setup; preserve editor safety. |
| `tests/Feature/Publishing/AdminPublishingWorkspaceTest.php` | Workspace without budget controls. |
| `tests/Feature/Publishing/PublishingSessionTest.php` | Session copy/state without financial dependencies. |
| `tests/Feature/Authorization/AuthorizationSyncCommandTest.php` | Adjust affected catalog expectations only. |
| `.ai/rules/ai.md` | Supersede the budget-reservation rule via Boost `record-rule`, never direct editing. |

### Deleted Files

| File Path | Reason |
| --- | --- |
| `app/Services/Publishing/AgentBudget.php` | No application financial enforcement. |
| `app/Services/Publishing/EditorialModelBudget.php` | No priced-endpoint validation or pre-call quotes. |
| `app/Services/Publishing/OpenRouterBilling.php` | No mandatory generation-cost reconciliation. |
| `app/Models/AgentBudgetReservation.php` | Obsolete budget-only model. |
| `database/factories/AgentBudgetReservationFactory.php` | Obsolete budget-only factory. |

Do not delete other tests. Existing historical migrations remain unchanged; the forward removal migration accounts for their schema. Scout for other direct references before deletion and include any necessary reference removal in the reported changed-file list, not unrelated cleanup.

## Implementation Details

### 1. Preflight and authoritative rules

**Pattern to follow**: `.ai/rules/index.md`, `.ai/rules/ai.md`, existing domain catalogs.

1. Confirm `main`, inspect `git status`, and preserve any foreign changes. No new branches, force updates, merge commits or pushes.
2. Read matching rules and relevant Laravel AI, Laravel best-practices and testing skills. Search installed-version documentation through Boost before API-dependent edits; when documentation retrieval is unavailable, inspect installed source and disclose the gap.
3. Use Boost `database-schema` before writing migrations. The existing schema files provide baseline context, not proof of a live database's state. If the tool is inaccessible from a child, ask the main session to perform the inspection.
4. Before source changes, call `record-rule` with glob `app/Ai/**` and the existing title `Native publishing agents and human approval` to replace the obsolete budget requirement. Preserve native agents/conversations/tools, owner authorization, evidence grounding and frozen revision protections. State that OpenRouter owns spend enforcement and missing billing metadata does not block valid output.
5. Confirm the old sentence was replaced, not left contradictory beside a new rule. If neither child nor main session can reach `record-rule`, stop for tooling access; do not invent a direct-edit fallback. Phase 2 reconciles generated/setup/design guidance.

### 2. Per-agent recommendations, not a global model

**Pattern to follow**: `app/Ai/Agents/Interviewer.php`, `app/Ai/Agents/FactReviewer.php`; native resolution in `vendor/laravel/ai/src/Promptable.php` and `vendor/laravel/ai/src/Gateway/TextGenerationOptions.php`.

Native `prompt(model: ...)` wins over `model()` and attributes. A defined `model()` method wins over `#[Model]`; returning null does not magically restore attribute lookup. Do not duplicate conflicting sources of truth. The simplest inspectable choice here is a native `model(): string` on each concrete agent, returning an optional captured override or that class's literal recommendation. A shared `provider()` returns `Lab::OpenRouter`. Native attributes or methods can own token/step/timeout choices; avoid recreating SDK resolution.

Illustrative shape, not a second framework:

```php
public function model(): string
{
    return $this->modelOverride ?? 'google/gemini-3.8-flash';
}
```

The base agent may accept the activity plus optional selected model/execution snapshot. Keep `forActivity()` as the existing role dispatch point. A small method returning each concrete class's native recommendation for the settings page is acceptable; do not add a provider registry, policy engine or dependency-injected model-routing service.

#### Research starting point, not a quality claim

Checked 2026-09-24 against the public [OpenRouter model catalog](https://openrouter.ai/api/v1/models), without a key or inference:

- `deepseek/deepseek-v4.1-flash`: advertises tool calling, structured outputs and the `reasoning_effort` parameter. Observed base input/output prices were $0.15/$0.60 per million tokens, with time-dependent pricing overrides present.
- `deepseek/deepseek-v4-pro-0813`: advertises the same tool, structured-output and reasoning parameters. Observed input/output prices were $0.462/$1.386 per million.
- `google/gemini-3.8-flash`: advertises tools, structured outputs and the `reasoning_effort` parameter. Observed input/output prices were $0.75/$3.75 per million.
- `openrouter/auto`: dynamic route; tools/structured output advertised, but no stable reasoning-effort catalog or fixed price. Do not interpret sentinel prices as actual costs.

Prices are research evidence only: never put them in application config or assert that they bound invoices. Metadata is not proof of writing quality. Recheck availability and provider capability when executing this spec and cite changes in the rationale, not in new runtime price logic.

The public catalog confirms parameter names, not every accepted effort value. The separate parameter endpoints returned HTTP 401 without authentication during planning; do not treat their allowed values as verified or fetch the owner's key to finish ideation. Confirm effort values through current public provider documentation when implementing, and omit unverified optional effort rather than blocking a valid model. Sources for request behavior: [native SDK configuration](https://laravel.com/framework/docs/ai-sdk#agent-configuration), [reasoning options](https://openrouter.ai/docs/guides/best-practices/reasoning-tokens), [provider selection](https://openrouter.ai/docs/guides/routing/provider-selection) and [Auto Router](https://openrouter.ai/docs/guides/routing/routers/auto-router).

Initial recommendations to validate; the effort levels below are proposed, conditional on supported values, and role-fit claims are hypotheses rather than observed quality:

- Interviewer: Gemini Flash, low effort, for responsive instruction-following and native AskAuthor interaction.
- Researcher: Gemini Flash, medium effort, for source synthesis with the existing Exa/evidence pipeline.
- Planner: DeepSeek V4 Pro, high effort, for argument construction and evidence/outline relationships.
- Drafter: Gemini Flash, medium effort, as the economical prose/document candidate; owner output review is decisive.
- FactReviewer: DeepSeek V4 Pro, high effort, for contradiction and support checks, using a different family from drafting.
- VoiceReviewer: Gemini Flash, medium effort, for editorial fit against supplied voice context.
- BuyerReviewer: DeepSeek V4.1 Flash, low effort, for focused audience and objection analysis without inventing buyer facts.
- ReviewReconciler: DeepSeek V4.1 Flash, low effort, for bounded grouping/canonical selection.
- RevisionRechecker: DeepSeek V4 Pro, high effort, for evidence-sensitive resolution checks.

The catalog also listed the cheaper `deepseek/deepseek-v4-flash-0731` ($0.03/$0.32 per million base input/output tokens) and `google/gemini-3.5-flash-lite` ($0.30/$2.50). They are alternatives, not silently rejected as lower quality: price and release recency do not establish editorial suitability. The proposed three-model set favors a small initial comparison surface and different drafting/factual-review families; revisit it if the bounded sample exposes a concrete weakness, not by adding every catalog entry to the UI. No latency or prose-quality measurements have been made during planning.

Implementation recheck, 2026-09-25 (public catalog and docs, no key, no inference): all five catalog entries above were still listed with the same base prices, and each of the three named candidates plus `openrouter/auto` still advertises `tools`, `structured_outputs`, `response_format`, `reasoning` and `reasoning_effort`. OpenRouter's reasoning guide documents `reasoning: {"effort": ...}` with `max`, `xhigh`, `high`, `medium`, `low`, `minimal` and `none`, so the proposed low/medium/high levels are sent unchanged for named models; Auto is marked reasoning-incompatible in the allowlist because its dynamic route has no stable effort semantics. The guide also states that `max_tokens` covers reasoning plus visible output on most providers, so the implemented native allowances are 4,000 (Interviewer), 6,000 (BuyerReviewer, ReviewReconciler), 8,000 (Researcher, VoiceReviewer), 12,000 (Planner, FactReviewer, RevisionRechecker) and 16,000 (Drafter), with a 50-second code-owned request timeout that stays below the 60-second Horizon supervisor and the documented `queue:work --timeout=600`. The recommendations themselves are unchanged and have not passed the live trial.

Live trial outcome, 2026-09-25 (Phase 3; observed, not vendor claims; details and every attempt in `model-validation.json`): all nine roles, the AskAuthor continuation and an Auto Router probe completed on synthetic data. Selections that changed from the proposal above: BuyerReviewer moved from DeepSeek V4.1 Flash (low) to DeepSeek V4 Pro (medium) after the fast model quoted non-evidence and returned unstructured findings; output allowances rose to 32,000 (Planner, Drafter, FactReviewer, RevisionRechecker), 24,000 (BuyerReviewer), 16,000 (Researcher, VoiceReviewer, ReviewReconciler) and 8,000 (Interviewer), because high-effort reasoning plus the structured answer overflowed the original limits. The request timeout became a 540-second runaway guard below the 600-second worker timeout (the owner prefers quality over latency for queued work), and requests now ask OpenRouter to sort providers by throughput and exclude 4-bit, 6-bit and integer quantizations after default price-first routing produced 37- to 298-second swings for the same model. The other role selections are unchanged. Open quality notes for the owner: the BuyerReviewer missed the seeded audience mismatch in the final run, and the RevisionRechecker needed a stricter item schema before it assessed each finding.

This is three named candidates plus Auto, not nine separate models. The role boundaries matter even when roles share a model. Native output token limits should accommodate the role (especially the drafter's JSON-encoded document), retain bounded tool steps, and fit existing job/worker timeout ordering. Do not raise workflow authority or add arbitrary UI controls for these parameters.

For OpenRouter requests, remove `provider.only`, `max_price` and the blanket no-provider-fallback policy inherited from pricing enforcement. Retain `require_parameters: true` so capability requirements are not silently discarded. OpenRouter may recover across compatible upstream endpoints; do not build cross-model application failover. Send only reasoning values supported by the selected named model; omit forced effort for Auto or incompatible overrides rather than guessing a conversion. Keep credentials in `config/ai.php`'s existing environment-backed provider config.

**Feedback loop**:
- Playground: HTTP-faked `EditorialAgentsTest` using concrete role agents.
- Experiment: every role with no override, each allowlisted override, Auto, an unknown identifier, and a reasoning-incompatible choice.
- Check command: `php artisan test --compact tests/Feature/Publishing/EditorialAgentsTest.php`

### 3. Typed settings with package lifecycle

**Pattern to follow**: installed `vendor/spatie/laravel-settings/src/SettingsContainer.php` and package migration stubs.

Use one `PublishingAgentSettings` group with a boolean `paused` and an array of optional overrides keyed by `EditorialActivityKind` values. An absent override means the concrete agent's recommendation, not a global default. Initialize the settings to paused on installation so deploying a live key does not unexpectedly drain historical pending work; the owner enables requests deliberately in Phase 2 or the isolated trial. Existing valid non-budget content is not changed by this default.

The package already uses scoped settings bindings. Resolve settings during actions/jobs, not in serialized job constructors or persistent singletons. Keep optional package settings caching off initially; do not add `SETTINGS_CACHE_ENABLED` or another operator knob. If cache is already enabled by deployment, test invalidation through package save behavior rather than introducing a parallel cache layer. Test separate scope resets for successive jobs, not merely changing the same injected object's property.

Validate override keys against the actual supported role values and model IDs against the small code-owned allowlist. The concrete native recommendation must itself be allowlisted. No secrets, prices or arbitrary endpoints are persisted. The default reset removes an override rather than copying today's recommendation into the database permanently.

**Feedback loop**:
- Playground: `PublishingAgentSettingsTest` on in-memory SQLite.
- Experiment: clean database, saved overrides, reset, malformed/unknown role and model, fresh request, and second job after another scope saves settings.
- Check command: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php`

### 4. Remove budgeting without removing the execution guard

**Pattern to follow**: `RunEditorialActivity::claimActivity()`, `reserveAfterFreshActivityCheck()`, `applyResult()`, `StartEditorialActivity::start()`, and `ResumeEditorialActivity`.

The transactional recheck currently buried in `reserveAfterFreshActivityCheck()` is not disposable. Retain its checks as a plainly named pre-invocation validation/claim method returning an execution decision, not a reservation. Recheck the actor, permission, attempt state, activity ownership/status, input version, approval hashes and revision hash immediately before HTTP. Retain the post-response recheck before applying output. Do not hold a database transaction across external inference.

Capture the effective execution choice on the activity at first invocation, using the existing `model_snapshot` column rather than a new ledger. Distinguish requested model (possibly Auto) from returned concrete model. Persist enough role options for a started activity's native approval continuation not to change when settings are saved. For named models, retain the original model. For Auto approval continuations, pin the concrete returned model before continuing. If a pending Auto tool approval lacks a resolvable returned model, pause with a model-continuity error instead of silently re-routing; this is not a billing hold. Legacy snapshots may contain price fields: ignore/remove obsolete financial keys while preserving usable execution identity.

Remove quotes, reserved money, settlement, overrun holds, price parsing, `/generation` cost lookups and financial branches. Generation IDs and raw usage already stored by the SDK remain useful diagnostics; do not introduce a new cost column/table, unknown-cost workflow or accounting UI. A successful structured response proceeds even when cost is null or absent.

Pause policy: reject new requests at start/approval/resume/execution/recovery boundaries while globally paused. Durable queued-but-unstarted activities remain eligible pending work and must not be marked running merely to discover the switch is off. Re-enable allows normal pending recovery, not automatic replay of requests whose outcome is uncertain. Native pending approvals and explicit per-attempt pause/park/abandon states remain separate.

Failure policy:
- Missing credentials, invalid model configuration and deterministic provider rejection (including 401/402/unsupported parameters) produce a concise actionable activity error. Never include a key or raw authorization header.
- A definitive pre-generation 429 may use the existing bounded retry/backoff mechanism; never retry indefinitely or change models silently.
- Timeout/disconnect/worker interruption with uncertain outcome pauses for deliberate review; do not automatically regenerate just because there is no longer a reservation.
- Old running activities are paused by recovery without billing calls. Preserve any known generation/conversation identity.
- Do not replay already-resolved native Decisions after a continuation fails; preserve native tool execution state and use SDK-supported continuation/recovery semantics.

Do not expand the workflow with an automatic retry service. For old budget-only pauses, make the existing explicit attempt resume path recover only positively identified pre-call blockers, after freshness/authorization checks. Uncertain paid outcomes, pending tool approvals and unrelated pauses must remain protected. If a legacy state cannot be safely classified, retain it with an actionable explanation rather than a mass status reset.

**Feedback loop**:
- Playground: existing `AgentBudgetTest` and `EditorialToolApprovalTest`, rewritten around observable execution outcomes.
- Experiment: valid result without cost; 401/402/429; timeout; stale revision before/after response; permissions revoked between claim and call; duplicate job delivery; setting changed during AskAuthor pause; recovery of pending versus ambiguous running work.
- Check command: `php artisan test --compact tests/Feature/Publishing/AgentBudgetTest.php tests/Feature/Publishing/EditorialToolApprovalTest.php`

### 5. Schema, authorization and immediate UI cleanup

**Pattern to follow**: existing publishing migrations, factories, catalogs and workspace partials.

Add a forward migration: preserve diagnostic generation IDs from budget records where needed, then drop `agent_budget_reservations`, `publishing_attempts.allowance_nano_usd` and `allowance_changes`. Never run `migrate:fresh` on the owner's database. Test upgrading representative old records, not just final-schema installation. A rollback can recreate empty schema but cannot recover intentionally deleted financial history; document that truth.

Remove Budget enum/catalog/policy references. Use normal `authorization:sync` behavior: it reports stale definitions and refuses destructive pruning with assignments. Do not add authorization writes to historical migrations or boot, and do not delete unrelated assignments. Phase 2 adds the separate configuration capability.

Remove the workspace's top-up properties, action, computed budget and panel, and update only affected financial wording. Keep the activity timeline, question-answer UI, save/conflict guards, review cycles and explicit approvals. Remove imports only when no longer used; no `_unused` placeholders. Do not redesign the recently committed workspace.

**Feedback loop**:
- Playground: migration fixtures and existing Livewire workspace tests.
- Experiment: populated legacy budget records beside articles/revisions/conversations, no budget records, budget-only paused work, ambiguous running work, and workspace rendering after table deletion.
- Check command: `php artisan test --compact tests/Feature/Publishing/PublishingBudgetRemovalTest.php tests/Feature/Publishing/AdminPublishingWorkspaceTest.php`

## Data Model

- New package `settings` table: use its installed migration shape, including unique group/name identity and JSON payload storage.
- New group: `publishing_agents` with `paused` and `model_overrides` settings, explicitly migrated.
- Remove only the financial table/columns named above. Preserve article, attempt, revision, approval, evidence, conversation and native message usage records.
- Reuse `editorial_activities.model_snapshot` for immutable requested/returned execution identity and relevant options; no extra configuration-history or cost ledger.

## Testing Requirements

| Test | Required behavior |
| --- | --- |
| `EditorialAgentsTest.php` | Every role's native model, allowlist, Auto request compatibility, no price/provider pins, reasoning options. |
| `PublishingAgentSettingsTest.php` | Initialized defaults, override/reset, invalid keys, separate request/job scopes and pause boundaries. |
| `PublishingBudgetRemovalTest.php` | Fresh and old-schema upgrade, nonfinancial record preservation, correct old-pause treatment. |
| `AgentBudgetTest.php` | Budget-free success/failure, no generation-cost lookup, idempotent claim, recovery without blind replay. |
| `EditorialToolApprovalTest.php` | Authorized answers/rejections, conversation binding, stable model across settings changes, Auto concrete-model pin. |
| Existing workflow/review/journey/authorization tests | Human approvals, evidence grounding, stale-input and ownership denials preserved. |
| Existing workspace/editor/session tests | No removed-service dependency, unchanged save and approval guards. |

The owner approved replacing obsolete budget assertions, not discarding safety tests because their names contain “budget.” Normal tests must prevent stray provider HTTP. Do not test the SDK's generic priority rules as a separate framework suite; exercise this application's concrete requests instead.

## Failure Modes

| Component | Failure | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Model selection | Unavailable model or unsupported parameter | Catalog/provider changes or incompatible override | Provider refuses a request | Current research, curated validation, require_parameters and actionable failure; no silent substitution. |
| Settings | Stale scoped/cache value | Another request saves while a worker stays warm | Pause or selection takes effect late | Package-scoped resolution and consecutive-job integration tests. |
| Native continuation | Model identity changes | Settings save or Auto re-selection during approval | Replayed state becomes incompatible | Snapshot execution; pin concrete model; stop if identity is unavailable. |
| Budget removal | Lost freshness guard | Reservation code is deleted wholesale | Stale or unauthorized inference/output | Preserve transactional pre/post checks and adversarial tests. |
| Recovery | Unknown response treated as never sent | Timeout or worker interruption | Duplicate generation or conflicting state | Pause ambiguous running work; no blind replay. |
| Migration | Nonfinancial records removed | Upgrade against populated legacy schema | Loss of editorial work | Forward migration and explicit preservation assertions. |
| Workspace | Removed budget dependency resolved | Article renders after schema/service deletion | Screen crashes | Remove immediate UI/model/policy dependencies in this phase. |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/EditorialAgentsTest.php tests/Feature/Publishing/PublishingAgentSettingsTest.php tests/Feature/Publishing/PublishingBudgetRemovalTest.php
php artisan test --compact tests/Feature/Publishing tests/Feature/Authorization tests/Feature/AdminHomeTest.php
vendor/bin/pint --dirty --format agent
composer types:check
bun test resources/js/admin/publishing
bun run build
git diff --check
```

Run affected tests after changes to them. Before committing, rerun the narrow tests after formatting. Ask the owner to run the complete suite when handing off if it was not run. Do not modify unrelated failures merely to make a broad command green.

## Rollout and Handoff

- Commit directly on `main`, scoped to this phase's changes. Use the available commit skill; if unavailable, disclose that and follow repository commit conventions. No co-author trailer, no push.
- Do not execute migrations on a non-testing database as incidental verification. Report the normal migration and `authorization:sync` steps; coordinate deliberate local rollout for the later trial.
- Record actual researched recommendations and any capability adjustment in this spec's research section before handing off; do not claim they passed the live trial yet.
- Leave the app paused by default until the owner enables it or the isolated trial deliberately configures its test settings.
- Phase 2 builds the settings screen and reconciles setup/design records. Cost reporting remains out of scope.
