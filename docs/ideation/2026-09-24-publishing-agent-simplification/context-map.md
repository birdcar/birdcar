# Context Map: Publishing Agent Simplification

**Phase**: 2 (Publishing settings and operational integration)
**Gates**: 5/5 ready
**Verdict**: GO

Repo root: `/Users/birdcar/Code/birdcar/birdcar`. All paths below are relative to it.

- **Phase 2** was scouted 2026-09-25 at HEAD `c044341` on `main`. Phase 1 shipped in that commit ("Configure agents natively without budget gates"; review PASS, cycle 1). The working tree holds only untracked `.DS_Store` and `.impeccable/mocks|questions` files, which are unrelated; do not stage them.
- **Phase 1** was scouted at `1c4f5b5`. Its sections are kept below as historical reference.
- **Tools the scout could not use:** Boost MCP (`get-absolute-url`, `browser-logs`, `record-rule`, `search-docs`, `database-query`), Glob/Grep and Horizon memory. Searching used read-only `rg`/`ls`. Read-only artisan commands were run: `migrate:status`, `route:list`, `make:livewire --help`.

## Gates

### Phase 2 (current)

| Gate | Status | Evidence |
| --- | --- | --- |
| Scope clarity | ready | Every file in the spec was read and each change is concrete (see "Phase 2 file-by-file changes"). Two listed files probably need no change: `app/Settings/PublishingAgentSettings.php` (its API already covers save and reset) and `tests/Feature/Publishing/PublishingSessionTest.php` (it has no pause or settings copy assertions). `.impeccable/design.json` changes only if the tooling syncs it; it has no allowance text. |
| Pattern familiarity | ready | Read these patterns: `⚡dashboard.blade.php` (SFC, `#[Layout('layouts.admin')]`, `Gate::authorize` inside actions), `⚡article-workspace.blade.php` (mount authorization, `saveError`), `navigation.blade.php`, `layouts/admin.blade.php`, `routes/admin.php` + `routes/web.php:19-25`, Publishing `Permission`/`Catalog`/`Role`, `config/admin.php` bootstrap roles, `EditorialAgent::recommendedModelFor()`/`allowsModel()`, `config/publishing_agents.php` allowlist, the settings class, and the Livewire/Flux vendor components. |
| Dependency awareness | ready | Consumers are mapped: the catalog affects the exact sorted permission list in `AuthorizationSyncCommandTest.php:45-51` and the root-admin bootstrap bundle. The navigation renders on every `admin.publishing.*` page and XPath tests read it. The settings class has 6 app consumers and many test consumers. Nothing else enumerates the Publishing permissions. |
| Edge case coverage | ready | A concrete list is under "Phase 2 edge cases". It covers tampered role and model keys, stored overrides that are no longer allowlisted, the difference between "use recommended" and explicitly choosing the recommended model, saved versus draft pause state, what unpausing actually does (recovery-driven), secrets in the snapshot and effects, revocation between mount and save, Livewire::test skipping route middleware, scoped settings inside one test container, cross-file Pest helpers, and 9 rows at 390px. |
| Test strategy | ready | Pest 5 + `Livewire::test`, with `$component->snapshot`/`->effects` available for payload assertions (`vendor/livewire/livewire/src/Features/SupportTesting/Testable.php:376-379`). Commands are listed under Conventions. For rendered QA, run `bun run build`, then do browser checks on the Herd admin host. It is blocked until the local DB runs 3 pending migrations (see Risks); if blocked, report it incomplete as the spec allows. |

### Phase 1 (reference, as recorded at scout time)

| Gate | Status | Evidence |
| --- | --- | --- |
| Scope clarity | ready | Every file in the spec was read and had a concrete change. Scouting found 2 required changes the spec did not list: `tests/Feature/Publishing/ReleaseReadinessTest.php:9,71-106` (created `AgentBudgetReservation` directly) and `resources/views/components/admin/publishing/⚡dashboard.blade.php:185` ("$5 agent allowance" copy). Docs/DESIGN/.impeccable belong to Phase 2. |
| Pattern familiarity | ready | Read all 10 agent files, the SDK resolution code, the Spatie settings package, the historical migrations, the catalogs/policy, and the workspace partials. |
| Dependency awareness | ready | Consumers of every deleted class, removed column/permission and `publishing_agents.*` key were mapped. |
| Edge case coverage | ready | Legacy pause-reason classification, migration ordering, Auto pinning, scoped settings, paused-by-default, SDK exception mapping, timeouts, rollback ordering. |
| Test strategy | ready | Pest 5.1.4, SQLite `:memory:`, `RefreshDatabase`, `Http::fake`/`preventStrayRequests`/`Bus::fake`. |

## Key Patterns

### Phase 2

