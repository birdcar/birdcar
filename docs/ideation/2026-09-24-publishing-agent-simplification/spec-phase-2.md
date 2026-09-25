# Implementation Spec: Publishing Agent Simplification — Phase 2

**Contract**: ./contract.md
**Phase**: Publishing settings and operational integration
**Estimated effort**: M
**Prerequisite**: Phase 1 — Native agent configuration and budget-free execution

## Technical Approach

Expose the settings implemented in Phase 1 through one Publishing settings page in the existing Admin shell. Use the repository's Livewire 4 single-file component convention and Flux Pro components. The page contains a pause switch and one optional curated model override per supported role, with the code recommendation clearly shown and a reset action. It must be usable without understanding provider IDs, token prices, routing tiers or billing.

Use a dedicated publishing capability for both route admission and Livewire mutations, registered in the domain catalog and synchronized through `authorization:sync`. This is an app-wide owner-operated feature, not tenant settings or a new Admin settings platform. Keep credentials in deployment configuration; the page may show only configured/not-configured status.

Reconcile current setup instructions and affected design/surface records with the new behavior. Follow the incumbent Flux/Inter light/dark design; do not redesign the publishing workspace, change marketing, introduce packages, run paid trials or deploy.

## Decisions Considered and Rejected

- **Per-agent recommendations with curated overrides** — rejected global default/premium selectors and arbitrary model/provider fields. An unset override follows that agent's code recommendation.
- **Small Publishing settings page** — rejected a generic settings module, tenant settings and new identity/role systems.
- **Credentials remain environment-backed** — rejected API-key editing or secret display in the settings page.
- **Pause means no new requests** — allow an in-flight request to finish, preserve durable pending work and human approval state; no remote cancellation claim.
- **OpenRouter owns spending** — no allowance, top-up, price matrix, cap editor or cost reporting UI. Native usage data may remain stored without being surfaced.
- **Bounded rendered QA is part of MVP** — the scope critic's suggestion to defer it was declined because responsive/light/dark/keyboard behavior is an existing Admin standard, not an added design project.
- **Reconcile old guidance** — rejected appending a new decision while leaving obsolete budget requirements in authoritative instructions.
- **Main-only commits** — explicitly requested by the owner; no isolation branch, merge commit or push.
- **Stop after artifact generation until invoked** — this spec does not authorize implementation merely by existing. Execute only when the owner later runs the appropriate command.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php`

**Playground**: Livewire feature tests for authorization, validation and persistence, plus the already-managed local site for bounded rendered interaction checks.

**Why**: Fast tests exercise mutations and permissions; real rendering checks catch focus, layout, theme and feedback problems that source assertions do not prove.

Resolve the actual local Admin URL through Boost `get-absolute-url` before using or sharing it. Do not start a competing server. Read recent browser logs only. If browser/tool access is unavailable, report rendered checks incomplete rather than substituting a build result.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `resources/views/components/admin/publishing/⚡settings.blade.php` | Focused full-page Livewire/Flux settings form. |

### Modified Files

| File Path | Changes |
| --- | --- |
| `routes/admin.php` | Named `/publishing/settings` Livewire route with publishing configuration permission. |
| `resources/views/components/admin/navigation.blade.php` | Permission-aware Settings tab/link within Publishing. |
| `app/Authorization/Publishing/Permission.php` | Add `ConfigureAgents = 'publishing.configure-agents'`. |
| `app/Authorization/Publishing/Catalog.php` | Register the capability and include it in the owner's publishing author role. |
| `app/Settings/PublishingAgentSettings.php` | Only adjustments needed by the form's persistence/reset API; no second settings implementation. |
| `tests/Feature/Publishing/PublishingAgentSettingsTest.php` | Route, Livewire, validation, reset, credential redaction and permission-revocation cases. |
| `tests/Feature/Publishing/PublishingAuthorizationTest.php` | Configuration capability isolation. |
| `tests/Feature/Authorization/AuthorizationSyncCommandTest.php` | Explicit catalog/sync expectations. |
| `tests/Feature/Publishing/AdminPublishingWorkspaceTest.php` | Permission-aware settings navigation and budget-free integration. |
| `tests/Feature/Publishing/PublishingSessionTest.php` | Only affected mode/status copy assertions. |
| `docs/development-setup.md` | Remove the publishing env/price/allowance setup and explain code defaults, settings and pause/recovery. |
| `docs/production-setup.md` | Replace old operator procedure and budget reconciliation guidance; preserve independent deployment safeguards. |
| `DESIGN.md` | Reconcile only the Admin publishing/settings and removed-allowance descriptions. |
| `.impeccable/design.json` | Synchronize the design sidecar if the configured documentation tooling updates it. |
| `.impeccable/surfaces/nents-admin-publishing-article-workspace-blade-php.md` | Remove obsolete allowance claims and describe the focused related settings surface. |

Use the existing surface brief with the new settings component as a related target; do not invent a new brand direction or broad design document. Generated `AGENTS.md`/`CLAUDE.md`/`GEMINI.md` should be regenerated only if they actually contain stale affected guidance, using the existing Boost mechanism rather than arbitrary rewrites; report any such generated changes explicitly.

### Deleted Files

None. Phase 1 owns budget-only source removal. Do not delete unrelated tests or previous ideation artifacts.

## Implementation Details

### 1. Permission boundary

**Pattern to follow**: `app/Authorization/Publishing/{Permission,Catalog}.php`, `routes/admin.php`, existing workspace `Gate::authorize()` calls.

1. Add the `ConfigureAgents` enum case and catalog definition; grant it through the existing publishing author role in the catalog. The current surface is owner-operated. Do not check a role name in application code or use `admin.view` as authorization for settings mutations.
2. Keep Admin host/auth/admission middleware and Publishing view admission. Add the configuration permission to the settings route.
3. Authorize on component mount/render as appropriate and again inside save, reset and pause mutation actions. A user whose permission was revoked after page load cannot mutate settings.
4. Show the navigation link only for the same capability. Do not rely on hidden UI as protection.
5. Run normal `authorization:sync` during deliberate local rollout. Do not seed in boot, enable Teams, grant direct user permissions or prune stale assignments silently.

**Feedback loop**:
- Playground: HTTP and Livewire tests with users built through existing factories and catalog roles.
- Experiment: guest; Admin-only; publishing access without ConfigureAgents; authorized owner; revoked permission between mount and save; unauthorized forged action.
- Check command: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php tests/Feature/Publishing/PublishingAuthorizationTest.php`

