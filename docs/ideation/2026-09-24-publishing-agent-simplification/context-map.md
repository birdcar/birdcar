# Context Map: Publishing Agent Simplification

**Phase**: 1 (Native agent configuration and budget-free execution)
**Gates**: 5/5 ready
**Verdict**: GO

Repo root: `/Users/birdcar/Code/birdcar/birdcar`. All paths below are relative to it. Scouted 2026-09-25 at HEAD `1c4f5b5` on `main`. The working tree has one uncommitted change, `.ai/rules/ai.md` (see Risks).

## Gates

| Gate | Status | Evidence |
| --- | --- | --- |
| Scope clarity | ready | Every file in the spec was read and has a concrete change. Scouting found 2 required changes the spec does not list: `tests/Feature/Publishing/ReleaseReadinessTest.php:9,71-106` (creates `AgentBudgetReservation` directly, so it fatals once the model is deleted) and `resources/views/components/admin/publishing/⚡dashboard.blade.php:185` ("$5 agent allowance" copy). Docs/DESIGN/.impeccable are confirmed Phase 2 (spec-phase-2.md file table). |
| Pattern familiarity | ready | Read all 10 agent files, the SDK resolution code (`vendor/laravel/ai/src/Promptable.php:342-405`, `Gateway/TextGenerationOptions.php:75-135`, `Gateway/OpenRouter/Concerns/BuildsTextRequests.php:43-78`, `Gateway/Concerns/HandlesFailoverErrors.php`), the Spatie settings package (`SettingsContainer.php:24-47`, `LaravelSettingsServiceProvider.php`, the migration stub, `config/settings.php`), the historical migrations, the catalogs/policy, and the workspace partials. |
| Dependency awareness | ready | Consumers of every deleted class, removed column/permission and `publishing_agents.*` key are mapped below, including 5 test files that call `RunEditorialActivity::handle()` with positional service arguments and 8 test files that toggle `publishing_agents.enabled`. |
| Edge case coverage | ready | A concrete list is in Risks and Edge Cases: legacy pause-reason classification, the migration being the last point where reservation state exists, Auto pinning via `meta->model`, the scoped-settings reset, the paused-by-default effect on every agent test, SDK exception mapping for 401/402/429/timeout, the Horizon 60s timeout, and the rollback ordering against the historical `down()`. |
| Test strategy | ready | Pest 5.1.4, SQLite `:memory:` (`phpunit.xml`), `RefreshDatabase` for Feature (`tests/Pest.php`), `Http::fake`/`Http::preventStrayRequests`/`Bus::fake`. Spec commands are listed under Conventions/Testing. The baseline suite was not run by the scout (read-only), so the builder should run a baseline first. |

## Key Patterns

- `app/Ai/Agents/EditorialAgent.php` — abstract base. Uses `Promptable`, `RemembersConversations`, implements `HasProviderOptions`, `HasStructuredOutput`, `HasTools`, `Conversational`. The constructor is `(EditorialActivity $activity, array $endpoint)`. `forActivity()` (L31-44) is the single `match` on `EditorialActivityKind` → 9 concrete classes. `maxSteps()` returns 1. `maxTokens()` reads the endpoint. `providerOptions()` (L76-105) builds `provider.only`, `allow_fallbacks:false`, `require_parameters:true`, optional `max_price`, duplicate `max_tokens`/`max_completion_tokens`, and for the Researcher only `plugins:[{id:web,engine:exa,mode:auto,max_results:5}]`. `tools()` gives AskAuthor to the Interviewer only. `schema()` is a per-kind match. Instructions L51-53 mention "budgets" and a "one-completion budget" (step bound, not money). `prompt_hash` does not include instructions, so rewording is optional and safe.
- `app/Ai/Agents/{Interviewer,Researcher,Planner,Drafter,FactReviewer,VoiceReviewer,BuyerReviewer,ReviewReconciler,RevisionRechecker}.php` — 11-line classes, each with only `roleInstructions()`. Add `model(): string` (override ?? literal recommendation) plus role options. `provider()` belongs on the base and returns `Lab::OpenRouter`.
- SDK resolution (verified in vendor):
  - `prompt(model:)` beats `model()`, which beats `#[Model]`. A defined `model()` method means the attribute is never read, even if it returns null (`Promptable.php:354-360`). `timeout` resolves from the call argument, then `timeout()`, then `#[Timeout]`, then 60 (L394-405).
  - `maxTokens`/`temperature`/`topP` resolve method, then attribute (`TextGenerationOptions::resolve`).
  - Request body: `model`, `messages`, `tools`+`tool_choice`, `response_format` json_schema, and `max_tokens` from `maxTokens()`. Then `array_merge($body, providerOptions())` (`BuildsTextRequests.php:63-76`), so `reasoning`, `provider` and `plugins` go at the top level via `providerOptions()`.
  - Approval continuations use a single provider/model with no failover (`providersForApprovalContinuation`).
  - Returned model: `$response->meta->model` = `data['model']` (`ParsesTextResponses.php:46,63`).