- `resources/views/components/admin/publishing/⚡dashboard.blade.php`
  - Livewire 4 anonymous SFC: `new #[Layout('layouts.admin')] class extends Component` (L20), then `?>` and markup.
  - Each action calls `Gate::authorize(AdminPermission::View->value)` plus the domain permission (L28-29, L41-43).
  - Uses `$this->validate([...])`. Data comes from `with()` (L61-83).
  - Flux markup: `flux:heading level="1" size="xl"`, `flux:text`, `flux:field`/`flux:error`, `flux:button` with `wire:loading.attr="disabled" wire:target="..."` (L173/176), and `wire:loading` hint spans (L183-184).
  - Status line: `<p role="status" class="mt-3 text-sm text-teal-800 dark:text-cyan-200">` (L181). Rules use `border-t border-zinc-200 dark:border-white/10`.
  - Root is `<section data-publishing-studio class="publishing-studio space-y-3" x-data="publishingSession('develop')">`. The settings page does not need `publishingSession` or `data-publishing-enter` motion (spec: no animation work).
  - Kind→label map in `activityFor()` (L131-142): Interview, Research challenge, Plan, Draft, Fact review, Voice review, Buyer review, Reconciliation, Recheck. Reuse this wording for role names.
- `resources/views/components/admin/publishing/⚡article-workspace.blade.php`
  - `mount()` starts with `Gate::authorize('view', $article)` (L76-78). Actions re-authorize (L95, L111).
  - Errors are caught into `public ?string $saveError` from `Throwable` (L35, L104-105).
  - Reads `app(PublishingAgentSettings::class)->paused` in `with()` (L912). It never stores the settings object in a property.
- `resources/views/components/admin/navigation.blade.php:1-11`
  - Wrapped in `@if (request()->routeIs('admin.publishing.*'))` + `@can(PublishingPermission::View->value)`.
  - Uses `flux:navbar.item` with `:current` and `:aria-current="... ? 'page' : null"`.
  - The Workspace item's current-matcher is `admin.publishing.dashboard, admin.publishing.articles.*`, so a settings route will not mark Workspace current.
- `resources/views/layouts/admin.blade.php`
  - The sidebar Publishing item (L28) and header label (L47) already match `admin.publishing.*`, so no change is needed.
  - Renders `session('status')` as a success callout (L53-55). A flash from a Livewire action therefore also appears on the next full page load; prefer a component feedback property.
- `routes/admin.php:10-18`
  - `Route::prefix('publishing')->name('publishing.')->middleware(PermissionMiddleware::using(PublishingPermission::View))`, then `Route::livewire('/...', 'admin.publishing.x')->name('x')`.
  - Wrapped by `routes/web.php:19-25`: domain `config('admin.host')`, `admin.` name prefix, `auth` + `PermissionMiddleware::using(AdminPermission::View)`.
  - Add `Route::livewire('/settings', 'admin.publishing.settings')->name('settings')->middleware(PermissionMiddleware::using(PublishingPermission::ConfigureAgents))`.
- `app/Providers/AppServiceProvider.php:57-59`
  - `Livewire::addPersistentMiddleware([PermissionMiddleware::class])` replays the route's permission middleware on real Livewire update requests.
  - `Livewire::test()` does not replay it, so `mount()` and every action must `Gate::authorize(...)`. That is what the tests prove.
- `app/Authorization/Publishing/Permission.php:5-12` and `Catalog.php:9-34`
  - Enum case, then `permissions()` list, then Author role list.
  - Add `ConfigureAgents = 'publishing.configure-agents'` in both lists.
  - `config/admin.php:13-16` bootstrap roles `[admin.access, publishing.author]` means the root admin gets the capability through the role after `authorization:sync`. No bootstrap change is needed (`.ai/rules/authorization.md`).
- `app/Settings/PublishingAgentSettings.php:10-67`
  - Public API: `public bool $paused`, `public array $model_overrides` (`@var array<string,string>`), `modelOverrideFor(kind): ?string`, `overrideModel(kind, model)`, `resetModel(kind)`.
  - `overrideModel` throws `InvalidArgumentException` for a non-allowlisted model. Both mutators drop invalid stored entries through the private `validOverrides()`.
  - `save()` (Spatie `Settings.php:187`) writes all properties. `refresh()` exists at L257.
  - A form save can loop `overrideModel`/`resetModel` per role, set `paused`, and call `save()` once. No class change is required.
- `app/Ai/Agents/EditorialAgent.php`
  - `AUTO_ROUTER = 'openrouter/auto'` (L25).
  - `recommendedModelFor(EditorialActivityKind)` (L60-63) is built for Phase 2. It builds the concrete agent through the `forActivity()` match (L32-45), so defaults come from the agent code. Nothing in app code calls it yet; it is tested at `EditorialAgentsTest.php:97`.
  - `allowsModel()` (L65-68). `modelDefinition()` is **private** (L80-86) and reads the whole `config('publishing_agents.models')` array because the IDs contain dots.
