# Implementation Spec: Agent Publishing Studio — Phase 3

**Contract**: ./contract.md\
**Phase**: First Admin publishing workspace\
**Estimated effort**: XL
**Prerequisites**: Phase 1 publishing foundation/release integrity and Phase 2 structured documents/archive import are implemented. No production deployment, account provisioning, provider credentials or paid calls are authorized.

**Execution authorization (2026-09-22)**: Nick explicitly approved adding exactly `@tiptap/core@2.11.7` and `@tiptap/pm@2.11.7` to the application, including `package.json` and `bun.lock` updates. This dependency gate is satisfied for those two packages only; additional dependencies still require approval.

**Retry authorization/context**: The owner approved retrying Phase 3 and fixing all outstanding review findings from `run-2026-09-22-2.json`, including the prior locking/SVG notes. Phases 1 and 2 are committed; continue from the uncommitted Admin implementation rather than replacing it. The owner confirmed the existing Herd hosts and authorized only the local `ADMIN_URL` override; the controller has set it to `http://admin.birdcar.test` while preserving `APP_URL=https://birdcar.test`. Do not change DNS, TLS, credentials, roles, other environment settings or production configuration.

**Historical records**: Controller-owned `run-*.json` and generated `run-*.html` are immutable receipts of earlier runs, not claims about the current code. Preserve them verbatim, even when their findings are subsequently fixed. Never delete or rewrite a failed report to make a later review look green. Current reports are explicitly included below; future controller reports must be preserved even when outside a phase's commit scope.

## Technical Approach

Build the first usable Admin application surface with standard Laravel web routes, Livewire 4 single-file components, Flux UI Pro, and a reusable Admin shell. Folio remains marketing-only. The route entry is `routes/web.php` on the configured Admin host with `auth` and `admin.view`; that group requires `routes/admin.php`, which declares explicit publishing routes and action-level publishing capability checks. `admin.index` admits any user with `admin.view`, shows no editorial data when publishing permissions are absent, and routes an authorized owner to Ideas/Active writing. Published is a separate Admin section, not mixed into the writing queue.

The workspace is UI for Phase 1/2 domain actions and server document APIs, not a second workflow engine. Livewire components call existing actions/services, authorize again inside each mutation, and persist the canonical Phase 2 document JSON: `{version: 1, type: "doc", content: [...]}`. The canonical JSON is authoritative; hydrate Flux/Tiptap from JSON and never use lossy HTML as the persistence model. Agent output is always represented as proposals or pending work, never as completed work unless a later phase wires the provider/scheduler services for real.

Use Flux Pro primitives with Inter and create decoupled Admin layout/navigation/feedback pieces under `resources/views/components/admin`, while publishing-specific page components live under `resources/views/components/admin/publishing`. Keep Admin assets separate from marketing scripts (`analytics`, `booking`, `interactions`, marketing diagrams) so authenticated UI does not inherit public tracking/behavior. The installed Flux Pro editor emits `flux:editor` with extension-registration/init hooks and `flux:editor:ready` with `{ editor }`; listen before editor creation and use those hooks rather than dispatching a fake initialization event. `flux:composer` supports `wire:model` for idea capture. If Tiptap imports are needed in production, gate the addition of pinned `@tiptap/core@2.11.7` and `@tiptap/pm@2.11.7` behind explicit owner approval; the disposable spike proved the versions, but did not authorize installation.

## Decisions Considered and Rejected