- `vendor/spatie/laravel-settings` 3.9.0:
  - Settings classes are bound with `$container->scoped()` (`SettingsContainer.php:28-47`). The queue worker calls `forgetScopedInstances()` per job (`vendor/laravel/framework/src/Illuminate/Queue/QueueServiceProvider.php:263`).
  - Auto-discovery covers `app_path('Settings')`, with a static class cache and `bootstrap/cache/settings.php` (not present now).
  - Migrations load from `database/settings` via `loadMigrationsFrom`, so `RefreshDatabase` runs them.
  - The provider publishes the stub to `database/migrations/2022_12_14_083707_create_settings_table.php` (tag `migrations`) and config (tag `config`).
  - Generators: `make:setting` (singular) and `make:settings-migration <name> [<path>]`.
  - `SettingsMigrator` API: `add`, `delete`, `deleteIfExists`, `update`, `exists`, `inGroup`.
- `database/migrations/2026_09_22_230000_create_editorial_activity_tables.php` — style reference (anonymous class, `Blueprint $table): void`, `jsonb`, `timestampTz`). Defines `agent_budget_reservations` (L55-73) and `allowance_changes` (L14). Its `down()` (L136-144) drops `allowance_changes`.
- `database/migrations/2026_09_22_201057_create_publishing_tables.php:65` — `allowance_nano_usd` default 5_000_000_000.
- `app/Authorization/Publishing/{Permission,Catalog}.php` — enum case plus catalog `permissions()` and `roles()` (Author gets all). `app/Console/Commands/SyncAuthorization.php:264` calls `$role->syncPermissions()`, so normal sync detaches Budget from Author, and `publishing.budget` becomes a stale unassigned definition that is reported (and prunable with `--prune`).
- `resources/views/components/admin/publishing/partials/*.blade.php` — Flux components, one-line Tailwind markup, `@can(...)` gating.

## Dependencies

- `app/Services/Publishing/EditorialModelBudget.php` (delete) is consumed by:
  - `RunEditorialActivity.php:18,48,64,81`
  - Tests: `AgentBudgetTest.php` (21 handle calls plus an anonymous subclass overriding `quote()` at L162-173), `EditorialReviewTest.php:19` (4 calls), `EditorialWorkflowTest.php:20,506` (`drainEditorialActivities`), `EvidenceResearchTest.php:15,62,102` (fetcher passed as the 5th positional argument), `PublishingJourneyTest.php:18,235`.
- `app/Services/Publishing/AgentBudget.php` (delete) is consumed by:
  - `RunEditorialActivity.php:17,48,86,113,120,146,900-913`
  - `RecoverEditorialActivities.php:9,20,44-99`
  - `⚡article-workspace.blade.php:22,646-664,885,936`
  - The same test files as above.