- `config/publishing_agents.php:8-13` allowlist:
  - `google/gemini-3.8-flash` → "Gemini 3.8 Flash"
  - `deepseek/deepseek-v4-pro-0813` → "DeepSeek V4 Pro"
  - `deepseek/deepseek-v4.1-flash` → "DeepSeek V4.1 Flash"
  - `openrouter/auto` → "OpenRouter Auto Router"
  - Each entry has `label` and `reasoning`; there are no prices.
- Current per-role recommendations (the `model()` literal in each agent):

  | Role | Model | Reasoning effort |
  | --- | --- | --- |
  | Interviewer | gemini-3.8-flash | low |
  | Researcher | gemini-3.8-flash | medium |
  | Planner | deepseek-v4-pro-0813 | high |
  | Drafter | gemini-3.8-flash | medium |
  | FactReviewer | deepseek-v4-pro-0813 | high |
  | VoiceReviewer | gemini-3.8-flash | medium |
  | BuyerReviewer | deepseek-v4.1-flash | low |
  | ReviewReconciler | deepseek-v4.1-flash | low |
  | RevisionRechecker | deepseek-v4-pro-0813 | high |

- Credential status source: `RunEditorialActivity.php:303-304` checks `(string) config('ai.providers.openrouter.key', '') === ''`, which is set by `config/ai.php:144` (`env('OPENROUTER_API_KEY')`). Mirror that server-side as a boolean only.
- Provider failure copy to document, from `RunEditorialActivity.php`:
  - 401/403: "Check the OpenRouter API key and its permissions" (L910).
  - 402: "no remaining credit or limit" (L911).
  - Other 4xx: "selected model is available and supports the requested parameters" (L912).
  - Stored override outside the allowlist: "Reset it in the publishing agent settings" (L246). The settings page's reset must be able to fix this.
  - `MODEL_CONTINUITY_ERROR` (L42).
  - Recovery pauses Running work older than 30 minutes as uncertain (`RecoverEditorialActivities.php:37-51`). It does **no billing lookups** now.
- Test patterns:
  - `tests/Feature/Publishing/AdminPublishingWorkspaceTest.php`:
    - `publishingUser()` (L25-32) = Admin Access + Author.
    - `publishingWriteOnlyUser()` (L34-41) uses direct `givePermissionTo(View, Write)`. This is the existing precedent for "publishing access without the new capability".
    - Nav XPath assertions (L113-145, L147-155).
    - Direct URL returns 403 for admission-only users (L157-164).
    - Livewire revocation pattern: `removeRole` + `forgetCachedPermissions()` + `->call()->assertForbidden()` + DB unchanged (L166-190).
    - Paused copy test (L240-258).
  - `makePublishingViewOnly()` is in `AdminPublishingEditorTest.php:38-45`.
  - `AdminAuthorizationTest.php:14-17`: a guest gets `assertRedirect('/login')`. Tests use the host `http://admin.birdcar.test` (from `.env` `ADMIN_URL`).
- `.impeccable/surfaces/nents-admin-publishing-article-workspace-blade-php.md` has frontmatter `related_targets` (L5); add the new SFC there. `DESIGN.md` "Admin operational extension" is at L312-326, with Do/Don't at L338/342/348.

### Phase 1 (retained)

- `app/Ai/Agents/EditorialAgent.php` — abstract base.
  - Uses `Promptable`, `RemembersConversations`; implements `HasProviderOptions`, `HasStructuredOutput`, `HasTools`, `Conversational`.
  - At scout time the constructor was `(EditorialActivity $activity, array $endpoint)`. Phase 1 changed it to `(EditorialActivity, ?string $modelOverride, ?array $pinnedExecution)`.
  - `forActivity()` is the single `match` on `EditorialActivityKind` → 9 concrete classes. `maxSteps()` returns 1.
  - `providerOptions()` now sends `provider.require_parameters`, `reasoning` when supported, and the Researcher `plugins` (Exa).
  - `tools()` gives AskAuthor to the Interviewer only. `schema()` is a per-kind match.
- `app/Ai/Agents/{Interviewer,Researcher,Planner,Drafter,FactReviewer,VoiceReviewer,BuyerReviewer,ReviewReconciler,RevisionRechecker}.php` — each has `#[MaxTokens]`, `model()` (override ?? literal), `roleInstructions()` and `roleReasoningEffort()`.
- SDK resolution (verified in vendor):
  - `prompt(model:)` beats `model()`, which beats `#[Model]`. `timeout` resolves from the argument, then `timeout()`, then `#[Timeout]`, then 60.
  - `maxTokens`/`temperature`/`topP` resolve method, then attribute.
  - The request body is merged with `providerOptions()` at the top level.
  - Approval continuations use a single provider/model with no failover.
  - The returned model is `$response->meta->model`.