### 2. Settings form and state

**Pattern to follow**: `resources/views/components/admin/publishing/⚡dashboard.blade.php`, `resources/views/components/admin/navigation.blade.php`, Phase 1's settings class and concrete SDK agents.

Use `php artisan make:livewire` with the project's SFC naming configuration; inspect generator options. Keep the component at the listed path, not under Folio marketing pages. Suggested named route: `admin.publishing.settings`.

The form has two compact sections:

- **Agent requests**: pause switch, plain explanation that existing requests may finish and paused queued work waits. Show the deployed-key status as a boolean notice. Missing credentials should explain what the operator needs to configure without echoing a key, token prefix or secret-bearing URL.
- **Models by task**: readable role name and purpose, current recommendation, optional curated override selector, and reset-to-recommended. Include OpenRouter Auto Router as an option with concise dynamic-selection copy. Do not imply a fixed cost or show prices that look live.

Use real model labels from the allowlist, not opaque pricing tiers. A distinct “Use recommended” selection removes the override. Defaults shown in the UI come from the concrete agents' actual native recommendations, not a copied second map that can drift. The small role-to-class mapping may reuse the existing dispatch enumeration; avoid a new generic registry.

Use explicit Save feedback and disable duplicate submission while saving. Choose an ordinary form-level save for the pause flag and model overrides so the owner sees when a change is persisted; do not give an unsaved toggle the appearance of an active stop switch. Display saved pause status separately from draft form state if needed. No autosave infrastructure or new JavaScript is required.