- `app/Services/Publishing/OpenRouterBilling.php` (delete) is consumed by `RunEditorialActivity.php:20,108` and `RecoverEditorialActivities.php:10,20,85`.
- `app/Models/AgentBudgetReservation.php` and its factory (delete) are consumed by:
  - `PublishingAttempt.php:88-94` (`budgetReservations()`), `EditorialActivity.php:80-84` (`reservations()`), `RunEditorialActivity.php:6`, `RecoverEditorialActivities.php:6`
  - Tests: `ReleaseReadinessTest.php:9,94-105` (**unlisted**), `EditorialToolApprovalTest.php:10,47,63,76`, `AgentBudgetTest.php`
- `publishing_attempts.allowance_nano_usd` / `allowance_changes` are consumed by:
  - `PublishingAttempt.php:13,33,36`, `PublishingAttemptFactory.php:38,41`, `AdvancePublishingAttempt.php:56,59`, `AgentBudget.php`
  - Tests: `EditorialWorkflowTest.php:31,44,419,436,615,626,630`, `EditorialToolApprovalTest.php:118-125`, `AdminPublishingEditorTest.php:374`
- `Permission::Budget` / `publishing.budget` is consumed by:
  - `Catalog.php:17,32`, `ArticlePolicy.php:63-67` (`budget()`), `activity.blade.php:19`, `⚡article-workspace.blade.php:648`, `AgentBudget.php:199`
  - Tests: `AgentBudgetTest.php:259` (syncPermissions list), `AuthorizationSyncCommandTest.php:36,47`
  - No `'budget'` policy-ability string callers exist elsewhere.
- `config('publishing_agents.enabled')` (replace with settings `paused`) is read at:
  - `StartEditorialActivity.php:114` (dispatch gate; the activity is still created as durable Pending)
  - `ApprovePublishingStage.php:68` (gates creation of the follow-up Research/Draft)
  - `EditorialModelBudget.php:15`
  - `partials/session.blade.php:44` (status copy)
  - Tests toggle it in `AdminPublishingEditorTest.php:381,410,449`, `EvidenceResearchTest.php:243-247` (helper), `EditorialReviewTest.php:378,497-503` (helper), `PublishingSessionTest.php:19`, and others.
- `config('publishing_agents.limits.*')` (retain these keys) is consumed by `EditorialOutput.php:119,320`, `PublicSourceFetcher.php:36,67,161`, `RunEditorialActivity.php:686,879`. The keys `routes`, `role_routes`, `enabled`, `exa_web_search_nano_usd` and env-backed `http_timeout` go.
- `EditorialAgent::forActivity()` / constructor is consumed by `RunEditorialActivity.php:65` and by tests that construct agents directly (`EditorialAgentsTest.php:34-42,51,64,67,68,80,99,100`, `AgentBudgetTest.php:63`).
- Dispatch/pause boundaries (complete list):
  - `StartEditorialActivity::start` L114. Every other start path funnels through it: workspace L103, dashboard L52, `RunEditorialActivity` follow-ups L956/969/1014, `FinishEditorialReview` L84/115.
  - `ApprovePublishingStage::approve` L68-75.
  - `ResumeEditorialActivity::handle` L73 (currently dispatches with no enabled check).
  - `RecoverEditorialActivities` L27-37.
  - `RunEditorialActivity::claimActivity` L160-232.
  - Schedule: `routes/console.php:11` runs `publishing:recover-activities` every 5 minutes.
- `model_snapshot`: written only at `StartEditorialActivity.php:108` (`[]`) and `RunEditorialActivity.php:380,416` (the priced endpoint, on AwaitingApproval/Completed). Read at `RunEditorialActivity.php:62-63`. No UI reads it. Factories/tests set `[]`.
- UI-only budget consumers: `partials/activity.blade.php:2,15-27`, `partials/develop.blade.php:9,91`, `partials/write.blade.php:94`, `partials/session.blade.php:44,49`, `⚡dashboard.blade.php:185` (**unlisted**), and `⚡article-workspace.blade.php` properties L44-45, mount L91, action L646-664, `with()` L885 and L936. `Str` stays imported because it is still used at L121.
- `.env.example:77-110` — the whole `PUBLISHING_AGENTS_*` block plus its price comments. Keep `OPENROUTER_API_KEY`/`OPENROUTER_BASE_URL` (consumed by `config/ai.php:142-146`) and the worker/queue guidance.