- `vendor/spatie/laravel-settings` 3.9.0:
  - Settings classes are bound with `scoped()`. The queue worker calls `forgetScopedInstances()` per job.
  - Auto-discovery covers `app_path('Settings')`.
  - Migrations load from `database/settings` via `loadMigrationsFrom`.
  - `SettingsMigrator` API: `add`/`delete`/`deleteIfExists`/`update`/`exists`/`inGroup`.
- `database/migrations/2026_09_22_230000_create_editorial_activity_tables.php` is the migration style reference.
- `app/Authorization/Publishing/{Permission,Catalog}.php`: `SyncAuthorization.php:264` calls `$role->syncPermissions()`, so a normal sync detaches removed permissions and reports stale definitions.
- `resources/views/components/admin/publishing/partials/*.blade.php`: Flux components, one-line Tailwind markup, `@can(...)` gating.

## Dependencies

### Phase 2

- `app/Authorization/Publishing/Permission.php` + `Catalog.php` are consumed by:
  - `SyncAuthorization` via `config/authorization.php:10-14`.
  - `tests/Feature/Authorization/AuthorizationSyncCommandTest.php:28-51`. The exact **sorted** list for `publishing.author` must become `['publishing.approve','publishing.configure-agents','publishing.develop','publishing.publish','publishing.view','publishing.write']`; add an `assertDatabaseHas` for `publishing.configure-agents`.
  - `config/admin.php:13-16` / `app/Actions/Admin/InviteAdministrator.php:237` (root admin gets the capability through the Author role).
  - No other test enumerates publishing permissions or user permission sets. `RunEditorialActivity.php:957` checks `publishing.develop` by string and is unaffected.
- `routes/admin.php` is consumed by the `route('admin.publishing.*')` helpers in views and tests. Adding the `admin.publishing.settings` name is additive. `php artisan route:list --path=publishing` currently shows 4 routes.
- `resources/views/components/admin/navigation.blade.php` is included by `layouts/admin.blade.php:48` on every Admin page and renders only on `admin.publishing.*`. It is asserted by:
  - `AdminPublishingWorkspaceTest.php:98-111`: admission-only users see no publishing nav and no `href=".../publishing`. Unaffected because the nav is hidden off publishing routes.
  - `AdminPublishingWorkspaceTest.php:113-145`: exactly one `aria-current="page"` link containing `/publishing`. The new Settings link must not be current on the dashboard.
  - `AdminPublishingWorkspaceTest.php:147-155`.
- `app/Settings/PublishingAgentSettings.php` is consumed by:
  - App: `RunEditorialActivity.php:125,244`, `StartEditorialActivity.php:115`, `ApprovePublishingStage.php:69`, `ResumeEditorialActivity.php:74`, `RecoverEditorialActivities.php:17-23`, `⚡article-workspace.blade.php:912`, `config/settings.php:18`.
  - Tests: `tests/Pest.php:48-53` (`setPublishingAgentsPaused`), `EditorialToolApprovalTest.php:111,137,154`, `EvidenceResearchTest.php:232`, `EditorialReviewTest.php:476`, `PublishingAgentSettingsTest.php`.
  - If the class stays unchanged, the blast radius is zero.
- `tests/Feature/Publishing/PublishingAgentSettingsTest.php` (extend, do not replace):
  - `beforeEach` (L20-25) syncs authorization, sets `ai.providers.openrouter.key` = `'test-key'`, and calls `Http::preventStrayRequests()`.
  - Existing helpers are `settingsAttempt()` (L27-36) and `interviewResponse()` (L38-44).
  - 7 domain tests already cover the clean-install pause, reset-unsets, unknown-model refusal, an invalid stored override pausing the runner, warm-worker scope, and pause durability (L46-162).