Validate the complete submitted array, including role keys and model IDs; reject unsupported keys rather than saving unbounded data. Save only validated properties. Update stale component state after save/reset. Do not serialize a Settings object or credential-bearing configuration into public Livewire properties.

**Feedback loop**:
- Playground: `Livewire::test` plus the existing local Admin site.
- Experiment: save one override; reload; reset it; submit unknown model/role; toggle then abandon without save; save pause; revoke permission before save; missing key; nine role rows with long labels.
- Check command: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php`

### 3. Integration with pending and running work

**Pattern to follow**: Phase 1's start/run/resume/recovery settings checks and `EditorialToolApprovalTest.php`.

Settings saves affect subsequent not-yet-started work without deployment or worker restarts. They must not mutate already-captured model snapshots. When an activity is waiting on AskAuthor, its continuation retains the original execution model even if the owner selects another model on this page. A pause blocks that continuation from issuing a new request without discarding the question, answer or SDK decision state.

Pausing AI requests does not pause scheduled publication or revoke human approval; these are distinct controls. Explain the scope accurately. Unpausing does not reset ambiguous failed/running statuses or automatically approve anything. The page must not dispatch a model request solely because someone changes a selection.

**Feedback loop**:
- Playground: separate scoped settings resolutions and queued/faked activity jobs.
- Experiment: queue then pause before execution; change model before first claim; change model after pending approval; save in one scope then execute in another; unpause with ordinary pending, manual-paused and uncertain work present.
- Check command: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php tests/Feature/Publishing/EditorialToolApprovalTest.php`

### 4. Extend the existing design, then verify rendered behavior

Read the relevant Flux, Livewire and Impeccable guidance and existing Admin surface brief. This is a narrow Operate-mode extension, not a new visual identity. Keep Flux Pro, Inter, neutral surfaces and established teal/cyan accents. Use existing layout/navigation components and native form focus/error affordances. No marketing changes, display-font experiment, animation package or custom select implementation.

Build once, inspect desktop/mobile and light/dark together, fix material issues in one batch, and confirm with at most one additional pass. Cover 1440px and 390px, keyboard tab order, visible focus, labels, long model names, validation feedback and saved/unsaved pause state. Use an existing authorized local session or explicitly isolated test fixtures; do not create privileged real accounts or bypass authentication to claim an authenticated test passed. Report access blockers honestly.

Save screenshots as local review evidence according to existing project tooling, but do not commit screenshots or fixture secrets incidentally. Passing a build or Livewire assertion alone is not browser evidence.

**Feedback loop**:
- Playground: managed local site and actual built assets.
- Experiment: the form interactions and viewport/theme cases above, including a failed save and keyboard reset.
- Check command: `bun run build` followed by the bounded browser interaction pass; feature tests remain the fast mutation loop.

### 5. Reconcile operational and design guidance

**Pattern to follow**: existing `docs/development-setup.md`, `docs/production-setup.md`, `.ai/rules/ai.md`, `DESIGN.md` and the current publishing surface brief.

Replace obsolete instructions, not merely append a disclaimer:

- Credentials remain deployment configuration; there is no publishing model/provider/price env matrix.
- Every role has a code recommendation and optional saved override; reset follows the recommendation.
- Package migrations initialize settings; first installation is paused until deliberately enabled.
- Changing a saved setting needs no config rebuild or process restart. Deploying changed code/schema still follows the existing worker/runtime restart requirements.
- OpenRouter account/key limits are external operator controls. Do not document app allowances, conservative financial reservations, top-ups, pricing ceilings or generation-cost reconciliation as prerequisites.
- Explain missing credentials, provider 401/402, unsupported parameters, bounded rate-limit retries and uncertain execution without financial hold semantics.
- Explain that a globally paused app does not cancel an in-flight request or pause independent scheduled publication.
- Preserve all unrelated authentication, deployment, queue-timeout and real-publication safeguards.