## Conventions

- **Naming**: agents are `app/Ai/Agents/{Role}.php`; actions are `app/Actions/Publishing/{Verb}{Noun}.php`; domain authorization lives in `app/Authorization/{Domain}/{Permission,Role,Catalog}.php`; the settings class goes in `app/Settings/PublishingAgentSettings.php` with `group(): string` returning `publishing_agents`. Enum keys are TitleCase. Override keys should be `EditorialActivityKind` values (`interview`, `research_challenge`, `plan`, `draft`, `review_facts`, `review_voice`, `review_buyer`, `reconciliation`, `recheck`).
- **Imports**: fully qualified `use` statements, no barrels. Permission enums are aliased (`Permission as PublishingPermission`). Blade partials use FQCNs inline.
- **Error handling**:
  - Actions throw `RuntimeException` with user-facing messages or `AuthorizationException`. Livewire actions catch `Throwable` into `$this->saveError`.
  - State changes happen inside `DB::transaction` with `lockForUpdate()`, and `forceFill()->save()` sets status plus `paused_at`/`pause_reason`/`error_reason`.
  - Jobs are dispatched with `->afterCommit()`.
  - SDK error mapping: 429 → `RateLimitedException`, 402 → `InsufficientCreditsException`, 502/503/504/520/522/524 → `ProviderOverloadedException`, connection/timeout → `ProviderConnectionException`. All are `FailoverableException` and wrap the original as previous. 400/401/403/404/422 surface as a raw `RequestException`. The current `isKnownNonBillableFailure()` walks the previous-exception chain.