- `tests/Feature/Publishing/PublishingSessionTest.php` has no assertions on agent-paused copy; it only calls `setPublishingAgentsPaused(true)` at L19. The paused copy assertion is at `AdminPublishingWorkspaceTest.php:251` ("Agent work is queued; publishing agents are paused"), rendered at `partials/session.blade.php:44`. Change these only if that copy changes.
- Docs and design text to reconcile:
  - `docs/development-setup.md`:
    - L58 is `PUBLISHING_AGENTS_ENABLED=false` in the env block.
    - **All of §6 (L169-258) needs replacing**: route/price/premium/Exa-allowance guidance, `PUBLISHING_AGENTS_*` blocks at L182-196 and L222-229, "Enable deliberately" with `PUBLISHING_AGENTS_ENABLED=true`, and the tinker check at L253 that reads `config("publishing_agents.enabled")`.
    - L280 "can perform billing lookups".
    - Smoke test L305-314 mentions the budget display, "separately reserved paid completion", the "$5 allowance" and "budget settlement/holds".
    - Troubleshooting L347-351 (L349 "Model and provider must be explicit"/pricing, L350 pricing ceilings, L351 budget/reservation records).
    - Stopping L357 (`PUBLISHING_AGENTS_ENABLED=false` + config clear).
    - Keep L329 `AgentBudgetTest.php`; that file still exists with Phase 1 budget-free coverage.
  - `docs/production-setup.md`:
    - L51 `PUBLISHING_AGENTS_ENABLED=false`.
    - L60 "budget settings".
    - L156 "may perform billing lookups".
    - L158 restart guidance: keep it for code deploys and add that settings saves need no restart.
    - **§7 L160-182**: replace the env matrix, price/reservation and $5 allowance text. Keep the AskAuthor paragraph at L182.
    - L197 "budget pauses remain visible".
    - L211 billing/reservation recovery.
    - L212 disable/recache: replace with a settings pause.
    - Keep L213-214 and the authentication, queue-timeout and publication safeguards.
  - Running the contract's legacy-variable check now returns `['docs/development-setup.md', 'docs/production-setup.md']`; `.env.example` and the config already pass.
  - `DESIGN.md:322` ("Develop shows ... allowance"): remove it, and add one sentence to L320-326 for the Settings surface. `.impeccable/design.json:211` has no allowance text, so sync it only if the tooling regenerates it.
  - Surface brief: L28 ("$5 attempt allowance"), L36 ("budget pause"), L40 ("grants the $5 allowance"), L42 ("allowance state"), and add to `related_targets` at L5.
  - `AGENTS.md`/`CLAUDE.md`/`GEMINI.md` contain no budget or publishing guidance, so they do not need regeneration.

### Phase 1 (retained, as mapped at `1c4f5b5`; these consumers were resolved in `c044341`)

- `app/Services/Publishing/EditorialModelBudget.php` (deleted) was consumed by:
  - `RunEditorialActivity.php`
  - Tests: `AgentBudgetTest.php`, `EditorialReviewTest.php`, `EditorialWorkflowTest.php`, `EvidenceResearchTest.php`, `PublishingJourneyTest.php`
- `app/Services/Publishing/AgentBudget.php` (deleted) was consumed by `RunEditorialActivity.php`, `RecoverEditorialActivities.php`, `⚡article-workspace.blade.php`, and the same test files.
- `app/Services/Publishing/OpenRouterBilling.php` (deleted) was consumed by `RunEditorialActivity.php` and `RecoverEditorialActivities.php`.
- `app/Models/AgentBudgetReservation.php` and its factory (deleted) were consumed by `PublishingAttempt.php`, `EditorialActivity.php`, `RunEditorialActivity.php`, `RecoverEditorialActivities.php`, and the tests `ReleaseReadinessTest.php`, `EditorialToolApprovalTest.php`, `AgentBudgetTest.php`.
- `publishing_attempts.allowance_nano_usd` / `allowance_changes` were consumed by `PublishingAttempt.php`, `PublishingAttemptFactory.php`, `AdvancePublishingAttempt.php`, `AgentBudget.php`, and tests.
- `Permission::Budget` / `publishing.budget` was consumed by `Catalog.php`, `ArticlePolicy.php`, `activity.blade.php`, `⚡article-workspace.blade.php`, `AgentBudget.php`, `AgentBudgetTest.php`, `AuthorizationSyncCommandTest.php`.
- `config('publishing_agents.enabled')` was replaced by settings `paused` at `StartEditorialActivity`, `ApprovePublishingStage`, `ResumeEditorialActivity`, recovery, the runner claim, and the session partial.
- `config('publishing_agents.limits.*')` keys were retained; `EditorialOutput.php`, `PublicSourceFetcher.php` and `RunEditorialActivity.php` consume them.
- Dispatch/pause boundaries: `StartEditorialActivity::start`, `ApprovePublishingStage::approve`, `ResumeEditorialActivity::handle`, `RecoverEditorialActivities`, `RunEditorialActivity::claimActivity`. The schedule is `routes/console.php:11` (recovery every 5 minutes).
- `model_snapshot` is now `{requested_model, model, reasoning_effort, returned_model}`. It is written in the pre-invocation transaction; Auto continuations pin the returned model.
- `.env.example:77-90` is now the budget-free AI block: `OPENROUTER_API_KEY`/`OPENROUTER_BASE_URL`, worker guidance, and "enable them deliberately in the publishing agent settings".

## Conventions