Use `record-rule` for shared rule changes; do not edit `.ai/rules` directly. The generated-guidance review must inspect actual output, not assume every guidance file embeds every rule. Update only affected files. Historic approved contracts remain historical records, not active operator instructions to rewrite.

**Feedback loop**:
- Playground: the contract's legacy-variable check plus rendered page and mutation tests.
- Experiment: follow the new setup text from initialized settings through one saved change without providing any model/price env input.
- Check command: `php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php`

## API / State

- GET `admin.publishing.settings` on the existing Admin host, through ordinary Laravel routes.
- Livewire save/reset operations enforce the same configuration permission server-side.
- Public component state contains only the pause boolean, allowlisted model selection strings, safe labels and feedback.
- No public API, tenant scope, credentials endpoint, provider discovery endpoint or remote key-management action is added.

## Testing Requirements

| Coverage | Expected result |
| --- | --- |
| Route and forged Livewire actions | Deny guest, Admin-only and users missing configuration permission; allow authorized owner. |
| Mutation after permission revocation | Deny save/reset/pause and leave persisted values unchanged. |
| Save/reload/reset | Persist only known roles/models; reset removes override and shows native recommendation. |
| Secrets | Neither HTML nor Livewire payloads contain the configured key or any portion intentionally exposed as identification. |
| Warm worker settings | Subsequent job scopes observe a saved pause/model choice. |
| Native approval continuity | Changed global preferences cannot change the paused activity's selected model or grant approval. |
| Existing workspace | Activity history and editor/approval guards work without budget storage. |
| Browser interaction | Keyboard, themes, widths and failure/success states are actually exercised and evidence gaps named. |

No browser testing dependency is installed for this feature. Use available browser tools and existing feature tests; do not add a plugin just to automate the manual visual checks.

## Failure Modes

| Component | Failure | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Form | Unauthorized mutation after render | Permission revoked or action forged | Global configuration changed improperly | Authorize inside every mutation. |
| Form | Unknown role/model persisted | Tampered Livewire state | Invalid requests later | Strict role/model allowlist validation. |
| Reset | Literal copied instead of override removed | Reset after recommendation changes | Future updates ignored | Unset override; derive display from agent code. |
| Pause UI | Unsaved toggle appears active | Owner leaves without saving | Owner believes queued work is stopped | Explicit save state and confirmation. |
| Credentials notice | Secret enters public state | Provider config bound to component property | Key disclosure | Boolean-only status computed server-side. |
| Rendering | Rows overflow mobile | Long labels at 390px | Settings unusable | Bounded viewport/theme pass and Flux controls. |
| Runbooks | Obsolete budget instructions survive | Guidance appended without reconciliation | Future operators recreate removed complexity | Mechanical variable check plus semantic review. |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/PublishingAgentSettingsTest.php tests/Feature/Publishing/PublishingAuthorizationTest.php tests/Feature/Authorization
php artisan test --compact tests/Feature/Publishing tests/Feature/AdminHomeTest.php
vendor/bin/pint --dirty --format agent
composer types:check
bun test resources/js/admin/publishing
bun run build
git diff --check
```

Also run the contract's exact legacy-environment-variable check against active config and setup instructions. Do not run the full contract verifier expecting success before the paid evidence exists; report Phase 3 criteria as pending.

## Rollout and Handoff

- Commit only this phase's changes directly on `main`; no push.
- Coordinate normal migrations and `authorization:sync` for deliberate local testing; do not alter OpenRouter workspace/key settings.
- Keep paid inference out of this phase. Presence of the live key is not permission to run sample generation early.
- Phase 3 performs the separately authorized live trial, after confirming workspace readiness. Owner editorial acceptance is still outstanding.
