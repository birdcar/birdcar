# Implementation Spec: Agent Publishing Studio — Phase 5

**Contract**: ./contract.md\
**Phase**: Release delivery and public cutover\
**Estimated effort**: L
**Prerequisite**: Agent development, evidence and bounded reviews

**Controller artifact boundary**: Preserve existing `run-*.json`, generated `run-*.html` and approved planning artifacts. They record historical runs and must not be deleted or rewritten when their findings are fixed. Leave new controller reports outside this phase's commit if unrelated; do not remove them to clean the diff. Reuse the verified Herd hosts/local Admin configuration; do not start another PHP server or change the environment.

## Technical Approach

Complete the concrete `Article` / `ArticleRevision` / `PublishingAttempt` / `EditorialApproval` / `ArticleRelease` domain already delivered. `ManageArticleRelease` must freeze and deliver the owner's exact approved package, never read the latest draft at delivery time. Use the existing Laravel scheduler and transactional database; a periodic due-release command makes recovery independent of one delayed queue message surviving. Queue delivery is at-least-once; the database transition is idempotent.

Add a CMS public reader used by Writing, article pages, RSS and sitemap together. Preserve Folio on the marketing host and all original public URLs, metadata, dates, link destinations and content meaning. Admin remains standard Laravel routes. Keep the old `ReadWriting` reader only for baseline import and an explicit temporary pre-launch fallback; do not leave a silent per-article fallback that mixes unpublished drafts or mismatched sources into the public archive.

Before changes, read `.ai/rules/{general,services,resources,writing}.md`, activate Laravel/Folio/testing skills and inspect installed versions. No package installation, production deployment, account provisioning or paid inference is implied. Fake external calls in automated tests. After request/database changes, use available Lerd traffic/query diagnostics on the affected local routes; never claim performance coverage from markup inspection alone.

## Decisions Considered and Rejected