- **Naming**:
  - Livewire SFCs are `resources/views/components/{area}/⚡{name}.blade.php`, referenced as `admin.publishing.{name}`.
  - Generate with `php artisan make:livewire admin.publishing.settings --no-interaction`. The vendor default `make_command` is `type=sfc`, `emoji=true`, and there is no published `config/livewire.php`. This creates `resources/views/components/admin/publishing/⚡settings.blade.php`. Confirm the path; if the generator adds a test file or a different path, move/delete it to match.
  - Route names are `admin.publishing.{name}`.
  - Permission enum keys are TitleCase and values are `domain.kebab-action`.
  - Settings role keys are `EditorialActivityKind` values (`interview`, `research_challenge`, `plan`, `draft`, `review_facts`, `review_voice`, `review_buyer`, `reconciliation`, `recheck`).
- **Imports**:
  - SFCs use full `use` statements at the top of the PHP block.
  - Permission enums are aliased: `Permission as PublishingPermission`, `Permission as AdminPermission`.
  - Blade-only files use an `@php use ...; @endphp` header (navigation, layout).
- **Authorization**:
  - Check permissions, never role names.
  - Actions call `Gate::authorize(AdminPermission::View->value)` and then the domain capability (dashboard precedent). `admin.view` alone is never sufficient.
  - Views use `@can(PublishingPermission::X->value)`.
  - Do not grant direct permissions in app code (tests may, per `publishingWriteOnlyUser`). No boot-time seeding; run `authorization:sync`.
- **Error handling / feedback**:
  - Livewire components catch `Throwable` into a `?string` feedback property. Validation errors use `flux:error name="..."`.
  - Show loading and duplicate-submit guards with `wire:loading.attr="disabled" wire:target="save"`.
  - `wire:dirty` (built into Livewire) marks unsaved state without new JS.
- **Types / state**:
  - Public properties are scalars and arrays only; never a `Settings` object or config array.
  - Resolve settings per action (`app(PublishingAgentSettings::class)` or method injection), as the workspace does at L912.
  - Larastan level 7 covers `app/`, `config/`, `database/`, `routes/` but **not** `resources/`, so SFC PHP is only verified by tests.
- **UI**:
  - Flux free/Pro 2.19: `flux:switch`, `flux:select` (default native variant; keyboard-safe, no custom select), `flux:field`/`flux:label`/`flux:description`/`flux:error`, `flux:callout`, `flux:badge color="teal"`, `flux:button variant="primary"`.
  - Inter and neutral zinc surfaces with `text-teal-800 dark:text-cyan-200` accents. Wrap long strings with `wrap-anywhere`/`min-w-0`, and use a `flex-col sm:flex-row` row layout.
  - Activate the `livewire-development`, `fluxui-development`, `laravel-permission-development` and `testing-best-practices` skills (`.claude/skills/`).
- **Testing**:
  - Pest functional style. `beforeEach` runs `PermissionRegistrar::forgetCachedPermissions()` + `$this->artisan('authorization:sync')`.
  - Build users with `User::factory()` + `assignRole(AdminRole::Access->value, PublishingRole::Author->value)`.
  - HTTP host is `http://admin.birdcar.test/publishing/settings`. Use `Livewire\Livewire::actingAs($user)->test('admin.publishing.settings')`.
  - Only `tests/Pest.php` helpers (`setPublishingAgentsPaused`) are available in every file. Helpers defined in other test files (such as `pendingAuthorInterview` and `publishingUser`) are **undefined** when one file runs alone, and **collide** if redeclared (full list in Phase 1 notes plus: `settingsAttempt`, `interviewResponse`, `journeyConfigureAgents`, `makePublishingViewOnly`, `publishingWriteOnlyUser`, `publishingAuthorizedUser`, `adminHomeUser`, `sessionAuthor`). Use fresh unique names such as `settingsPageOwner()`.
  - Commands:
    - Inner loop: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php`
    - Permission: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php tests/Feature/Publishing/PublishingAuthorizationTest.php tests/Feature/Authorization`
    - Continuity: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php tests/Feature/Publishing/EditorialToolApprovalTest.php`
    - Full phase: `php artisan test --compact tests/Feature/Publishing tests/Feature/AdminHomeTest.php`
    - Finish with `vendor/bin/pint --dirty --format agent`, `composer types:check`, `bun test resources/js/admin/publishing`, `bun run build` and `git diff --check`.
    - Legacy-variable check: `python3 -c 'import re; from pathlib import Path; files = [Path(p) for p in [".env.example", "config/publishing_agents.php", "docs/development-setup.md", "docs/production-setup.md"]]; stale = [str(p) for p in files if re.search(r"PUBLISHING_AGENTS_[A-Z0-9_]+", p.read_text())]; assert not stale, stale'`. It currently fails for both docs.
    - Run a baseline of the Phase 2 test files before editing; the scout did not run tests.
  - Rendered QA:
    - Resolve the Admin URL with Boost `get-absolute-url`. `.env` has `ADMIN_URL=http://admin.birdcar.test`, which is http; dev docs say https.
    - Save screenshots under `.impeccable/review/publishing-settings/` (gitignored at `.gitignore:33`; `/.playwright-mcp/` is also ignored). Never commit them.