- **Types**: strict PHPDoc array shapes, explicit return types, constructor promotion, `#[Fillable([...])]` attribute on models, a `casts()` method. Larastan level 7 over `app/`, `config/`, `database/`, `routes/` (`phpstan.neon`), so published config and settings migrations are analyzed.
- **Testing**:
  - Pest functional style in `tests/Feature/Publishing/*Test.php`. Setup is `beforeEach` with `PermissionRegistrar::forgetCachedPermissions()` + `$this->artisan('authorization:sync')`.
  - Users come from `User::factory()` + `assignRole(PublishingRole::Author->value)`.
  - HTTP: `Http::preventStrayRequests()` + `Http::fake(['https://openrouter.ai/api/v1/chat/completions' => ...])`, with `Http::sequence()` for tool approval (`EditorialToolApprovalTest.php:142-170`). Jobs run via `app()->call([new RunEditorialActivity($id), 'handle'])`.
  - **Helper functions are global.** New files need unique names; these already exist: `editorialAuthor`, `evidenceAuthor`, `reviewAuthor`, `agentBudgetAuthor`, `agentBudgetAttempt`, `sessionAuthor`, `readinessAuthor`, `releaseAuthor`, `publishingUser`, `editorUser`, `pendingAuthorInterview`, `runApprovalActivity`, `drainEditorialActivities`, `editorialActivity`.
  - Commands:
    - Inner loop: `php artisan test --compact tests/Feature/Publishing/EditorialAgentsTest.php`
    - Settings: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php`
    - Execution: `php artisan test --compact tests/Feature/Publishing/AgentBudgetTest.php tests/Feature/Publishing/EditorialToolApprovalTest.php`
    - Schema/UI: `php artisan test --compact tests/Feature/Publishing/PublishingBudgetRemovalTest.php tests/Feature/Publishing/AdminPublishingWorkspaceTest.php`
    - Full phase: `php artisan test --compact tests/Feature/Publishing tests/Feature/Authorization tests/Feature/AdminHomeTest.php`, then `vendor/bin/pint --dirty --format agent`, `composer types:check`, `bun test resources/js/admin/publishing`, `bun run build`, `git diff --check`.
    - Also rerun `tests/Feature/Publishing/ReleaseReadinessTest.php`, which is not in the spec's lists.

## Risks

- **Rule already superseded in the working tree.** `.ai/rules/ai.md` has an uncommitted diff that already replaces "Keep budget reservations… A narrow OpenRouter billing lookup is permitted" with the spec's intended wording. The builder must confirm with the main session that `record-rule` produced it (spec §1.4-1.5). If so, treat it as done and include it in the Phase 1 commit; do not hand-edit or duplicate it. If its origin cannot be confirmed, stop per spec rather than direct-editing. Boost MCP tools (`record-rule`, `database-schema`) were not available to the scout.
- **Unlisted required changes.**
  - `tests/Feature/Publishing/ReleaseReadinessTest.php`: its test "release freshness hashes evidence contents and excludes budget bookkeeping" (L71-123) inserts an `AgentBudgetReservation`. Drop that fixture and rename the test; keep the evidence-freshness assertions.
  - `⚡dashboard.blade.php:185`: "$5 agent allowance" copy.
  - Report both in the changed-file list per spec L112.
- **Decision-log / premise mismatches.**
  - (a) Failure policy says a 429 "may use the existing bounded retry/backoff mechanism". In reality `failOrPause()` (L871-889) only marks `Failed` with `available_at +1min` and `max_retries=2`. Nothing re-dispatches `Failed` work: recovery only picks up `Pending` and `Running` older than 30 minutes, and the job has no `$tries`/`backoff`. So the "existing mechanism" is effectively manual. Do not add a retry service (spec L213); document the real behavior.
  - (b) "Publish the settings package's config through its provider" conflicts with "no new environment knobs / do not add SETTINGS_CACHE_ENABLED". The published `config/settings.php` contains `env('SETTINGS_CACHE_ENABLED', false)` and `env('SETTINGS_CACHE_MEMO', false)`. Hard-code `false` after publishing.
  - (c) The spec lists `PublishingAuthorizationTest.php` and `AdminPublishingWorkspaceTest.php` for "remove budget-only assertions", but neither has any budget references. Changes there should be minimal or additive only (e.g., assert the workspace renders without an allowance panel).
  - (d) The spec says "fast-forwarded to main at df1d96e". `main` has since advanced to `1c4f5b5` (artifacts plus an unrelated three.js commit). This is not a contradiction, but capture the starting SHA for review.
- **Paused-by-default breaks every agent-running test.** The settings migration initializes `paused=true` and `RefreshDatabase` runs `database/settings`, so each test that currently sets `publishing_agents.enabled=true` (AgentBudget, EditorialReview, EditorialToolApproval, EditorialWorkflow, EvidenceResearch, PublishingJourney, AdminPublishingEditor) must unpause through the settings class. Helpers that temporarily *disable* agents during approval (`evidenceAttempt` `EvidenceResearchTest.php:243-247`, `reviewAttemptWithReviewedPlan` `EditorialReviewTest.php:497-503`) rely on approval *not creating* follow-up activities while disabled. Preserve that `ApprovePublishingStage` semantic, or update those helpers deliberately.
- **Scoped settings and fakes.** `Settings::fake()` registers via `instance()` on a scoped abstract, so `forgetScopedInstances()` drops it. `SyncQueue` does not reset scope. Warm-worker and "second job after another scope saves" tests should persist real settings, then call `app()->forgetScopedInstances()` between handles. Resolve settings inside `handle()`/actions, never in the job constructor (`RunEditorialActivity` serializes only `activityId`, so keep it that way).
- **Test seam removal.** "Activity revalidates frozen inputs immediately before budget reservation" (`AgentBudgetTest.php:144-178`) injects a mutation between claim and pre-HTTP check through an `EditorialModelBudget::quote()` subclass. The builder needs a new, non-framework seam for the "revoked/stale between claim and call" experiment (e.g., a model event on the claimed Running activity, or splitting claim and pre-invocation validation so the test can mutate between them). Do not keep a pricing class just for the test.
- **Legacy pause classification needs reservation state, which the migration destroys.**
  - Budget-era activity `pause_reason`s:
    - 'Billing outcome is unknown.' (L115). The response was received but *discarded*, so this is an uncertain paid outcome; keep it protected.
    - The 14 pre-call blocker strings in `isDeterministicPreCallSpendBlocker()` (L847-862). No HTTP was made; these are positively identified pre-call blockers.
    - Arbitrary exception messages after a reservation (L147-148). Their certainty depends on the reservation state (`released` means no charge, `unknown` means uncertain).
  - Attempt-level pauses written by `AgentBudget`: 'Agent billing outcome is unknown: …' and 'Agent cost exceeded its budget reservation.'
  - `AdvancePublishingAttempt::resume()` (L302-313) re-pends only activities with 'Publishing attempt paused.'.
  - Classify, or preserve a marker, inside the forward migration *before* dropping `agent_budget_reservations`. Also copy `provider_generation_id` into a null `editorial_activities.generation_id`, using portable query-builder chunks (no `UPDATE … FROM`; tests are SQLite, local runtime is PostgreSQL per `.env` `DB_CONNECTION=pgsql`). Never `migrate:fresh` the owner's DB.
- **Rollback ordering.** The forward migration's `down()` must recreate `allowance_nano_usd`, `allowance_changes` and `agent_budget_reservations` as empty schema. Otherwise rolling back past `2026_09_22_230000` fails, because that `down()` drops `allowance_changes`. Drop the table (it has FKs to `publishing_attempts`/`editorial_activities`) before dropping the columns.
- **Timeout ordering.** The current HTTP timeout is 30s (`publishing_agents.http_timeout`), the SDK default is 60s, and the Horizon supervisor `timeout` is 60 (`config/horizon.php:210`). `.env.example` documents `queue:work --timeout=600` and `DB_QUEUE_RETRY_AFTER=660`. Research also runs `PublicSourceFetcher` (10s × up to 10 sources) inside the same job. A larger Drafter/Planner timeout must stay below the worker timeout, and under Horizon below 60s unless Horizon is adjusted. Make `http_timeout` a code-owned literal, not env.
- **Auto Router and continuation.** Capture the requested model (possibly `openrouter/auto`) at first invocation. For an Auto AwaitingApproval, pin `meta->model` (or raw `model`). If it is missing or equals `openrouter/auto`, pause with a model-continuity error. Legacy snapshots contain `pricing`/`max_price`/`provider`/`context_tokens` keys; strip them. The current reuse condition `filled(tool_decisions) && filled(model_snapshot)` (L62) must work with the new snapshot shape, including a legacy snapshot whose `model` is still valid.
- **Researcher plus Auto.** The Exa `plugins` option and `require_parameters:true` combined with `openrouter/auto` or a non-reasoning override: tests must assert the actual request body for each combination. Send `reasoning` only when the allowlist marks the model as supporting it. Omit it for Auto.
- **Pause must not consume claims.** When paused, `claimActivity()` must leave `Pending` untouched (no `run_count++`, not `Running`). `ResumeEditorialActivity` should still persist `tool_decisions`/`Pending` durably but skip dispatch. Recovery should skip re-dispatch while paused. Decisions already resolved must not be replayed after a failed continuation.
- **Secrets in errors.** `RequestException` messages include response-body excerpts, not request headers, but sanitize them before storing `error_reason`/`pause_reason`. Never persist the Authorization header or the key. Missing-credential detection moves out of `EditorialModelBudget::pricedEndpoint()` (L19-22) into the execution path.
- **Octane.** The app runs Octane (the rule forbids request state in singletons). Settings are already scoped, so do not wrap them in a singleton service or static cache.
- **No automated lock-contention coverage.** `AgentBudgetTest.php:523-526` acknowledges that SQLite cannot prove row-lock races. That stays true for duplicate-delivery claims; idempotent claim tests exercise only sequential double delivery.