- **Approve the exact release** — rejected treating an approved draft or model verdict as authorization to publish mutable content.
- **Published content stays live during revision** — rejected switching public reads to a working revision or hiding the old release.
- **Full archive migration** — rejected legacy read-only/on-demand imports; the temporary reader switch is a rollout mechanism, not a reduced final scope.
- **Original source preservation** — rejected modifying source files to make parity assertions pass; compare to pre-project commit `72f7d8ad8521573cb224022c902447f9ca4c4351`.
- **Material facts and safety block release** — rejected blanket overrides of known unsupported claims, invented experience, confidentiality/rights issues or invalid release integrity. Reasoned false-positive resolution is permitted; style and discovery are advisory.
- **Publishing ends at publish/schedule** — rejected automatic distribution, generated images, marketing redesign and deployment as hidden additions.
- **Explicit routing boundaries** — rejected Folio for the Admin app; retain Folio only for public marketing pages and standard infrastructure routes for feed/sitemap.
- **Owner acceptance remains separate** — rejected green tests as proof of voice, usability or launch approval. Skipped/incomplete Publishing tests must fail acceptance.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/ReleaseIntegrityTest.php`\
**Playground**: Release feature tests with a fixed clock, fake external requests and factory users/releases, plus the real local marketing site for rendered parity.
**Why**: Deterministic state tests catch stale delivery; browser checks catch visual/content losses they cannot detect.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `app/Actions/Publishing/CheckArticleRelease.php` | Current-revision readiness, link and asset findings |
| `app/Actions/Publishing/ReadPublishedWriting.php` | Published-only public projections and explicit rollout-source selection |
| `app/Console/Commands/PublishDueArticles.php` | Idempotent scheduled-release delivery and recovery |
| `config/publishing.php` | Explicit files/database public-reader cutover setting |
| `tests/Feature/Publishing/ReleaseReadinessTest.php` | Material blockers and package completeness |
| `tests/Feature/Publishing/PublicWritingTest.php` | Public visibility and atomic cutover boundaries |

### Modified Files

| File Path | Changes |
| --- | --- |
| `app/Actions/Publishing/ManageArticleRelease.php` | Complete prepare/approve/publish/schedule/withdraw integration |
| `app/Actions/Publishing/ApprovePublishingStage.php` | Require current release readiness for exact approval |
| `app/Actions/Publishing/WriteArticle.php` | Preserve release invalidation on every release-affecting mutation |
| `app/Models/ArticleRelease.php` | Delivery/check casts or scopes where needed |
| `.env.example` | Document public-reader switch without changing live environment files |
| `routes/console.php` | Register the due-release schedule |
| `routes/web.php` | Feed/sitemap consume published-only reader; preserve host boundaries |
| `resources/views/pages/writing/index.blade.php` | Use the published-only public projection |
| `resources/views/pages/writing/[slug].blade.php` | Render frozen safe public HTML and metadata |
| `resources/views/writing-feed.blade.php` | Use published release projections and safe XML escaping |
| `resources/views/components/admin/publishing/⚡article-workspace.blade.php` | Wire release checks, approval and delivery to concrete actions |
| `tests/Feature/Publishing/ReleaseIntegrityTest.php` | Scheduler/concurrency/live-version integration cases |
| `tests/Feature/Publishing/ArchiveMigrationTest.php` | Verify imported releases through public reader after cutover |
| `tests/Feature/MarketingSiteTest.php` | Preserve existing requirements using imported test records in CMS mode |
| `tests/Feature/MarketingDiagramTest.php` | Preserve static/accessible figure assertions in CMS mode |
| `tests/Feature/MarketingDiscoveryTest.php` | Preserve canonical/feed/sitemap behavior in CMS mode |

### Deleted Files

None. Preserve original archive Markdown/JSON and all existing tests; extend the existing Phase 3 workspace, never create a second one.

## Implementation Details

### 1. Release checks and exact package

**Pattern to follow**: `tests/Feature/MarketingDiagramTest.php`, `app/Services/MarketingSite.php` and Phase 1's release action.

`CheckArticleRelease` evaluates an actor-authorized, specific current revision with its attempt and review/evidence manifest. It returns structured findings with severity, location, resolution and the checked input hash. It does not return an unexplained model boolean. Deterministic checks cover required title/description, unique immutable public slug, public date, supported document/schema, safe links, valid charts/diagram source and complete renderable assets. Reuse `ArticleDocument` validation/rendering from Phase 2.

Editorial blockers include unresolved material unsupported claims, invented experience/details and source publication restrictions. The three review lenses and human dispositions come from Phase 4. A model's citation is not supporting evidence by itself. Treat an external link timeout as unknown, not automatically as a false claim; record it for resolution using supporting evidence or removal. Do not silently rewrite original archived link destinations because an old URL is now unavailable. Historical imports are baseline snapshots, not retrospectively re-approved manuscripts.

A blocking finding can be resolved by revision/removal, supporting evidence, or an explicit reasoned false-positive disposition. A bare “publish anyway” switch may not clear real unresolved factual/rights issues. Advisory voice/discovery suggestions remain nonblocking. Missing/stale required checks and still-running review work cannot be treated as success.

Prepare a release from the exact current revision after the owner selects publish-now or schedule intent. Freeze document, safely generated HTML, title/description/tags, public date, slug, content schema/render version, evidence and review-disposition identifiers/hashes, and delivery intent in `ArticleRelease.payload`; hash all release-affecting inputs. Evidence excerpts and private notes stay private even if their hashes are in the package. Only public projections are rendered externally.

The release approval action authorizes `publishing.approve`, checks the displayed hash against current server state, and records the human actor. Publishing/scheduling separately requires `publishing.publish`. A change to any frozen input invalidates the old approval and schedule; schedule changes also require a newly approved package. Recomputing HTML using a later renderer is not a way to silently modify an approved release: prepare a new package.

**Feedback loop** — Playground: release readiness tests with prepared drafts and specific findings. Experiment: missing caption, invalid SVG, stale evidence hash, advisory-only finding, real rights blocker and justified false positive. Check: `php artisan test --compact tests/Feature/Publishing/ReleaseReadinessTest.php`.

### 2. Publish, schedule, withdraw and retry

Operate under short transactions locking article then attempt/release consistently. Verify the approval digest, exact revision, current readiness, chosen delivery intent, expected previous live-release pointer, and current actor permissions. Set the article's live pointer and release publication state atomically. Initial publication establishes the original public date; later revisions retain it. Return the existing successful result for an identical duplicate delivery.

Use `publishing:publish-due` with a bounded batch query indexed by status/scheduled instant. Register `Schedule::command('publishing:publish-due')->everyMinute()->withoutOverlapping()`. Database invariants remain authoritative even if overlapping schedulers run; add `onOneServer()` only when a suitable shared cache is confirmed. Persist UTC, display the owner's selected zone and offset in confirmation, and reject invalid/past schedule input rather than silently changing it.

Due delivery rechecks authorization for the stored approving/publishing actor and all version/approval conditions. A withdrawn/stale release is not delivered. A temporarily unavailable database produces retryable failure without changing live content. Recovery scans find still-due releases after worker restarts; no timestamp-only claim that “the job was queued” counts as publication. Scheduled edits withdraw the old delivery inside the same save transaction. An older release must not displace a newer live version when retried.

Human actions expose actionable success/paused/conflict/failure states and the exact live revision identity. Disable real delivery while the public reader is still in file mode, so “Published” cannot mean invisible CMS-only data.

**Feedback loop** — Playground: fixed-clock release tests and command invocation via the test harness. Experiment: before/at/after due time, duplicate workers, revoked actor, missed scheduler interval, save while due and older job after newer publish. Check: `php artisan test --compact tests/Feature/Publishing/ReleaseIntegrityTest.php`.

### 3. Published-only public projections

**Pattern to follow**: `app/Actions/ReadWriting.php`, `resources/views/pages/writing/[slug].blade.php`, `resources/views/writing-feed.blade.php` and existing discovery tests.

`ReadPublishedWriting::all()` returns public article projections sorted by original public date and stable slug; `find(string $slug)` selects only an article with a delivered published release. Projection fields are slug, title, description, date, tags, trusted rendered HTML and reading minutes. Never serialize the whole Article/Attempt/Release/Evidence model into a page or feed. Eager-load the actual published snapshots and avoid one query per article/figure/source. No request-specific singleton/static cache under Octane.

Public views render only HTML generated and frozen by the allowlisted document renderer. An authorized preview uses that same renderer without selecting the public live pointer. Unpublished articles are absent from listing, RSS, sitemap and direct URLs; working titles/metadata/figures cannot leak into published surfaces. Public canonical URLs continue to come from `MarketingSite` / `config('marketing.url')`, not the incoming host or Admin host. Keep existing trailing slash conventions, robots/indexing behavior, author mention destinations and structured `BlogPosting` publication date.

Provide a narrow `publishing.public_reader` setting (`files` before cutover, `database` after) for the actual migration/rollback need, not a plugin interface. In files mode, explicitly map the existing legacy reader into the same public projection. In database mode, missing/unpublished records do not fall back individually to files. RSS/sitemap/pages all use this single selection. Test CMS mode explicitly; tests that merely exercise legacy fallback do not satisfy acceptance.

**Feedback loop** — Playground: existing public feature suites with importer-backed fixtures and draft/private canaries. Experiment: ten baseline essays, a draft-only slug, a published essay with a secret working title, changed published revision, Admin/tenant hosts and malformed feed text. Check: `php artisan test --compact tests/Feature/Publishing/PublicWritingTest.php tests/Feature/MarketingDiscoveryTest.php`.

## Testing Requirements

| Test | Coverage |
| --- | --- |
| `ReleaseReadinessTest.php` | Real blockers versus advisory findings, stale checks, safe assets, reasoned resolution, exact package integrity |
| `ReleaseIntegrityTest.php` | Duplicate delivery, scheduled edit invalidation, actual live-pointer isolation, missed run recovery, revoked actor, time-zone confirmation |
| `PublicWritingTest.php` | No draft/source-note leaks through article/list/RSS/sitemap, no per-article fallback, reader-mode delivery guard |
| `ArchiveMigrationTest.php` plus existing public suites | Complete imported archive metadata/text/link/figure parity in CMS mode, repeat-import safety, source files unchanged |

Use `Http::preventStrayRequests()` and fakes. Test actual concurrency with a nonproduction transactional database when available; SQLite-only tests prove stale checks but not row-lock behavior. Do not substitute skipped tests for missing concurrency evidence: report the limitation at the owner gate.

Manual review covers desktop/mobile, no JavaScript, reduced motion and print for all special block types against originals; keyboard operation of static figure controls remains intact. Use the already-running Herd site; do not start a competing PHP server. Build changed assets with `bun run build` and resolve the actual local URL before sharing it. Public pages retain the marketing design; the Admin shell retains Flux/Inter.

## Failure Modes

| Component | Failure / trigger | Impact | Mitigation |
| --- | --- | --- | --- |
| Readiness | Evidence/figure/check snapshot changes after approval | Inexact publication | Bind checks and approval to release hash; withdraw on change |
| Scheduler | Lost tick, duplicate worker or restarted process | Missed or repeated publish | Periodic durable scan and transactional idempotency |
| Publication | Old due release runs after newer release | Public regression | Compare expected prior live pointer and current approvals |
| Public reader | Draft attributes used on listing/feed | Private content leak | Explicit published-only projections and canary assertions |
| Cutover | Import incomplete or DB unavailable | Missing archive | Parity prerequisite, explicit whole-reader switch, no silent mixed fallback |
| Rollback | Switching back to files after new CMS publications | New content disappears | File rollback allowed only before first CMS-native delivery; thereafter restore compatible code/DB snapshots |
| Figures | New renderer differs from approved rendering | Changed release meaning | Freeze public HTML; new rendering requires a new approved release |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/ReleaseReadinessTest.php tests/Feature/Publishing/ReleaseIntegrityTest.php tests/Feature/Publishing/PublicWritingTest.php tests/Feature/Publishing/ArchiveMigrationTest.php
php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiagramTest.php tests/Feature/MarketingDiscoveryTest.php
vendor/bin/pest --compact --fail-on-skipped --fail-on-incomplete tests/Feature/Publishing
vendor/bin/pint --dirty --format agent
composer types:check
bun run build
git diff --exit-code 72f7d8ad8521573cb224022c902447f9ca4c4351 -- resources/writing
```

Run the contract-wide acceptance commands after integration. Ask the owner to run `php artisan test --compact` for complete-suite confirmation. The phase commit body includes `docs/ideation/2026-09-22-agent-publishing-studio/spec-phase-5.md` verbatim; no deployment or owner editorial acceptance is inferred from that commit.

## Cutover and Recovery Procedure

1. In a confirmed local/staging environment, verify migrations, backups and original archive baseline. Never use `migrate:fresh` against existing application data.
2. With explicit operator authorization, execute the importer dry run, review its complete archive report, then import. Existing imported records are not overwritten. No seed/boot side effects.
3. Run public suites in database mode and compare rendered archive cases to the file-backed originals. Fix losses before switching the real local reader.
4. Select database mode as one whole-reader change; verify article URLs, Writing, feed and sitemap together. Keep actual publication disabled until this succeeds and the owner account is provisioned.
5. Before any CMS-native publication, the explicit files-mode fallback is reversible and original files remain intact. After new CMS releases exist, do not use file fallback as a lossless rollback: preserve DB/releases and revert to compatible code or restore a coordinated backup with owner approval.
6. Production rollout remains a separate authorized operation. Phase 6 contains the owner-controlled live editorial/usability acceptance, not an automatic deploy.