- **Commits**: directly on `main`, no push, no Co-Authored-By unless requested. Stage only this phase's files.

## Risks

### Phase 2

- **Local DB is not migrated for Phase 1 (this blocks rendered QA).** `php artisan migrate:status` shows these as **Pending** on the local PostgreSQL DB:
  - `2022_12_14_083707_create_settings_table`
  - `2026_09_24_230000_create_publishing_agent_settings` (`database/settings`)
  - `2026_09_24_230100_remove_publishing_budget_storage`

  Until they run, the new page and the existing article workspace (`⚡article-workspace.blade.php:912`) will throw on the missing `settings` table. The spec allows "normal migrations and authorization:sync for deliberate local testing". Running `php artisan migrate` irreversibly drops budget history (the owner approved schema removal in the contract; `down()` restores only empty schema). Never use `migrate:fresh`/`db:wipe`. After migrating, agents are `paused=true`. Then run `php artisan authorization:sync`: Author gains `publishing.configure-agents`, and a leftover `publishing.budget` is reported as stale. Do not `--prune` silently. If the owner session or browser tools are unavailable, report rendered checks incomplete rather than substituting build output.
- **Decision-log / premise checks against reality:**
  - (a) "Pause means no new requests". Unpausing on the page does **not** dispatch anything. Pending work is re-dispatched only by `publishing:recover-activities` (every 5 minutes, and only if the scheduler runs) or by a new start. Paused, Failed and uncertain work stays as-is, and AwaitingApproval still waits for the author (`RecoverEditorialActivities.php:23-51`). The spec forbids the page dispatching, so the copy must say queued work resumes on the next recovery pass rather than immediately.
  - (b) "Reuse the existing dispatch enumeration / no second map". Recommendations are available through `EditorialAgent::recommendedModelFor()`, but allowlist labels have no public accessor (`modelDefinition()` is private, L80). Read `config('publishing_agents.models')` as a whole array in the component; never use dotted lookups, because IDs contain dots. The alternative is a small public static on `EditorialAgent`, but `EditorialAgent.php` is not in the spec's file table; report it if added.
  - (c) "Bounded rendered QA is part of MVP" assumes a working local site, which is false until the migrations above run.
  - (d) The file table lists `PublishingAgentSettings.php` and `PublishingSessionTest.php` as modified, but neither obviously needs changes. Leave them untouched unless a concrete need appears, and report that they were left unchanged.
  - (e) Neither docs nor rules still claim recovery does billing lookups in code, but docs L280 and prod L156 still say it. Correct that wording.
- **Rule changes need Boost `record-rule`**; do not edit `.ai/rules` directly. Phase 1 precedent (implementation notes): calling `record-rule` with an existing title appended a duplicate section, and the builder removed only the exact duplicate. `record-rule` was unavailable to the scout; if it is unavailable to the builder, skip rule edits and report it. `.ai/rules/ai.md` already states the budget-free policy, so a new rule is optional.
- **Tests bypass route middleware.** `Livewire::test` does not run `PermissionMiddleware`, so a mount without `Gate::authorize` would let the Livewire tests pass for unauthorized users. Authorize in `mount()` and in every mutating action (`save`, `resetRole`/`resetModel`, and any pause action).
- **Scoped settings inside one test container.** After a Livewire save, `app(PublishingAgentSettings::class)` returns the same in-memory instance. To prove persistence or "reload" and warm-worker behavior, call `app()->forgetScopedInstances()` and re-mount or run the job. Assert the DB payload through `DB::table('settings')`.
- **Secret redaction.**
  - Set a distinctive key in the test, e.g. `config()->set('ai.providers.openrouter.key', 'sk-or-v1-scout-secret-123456')`.
  - Assert `assertDontSee` on the full HTTP response, which includes the `wire:snapshot` attribute.
  - Assert `json_encode($component->snapshot)` and `json_encode($component->effects)` after `save` contain no part of it (no `sk-or` prefix either).
  - Also check with the key missing: a boolean notice only, and never echo `OPENROUTER_BASE_URL`.
- **Two-tab last-write-wins.** `Settings::save()` writes every property. With a single owner this is acceptable. After save, reload component state from a fresh settings read so stale drafts are not shown.
- **Navigation XPath tests.** Keep exactly one `aria-current="page"` publishing link per page. Settings must be current only on `admin.publishing.settings`.

### Phase 2 edge cases for the builder

1. Tampered state:
   - Validate `models` with the `array:` + role-values rule (rejects unknown keys such as `not_a_role`).
   - Validate `models.*` as `nullable|string` + `Rule::in(allowlist ids)`.
   - Validate `paused` as `required|boolean`.
   - Reject rather than silently drop. Assert that the DB is unchanged after a failed validation.