- **Standard Admin routes and reusable shell** — rejected Folio application routing and publishing-specific global Admin architecture. Folio remains marketing-only; Admin app routes are explicit Laravel routes.
- **Build a shared Admin shell and explicit module route/navigation boundaries** — rejected coupling the shell/navigation to publishing or prebuilding a generic plugin framework. Future Admin modules are expected, but dynamic registries and permission-management UI are out of scope.
- **Use Ideas plus Active writing on the landing dashboard, with Published separate** — rejected Ideas plus Published as the primary split and decisions-first landing.
- **Use Flux Composer for initial idea entry** — rejected the manuscript editor or a large article form for capture. Develop idea authorizes starting the process; Save for later is passive.
- **Use rich text with Markdown shortcuts for the manuscript** — rejected Markdown-source editing or interchangeable source/rendered modes.
- **Use documented Flux custom Tiptap extensions for one continuous manuscript editor** — rejected stock Flux persistence for custom figures and splitting the manuscript solely to avoid extensions.
- **Canonical structured documents are authoritative** — rejected persisting editor HTML as the source of truth.
- **Agents propose rather than overwrite** — rejected silent replacement of authored text. UI must show proposals and human dispositions.
- **Separate live and working versions** — rejected exposing mutable drafts as live content or hiding a published article while editing a replacement.
- **Require separate angle, plan and exact-release approval for new pieces** — rejected combining gates. Imported historical releases do not need fabricated historical approvals.
- **Admin admission is `admin.view`; publishing actions require publishing capabilities** — rejected role-name checks, organization membership bypass, and admission-only editorial access.
- **Use existing Fortify account login/logout and 2FA challenge when applicable** — rejected a new account-management suite for this phase.
- **Separate automated safety checks from owner editorial/usability acceptance** — rejected treating green markup tests as proof of UX, voice, or rendered behavior.
- **No unapproved dependency installation** — rejected assuming production Tiptap packages, browser-test dependencies, or paid provider SDKs may be added without approval.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/AdminPublishingWorkspaceTest.php`

**Playground**: Pest feature/Livewire tests first, then the already-running Herd site at the verified configured Admin host for desktop/mobile checks. Do not start another PHP server. Build changed assets with `bun run build` or use an already configured asset worker.

**Why this approach**: Most risk is route/auth/state behavior and save/conflict handling; feature tests give fast text feedback, while real browser checks are still required for Flux editor, keyboard, reload, and responsive UX.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `docs/ideation/2026-09-22-agent-publishing-studio/run-*.json` | Controller-owned historical run records; preserve verbatim. |
| `docs/ideation/2026-09-22-agent-publishing-studio/run-*.html` | Officially generated historical run reports; preserve verbatim. |
| `routes/admin.php` | Standard Admin module route file required from `routes/web.php`; declares explicit publishing routes. |
| `config/admin.php` | One `ADMIN_URL` setting with derived host, preserving scheme/port for local and production use. |
| `resources/views/auth/login.blade.php` | Minimal Flux existing-account login view. |
| `resources/views/auth/two-factor-challenge.blade.php` | Existing Fortify challenge and recovery-code entry. |
| `app/Http/Responses/AdminLoginResponse.php` | Safe intended redirect for both login and two-factor completion contracts. |
| `app/Http/Responses/AdminLogoutResponse.php` | Safe logout redirect without arbitrary intended destinations. |
| `app/Http/Controllers/Admin/PreviewArticleController.php` | Authorized selected-revision preview, not a public draft URL. |
| `app/Http/Requests/PreviewArticleRequest.php` | Authorize and validate article-bound revision selection. |
| `resources/views/admin/publishing/preview.blade.php` | Marketing typography/rendering without analytics or app scripts. |
| `public/fonts/inter-latin-variable.woff2` | Self-hosted Inter from an official licensed source, if no reusable asset exists. |
| `public/fonts/inter-OFL.txt` | Required upstream font license accompanying the asset. |
| `resources/views/layouts/admin.blade.php` | Shared Admin HTML shell with Inter, Flux assets, safe skip links, navigation slots, and no marketing scripts. |
| `resources/css/admin.css` | Admin-only Tailwind/CSS entry including Flux-friendly layout, editor isolation, mobile states, and diagram/list/whitespace fixes. |
| `resources/js/admin.js` | Admin-only JS entry for editor extension registration, autosave helpers, session recovery, and beforeunload handling; no analytics/booking imports. |
| `resources/js/admin/publishing/editor-extensions.js` | Flux editor hook registration for protected blocks, notes/callouts/charts/diagrams, IDs, paste/filter behavior, and `flux:editor:ready` hydration. |
| `resources/js/admin/publishing/autosave.js` | Serial debounced autosave queue, visible saved/unsaved/error state integration, sessionStorage recovery, mutation IDs, and conflict surfacing. |
| `resources/js/admin/publishing/document-helpers.js` | Pure helpers for editor JSON normalization, protected-block client metadata, safe recovery serialization, and reload/error edge cases. |
| `resources/views/components/admin/navigation.blade.php` | Shared static Blade navigation/user menu using Flux; no extra stateful shell component or module registry. |
| `resources/views/components/admin/⚡index.blade.php` | Admin landing page; admits `admin.view`, displays no editorial data without publishing permission, and links authorized owner into publishing. |
| `resources/views/components/admin/publishing/⚡dashboard.blade.php` | Publishing workspace dashboard with Composer capture, Ideas, Active writing, attention indicators, and no Published mixing. |
| `resources/views/components/admin/publishing/⚡published.blade.php` | Separate published archive list for CMS-backed published releases. |
| `resources/views/components/admin/publishing/⚡article-workspace.blade.php` | Main per-article workspace page composing brief/interview, gates, editor, review proposals, release checks, preview, and approval surfaces. |
| `resources/views/components/admin/publishing/partials/*.blade.php` | Small Blade partials for Composer actions, save badge, conflict banner, proposal cards, protected passage controls, release checklist, and mobile panels where SFC size would otherwise become unreviewable. |
| `tests/Feature/Publishing/AdminPublishingWorkspaceTest.php` | Route admission, permission boundaries, dashboard empty states, capture/develop/save-for-later, and published separation. |
| `tests/Feature/Publishing/AdminPublishingEditorTest.php` | Livewire save CAS, mutation IDs, conflict responses, protected block decisions, proposal-only agent results, and release-approval invalidation UI contracts. |
| `tests/Feature/Auth/AdminFortifyFlowTest.php` | Existing-account login/logout, intended redirect safety, Admin-host access, and 2FA challenge routing when enabled. |
| `resources/js/admin/publishing/document-helpers.test.js` | Bun pure-JS tests for schema normalization, serial save/recovery behavior and state guards; no new browser-test dependency. |

### Modified Files

| File Path | Changes |
| --- | --- |
| `.ai/rules/general.md` | Controller-recorded Herd development rule, replacing obsolete localhost startup instructions. |
| `docs/ideation/2026-09-22-agent-publishing-studio/spec-phase-3.md` | Controller-approved retry regressions and local routing context. |
| `docs/ideation/2026-09-22-agent-publishing-studio/spec-phase-4.md` | Controller-added historical artifact preservation boundary only. |
| `docs/ideation/2026-09-22-agent-publishing-studio/spec-phase-5.md` | Controller-added historical artifact preservation boundary only. |
| `app/Actions/Publishing/WriteArticle.php` | Preserve save invariants and lock/revalidate the current attempt before invalidating release state. |
| `app/Actions/Publishing/ManageArticleRelease.php` | Necessary workspace integration only; retain existing snapshot/approval guards. |
| `tests/Feature/Publishing/ReleaseIntegrityTest.php` | Regression coverage for save/attempt/release invalidation boundaries. |
| `tests/Feature/Publishing/DocumentRoundTripTest.php` | Empty/whitespace SVG accessibility metadata regression. |
| `routes/web.php` | Replace empty Admin no-content route with Admin host/auth/`admin.view` group that requires `routes/admin.php`; keep marketing Folio/public infrastructure boundaries unchanged. |
| `vite.config.js` | Add separate Admin CSS/JS inputs and Inter font loading if needed; do not mix Admin entry with `resources/js/app.js` marketing imports. |
| `resources/css/app.css` | Keep shared tokens only if necessary; do not place Admin page rules in the marketing bundle. |
| `app/Providers/FortifyServiceProvider.php` | Register minimal Fortify views/responses needed for login, logout redirects, and existing 2FA challenge; do not add profile/password/passkey management UI. |
| `app/Providers/AppServiceProvider.php` | Preserve custom Spatie route middleware on Livewire update requests using the installed persistent-middleware API if not already registered. |
| `config/fortify.php` | Use the configured Admin host for the current Admin sign-in entry and safe home; preserve existing identity and 2FA features. |
| `.env.example` | Add the nonsecret `ADMIN_URL` example; do not rewrite the live environment or assume local DNS is configured. |
| `app/Services/Publishing/ArticleDocument.php` | Consume the Phase 2 API and fix the reviewed blank SVG title/description fallback edge case. |
| `tests/Feature/Authorization/AdminAuthorizationTest.php` | Update expectations for `admin.index` rendering and admission-only non-editorial state. |
| `package.json` | Only with explicit owner approval, add pinned Tiptap core/pm 2.11.7. |
| `bun.lock` | Lock only the approved dependency changes; otherwise leave unchanged. |

### Deleted Files

None.

## Required Retry Regressions

1. Angle, plan and release approval must submit the digest actually rendered to the human. Never recompute the expected digest from fresh server state at click time. Render the page, change the relevant input concurrently, submit the old displayed approval and assert rejection with a visible stale-input message and no new approval. Cover all three gates.
2. Queue a second edit while the first autosave promise is unresolved. After the first succeeds, the second sends the newly acknowledged base revision, retains its newer document/metadata and succeeds without a false conflict. Preserve pending edits/recovery data; never rebase over a genuine external conflict or silently overwrite human text.
3. The actual logout control clears only this authenticated user's publishing sessionStorage namespace. Test this client behavior, preservation of unrelated keys, and the server's safe redirect; clearing only Laravel's intended URL is insufficient.
4. Treat `config('admin.url')` as the authoritative origin for login/2FA/home/fallback/logout behavior while route matching uses the host. Test the confirmed Herd domain and an explicit nondefault scheme/port. Do not force a global request-specific URL root under Octane or weaken intended-redirect validation.
5. Limit dependency changes to the two exact approved Tiptap versions and necessary transitive additions. Preserve unrelated lock entries. Do not disable Socket Firewall, change registry/security settings, or bypass package inspection to avoid URL rewrites. If preserving unrelated entries is genuinely impossible under the configured tooling, report that approval blocker rather than bypassing it.
6. In `WriteArticle`, lock the article and then its current attempt in the same transaction, revalidate article/pointer ownership and only then invalidate approvals/schedules. Add meaningful regression coverage; do not claim SQLite sequential tests prove real row-lock contention.
7. Treat empty or whitespace-only SVG title/description text as missing and generate accessible text from the approved caption. Test both missing and blank nodes without altering source essays.

Run the relevant failing regression before each fix, then the complete Phase 3 validation. Preserve the earlier run records unchanged; their failed status is historical evidence, not a defect to erase.

## Implementation Details

### 1. Admin routing, host configuration, and admission

**Pattern to follow**: existing `routes/web.php` Admin domain group, `.ai/rules/services.md`, `.ai/guidelines/authorization.md`, `app/Authorization/Admin/Catalog.php`.

**Overview**: Move Admin application routing into a standard route module without using Folio. The shell admits `admin.view` users, but publishing content/actions require publishing permissions at the route and Livewire action boundaries.

**Route shape**:

```php
// routes/web.php
Route::domain(config('admin.host', 'admin.birdcar.dev'))
    ->name('admin.')
    ->middleware(['auth', PermissionMiddleware::using(AdminPermission::View)])
    ->group(base_path('routes/admin.php'));

// routes/admin.php
Route::livewire('/', 'admin.index')->name('index');

Route::prefix('publishing')->name('publishing.')
    ->middleware(PermissionMiddleware::using(PublishingPermission::View))
    ->group(function (): void {
    Route::livewire('/', 'admin.publishing.dashboard')->name('dashboard');
    Route::livewire('/published', 'admin.publishing.published')->name('published');
    Route::livewire('/articles/{article}', 'admin.publishing.article-workspace')->name('articles.show');
    Route::get('/articles/{article}/preview', PreviewArticleController::class)->name('articles.preview');
});
```

**Key decisions**:

- Use a configurable Admin host for local development instead of hard-coding only `admin.birdcar.dev`; keep marketing routes on the marketing host.
- For the first module, `admin.index` redirects an authorized publishing owner to `admin.publishing.dashboard`; admission-only users receive the shared shell with no editorial data. The shell itself never queries articles. Future modules can change the landing composition without rewriting publishing pages.
- Publishing routes and Livewire reads require publishing.view; every mutation still calls its policies/actions. Preserve custom Spatie route middleware across Livewire update requests using the installed persistent-middleware API, not only the initial GET. Test revoking admin.view while publishing permissions remain, as well as revoking a publishing capability.
- No generic module/plugin framework. Use a simple static navigation data object/Blade include that future Admin modules can extend by editing code.

**Implementation steps**:

1. Add `routes/admin.php` and require it from the existing Admin group in `routes/web.php`.
2. Add `config/admin.php` with `url` from `ADMIN_URL` (production default `https://admin.birdcar.dev`) and derived `host`. Use the configured scheme/port for links; operator config supplies the actual local URL. Do not hard-code a `.test` domain, silently modify DNS, or send local browser tests to production. Use the SFC Artisan generator/help and an explicit `#[Layout('layouts.admin')]`; installed Livewire component resolution must be verified, not inferred from Folio.
3. Register route names: `admin.index`, `admin.publishing.dashboard`, `admin.publishing.published`, `admin.publishing.articles.show`.
4. Assert guests redirect to Fortify login with a safe intended URL, admission-only users see the shared shell and no editorial data, and publishing authors can reach the dashboard.

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/AdminPublishingWorkspaceTest.php` with users for guest, `admin.view` only, and publishing author.
- **Experiment**: Request Admin index, publishing dashboard, published list, and article workspace for all three actors and both configured/local hosts.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/AdminPublishingWorkspaceTest.php tests/Feature/Authorization/AdminAuthorizationTest.php`

### 2. Fortify existing-account login/logout and intended redirects

**Pattern to follow**: `app/Providers/FortifyServiceProvider.php`, `config/fortify.php`, Fortify skill endpoint list.

**Overview**: Provide only the authentication surfaces required to enter/leave Admin with an existing account. Preserve the existing Fortify two-factor challenge when a user has 2FA enabled; do not add account registration/profile/password/passkey management pages as part of this workspace.

**Key decisions**:

- Use Fortify's real `/login`, `/logout`, and `/two-factor-challenge` flows rather than custom controllers.
- Intended redirects must only return users to safe same-application/Admin paths. Never allow open redirects to arbitrary hosts after login/logout.
- Registration may remain configured for the broader starter kit, but this phase does not expose or design an account creation suite for publishing.

**Implementation steps**:

1. Add minimal Flux/Blade Fortify views for login and 2FA challenge if vendor default views are not usable in this app.
2. Bind Fortify response contracts only if needed to send Admin users to the intended Admin URL or `admin.index` fallback.
3. Ensure logout returns to the Admin login/public-safe location and clears intended URLs.
4. Cover existing-account success/failure, throttled invalid attempts where practical, 2FA challenge handoff, and malicious intended URL rejection.

**Feedback loop**:

- **Playground**: `tests/Feature/Auth/AdminFortifyFlowTest.php` using real Fortify endpoints and factory users.
- **Experiment**: Login with valid credentials, invalid credentials, 2FA-enabled account, Admin intended URL, marketing intended URL, and external intended URL.
- **Check command**: `php artisan test --compact tests/Feature/Auth/AdminFortifyFlowTest.php`

### 3. Shared Admin shell, navigation, assets, and feedback patterns

**Pattern to follow**: Flux UI skill, Livewire SFC conventions, existing marketing layout only for asset-boundary contrast.

**Overview**: Create reusable Admin layout primitives independent from publishing. The shell provides consistent module navigation, user menu/logout, flash/toast/status regions, responsive panels, keyboard skip links, and Inter typography.

**Key decisions**:

- Keep shell files under `resources/views/components/admin`, not `admin/publishing`, so future modules can use them.
- Use Flux Pro components before custom controls: navbar, button, badge, callout, card, tabs, dropdown, modal, toast, tooltip, table, composer, editor.
- Separate Admin `@vite(['resources/css/admin.css', 'resources/js/admin.js'])` from marketing `app.css/app.js` to avoid public analytics and marketing behaviors in Admin.
- Do not make future module discovery dynamic. A static list with permission checks is enough.

**Implementation steps**:

1. Build `resources/views/layouts/admin.blade.php` and shared static Blade navigation. Flux already supplies mobile-menu interactivity; an additional stateful Livewire shell is unnecessary. Self-host licensed Inter without adding a font package.
2. Add reusable feedback components/partials for save status, inline errors, destructive confirmation, conflict alert, and empty states.
3. Add responsive navigation with keyboard-visible focus, skip-to-main, and mobile drawer behavior.
4. Verify no marketing PostHog/booking/interaction initialization runs on Admin pages.

**Feedback loop**:

- **Playground**: Dev server on Admin host plus `AdminPublishingWorkspaceTest` assertions for rendered landmarks/no-data states.
- **Experiment**: Render with admission-only, publishing author with zero articles, publishing author with many ideas, mobile width, keyboard Tab/Escape navigation, and logout form.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/AdminPublishingWorkspaceTest.php && bun run build`

### 4. Publishing dashboard: Composer capture, Ideas, Active writing, Published separation

**Pattern to follow**: `flux:composer` stub supports `wire:model`; Phase 1 `WriteArticle`/`AdvancePublishingAttempt` actions.

**Overview**: The dashboard is the owner's creation workspace. Composer captures a simple idea; Develop idea creates/captures and deliberately starts the attempt; Save for later creates a passive idea without a running attempt. Ideas and Active writing are separate regions. Published is a separate route/list.

**Key decisions**:

- `Develop idea` is explicit authorization to start interview/brief work, but Phase 3 only transitions state and shows pending/wired-later surfaces; it must not claim an agent completed work.
- `Save for later` remains passive and must not consume budget or create a publishing attempt.
- Attention indicators are derived from real stage/approval/blocker state, not fake analytics.
- Dashboard cards may show article title/idea/status only to users with `publishing.view`.

**Implementation steps**:

1. Build dashboard SFC with `public string $idea`, validation, `developIdea()`, and `saveForLater()` methods.
2. Use Phase 1 actions for capture/develop and policy checks for view/write/develop.
3. Query Ideas as passive/unstarted or parked backlog; query Active writing as current attempts not abandoned/published.
4. Add separate `published` SFC reading only published release metadata.
5. Add empty states for no publishing permission, no ideas, no active writing, and no published releases.

**Feedback loop**:

- **Playground**: `AdminPublishingWorkspaceTest` plus local dashboard.
- **Experiment**: Empty idea, long idea, duplicate submit, Develop vs Save for later, revoked permission between render and action, many active articles, mobile cards.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/AdminPublishingWorkspaceTest.php`

### 5. Article workspace surfaces and human gates

**Pattern to follow**: Phase 1 approval actions; Phase 2 `App\Services\Publishing\ArticleDocument` validate/canonicalize/render.

**Overview**: The article workspace shows the actual editorial journey: capture context, brief/interview, human angle approval, human angle/plan gates, editing/protection, review choices, release checks, and exact approval. Provider, schedule, and agent services are wired in later phases; Phase 3 UI must show pending/unavailable/proposal states honestly.

**Key decisions**:

- Changes to existing prose are proposals with input revision IDs. A first draft may populate a still-empty untouched manuscript after plan approval, without a fourth mandatory gate. Accept/reject changes to existing prose through server actions with stale-input checks.
- Human gates are deliberate buttons/forms with digest/version checks, not client booleans.
- Release approval binds exact content and delivery intent from Phase 1/2; changing document/metadata invalidates approval and withdraws schedules per domain rules.
- No fake completion messages: labels should say “Queued later”, “Not wired yet”, “Proposal ready”, or real state from the database.

**Implementation steps**:

1. Compose article workspace panels for Brief/Interview, Angle, Plan, Manuscript, Reviews, Preview, and Release.
2. Bind approval buttons to Phase 1 `ApprovePublishingStage`/release actions with expected hash/revision.
3. Show missing-answer/blocker states and park/abandon actions without dispatching provider jobs.
4. Provide review-choice UI that can display proposal records when Phase 4 creates them; until then show empty/pending states only.
5. Ensure imported published articles open with historical published state but require normal gates for new replacement attempts.

**Feedback loop**:

- **Playground**: `AdminPublishingEditorTest` with existing Phase 1/2 stage, approval and imported-release factories. Proposal/evidence model factories arrive in Phase 4; do not require those nonexistent classes to make this phase pass.
- **Experiment**: Approve angle then edit brief; verify approval invalidation and live-version isolation; exercise disabled/pending review and delivery surfaces honestly. Phase 4 tests actual proposal acceptance; Phase 5 tests final release readiness.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/AdminPublishingEditorTest.php tests/Feature/Publishing/EditorialWorkflowTest.php tests/Feature/Publishing/ReleaseIntegrityTest.php`

### 6. Continuous editor, semantic blocks, autosave, conflicts, and recovery

**Pattern to follow**: Flux Pro editor hook implementation in `vendor/livewire/flux-pro/dist/editor.js`; Phase 2 document API.

**Overview**: Build one continuous manuscript editor with Markdown shortcuts and supported semantic blocks. The editor hydrates from canonical JSON, validates/canonicalizes through `ArticleDocument`, saves with expected revision and client mutation IDs, and surfaces conflicts without overwriting unsaved local text.

**Key decisions**:

- Use `document.addEventListener('flux:editor', ...)` to call `registerExtensions/registerExtension` and `init`; use `flux:editor:ready` detail `{ editor }` for hydration and event binding.
- Match Phase 2 Tiptap-native node/mark names exactly, with `attrs.id`/`attrs.protected` and link `attrs.href`. Strip only the outer document `version` for Tiptap hydration and restore it on serialization. Preserve prose, HR/code/list nodes, notes/callouts, charts and preset/restricted-SVG-source diagrams; normalize supported default/null attrs consistently.
- Unsupported nodes, unsafe URLs, invalid charts, executable markup, and unsafe diagram source are server validation errors with actionable messages.
- Autosave is serial and debounced. It includes expected revision and mutation ID; no overlapping saves, silent retries, or silent conflict overwrite.
- Use `sessionStorage` for tab-scoped recovery keyed by authenticated user ID and article ID, with base revision and schema version. Reuse the key across reloads; do not generate a fresh unreadable namespace on each mount. Show recovery choices, clear only the acknowledged saved payload, and clear this user's recovery namespace on logout. If newer edits exist when a save returns, keep them and their recovery buffer.
- Add `beforeunload` only when local unsaved changes exist.
- Editor CSS must isolate diagram whitespace and list styles; the prototype exposed defects where embedded figures inherited prose whitespace/list rules.

**Implementation steps**:

1. Implement editor extensions and commands for note, callout, chart, diagram, protected passage metadata, and block controls. Allocate stable node IDs once and write them into editor state, never regenerate IDs on every serialization; pasted clones receive new IDs. Support Phase 2's HR/code/list nodes through existing or narrowly defined core extensions, without unapproved extra packages.
2. Hydrate editor JSON from `ArticleRevision.document` after `flux:editor:ready`; do not hydrate from rendered HTML.
3. On editor update, canonicalize locally where possible, enqueue serial debounced save, and show Saved/Unsaved/Saving/Error/Conflict. One save coordinator submits document and metadata together; independent Livewire metadata saves must not race the editor queue. Keep the editor DOM in wire:ignore and preserve focus/selection; tear down event listeners on component removal.
4. Livewire save action calls `ArticleDocument::validate`, `canonicalize`, and `renderHtml`/preview API as defined by Phase 2 before invoking Phase 1 write action.
5. On conflict, keep local editor content intact, show the authoritative latest revision metadata, and offer explicit recovery/copy/compare restart choices. Do not auto-merge unless separately specified later.
6. Add CSS scope such as `.admin-editor [data-article-diagram]` and `.admin-editor [data-article-figure]` to reset whitespace/list styles inside diagrams/figures.

**Feedback loop**:

- **Playground**: Bun pure-helper tests for JS normalization/recovery plus Livewire editor feature tests; local browser for Flux editor integration.
- **Experiment**: Reload with unsaved changes, failed save then retry, two-tab stale save, protected paragraph edit proposal, chart invalid value, diagram whitespace/list rendering on desktop/mobile.
- **Check command**: `bun test resources/js/admin/publishing/document-helpers.test.js && php artisan test --compact tests/Feature/Publishing/AdminPublishingEditorTest.php tests/Feature/Publishing/DocumentRoundTripTest.php`

### 7. Public preview and release checks

**Pattern to follow**: Phase 2 `ArticleDocument::renderHtml` output and existing public article route/view expectations.

**Overview**: Preview uses the same server renderer as public delivery, inside Admin chrome. Release checks display real readiness state from domain data: exact release approval, metadata, unresolved material blockers, links/assets, schedule intent, and live-version isolation.

**Key decisions**:

- Preview is not persistence. It renders canonical JSON and metadata from the selected saved revision through `ArticleDocument::renderHtml`. Display an authenticated preview URL in an iframe with `sandbox="allow-same-origin"` and no script permission, using marketing CSS/Barlow without marketing JavaScript/analytics. Send private no-store/noindex headers; authorize the selected revision against its article. Admin chrome stays Inter.
- Release checks do not claim publication/schedule completion in Phase 3 unless Phase 5 delivery exists.
- Editing approved release content invalidates approval and schedule status in the UI immediately after server response.

**Implementation steps**:

1. Add preview panel/action that requests rendered output from `ArticleDocument` for the current canonical JSON.
2. Show validation errors near the responsible block and in the shared feedback region.
3. Display release checklist and exact approval button only when Phase 1/2 data says it is eligible.
4. Keep Published list reading `published_release_id`, not latest working revisions.

**Feedback loop**:

- **Playground**: `AdminPublishingEditorTest` and manual Admin preview.
- **Experiment**: Valid document, invalid chart, unsafe URL, edited approved release, published article with newer draft, mobile preview.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/AdminPublishingEditorTest.php tests/Feature/Publishing/DocumentRoundTripTest.php`

## Data Model

No new publishing domain tables are planned for Phase 3. Use Phase 1 models (`Article`, `ArticleRevision`, `PublishingAttempt`, `EditorialApproval`, `ArticleRelease`) and Phase 2 document records/fields exactly as implemented.

Client-only/editor state shape must remain recoverable and non-authoritative:

```ts
type SaveState = 'saved' | 'unsaved' | 'saving' | 'error' | 'conflict';

type RecoveryPayload = {
  articleId: number;
  baseRevisionId: number | null;
  mutationId: string;
  document: { version: 1; type: 'doc'; content: unknown[] };
  metadata: Record<string, unknown>;
  savedAtClient: string;
};
```

Store recovery data in `sessionStorage` with a tab-specific key. Never write recovery data to localStorage or treat it as server truth.

## API Design

Phase 3 uses standard web routes and Livewire actions, not JSON API endpoints and not Folio pages.

| Method | Route name/path | Description |
| --- | --- | --- |
| `GET` | `admin.index` `/` | Shared Admin landing; admission-only safe state. |
| `GET` | `admin.publishing.dashboard` `/publishing` | Composer, Ideas, Active writing for authorized publishing users. |
| `GET` | `admin.publishing.published` `/publishing/published` | Published CMS releases list. |
| `GET` | `admin.publishing.articles.show` `/publishing/articles/{article}` | Article workspace/editor/preview/release UI. |
| `POST` | Fortify `/login` | Existing-account login with intended redirect handling. |
| `POST` | Fortify `/logout` | Logout from Admin. |
| `GET/POST` | Fortify `/two-factor-challenge` | Existing 2FA challenge where applicable. |

Livewire action payload contracts must include server-checked versions:

```php
saveDocument(
    int $articleId,
    ?int $expectedRevisionId,
    string $clientMutationId,
    array $document,
    array $metadata,
): void
```

Server response/state must expose the authoritative revision ID/hash on success and conflict details on mismatch. The client must not replace unsaved text with server content unless the user chooses to discard/reload.

## Testing Requirements

### Feature and Livewire tests

| Test file | Coverage |
| --- | --- |
| `tests/Feature/Publishing/AdminPublishingWorkspaceTest.php` | Admin route admission, admission-only no-data state, publishing dashboard access, Composer validation, Develop vs Save for later, Ideas/Active split, Published separate route. |
| `tests/Feature/Publishing/AdminPublishingEditorTest.php` | Article workspace authorization, save CAS, mutation ID reuse/collision, validation errors, conflict responses, protected passage/proposal UI contracts, gate buttons, release invalidation display. |
| `tests/Feature/Auth/AdminFortifyFlowTest.php` | Existing-account login/logout, safe intended redirects, Admin host/local host behavior, invalid credentials, 2FA challenge when configured. |
| Existing Phase 1/2 tests | Re-run `EditorialWorkflowTest`, `ReleaseIntegrityTest`, `PublishingAuthorizationTest`, and `DocumentRoundTripTest` where UI calls their actions/services. |

### Bun helper tests

| Test file | Coverage |
| --- | --- |
| `resources/js/admin/publishing/document-helpers.test.js` | Canonical editor helper behavior, recovery serialization, unsupported node guard helpers, mutation ID behavior, beforeunload predicate. |

Use Bun for pure JS helper tests only. No new browser-test dependency is required or authorized. If the implementer uses existing browser tooling for integration checks, record it as manual/integration verification, not as a new acceptance dependency.

### Manual integration checks

- [ ] Desktop: login, dashboard, develop idea, open active article, edit, see save states, reload after saved state.
- [ ] Desktop: failed save/retry, stale two-tab conflict, recovery from reload with unsaved changes, beforeunload prompt.
- [ ] Desktop: keyboard-only navigation through shell, Composer, editor toolbar/block controls, proposal decisions, approval buttons, modals/drawers, logout.
- [ ] Mobile: dashboard Ideas/Active, article workspace panels, editor controls, conflict/recovery UI, release checklist.
- [ ] Editor: notes, callouts, chart, diagram, protected passage, list inside/near diagrams, whitespace isolation, invalid block errors.
- [ ] Authorization: admission-only user sees Admin shell but no editorial data; direct publishing URLs/actions fail closed.

Do not claim markup tests prove UX. Real desktop/mobile keyboard and reload/error/conflict scenarios must be checked by a human/operator.

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Guest requests Admin URL | Redirect through Fortify login and preserve safe intended Admin path only. |
| Admission-only user opens publishing URL | Return 403 or safe Admin index; do not show article names/counts/status. |
| Permission revoked after render | Livewire action re-authorizes and returns 403/error toast without mutating. |
| Save validation fails | Keep editor content, show field/block-specific errors, leave revision unchanged. |
| Save conflicts with newer revision | Mark conflict, preserve local content/recovery payload, show latest server revision metadata and explicit choices. |
| Network/server save failure | Mark error, keep unsaved state, keep recovery payload, allow retry; no fake saved message. |
| Duplicate mutation ID same payload | Treat as idempotent success and return existing authoritative revision. |
| Duplicate mutation ID different payload | Conflict; do not mutate. |
| Flux editor extension unavailable | Disable editor save/publish controls, show actionable dependency/configuration error, do not fall back to lossy HTML persistence. |
| Tiptap package not approved/installed | Stop implementation of dependent extension imports and document approval requirement; do not add unpinned or transitive alternatives. |
| 2FA required | Show existing Fortify challenge; do not bypass to Admin index. |
| Unsafe intended redirect | Drop intended target and route to safe Admin fallback. |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Routing | Folio accidentally owns Admin path | Admin page placed under `resources/views/pages` or Folio config changed | Auth/app middleware boundaries bypassed or route conflict | Standard routes only; tests assert route names/middleware and Folio remains marketing-only. |
| Shell/navigation | Publishing coupling | Shell queries articles for all Admin users | Admission-only data leak and poor future module fit | Shell accepts navigation items/status from authorized components only; no editorial queries in shared shell. |
| Auth redirects | Open redirect | External intended URL before login | Account/session phishing risk | Validate intended host/path and fallback to `admin.index`. |
| Dashboard | Develop double-submit | User clicks twice or request retries | Duplicate attempts/budget identity | Domain idempotency/locks; disable loading button; test duplicate action. |
| Composer | Passive idea starts work | Save for later calls develop action | Unwanted agent/budget workflow | Separate action methods and tests for no attempt created. |
| Editor persistence | Lossy HTML path | Reading `innerHTML` or public preview HTML for save | Block IDs/protection/charts/diagrams lost | Save only canonical JSON through `ArticleDocument`. |
| Editor extensions | Custom blocks dropped | Flux editor loads without registered extensions | Notes/callouts/charts/diagrams disappear | Register extensions before editor creation; fail visibly if unsupported nodes encountered. |
| CSS | Diagram/list contamination | Prose typography styles cascade into SVG/source diagrams | Broken visual meaning, prototype defect repeats | Scoped resets and manual desktop/mobile checks. |
| Autosave | Parallel saves arrive out of order | Debounce fires while previous save pending | Older content overwrites newer prose | Serial queue, expected revision, mutation IDs, no silent conflict overwrite. |
| Recovery | Cross-tab stale recovery | localStorage/global key reused | Wrong article/tab content offered | `sessionStorage` tab-scoped keys with article/revision metadata. |
| Proposal UI | Agent text appears accepted | Proposal rendered inline as final prose | Authorship/control violation | Distinct proposal cards/diffs; accept action required and version-checked. |
| Release UI | Fake completion | Schedule/provider not wired but UI says published/scheduled | Owner trusts false state | Honest pending/not-wired labels until Phase 5 services exist. |
| Tests | Markup-only confidence | Feature tests assert strings but no browser task review | Broken keyboard/mobile/editor UX ships | Required manual desktop/mobile/keyboard/reload/error/conflict checklist. |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Auth/AdminFortifyFlowTest.php tests/Feature/Publishing/AdminPublishingWorkspaceTest.php tests/Feature/Publishing/AdminPublishingEditorTest.php
php artisan test --compact tests/Feature/Publishing/EditorialWorkflowTest.php tests/Feature/Publishing/ReleaseIntegrityTest.php tests/Feature/Publishing/PublishingAuthorizationTest.php tests/Feature/Publishing/DocumentRoundTripTest.php
bun test resources/js/admin/publishing/document-helpers.test.js
bun run build
vendor/bin/pint --dirty --format agent
composer types:check
```

If production Tiptap dependencies are explicitly approved, add only the two pinned packages and commit the resulting lockfile. Without approval, pause rather than improvise a lossy alternative. Use the commit skill when available and include `docs/ideation/2026-09-22-agent-publishing-studio/spec-phase-3.md` verbatim in the phase commit body.

## Rollout and Boundaries

This phase is local/Admin-workspace implementation only. It does not provision Nick's role, import production data, deploy to production, install unapproved dependencies, call providers, schedule real publication, or cut over public Writing/RSS/sitemap. The operator bootstrap from Phase 1 remains a later explicit action: run `authorization:sync`, identify Nick's existing account with approval, assign catalog roles deliberately, then verify sign-in.

Future Admin modules should reuse the shell/navigation/feedback components by editing static code, not by adopting a generic plugin framework. Phase 4 wires real agent/evidence/review services; Phase 5 wires delivery/cutover. Until those phases exist, Phase 3 UI must avoid fake completion and keep all agent outputs as proposals or pending states.