2. "Use recommended" (`''`) calls `resetModel()` and removes the key. Explicitly choosing the model that equals the current recommendation saves a pinned override. These are different semantics; test both, and show which one applies.
3. A stored override that is no longer allowlisted (legacy row or shrunk allowlist) must render as "unsupported, reset required" rather than a blank select. Saving or resetting cleans it (`validOverrides()`). The runner message at L246 points the owner here.
4. Pause: show the saved status (from settings, in `with()`) separately from the draft switch. Mark unsaved changes with `wire:dirty`. Only Save persists, so an abandoned toggle leaves the DB unchanged. Copy must cover:
   - existing in-flight requests may finish;
   - queued work waits;
   - scheduled publication and human approvals are unaffected;
   - unpausing resets no statuses.
5. Saving any selection or pause must not dispatch jobs or HTTP. Assert with `Bus::fake()` + `Http::preventStrayRequests()` + `Http::assertNothingSent()` + `Bus::assertNothingDispatched()`, with Pending and AwaitingApproval work present.
6. Mutation after revocation:
   - Remove the Author role, or both Admin Access and Author, then `forgetCachedPermissions()`.
   - `save`, `reset` and pause each return 403, and the DB is unchanged.
   - Forged `->call('save')` from an Admin-only user or a View+Write direct-permission user returns 403.
7. Route matrix:
   - guest → redirect `/login`
   - Admin-only → 403 (Publishing View group middleware)
   - Admin + View/Write without ConfigureAgents → 403, and no Settings nav link
   - Author → 200 with the Settings link current
8. Continuity:
   - With `pendingAuthorInterview`-style state built locally (the helper is not callable from this file), a Livewire save of a different Interview model must not change the continuation model.
   - A Livewire save of `paused=true` keeps `tool_decisions` durable without dispatch.
   - A Livewire override save followed by `forgetScopedInstances()` and a job run sends the saved model in the fake request body.
9. Auto Router option: copy about dynamic selection only, with no price or fixed cost. It is valid for all 9 roles. The Researcher still sends Exa plugins, and reasoning is omitted for Auto (Phase 1 behavior).
10. Layout: 9 role rows with long labels at 390px, plus 1440px, light/dark, tab order (switch → selects → reset buttons → Save), visible focus, and failed-save feedback (a validation error from a tampered value can be simulated in tests; in the browser, use a missing-key notice).

### Phase 1 (retained; historical risks, resolved in `c044341` unless noted)

- **Rule already superseded in the working tree.** `.ai/rules/ai.md` had the intended wording uncommitted. Resolved: `record-rule` was called and its duplicate removed.
- **Unlisted required changes.** `ReleaseReadinessTest.php` fixture and `⚡dashboard.blade.php:185` copy. Resolved, along with a `tests/Pest.php` helper.
- **Decision-log / premise mismatches.**
  - (a) The 429 "existing bounded retry" is effectively manual (`failOrPause` marks Failed; recovery ignores Failed). It was kept as-is and is still true, so document it accurately in Phase 2 docs.
  - (b) Published `config/settings.php` env knobs are hard-coded `false` (`config/settings.php:71-81`), so there is no settings cache. Saved changes therefore need no config rebuild or restart.
  - (c) `PublishingAuthorizationTest.php`/`AdminPublishingWorkspaceTest.php` had no budget references.
  - (d) `main` had advanced past `df1d96e`.
- **Paused-by-default breaks agent tests.** Resolved via `setPublishingAgentsPaused()`.
- **Scoped settings and fakes.** Still relevant: use `forgetScopedInstances()` between scopes, and never resolve settings in job constructors.
- **Test seam removal.** Resolved with an `EditorialActivity::saved` listener seam.
- **Legacy pause classification.** Resolved in the forward migration (`EditorialActivity::LEGACY_PRE_CALL_PAUSE_REASON`, `RESUMABLE_PAUSE_REASONS`).
- **Rollback ordering.** The forward `down()` recreates empty budget schema.
- **Timeout ordering.** A code-owned 50s `http_timeout` stays below Horizon's 60s. Docs must stop referencing `PUBLISHING_AGENTS_HTTP_TIMEOUT`.
- **Auto Router and continuation.** Resolved: pin the returned model, otherwise `MODEL_CONTINUITY_ERROR`.
- **Researcher plus Auto.** Request bodies were asserted per combination.
- **Pause must not consume claims.** Resolved (`claimActivity` returns early when paused).
- **Secrets in errors.** Resolved: fixed messages that never echo response bodies. Still true for Phase 2 UI: never bind provider config to public state.
- **Octane.** Settings are scoped; do not wrap them in a singleton or static cache. This applies to the Phase 2 component too.
- **No automated lock-contention coverage.** Unchanged.
