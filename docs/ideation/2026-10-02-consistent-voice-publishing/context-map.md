# Context Map: Consistent Voice Publishing

**Phase**: 7 (Publishing board)
**Gates**: 5/5 ready
**Verdict**: GO

## Gates (Phase 7)

| Gate                 | Status | Evidence |
| -------------------- | ------ | -------- |
| Scope clarity        | ready  | The spec names 5 new files, 4 modified files and 1 deleted file. This map adds the files it misses and the change each needs: (1) `app/Actions/Publishing/AdvancePublishingAttempt.php` has no `unpark()` (only `park` 92-100 and `abandon` 102-111), and neither method withdraws a scheduled release; (2) `MoveResult` needs its own file, because the spec sketches it inline; (3) the tests that drive the deleted component: `AdminPublishingWorkspaceTest.php:139-439` (component name, composer markup, "Active writing", per-list pagination, "published is listed separately"), `ContentIdeaTest.php:255-269`, `EditorialInputContractTest.php:491-505` and `PublishingJourneyTest.php:124` (which asserts the "Publishing workspace" heading); (4) `⚡index.blade.php` already titles items by metadata or the labeled idea (88-92, 117-121) and already links `admin.publishing.dashboard` (59, 70, 106, 130), so its change is copy only; (5) optionally `resources/views/layouts/admin.blade.php` for `<flux:toast />`, since no toast exists anywhere today. |
| Pattern familiarity  | ready  | Read the deleted `⚡dashboard.blade.php` in full: composer actions 26-60, queries 62-86, status and activity labels 105-168, and markup 172-282. Also read `AdvancePublishingAttempt`, the workspace `startDevelopment` (123-137), and the strongest eager-load precedent, `ReadAdminHome.php:38-77`, with its decision rules 105-125. Also read the Flux Pro 2.20 kanban stubs, the Livewire 4.4.6 `wire:sort` source (`livewire.esm.js` ~17090-17140), the `flux:modal wire:model.self` precedent (`⚡security.blade.php:456-469`), the session poll (`session.blade.php:56`), the release checklist, `motion.js`, and the `admin.css` publishing block (51-140) and reduced-motion block (281-285). |
| Dependency awareness | ready  | The route name `admin.publishing.dashboard` is linked from `layouts/admin.blade.php:34`, `navigation.blade.php:7`, `session.blade.php:4` and `⚡index.blade.php` (4 places); keeping the name keeps them working. 8 tests in 3 files use the component name `admin.publishing.dashboard`, and 2 more files GET `/publishing`. `motion.js:12,60,184-186` and `admin.js:26` read `data-publishing-studio`, `publishingArrivals` and `data-publishing-enter`. Nothing in the app calls `park` or `abandon` (only EditorialWorkflowTest:424-425,454 and ContentIdeaTest:211). Every agent and release action already refuses a parked attempt: StartEditorialActivity:58, RunEditorialActivity:249,381, ApprovePublishingStage:325, ManageArticleRelease:514,644 and RecoverEditorialActivities:29. |
| Edge case coverage   | ready  | The list below covers: column precedence (killed before parked); archive imports; published pieces with a new attempt; the literal Writing rule, which flips a piece back on every autosave; park and kill on Ideas and Published; scheduled releases when a piece is parked; unparking with activities the worker paused; interviews waiting for answers; the global agent pause; how to detect package acceptance; same-column reorders; card-id scoping; the `wire:sort` attribute order; polling during a drag; the idea-label test; ten-column width; and unbounded Ideas and Killed columns. |
| Test strategy        | ready  | Commands, helper prefixes, factories, the query-count precedent (AdminHomeTest:270-285) and the tests to repoint are below. `birdcar_test` and `birdcar_development` were confirmed on 127.0.0.1:5433 with `psql -l` this session. Versions: Laravel 13.33.0, Livewire 4.4.6, Flux and Flux Pro 2.20.0. The scout did not run the suite. |

### Phase 9 gates (reference)

| Gate                 | Status | Evidence |
| -------------------- | ------ | -------- |
| Scope clarity        | ready  | The spec names 8 new and 4 modified files. This map adds the rest and the change each needs: (1) `EditorialOutput.php:24,46`: a `VOICE_FINDING_KINDS` constant and a ReviewVoice validator arm, if the voice `kind` becomes an enforced enum (the validator-to-schema audit, contract line 154); (2) `ReviewRubricTest.php:167-170`: `reviewRubricFinding` sends voice findings with no `kind` (used at 187 and 215), so it goes red under an enforced enum; (3) `EditorialAgentsTest.php:100-121`: add a VoiceReviewer `kind` assertion, because no spec test covers the kinds change; (4) optionally `tests/Fixtures/Publishing/DriftGoldenSet.php` (namespace `Tests\Fixtures\Publishing`, precedent `tests/Fixtures/Mail/*.php`), so DriftFixtureTest and the trial build and lint passages the same way; (5) the shared review arm in `EditorialAgent::schema` 271-273 must split for ReviewVoice, because `EditorialAgentsTest:113` pins the shared description to exactly 'At most 25 findings.'. Every other file and its change is concrete in the spec. |
| Pattern familiarity  | ready  | Read the spec's pattern `tests/Manual/PublishingModelTrialTest.php` in full: the guard at 85-100 already requires pgsql and birdcar_test; prepare 102-124; exchanges 485-511; record 531-538; run 417-427. Also read the only job (`app/Jobs/IndexPublishedExemplar.php`), the commands `PublishingCanonHash`, `PublishingIndexExemplars` and `PublishingImportArchive` (JSON output), the Phase 6 migration, the immutability guards in `ArticleRevision` and `ArticleRelease`, the fixed finding-kind pattern (`EditorialAgent::finding($schema, $kinds)` 421-435 and `EditorialOutput::validateKitFindings`), and the retrieval-then-voice-review chain in `ExemplarRetrievalTest.php:183-226,318-364`. |
| Dependency awareness | ready  | `WriteArticle::save` has 10 callers. Four save as `human`: the workspace `saveDocument`, `saveDetails`, `saveKitFields` and the protection toggle. Six save with agent origins: RunEditorialActivity 625, 651 and 1285, ApplyEditorialProposal 107, AcceptPackageProposal 77 and SpawnDistributionKit 135. Under `QUEUE_CONNECTION=sync` the new job runs inline at commit in every Feature test that saves as a human after an agent revision. The tests that assert nothing was pushed or dispatched (AdminPublishingEditorTest 467/506/535/546, AdminHomeTest:84, PublishingAgentSettingsTest:427) save only first revisions or nothing, so they stay green. No app code hashes whole revision rows. Only `RunEditorialActivity::storeFindings:914` reads the voice finding `kind` (default `editorial`). The canon edit changes the version that every new activity freezes, which is expected. |
| Edge case coverage   | ready  | The list below covers: passage lint false positives (`walkthrough-link` hits the last block of any bare passage; `title-sentence-case` hits a Title Case title); catches that come from lint alone; the reviewer seeing the real passages in its own exemplars; findings caused by reviewing an excerpt as if it were a whole post; retrieval, which needs query text and a plan approval made while unpaused; failure recording with `max_retries` 0; the canon-hash source after Phase 8; autosave granularity; mutation-id replays; which origins count as agent; job idempotency; guard semantics; tokenization; prefix and suffix trimming; several distances on one piece; and a missing idea. |
| Test strategy        | ready  | Commands, fixtures, fakes and setup are below. `birdcar_test` and `birdcar_development` were confirmed on 127.0.0.1:5433 with `psql -l` this session; Laravel is 13.33.0. The scout did not run the suite. |

### Phase 6 gates (reference)

| Gate                 | Status | Evidence |
| -------------------- | ------ | -------- |
| Scope clarity        | ready  | The spec names most files. This map adds the ones it misses and the change each needs: (1) `EditorialOutput.php:31-47,79-92`, the exhaustive `validate`/`allowedKeys` arms (DraftKit per piece kind, ReviewKit findings); (2) `LintRevision.php:206-219`, where `finishAndQueueReviews` starts the three lenses, and `skipRepair` (221-262), which looks only for a Draft; (3) `Lint/Rules/VoiceRule.php:12-15`, the one `appliesTo()` every Phase 3 and Phase 4 rule inherits; (4) every exhaustive `match` on EditorialActivityKind and ApprovalKind, including `admin/settings/⚡publishing.blade.php:213,230`; (5) LintRepair is tied to Plan approval (`StartEditorialActivity:374-394`, `RunEditorialActivity:1460-1467`), so kit repairs are refused or go stale; (6) `RunEditorialActivity::agentWrittenBlockHashes:1297`, which ignores DraftKit, so no kit patch would ever apply; (7) `WriteArticle::save:155` invalidates only Release approvals; (8) the hard-coded test lists in `VoiceCanonTest:9-15`, `EditorialAgentsTest:38-50,150-162` and `EditorialInputContractTest:174-290`, plus the `VoiceLintTest:266-270` "only blog posts" test, which breaks; (9) `ReadPublishedWriting.php:20,42` has no kind filter (the spec's open item). |
| Pattern familiarity  | ready  | Read the lint base classes (`VoiceRule`, `PatternRule`, `MeasuresMetadataLength`), `EmDash`, `WalkthroughLink`, `SeoTitleLength`, `LintTarget`, `VoiceLinter`, `LintHit` and the tag registration (`AppServiceProvider:50-79`). Read the Drafter, FactReviewer, LintRepairer and Interviewer agents, `EditorialAgent::schema/finding`, the EditorialOutput validators, `LintRevision`, the RunEditorialActivity post-draft chain, `StartEditorialActivity`, `ApprovePublishingStage`, and `ManageArticleRelease` (prepare, claimManifest, approve, deliver). Also read `WriteArticle::addPiece/save`, `AdvancePublishingAttempt::develop`, the workspace and `write.blade.php`, `VoiceCanon`/`CanonSnapshot`/`roles.json`, and the bat-kol sources: `channels/blog.md:185-194` (distribution kit), `channels/bluesky.md:17-22,92-103` and `registers/public.md:205-212`. Test templates are listed below. |
| Dependency awareness | ready  | Mapped below: 9 exhaustive kind matches in app and 3 in tests, 6 ApprovalKind matches, the 5 places LintRepair is tied to Plan approval, the Draft-only post-draft chain (lintThenQueueReviews, agentWrittenBlockHashes, actorCanRunKind, finishAndQueueReviews, skipRepair), the linter consumers (LintRevision, CheckArticleRelease, validatePackage, IndexExemplarSections), the callers of `ManageArticleRelease::approve` (workspace plus 5 test files, all approving while agents are paused), and the public readers (ReadPublishedWriting, sitemap, RSS). |
| Edge case coverage   | ready  | Concrete list below. It covers where kit fields are stored and what role each block has, the lint context kit rules need (flagship URL, target query), kit rules a single-block patch cannot fix, URL and sentence counting, `#` inside URLs, protected blocks on a restart, owner edits on a restart, edits after kit approval, the "posted" check after develop-after-publish, which blog posts spawn a kit (sibling, archive), kit author, spawn failing after the approval commit, paused spawn, claim manifest false blocks, KitReviewer evidence ids, sibling context without titles, and schema calls without a piece kind. |
| Test strategy        | ready  | Commands, setup, fake routing and fixture builders are below. `birdcar_test` was confirmed on 127.0.0.1:5433 with `psql -l` this session; Laravel is v13.33.0. The scout did not run the suite. |

### Phase 3 gates (reference)

| Gate                 | Status | Evidence |
| -------------------- | ------ | -------- |
| Scope clarity        | ready  | The spec names most files. This map adds the ones it misses and the change each needs: (1) exhaustive `match` arms for `LintRepair` in `ApprovePublishingStage.php:328` and in the settings page `admin/settings/⚡publishing.blade.php:215,230`; (2) `ApplyEditorialProposal.php:68` rejects any finding whose `input_hash` differs from the revision content hash, so "Accept fix" on a lint finding (which stores the hit hash) always throws "stale"; (3) `StartEditorialActivity::completedDraftExistsForRevision` (505-524) refuses review lenses on a repair revision, and `reviewFindings` (246-273) would feed lint findings to reconciliation and recheck; (4) `CheckArticleRelease::materialEditorialBlockers` (410-435) would block lint findings a second time; (5) fixtures in 8 test files go red because every blog post now needs exactly one /walkthrough link. |
| Pattern familiarity  | ready  | Read the spec's pattern files: the walkers in `CheckArticleRelease.php:231-297` and the node, mark and attr rules in `ArticleDocument.php` (headings can only be level 2 or 3). Also read the `Drafter` and `ReviewReconciler` role agents, `EditorialAgent::schema`, `EditorialOutput`, the patch pipeline in `ApplyEditorialProposal`, the origin guards in `WriteArticle`, and the workspace actions plus the finding card in `write.blade.php`. `#[Tag]` exists in Laravel 13.33 (`Container/Attributes/Tag.php` calls `tagged()`). Test templates: `EditorialReviewTest:122-150`, `PublishingJourneyTest:30-84`, `EditorialInputContractTest:166-186`, `ReleaseReadinessTest:363-524`. |
| Dependency awareness | ready  | Adding the enum case touches 8 exhaustive matches plus the explicit dataset in EditorialInputContractTest. Lint findings are read by FinishEditorialReview, `StartEditorialActivity::reviewFindings`, `CheckArticleRelease::materialEditorialBlockers`, ApplyEditorialProposal, `ReleaseFreshnessManifest::reviewManifest` and the workspace `with()`. CheckArticleRelease is called by `ManageArticleRelease:128,375` and `PublishDueArticles`. Release-path tests: ReleaseIntegrityTest (53 sites), ReleaseReadinessTest (21), EditorialWorkflowTest, AdminPublishingEditorTest, PublicWritingTest. Draft-to-lens tests: EditorialReviewTest, EditorialWorkflowTest, PublishingJourneyTest. |
| Edge case coverage   | ready  | Concrete list below. It covers where the spec's word lists differ from the canon, the pronoun "I" in headings, bold and link marks split across text nodes, protection inherited from an ancestor, repeated excerpts sharing a hash, metadata hits that cannot be patched, `[NEED:`/`[TODO:` markers the repairer must not touch, several hits in one block, archive essays, saves with no attempt, review-cycle restarts, and override scope. |
| Test strategy        | ready  | Commands, setup and fixture changes are below. `birdcar_test` was confirmed on 127.0.0.1:5433 (`psql -l`). The scout did not run the suite. |

### Phase 2 gates (reference)

| Gate                 | Status | Evidence |
| -------------------- | ------ | -------- |
| Scope clarity        | ready  | The spec names every file except four, and this map adds them with the change each needs. (1) `app/Providers/AppServiceProvider.php:22-25`: the scoped `VoiceCanon` binding goes in `register()`, which is currently empty. (2) `config/publishing_agents.php`: add canon and archive path keys so VoiceCanonTest can point at a temporary copy; no such key exists yet. (3) `tests/Feature/Publishing/EditorialAgentsTest.php:24-30`: its `editorialActivity()` helper builds input with no canon, so about 16 cases throw once `instructions()` requires `input.canon`. (4) Optionally `app/Models/EditorialActivity.php:31-42`: a constant for the restart message, next to the other `*_PAUSE_REASON` constants. |
| Pattern familiarity  | ready  | Read the spec's pattern file `ArchiveSourceManifest.php` (sha256 file hashing) and these files. `PublishingFingerprint`. `ArchiveMarkdownImporter::parseFile` (frontmatter split plus Symfony Yaml). `ReadWriting.php` (directive regexes). `InviteAdministrator.php:355`, the `final readonly class` precedent. The command shape in `PublishingImportArchive` and `RecoverEditorialActivities`. The 9 role agents. The `Http::fake`/`Http::recorded()` pattern in `EditorialWorkflowTest.php:280-401` and `PublishingJourneyTest.php:30-80`. The per-kind dataset in `EditorialAgentsTest.php:125-153`. |
| Dependency awareness | ready  | `StartEditorialActivity::start()` is the only place activities are created. Its 10 app callers are listed below, and nothing calls `new StartEditorialActivity`. The SDK calls `instructions()` on every request (`vendor/laravel/ai/src/Providers/Concerns/GeneratesText.php:74`), including AskAuthor continuations. EditorialAgentsTest and the Manual trial's replay helpers also call it. The `voice[_ ]?samples?` sweep has about 45 hits today; every one is listed below. |
| Edge case coverage   | ready  | Concrete list below. It covers dotfiles in the canon directory, path relativity under the config override, directive-block stripping (with or without contents), files referenced but not copied (`FW/sisyphean-arc.md`), excluding archive sources from rule ids, a gate on activities created before the canon and how they restart, the idempotency keys that ignore prompt_hash for review kinds, the output contracts carried by the Reconciler and Rechecker instructions, and duplicate rubric tokens. |
| Test strategy        | ready  | Commands and a step-by-step setup for the contract test are below. Agents are paused by default (settings migration line 9), so start activities while paused, then unpause and run them. `birdcar_test` was confirmed on 127.0.0.1:5433 (psql -l). The scout did not run the suite because it writes to the database. |

### Phase 1 gates (reference)

| Gate                 | Status | Evidence |
| -------------------- | ------ | -------- |
| Scope clarity        | ready  | The spec named every file except four, and the Phase 1 map added them: `session.blade.php:5`, `ReadAdminHome.php:37-60`, `develop.blade.php:109` and `tests/Manual/PublishingModelTrialTest.php:140,149`. |
| Pattern familiarity  | ready  | Article, PublishingAttempt, the enums, WriteArticle, PublishingAttemptFactory, the data-migration pattern and the migration-test pattern. |
| Dependency awareness | ready  | 13 `articles.idea` readers, 100 `capture()` calls, 6 nested factories, and the import and published-writing consumers. |
| Edge case coverage   | ready  | Summary derivation, backfill nulls, the in-memory `kind` default, jsonb key order, the FK conflict, strict lazy loading and running the import twice. |
| Test strategy        | ready  | birdcar_test on Herd PG 18 and the inner, phase and final commands. |

### Phases 4 and 5 (no persisted map)

The scout maps for Phases 4 and 5 were never written to this file; the Phase 4 notes say the write was interrupted. These are the facts from their implementation notes and the current code that Phase 6 depends on:

- **Claim manifest**
  - Frozen at `ManageArticleRelease::prepare()` into `payload.claim_manifest` (line 157; built by `claimManifest()` at 193-263) as `list<{statement, supporting_source_ids}>`. The statements carry no quotation text.
  - Release approval approves that payload's hash, so in effect the manifest is frozen at approval.
  - With no completed research the manifest is `[]` (PackagingTest:466-475).
- **Brief keys**: the attempt brief stores `post_type` and `target_query` in snake_case (`RunEditorialActivity::applyInterview` 500-539). `target_query` may be null.
- **Retrieval skips kit kinds**
  - Only blog-post Draft and ReviewVoice inputs carry `canon.retrieved_exemplars` and `canon.retrieval_status` (`StartEditorialActivity::frozenCanon` 145-160).
  - `ApprovePublishingStage::exemplarContextFor` (98-118) returns null for anything that is not a blog post.
  - `deliver()` dispatches `IndexPublishedExemplar` only for blog posts (ManageArticleRelease 423-425).
- **Metadata lint**
  - The Packager lints metadata through `LintTarget::fromDocument([], metadata, PieceKind::BlogPost)` (EditorialOutput 377-388).
  - The SEO title is required only for blog posts that are not archive imports (CheckArticleRelease:74).
- **Archive essays**: `LintRevision::isArchiveImport` (line 423) makes `hits()` return `[]` for articles with an import-origin release.

### Phases 7 and 8 (not yet implemented)

When this Phase 9 scout ran, Phases 7 and 8 had no commits and no map sections. Phase 9 depends on neither; the contract lists only Phases 4 and 5 as blockers.

- Phase 8 adds `2026_10_02_400000_create_voice_canon_versions_table.php`, so Phase 9's `2026_10_02_500000_...` still sorts last.
- Phase 8 makes `VoiceCanon::snapshot()` read the active database version (spec-phase-8.md:17). See the Phase 9 Risks section for how this affects the drift trial's canon hash.
- Phase 7 (the board) touches no Phase 9 file.

### Phase 8 (still not implemented when the Phase 7 scout ran)

- HEAD is `51a8ea5` (Phase 9). Phase 8 has no commits.
- The only file both phases touch is `routes/admin.php`: Phase 8 adds `GET /settings/voice-canon/versions/{version}/download` (spec-phase-8.md:76), and Phase 7 edits line 36. The edits don't overlap.
- Phase 7 adds no agent role, so the validator-to-schema audit (contract line 154) does not apply.

## Key Patterns (Phase 7)

- **Composer and capture actions** (the spec's pattern: the deleted `resources/views/components/admin/publishing/⚡dashboard.blade.php`)
  - **`saveForLater` (26-37)**: `Gate::authorize(AdminPermission::View->value)`, then `Gate::authorize(PublishingPermission::Write->value)`, then `validate(['idea' => ['required', 'string', 'max:5000']])` and `captureIdea`. It flashes "Idea saved for later." and resets the ideas page.
  - **`developIdea` (39-60)**:
    - Authorizes View, Write and Develop.
    - One `DB::transaction` runs `captureIdea`, then `develop($actor, $article, null, ['idea' => $body])`, then `start(..., Interview, ['idea' => $body], 'initial-interview')`.
    - It flashes `status` and `publishing-arrival`, then calls `redirectRoute('admin.publishing.articles.show', ['article' => $article], navigate: false)`.
  - **Form markup (178-199)**: move it over unchanged.
    - `wire:submit` picks `developIdea` or `saveForLater` by the Develop permission.
    - It has the `flux:composer` with `wire:model="idea"` and the two buttons with their `aria-label`s.
    - `AdminPublishingWorkspaceTest:142-163` asserts `wire:submit="developIdea"`, `aria-label="Develop idea"`, `aria-label="Save for later"` and `data-flux-composer`.
  - **Root**: `<section data-publishing-studio class="publishing-studio space-y-3" x-data="publishingArrivals">`, with `data-publishing-enter` on the top blocks. `motion.js` animates the first 10 of these once, on init.
  - **Card heading**: `<x-admin.publishing.piece-heading :title="$title" :summary="$article->contentIdea->summary_title" />`. It renders the metadata title, or `idea-label` with an "Idea" badge (`idea-label.blade.php` has `data-idea-label`).
  - **Label sources to reuse for the status chip**: the activity-kind labels (139-153: "Interview", "Voice-rule repair", "Kit draft" ...), the activity-status labels (155-165: queued, running, needs attention ...), and `statusFor` (105-129), which shows paused and parked reasons.
- **Ideas to Shaping on an existing idea piece**
  - Copy the workspace `startDevelopment` (`⚡article-workspace.blade.php:123-137`).
  - In one transaction: `develop($actor, $article, $article->working_revision_id, ['idea' => $article->contentIdea->body])`, then `start($actor, $attempt, EditorialActivityKind::Interview, [], 'interview-'.$attempt->review_cycle)`.
  - Pass the locked article's `working_revision_id` as the expected revision. Otherwise `develop` throws "The article has changed since develop was requested." (AdvancePublishingAttempt:36-38). An idea can have a working revision if the owner typed in its workspace.
- **Park, kill and unpark** (`app/Actions/Publishing/AdvancePublishingAttempt.php`)
  - **`park($actor, $attempt, $reason)` (92-100)** sets `parked_at` and `parked_reason`.
  - **`abandon` (102-111)** sets stage Abandoned, `abandoned_at` and `abandoned_reason`.
  - **Authorization**: both require **`publishing.develop`** (`authorize` 462-467, which throws `AuthorizationException('This user is not allowed to advance publishing work.')`).
  - **Guard**: both go through `updateAttempt` (375-413) and then `ensureAttemptCanBeMutated` (451-460).
    - The attempt must be the current one: "Only the current publishing attempt can be changed."
    - Terminal attempts (stage Published or Abandoned, or `abandoned_at` set) throw "Terminal publishing attempts cannot be changed."
  - **No `unpark()`**: add one beside `resume()` (82-90) as `updateAttempt($attempt, ['parked_at' => null, 'parked_reason' => null])`, with the same authorization as `park`.
  - **Activities**: `park` leaves activities alone. Only `pause` touches them (385-394), and `resume` returns `RESUMABLE_PAUSE_REASONS` work to Pending (396-407).
- **Board query** (closest precedent: `app/Actions/Admin/ReadAdminHome.php:38-77`)
  - **Scope**: owner only, `where('author_id', $actor->id)`.
  - **Column-constrained eager loads**: `contentIdea:id,summary_title` (add `body` if the card shows it) and `workingRevision:id,article_id,content_hash,metadata,updated_at`.
  - **Latest activity per kind**: `'currentAttempt.editorialActivities' => fn ($q) => $q->select([...])->whereNotExists(<a newer row with the same attempt and kind>)->orderByDesc('id')` (66-73).
    - The select there omits `pause_reason`, `error_reason`, `proposal` and `completed_at`.
    - Add every column PieceStatus reads; strict mode throws on unselected attributes.
  - **Owner-owed flags with `withExists`**:
    - `has_open_findings` (48-55): current cycle, not stale, undisposed, not duplicate or superseded, on the working revision's content hash.
    - `has_approved_release` (56-59).
    - `has_prepared_release` (60-63): status `prepared`, not withdrawn, `revision_id` equal to the working revision.
  - **Active approvals**: `'currentAttempt.approvals' => fn ($q) => $q->whereNull('invalidated_at')` (65).
  - **Decision rules to mirror for "waiting"** (105-125):
    - An AwaitingApproval activity started by this user (an interview waiting for answers).
    - At Developing: a completed interview or an angle.
    - At Drafting: `$hasPlan` (111-113), meaning outline and visualPlan are filled and, for `source = agent`, the plan activity completed.
    - At InReview: open findings or a prepared release.
    - At Approved: an approved release.
  - **Do not copy `isCurrentActivity`** (145-173): it calls `ApprovePublishingStage::snapshotInputHashFor` for every activity, which queries.
  - **Order**: `latest('updated_at')->orderByDesc('id')`. The old dashboard used `latest()->latest('id')`.
- **Flux kanban** (Flux Pro 2.20.0, `vendor/livewire/flux-pro/stubs/resources/views/flux/kanban/`)
  - **`flux:kanban`** renders `<div class="flex gap-4" data-flux-kanban>`.
  - **`flux:kanban.column`** wraps an inner div with `rounded-lg w-80 max-w-80` and a zinc-100/800 background.
  - **`flux:kanban.column.header`** takes `heading`, `subheading`, `count`, `badge` and an `actions` slot. `count` is hidden when it is 0 (`@if ($count)`).
  - **`flux:kanban.column.cards`** is `px-2 pb-2 flex flex-col gap-2` with `data-flux-kanban-column-cards`. There is also `column.footer`.
  - **`flux:kanban.card`** takes `heading`, `header` and `footer` slots. With `as="button"` it renders `<ui-button>`; otherwise a div with `flux-kanban-card`.
  - All of these are `@blaze(fold: true)`.
- **`wire:sort`** (Livewire 4.4.6; `vendor/livewire/livewire/dist/livewire.esm.js` ~17085-17140, the copy that `resources/js/admin.js` imports; the Alpine sort plugin is around `livewire.js` 12100-12200)
  - **Each column's card container**: `wire:sort="moveCard"`, then `wire:sort:group="board"` (shared by all columns), then `wire:sort:group-id="{{ $column->value }}"`.
  - **Each card**: `wire:sort:item="{{ $piece->id }}"`.
  - **The call**: only the **receiving** list calls the handler, as `moveCard($item, $position, $groupId)`. A reorder inside one column calls it too.
  - **Options**: `wire:sort:handle`, `wire:sort:ignore` (on links and buttons inside a card) and `wire:sort:config="{...}"` (SortableJS options; the default is `animation: 150`).
  - **During a drag**: the body gets the class `sorting`, and the dragged copy gets `.sortable-ghost`.
- **Modal precedent** (`resources/views/components/admin/settings/account/⚡security.blade.php:456-469`)
  - `<flux:modal wire:model.self="confirmingTwoFactorDisable" class="settings-dialog">`, then a heading and text, a `<flux:modal.close>` ghost button, and an action button that calls the method.
  - A bool property is opened with `wire:click="$set('confirmingX', true)"` and cleared by the action (171).
- **Polling**
  - `partials/session.blade.php:56` uses `wire:poll.5s.visible="refreshAgentWork"`, only while work runs.
  - Livewire 4 islands (`@island(name: ...)`) re-render one region on its own (livewire-development skill, lines 101-106). Use them to limit the poll to the cards.
- **Kit stage labels**
  - Approved shows "Ready to post", published shows "Posted", anything else shows "In review" (`partials/distribution-kit.blade.php:10`, `partials/session.blade.php:34`). The board would be the third copy, so a shared label (for example `BoardColumn::label()` taking the piece kind) removes the duplication.
  - `PieceKind::label()` gives "LinkedIn post", "Bluesky thread", "Email blurb" and "Blog post".
- **Kit approval helper**: `ApprovePublishingStage::kitApprovalProblems($attempt)` (183) lists kit approval blockers, but it queries, so never call it per card.
- **CSS** (`resources/css/admin.css`)
  - Publishing styles are at 51-140. `.publishing-studio` is a grid capped at 88rem, with breakpoints at 1200, 1023 and 639px.
  - Reduced motion is at 281-285.
  - Tailwind v4 scans `@source '../views/**/*.blade.php'`.
  - It is a Vite input (`vite.config.js:9`), so run `bun run build` after changes.
- **JS**
  - `motion.js` exports `createPublishingArrivals` and `installPublishingMotion`, and returns early under `prefers-reduced-motion` (`reduceMotion()` at 7-9).
  - Its tests are `bun:test` (`motion.test.js` mocks `motion`) and are not in `composer test`.
  - `admin.js:2` imports Livewire from `vendor/livewire/livewire/dist/livewire.esm`, which bundles SortableJS and wire:sort, so no new JS dependency is needed.

## Dependencies (Phase 7)

- **Route** (`routes/admin.php:36`)
  - Today it reads `Route::livewire('/', 'admin.publishing.dashboard')->name('dashboard')`. Point it at `admin.publishing.board` and keep the name.
  - The group middleware is `PermissionMiddleware::using(PublishingPermission::View)` (34).
  - Links by name:
    - `layouts/admin.blade.php:34` (the sidebar).
    - `navigation.blade.php:7` ("Workspace"; current for `dashboard` and `articles.*`).
    - `partials/session.blade.php:4` ("All writing").
    - `⚡index.blade.php:59,70,106,130` ("Open Publishing ...").
  - `AdminFortifyFlowTest:98-165` redirects to `/publishing`, which is unaffected.
- **Tests that render the dashboard component or GET `/publishing`**
  - Every one moves to the board. CLAUDE.md says "Do not delete tests or test files without approval", so rewrite each into its board equivalent instead of dropping it.
  - `tests/Feature/Publishing/AdminPublishingWorkspaceTest.php`:
    - 139 (shell markup and composer attributes) and 182 (navigation).
    - 194 (forbidden without publishing.view).
    - 203 and 216 (revoked permission or admission makes `saveForLater` forbidden).
    - 229 (passive idea; asserts "Publishing workspace").
    - 249 (developIdea creates one pending Interview and redirects).
    - 298 (develop forbidden without the permission).
    - 310 (asserts "Ideas", "Active writing", "In review", owner scope, and the full idea text "Final accessible sentence.").
    - 332 (independent pagination: `paginator-ideasPage-page2`, `paginator-activePage-page2`).
    - 353 (created_at ties broken by newest id across pages).
    - 376 ("published work is listed separately" asserts the published piece is **not** on /publishing; the board must now show it in Published).
    - 400 ("Draft in progress on published work").
  - `tests/Feature/Publishing/ContentIdeaTest.php:255-269` checks that the dashboard labels idea summaries, using `contentIdeaAssertLabeledOnly` (479-490).
  - `tests/Feature/Publishing/EditorialInputContractTest.php:491-505` checks that `developIdea` freezes the canon into the interview.
  - `tests/Feature/Publishing/PublishingJourneyTest.php:124` GETs /publishing and asserts "Publishing workspace". Keep that heading or update the test.
- **Global helpers in AdminPublishingWorkspaceTest**
  - `publishingUser`, `publishingWriteOnlyUser`, `articleRevisionWithTitle`, `activeArticleFor`, `publishedArticleFor` and `workspaceXpath*`.
  - PublishingBoardTest must not redeclare them, and it can't rely on them: a file run alone doesn't load them.
- **JS hooks**
  - `motion.js:12` (`rootFor` finds `[data-publishing-studio]`), 60 (`[data-publishing-enter]`) and 184-186 (`publishingArrivals` and the milestone listener).
  - `admin.js:26`.
  - `motion.test.js:59,103` use the same selectors.
  - Keep the attributes on the board root.
- **`AdvancePublishingAttempt`**: only tests call `park`, `abandon`, `pause` and `resume` (EditorialWorkflowTest:415-470,644-648; ContentIdeaTest:211; PublishingAgentSettingsTest:183; ReleaseIntegrityTest:514). Adding `unpark()` changes no existing caller.
- **What parking does elsewhere** (why unpark matters)
  - **Refusals**:
    - `StartEditorialActivity:58` refuses to start work.
    - `ApprovePublishingStage:325` and `ManageArticleRelease:514,644` refuse approvals and delivery.
    - `RetryEditorialActivity:35` and `ResumeEditorialActivity:35` refuse.
  - **Activities the worker pauses**:
    - `RunEditorialActivity:249` pauses a claimed activity with "The publishing attempt is blocked or no longer current."
    - `RunEditorialActivity:381` pauses a returning result with "The agent result returned after the publishing attempt was blocked or replaced."
    - Neither reason is in `EditorialActivity::RESUMABLE_PAUSE_REASONS` (45), and `RecoverEditorialActivities:24-29` releases only configuration reasons.
  - **Admin home**: `ReadAdminHome:94-98` lists parked pieces as Blocked.
- **Scheduled releases**
  - `publishing:publish-due` (`app/Console/Commands/PublishDueArticles.php`) delivers due `scheduled` releases.
  - When `deliver` throws "Blocked publishing attempts cannot be delivered.", it warns and returns FAILURE on that run.
  - `ManageArticleRelease::withdrawScheduledReleasesForAttemptId` (458) is the existing withdraw helper; rethink, selectAngleOption and chooseFramework use it.
- **Scheduling path**
  - The workspace has no scheduling dialog or modal. Scheduling lives in the release checklist's "Prepare a different release" disclosure (`partials/release-checklist.blade.php:40-51`): slug, `releaseScheduledAt` and `releaseScheduledTimezone`, then "Check and prepare release", which calls `prepareRelease` (workspace 458-481, `publish` gate).
  - Next comes "Approve and schedule this release" (`approveRelease` 483-503, `approve` gate). `ManageArticleRelease:351` sets the Scheduled stage when `scheduled_at` is set.
  - The workspace opens on the release tab for Approved, Scheduled and Published stages (`with()` 1364-1368), so redirecting there is enough.
- **Package acceptance**
  - Nothing on the package activity records acceptance.
  - Accepting writes an `agent-accepted` revision with `client_mutation_id = 'package-{activityId}-{24 hex}'` (AcceptPackageProposal:54-85).
  - The workspace's current package is the latest Completed Package in the current `review_cycle` (`latestPackage` 213-223).
- **Kit pieces** (from the Phase 6 implementation notes)
  - Every parentless blog post that is not an archive import spawns its own kit, so a kit nests under its `parent_article_id`, which can be a sibling.
  - Kit attempts start at InReview. Kit approval moves them to Approved ("Ready to post"), and `MarkKitPiecePosted` moves them to Published ("Posted").
  - A save after kit approval invalidates the approval and moves the kit back to in review.
  - The Admin home still lists kit pieces as their own items; this spec does not change that.
- **Archive imports**: `ImportWritingArchive` sets `published_release_id` and `first_published_at` and creates no attempt (89-141), so `current_attempt_id` is null.
- **`first_published_at`**: set at `deliver` from `original_public_date` or the delivery time (ManageArticleRelease:431-440). Archive imports keep their original dates.
- **Strict mode** (`AppServiceProvider::configureModelStrictness` 158-172): lazy loading, missing attributes and silently discarded attributes all throw outside production.
- **ArticlePolicy** (`app/Policies/ArticlePolicy.php`): view, update, develop, approve and publish each require the permission **and** `author_id === user.id`.
- **Roles**: only `publishing.author` exists (`app/Authorization/Publishing/Catalog.php`), and it holds every publishing permission.

## Conventions (Phase 7 additions)

- **Names**
  - `App\Services\Publishing\BoardColumn` is a string-backed enum with TitleCase cases, per the spec. It is the first enum outside `App\Models\Publishing`, which is fine because the spec places it there.
  - `App\Services\Publishing\PieceStatus` is a `final readonly class`, like `CanonSnapshot` and `FrameworkCatalog`.
  - `App\Actions\Publishing\MovePublishingPiece` is a plain `class`, like the sibling actions, resolved with `app()`.
  - Put `MoveResult` in its own file. `InviteAdministrator.php:355` declares a second class in the same file, but don't copy that: PSR-4 cannot autoload such a class by itself.
- **Generators**
  - `php artisan make:enum Services/Publishing/BoardColumn --no-interaction`.
  - `make:class` for PieceStatus, MovePublishingPiece and MoveResult.
  - `php artisan make:test --pest PublishingBoardTest --no-interaction`, then place it under `tests/Feature/Publishing/`.
  - Check every generated namespace.
  - For the SFC, try `php artisan make:livewire` and confirm it lands at `resources/views/components/admin/publishing/⚡board.blade.php`. There is no `config/livewire.php`, so the defaults apply. Otherwise write the file by hand, like its siblings.
- **Livewire methods**
  - Authorize first, then validate, then call the action. Show errors in a property (like the workspace's `$saveError`) or a toast.
  - Authorize `PublishingPermission::View` (and `AdminPermission::View`, as the dashboard did) inside `with()` and inside every action, because `Livewire::test` skips route middleware.
- **Use the policy, not the raw permission**: in MovePublishingPiece, call `Gate::forUser($actor)->authorize('develop', $piece)` (or `publish`), which also enforces ownership.
- **Errors**
  - Domain refusals are `RuntimeException`.
  - Validate the reason with `$this->validate([...])` in the component, or `ValidationException::withMessages(['moveReason' => ...])` in the action.
  - `parked_reason` and `abandoned_reason` are `text` columns (`2026_09_22_201057_create_publishing_tables.php:62,64`). Trim the reason and choose a max length, for example 1000.
- **Tests**
  - Helper prefix `publishingBoard*`, constant prefix `PUBLISHING_BOARD_*`.
  - `beforeEach`: `app(PermissionRegistrar::class)->forgetCachedPermissions()`, then `authorization:sync`.
  - For a user without develop, call `givePermissionTo(View, Write)` like `publishingWriteOnlyUser`.
  - Render with `Livewire\Livewire::actingAs($u)->test('admin.publishing.board')`.
- **Run after edits**
  - `vendor/bin/pint --dirty --format agent`.
  - `env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress` (level 7 includes tests).
  - `bun run build` after CSS or JS changes.
  - `bun test resources/js/admin/publishing/motion.test.js` if `motion.js` changes.

## Risks (Phase 7)

- **The "existing release scheduling dialog" does not exist.**
  - Scheduling means preparing a release with a time (`publish`) and then approving that exact release again (`approve`). Both happen in the release checklist (see Dependencies).
  - So spec step 4's fallback applies: Ready to Scheduled returns `needsDialog('release')`, and the board redirects to the workspace, which opens on the release tab for Approved pieces. Say so in the commit.
  - This does not contradict the decision log: "crossing a gate opens the workspace" still holds.
- **The spec's authorization conflicts with the action.**
  - The spec says "write for park and kill", but `AdvancePublishingAttempt::park` and `abandon` authorize `publishing.develop`. A write-only user would pass the board's check and then hit an `AuthorizationException`.
  - Use the `develop` policy for park, kill and unpark, or change the action. Record the choice.
- **There is no unpark, and unparking may not resume work.**
  - Add `unpark()` (see Key Patterns).
  - Any activity a worker claimed while the attempt was parked is left Paused with one of two reasons that cannot be resumed (RunEditorialActivity:249,381). After unparking, the piece would show "failed" with no Retry, because Retry applies only to Failed activities.
  - Decide whether unpark returns those Paused activities to Pending (as `updateAttempt` does for `ATTEMPT_PAUSE_REASON`) or leaves them and shows the reason. Record it.
- **Parking or killing a Scheduled piece leaves its release scheduled.**
  - `publishing:publish-due` will warn and return FAILURE on every run until the release is withdrawn.
  - After an unpark, a release whose time has passed publishes on the next run.
  - Withdraw scheduled releases (`withdrawScheduledReleasesForAttemptId`) inside the park and kill moves, or refuse those moves for Scheduled pieces. Record the choice.
- **Park and kill cannot work as written for Ideas and Published.**
  - **Ideas**: they have no attempt, and the reason columns live on the attempt. Either refuse ("Ideas are already off the clock"), or create an attempt through `develop()` and then park or abandon it (this needs develop permission and leaves an attempt with no interview). Record the choice.
  - **Published**: published attempts are terminal, and archive imports have no attempt, so `ensureAttemptCanBeMutated` refuses. Refuse with a message that says why.
  - The spec's "any column to Parked or Killed" is a spec gap here, not a decision-log contradiction.
- **Read literally, the Writing rule moves reviewed pieces back.**
  - The spec says Writing is "in_review with no completed draft on the current revision".
  - A completed Draft targets its own `revision_id` or `proposal.applied_revision_id`. The editor autosaves 250 ms after typing stops (`resources/js/admin/publishing/autosave.js:3`), and every owner save is a new revision. A piece in Review would drop back to Writing on the first keystroke.
  - Read "draft done" as "a completed Draft exists in this attempt" (an applied LintRepair also counts), and keep the rule that any pending or running draft, lint_repair or draft_kit means Writing. Record the reading.
- **Two derivations of "waiting".**
  - ReadAdminHome ("Your decision" on Admin home) and PieceStatus will disagree unless they share rules.
  - The spec's list leaves out an interview waiting for the owner's answers (`AwaitingApproval`, ReadAdminHome:115-118). Include it.
- **No stored flag says a package was accepted.** Checking it means looking for a revision whose `client_mutation_id` starts with `package-{id}-`. Do that in SQL (a `withExists` or subquery) to keep the query count flat.
- **Query count, caps and correctness.**
  - Capping Published at 12 needs its own limited query.
  - The SQL that separates Published from everything else must match `BoardColumn::forPiece` exactly. Otherwise a piece appears twice or not at all.
  - Ideas and Killed have no cap; the old dashboard paginated Ideas at 12. A collapsed Killed column still loads. Consider caps and record the choice.
  - Never call `ContentIdea::flagship()` per card; it queries (ContentIdea:77-85).
- **`wire:sort` gotchas** (verified in the shipped bundle)
  - **Attribute order**: Livewire's init loop returns at the first `wire:sort:group` attribute (`livewire.esm.js` ~17096-17097). `wire:sort="..."` must therefore come **before** `wire:sort:group` on the element, or the handler never binds.
  - **Blaze folding**: `flux:kanban.column.cards` is `@blaze(fold: true)`. Confirm that the `wire:sort*` attributes and the dynamic group id survive folding, or put them on a plain wrapper div.
  - **Refused drags**: SortableJS has already moved the DOM node when the server refuses. Don't use `.renderless`, `skipRender()` or `wire:ignore`. Give every card a `wire:key` so the re-render puts it back.
  - **Polling mid-drag**: a poll that re-renders during a drag can fight SortableJS. Skip or defer the poll while `document.body.classList.contains('sorting')`, or poll through an island outside the sortable lists. Check this in the browser.
  - **Clicks**: mark links and buttons inside a card `wire:sort:ignore`, or use a handle, so clicks still open the workspace and the card menu.
  - **Reduced motion**: set `animation: 0` in `wire:sort:config` when `prefers-reduced-motion: reduce`, and turn off CSS transitions on `.sortable-ghost` and `body.sorting`.
- **No toast exists.** `layouts/admin.blade.php` has no `<flux:toast />`. Either add it (a file the spec doesn't list) or show refusals in an in-board `role="alert"` region, which is also easier to assert. Record the choice.
- **The idea-label test.** Never pass the idea summary as `flux:kanban.card heading=` or put it in a modal heading. Both render a bare `[data-flux-heading]`, and `contentIdeaAssertLabeledOnly` (and any board equivalent) fails. A card wrapped in an `<a>` passes only if the `<a>` contains the `[data-idea-label]` span.
- **Width.** Ten columns at Flux's `w-80` add up to 200rem, while `.publishing-studio` caps at 88rem and `#admin-main` at 100rem (`layouts/admin.blade.php:62`). The board scrolls sideways on desktop too.
  - Wrap the kanban in `overflow-x-auto` inside a `min-w-0` parent.
  - Consider narrower columns and a collapsed Killed column. Flux's `w-80` class is merged with yours, so check that the override wins.
- **Rewrite tests, don't delete them.** CLAUDE.md forbids deleting tests without approval. Turn the pagination, tie-break and "listed separately" tests into board versions: Published capped at 12, newest first with ties broken by id, and a link to the library.
- **Mobile.** Cross-column drags are poor on touch screens. The spec's mitigation, a card menu with the same allowed moves as buttons (Failure Modes table), is missing from File Changes but required.
- **Decision log checked against the code; no contradictions.**
  - **"Board columns follow stage; drags only for single human actions; crossing a gate opens the workspace"**: every gate action already lives in the workspace (angle, plan and release approvals), and every action refuses parked attempts. Only the "scheduling dialog" premise is missing (above).
  - **"Keep every current stage and gate"**: EditorialStage has 7 values. All 10 columns can be derived from them plus `parked_at`, `abandoned_at` and activities, so no new stage is needed.
  - **"One idea owns many pieces"**: `content_idea_id`, `parent_article_id` and per-piece attempts all exist (Phases 1 and 6), and kits nest under the post that spawned them.

## Edge Cases (Phase 7)

- **Column precedence** (`BoardColumn::forPiece`, as one exhaustive `match`)
  1. **Killed**: the current attempt's stage is Abandoned, or `abandoned_at` is set. EditorialWorkflowTest:424-425 parks and then abandons, so both flags can be set at once; Killed must win.
  2. **Parked**: `parked_at` is set.
  3. **No current attempt**: Published if `published_release_id` is set (archive imports); otherwise Ideas.
  4. **By stage**: Developing goes to Shaping, Drafting to Planning, InReview to Writing or Review, Approved to Ready, Scheduled to Scheduled, and Published to Published.
  - **Develop after publish**: a published piece with a new attempt follows its new stage. Keep the old "Draft in progress on published work" note (dashboard 259-261; AdminPublishingWorkspaceTest:400).
  - **Paused attempts**: `paused_at` is not a column; show the pause reason on the status chip.
  - **Top-level cards**: only blog posts with no parent (`kind = blog_post` and `whereNull('parent_article_id')`), so siblings get their own cards and kit pieces never do.
- **Writing vs Review** (see Risks)
  - **Writing**: any Draft, LintRepair or DraftKit is Pending or Running, or the attempt has no completed Draft. The second case also covers a plan approved while agents were paused, since `ApprovePublishingStage` then creates no Draft.
  - **Review**: everything else at InReview. Package, the review lenses, reconciliation and recheck all count as Review.
- **PieceStatus**
  - **running**: a current activity is Pending or Running. When agents are paused globally (`PublishingAgentSettings::paused`), say "queued; agents paused", as the workspace does (`session.blade.php:62`), not "running".
  - **failed**: the latest activity that isn't Stale is Failed or Paused. The reason is `pause_reason ?? error_reason` (`partials/activity.blade.php:10-11`). Ignore Stale activities; Declined counts as neither.
  - **waiting**: the owner owes one of these:
    - An answer to an AwaitingApproval interview the viewer started.
    - An angle decision: `interview_context.angle_options` exist with no active Angle approval.
    - A plan decision: the plan is ready (ReadAdminHome's `hasPlan`) with no active Plan approval.
    - Undecided blocking findings: current cycle, not stale, `disposition` null, `reconciliation_state` not duplicate or superseded. The `lint` and `review_kit` lenses count.
    - The latest completed Package in the cycle has no accepted revision.
    - A prepared release exists on the working revision.
    - A kit child is at InReview with a completed draft_kit and review_kit and no active Kit approval.
  - **Precedence** when several apply: pick one (for example failed, then running, then waiting, then idle) and record it.
- **Moves** (`MovePublishingPiece::move`)
  - **Re-derive under a lock**: lock the article (`lockForUpdate`), reload it with the board's eager loads (share one eager-load definition so both paths stay strict-mode safe), and re-derive `from`. On a mismatch, refuse with "This piece moved; refresh".
  - **Reorder in the same column**: do nothing and show no message.
  - **Ideas to Shaping**: develop and start the interview (develop policy). Without develop, no attempt is created and the refusal is reported (the spec's "viewer without publishing.develop" test).
  - **Ready to Scheduled**: return `needsDialog('release')` and redirect to the workspace (publish policy).
  - **Any column to Parked or Killed**
    - A reason is required: trimmed, non-empty, with a max length. An empty reason is a validation error and changes nothing.
    - Parked to Killed is allowed (`abandon` accepts a parked attempt).
    - Killed to anywhere is refused, because the attempt is terminal.
  - **Out of Parked**: dropping on the piece's own stage column unparks it. Any other column is refused, and the message names the right column.
  - **Gate crossings are refused, naming the gate**:
    - Shaping to Planning: "Open the workspace to approve the angle".
    - Planning to Writing or Review: approve the plan.
    - Writing to Review: the piece moves on its own when the draft finishes.
    - Review to Ready: approve the exact release.
    - Ready or Scheduled to Published: publish from the workspace.
    - Backward moves are refused.
  - **Card ids come from the client**: look the piece up within `author_id = $actor->id` and top-level blog posts only. Refuse any other id without revealing it.
  - **Failures**: turn `RuntimeException` and `AuthorizationException` into `refused($message)` with no state change; the card snaps back on re-render.
- **Nesting**
  - Eager-load `children`, filtered to kit kinds and ordered by id, each with `currentAttempt` (plus activities and approvals if the chips show status).
  - A flagship with 3 kit pieces renders one top-level card with 3 chips (spec experiment).
- **Killed column**: collapsed by default (Alpine `x-show` or `<details>`). Decide whether it renders on the server or loads on expand.
- **Published column**: the 12 newest by `first_published_at desc, id desc` (archive imports keep their original dates), plus a link to `route('admin.publishing.published')`.
- **Polling**
  - Put `wire:poll.10s` (consider `.visible`) on the cards region only.
  - The composer's unsaved `idea` text must survive a poll. Deferred `wire:model` sends it with the poll, so confirm the morph keeps the caret, or isolate the cards in an island.
- **Reason modal**: one `flux:modal wire:model.self` that holds the pending piece id and target column. Cancel clears both and re-renders, so the card snaps back.

## Test Strategy (Phase 7)

- **Inner loop**: `php artisan test --compact tests/Feature/Publishing/PublishingBoardTest.php`
- **Repointed tests**: `php artisan test --compact tests/Feature/Publishing/AdminPublishingWorkspaceTest.php tests/Feature/Publishing/ContentIdeaTest.php tests/Feature/Publishing/EditorialInputContractTest.php tests/Feature/Publishing/PublishingJourneyTest.php`
- **Regression files**: `php artisan test --compact tests/Feature/Publishing/EditorialWorkflowTest.php tests/Feature/AdminHomeTest.php tests/Feature/Publishing/PublishingAuthorizationTest.php tests/Feature/Publishing/DistributionKitTest.php tests/Feature/Publishing/SiblingPieceTest.php tests/Feature/Auth/AdminFortifyFlowTest.php`
- **Phase check**: `php artisan test --compact tests/Feature/Publishing`
- **Final**: `vendor/bin/pint --dirty --format agent`, `env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress`, `bun run build`, then `composer test`. Run `bun test resources/js/admin/publishing/motion.test.js` if `motion.js` changes.
- **PublishingBoardTest setup**
  - **Fixtures**
    - Pieces: `Article::factory()->idea($body)->create(['author_id' => $user->id])`, then `PublishingAttempt::factory()->create(['article_id', 'user_id', 'stage', 'parked_at', 'abandoned_at', ...])` and `forceFill(['current_attempt_id' => ...])->save()`.
    - Activities: `EditorialActivity::factory()` defaults `article_id` and `attempt_id` to separate factories, so set both. Also set `kind`, `status`, `stage`, `review_cycle` and `initiating_user_id`. A `->running()` state exists.
    - Others: `EditorialApproval::factory()` (with an `invalidated()` state), `EditorialFinding::factory()` (blocking by default; `advisory()`), and `ArticleRelease::factory()` with `status`.
  - **Kits**: `app(WriteArticle::class)->addPiece($actor, $idea, PieceKind::LinkedinPost, $flagship)` for each kit kind, each with an attempt at InReview, Approved or Published.
  - **Archive import**: a published release and no attempt (the `publishedArticleFor` pattern, under the new prefix).
  - **Column mapping**: one dataset row per stage and flag combination, including parked plus abandoned, published with a new attempt, and an archive import. Assert that each piece appears in exactly one column.
  - **Moves**
    - Call the handler: `Livewire::actingAs($u)->test('admin.publishing.board')->call('moveCard', $id, 0, 'shaping')`, then assert a Pending Interview activity exists.
    - Refused moves (Shaping to Planning, Planning to Writing, Review to Ready): assert the message and that the stage didn't change.
    - Park with an empty reason: `assertHasErrors`.
    - Unpark: `parked_at` becomes null.
    - A user without develop: no attempt is created.
  - **The action alone**: test `MovePublishingPiece` directly for the server-side rules, including a concurrent change (change the stage between render and call; expect "This piece moved; refresh").
  - **Status chips**: running (a Running activity), waiting (angle options without an approval) and failed (a Failed activity with `error_reason`; assert the reason text).
  - **Query count** (precedent AdminHomeTest:270-285)
    - Render once to warm the permission cache.
    - Then `DB::enableQueryLog(); DB::flushQueryLog();`, render with 3 pieces and again with 30 (mixed columns, with kits), and assert the counts are equal or below N.
    - Strict mode makes any lazy load throw.
  - **Authorization**: a user without publishing.view is forbidden (the check in `with()`), and another author's card id is refused.
  - **Poll**: `assertSeeHtml('wire:poll.10s')`.
- **Environment**
  - `birdcar_test` on 127.0.0.1:5433 (confirmed this session). No `--parallel`.
  - Never run `tests/Manual`.
  - For the browser check, resolve `admin.birdcar.test/publishing` through Boost `get-absolute-url` after `bun run build`. The owner does the judgment review.

---

## Key Patterns (Phase 9)

- **Paid trial** (spec pattern `tests/Manual/PublishingModelTrialTest.php`)
  - **File setup**: the header comment documents the env flag and the cost. `pest()->extend(TestCase::class)` (line 49) binds Manual tests to `Tests\TestCase`; Pest.php applies RefreshDatabase only to `Feature`. The PHPStan extension already binds `$this` for `tests/Manual` (`tests/PHPStan/PestClosureThisExtension.php:23`).
  - **Skip first**: `getenv(...)`, then `$this->markTestSkipped(...)` (52-55).
  - **`liveTrialRefuseUnlessIsolated()` (85-100)**: already refuses unless the environment is testing, config is not cached, the connection is `pgsql` on database `birdcar_test`, and no DB URL is set. Phase 1 fixed it. Copy it as `driftTrialRefuseUnlessIsolated`.
  - **`liveTrialPrepareApplication()` (102-124)**:
    - Refuses without `ai.providers.openrouter.key`, or with a non-public URL.
    - Runs `migrate:fresh --force` and `authorization:sync`.
    - Fakes Queue, Mail and Notification, and sets `publishing_agents.limits.max_retries = 0`.
    - Unpauses agents and clears model overrides.
    - The drift trial must also check `ai.providers.openrouter-embeddings.key` and `url` (config/ai.php:148-156; same `OPENROUTER_API_KEY` env var).
  - **HTTP**: `Http::preventStrayRequests(); Http::allowStrayRequests(['https://openrouter.ai/api/v1/*']); Http::record();` (65-67). The wildcard covers `/embeddings`.
  - **`liveTrialRun` (417-427)**: runs one activity with `app()->call([new RunEditorialActivity($id), 'handle'])` and returns the fresh row and the duration.
  - **`liveTrialNewProviderExchanges` (485-511)**: reads only the new entries of `Http::recorded()`, tracking a `static $consumed` count. Copy it under the new prefix if you record usage or the model.
  - **`liveTrialRecord` (531-538)**: writes JSON with `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR` and a trailing newline. The drift file is one run object, not a list of appended trials.
  - **`liveTrialFlaggedBlocks` (544-549)**: reads `EditorialFinding::where('activity_id', ...)->pluck('block_id')`. The same query gives the reviewer's flagged blocks.
  - **`liveTrialDocument` (555-562)**: builds paragraphs from `block id => text`. Use explicit ids so injected block indexes map to known ids.
- **Per-passage chain** (ExemplarRetrievalTest is the closest template)
  - **`exemplarRetrievalAttempt` (183-218)**:
    - `captureIdea`, then save the revision **before** `develop(actor, article, revisionId, brief)`.
    - Approve Angle, then create a completed ResearchChallenge row that carries the matching angle hash.
    - `rethink(plan: [...])` with a human-written plan. No framework is needed: `ensureFrameworkChosen` applies only to `source = agent` (ApprovePublishingStage:427-433).
  - **The chain test (318-364)**:
    - Unpause, `Queue::fake()`, then approve Plan. `exemplarContextFor` embeds the query, and the approval creates a Pending Draft (ApprovePublishingStage:83-90) whose frozen canon carries `retrieved_exemplars`.
    - Force-complete the Draft (`status`, `completed_at`).
    - Start `ReviewVoice` with `['draft_activity_id' => $draft->id]`. It copies the Draft's canon (`StartEditorialActivity::draftCanon` 246-263).
  - **Eligibility**: the Draft's `revision_id` is the working revision, which satisfies `completedDraftTargetsRevision` (898-917).
  - **Don't copy ReviewRubricTest's pause handling**: `reviewRubricDraftedAttempt` (~93-130) builds the same chain but approves the plan while paused, so it never retrieves.
- **Job** (`app/Jobs/IndexPublishedExemplar.php`, the only job)
  - **Shape**:
    - `implements ShouldQueue`, `use Dispatchable, InteractsWithQueue, Queueable, SerializesModels`.
    - `public int $tries = 1`, `public int $timeout = 120`, and a constructor that takes the row id (`public int $articleId`).
    - `handle()` takes its dependencies by injection, finds the row, and returns early when it is missing.
  - **Dispatch after commit**: `IndexPublishedExemplar::dispatch($id)->afterCommit()` (ManageArticleRelease:445) and `RunEditorialActivity::dispatch($id)->afterCommit()` (StartEditorialActivity:125).
  - **Tests**:
    - `Queue::fake()`, then `Queue::assertPushedTimes(Job::class, 1)` or `assertPushed(Job::class, fn ($job) => $job->articleId === ...)` (ExemplarRetrievalTest:432-433).
    - Run the job directly: `(new Job($id))->handle(app(...))` (ExemplarRetrievalTest:442).
- **Command**
  - Shape: `protected $signature`, `$description`, an injected `handle()`, and `self::SUCCESS`/`FAILURE` (PublishingIndexExemplars, PublishingCanonHash).
  - JSON output: `$this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))` (PublishingImportArchive:53).
  - A missing target calls `$this->error(...)` and returns `self::FAILURE` (PublishingIndexExemplars:36-40).
  - Tests use `$this->artisan(...)->assertSuccessful()` or `assertFailed()`. For JSON, call `Artisan::call(...)`, then `json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)`.
  - Generate files with `php artisan make:command PublishingEditDistances --no-interaction`, `php artisan make:job RecordEditDistance --no-interaction` and `php artisan make:class Services/Publishing/EditDistance --no-interaction`. Check the generated namespace.
- **Migration**
  - Copy `2026_10_02_300000_add_posted_fields_to_articles_table.php`: anonymous class, a class docblock, `Schema::table`, and a `down()` that drops what `up()` added.
  - `up()`: `$table->integer('agent_edit_distance')->nullable(); $table->foreignId('agent_baseline_revision_id')->nullable()->constrained('article_revisions')->nullOnDelete();`
  - `down()`: `dropConstrainedForeignId('agent_baseline_revision_id')`, then `dropColumn('agent_edit_distance')`.
  - Use a single-column FK, not the composite `(article_id, x) → (article_id, id)` used elsewhere. Postgres `ON DELETE SET NULL` on a composite FK would also null the NOT NULL `article_id`.
  - `article_revisions` (2026_09_22_201057:30-46) has no DB triggers; immutability is enforced only in the model.
- **Immutability guard** (`ArticleRevision.php:22-41`, mirroring `ArticleRelease.php:23-41`)
  - The `updating` hook throws `RuntimeException('Article revisions are append-only and cannot be mutated.')` when any `IMMUTABLE_ATTRIBUTES` column is dirty.
  - The new columns are not in that list, so today they would be freely mutable.
  - ReleaseIntegrityTest:59-60 asserts the existing message (`'append-only'`).
- **Fixed finding kinds** (Phase 6 precedent)
  - `EditorialOutput::KIT_FINDING_KINDS` (24).
  - Schema: `$this->finding($schema, EditorialOutput::KIT_FINDING_KINDS)` (EditorialAgent:301) makes `kind` a required enum described "One of: ..." (426).
  - `validateKitFindings` (~456-476) rejects any other kind.
  - Test precedent: `EditorialAgentsTest:121` asserts `['items']['properties']['kind']['enum']`.
- **Rubric file**: `roles/voice-reviewer.md` must keep its `<!-- lens-section: voice -->`, `craft` and `framework-adherence` markers (ReviewRubricTest:30-34, 59-71). Add the kind definitions to its closing "How to report" section.

## Dependencies (Phase 9)

- **`WriteArticle::save` (90-170)**
  - **Parent revision**: it is `$lockedArticle->working_revision_id` (144, read again as `$previousRevisionId` at 153). Its origin is not loaded today. Read it inside the transaction with `ArticleRevision::query()->whereKey($previousRevisionId)->value('origin')`.
  - **Replays**: a mutation-id replay returns the existing revision early (110-123). Dispatch only after `ArticleRevision::create` (141), never on a replay.
  - **Callers with origin `human`**: the workspace `saveDocument` (353-379, autosave), `saveDetails` (139-148, through saveDocument), `saveKitFields` (1060-1075, through saveDocument) and the protection toggle (~954).
  - **Callers with agent origins**:
    - `agent-initial`: `RunEditorialActivity::applyDraft` (625) and `applyDraftKit` (651).
    - `agent-repair`: RunEditorialActivity:1285.
    - `agent-accepted`: `ApplyEditorialProposal:107`, `AcceptPackageProposal:77-85` (a metadata-only change) and `SpawnDistributionKit:135` (a kit proposal the owner accepted).
  - **Imports**: `ImportWritingArchive:92-97` creates `origin = 'import'` revisions directly with `ArticleRevision::create`, bypassing `save()`, so imports never dispatch.
  - **Legacy `'agent'` origin**: still listed in the protected-block guard (342), but nothing writes it. Include it in the agent set or not, and record the choice.
  - **Nested transactions**: SpawnDistributionKit, AcceptPackageProposal and ApplyEditorialProposal wrap `save()` in their own `DB::transaction`. `afterCommit` waits for the outermost commit and drops the job on rollback.
- **Queue behaviour**
  - **Tests run the job inline**: `phpunit.xml` sets `QUEUE_CONNECTION=sync`. `SyncQueue::push` (vendor/laravel/framework/src/Illuminate/Queue/SyncQueue.php:195-207) registers `afterCommit` jobs on `db.transactions` and runs them at commit. An exception in the job propagates out of `save()` to the caller after the revision has committed.
  - **Production does not**: the default connection is `database` (`.env.example:38`, config/queue.php:16). Use the default queue; `RunEditorialActivity::QUEUE` is `publishing-agents`, and `composer dev` runs workers for both queues.
  - **Tests that will run the job inline** (human save after an agent revision): VoiceLintWorkflowTest (workspace saveDocument after an agent draft or repair), DistributionKitTest (kit field edits after `agent-initial`), PackagingTest and EditorialReviewTest (saves after `agent-accepted`), and AdminPublishingEditorTest's proposal flows. A buggy job turns them red.
  - **Tests that stay green**:
    - `Queue::assertNothingPushed()` (AdminPublishingEditorTest:467,506,535,546; AdminHomeTest:84) and `Bus::assertNothingDispatched()` (PublishingAgentSettingsTest:427) only save first revisions (null parent) or don't save.
    - `ExemplarRetrievalTest:432` asserts `assertPushedTimes(IndexPublishedExemplar::class, 1)`, which counts only that class.
- **`ArticleRevision`**
  - `#[Fillable]` (13) excludes the new columns, and strict mode throws on mass-assigning them (`.ai/rules/app.md`). The job must write with `forceFill` or a query-builder update.
  - No app code hashes or serializes whole revision rows: searching for `toArray`/`getAttributes` on revisions finds nothing. Content hashes cover only `document` and `metadata` (WriteArticle:101-104), so setting the new columns later changes no hash.
  - There are 18 `ArticleRevision::query()` call sites, many with column-constrained selects. None reads the new columns.
- **Voice finding kinds**
  - **Schema**:
    - `EditorialAgent::schema` 271-273 shares one arm for ReviewFacts, ReviewVoice and ReviewBuyer through `finding($schema)`, which makes `kind` a free, nullable string. `newBlockingFindings` (289) also uses `finding($schema)`.
    - Split ReviewVoice into its own arm. Don't change the shared description: `EditorialAgentsTest:113` pins FactReviewer's to `'At most 25 findings.'`.
  - **Validator**: `EditorialOutput::validate` (line 46) sends all three lenses to `validateFindings` (574-596), which ignores `kind`. If the schema becomes an enum, add a voice arm that mirrors `validateKitFindings` but keeps the returned severity.
  - **Storage**: `RunEditorialActivity::storeFindings` (898-926) stores `kind` (default `editorial`) and `block_id`. It does not check `block_id` against the manuscript.
  - **Faked voice findings in the suite**:
    - Only `ReviewRubricTest::reviewRubricFinding` (167-170) returns a finding, with no `kind`. It is used at 187 and 215 for all three lenses.
    - Every other faked voice payload returns `['findings' => []]`: EditorialInputContractTest:238, PublishingJourneyTest:94, VoiceLintWorkflowTest:122, EditorialWorkflowTest:320-328, AdminPublishingEditorTest:123 and ReleaseIntegrityTest:736.
    - Rows created directly with `EditorialFinding::create` and `kind => 'style'`/`'voice'` (EditorialReviewTest, ReleaseReadinessTest) bypass the validator.
- **Canon edit** (`roles/voice-reviewer.md`)
  - It changes the canon version that every new activity freezes; no test pins a literal hash.
  - `review_voice` freezes `voice/core.md` and `roles/voice-reviewer.md` (roles.json), so the EditorialInputContractTest row (rubric heading `# Core voice`) does not change.
  - The edit must keep the three lens markers and add no `[rule:` tag, which would break the two-way lint check and the VoiceCanonTest tag counts.
  - It must not mention any `*.md` path outside the canon, `~/.config`, `/Users/`, `FW/` or "voice sample" (VoiceCanonTest).
- **Canon hash consumers**: `PublishingCanonHash` prints `app(VoiceCanon::class)->snapshot()->version`. Frozen inputs carry the same value as `canon.version` and `canon.hash` (`CanonSnapshot::frozenFor` 103-117). The trial must read `canon_hash` from the same service.
- **Retrieval inside the trial**
  - **When it runs**: `ApprovePublishingStage::exemplarContextFor` (104-121) returns null while agents are paused and for non-blog pieces.
  - **The query**: `ExemplarRetriever::forAttempt` (34) embeds `queryText` (81-94), built from `brief.summary`, `brief.target_query`, `angle.thesis` and `plan.argument`. An empty query or an empty index gives status `insufficient`.
  - **Indexing**: `publishing:index-exemplars` (PublishingIndexExemplars) indexes blog posts that have a `published_release_id`. `IndexExemplarSections::isEligible` (105-109) skips the four canon slugs.
  - **Import**:
    - `ImportWritingArchive::write(source, baseline, operator)` publishes each essay (sets `published_release_id`, 133-140) and needs an existing operator (`--actor`).
    - A relative source path resolves through `base_path` (ArchiveMarkdownImporter:392). It runs no git commands, and the default baseline string works.
  - **What the reviewer sees**: the four canon essays and the site copy, in full, as `input.canon.exemplars` (roles.json `review_voice.exemplars: true`; `CanonSnapshot::exemplars` 59-80).
- **A voice review on its own**: `queueReconciliationAfterCompletedReviewLens` (RunEditorialActivity 1371-1412) returns unless the facts, voice and buyer reviews all completed on the revision. A trial voice review therefore starts nothing else.

## Conventions (Phase 9 additions)

- **Names**
  - `App\Services\Publishing\EditDistance`. The spec sketches it as `final class`; `DocumentBlocks` and `KitDocument` are `final` service precedents.
  - `App\Jobs\RecordEditDistance`.
  - `App\Console\Commands\PublishingEditDistances`, signature `publishing:edit-distances {idea}`.
- **Test helper prefixes**
  - Helpers: `driftFixture*` (DriftFixtureTest), `driftTrial*` (VoiceDriftTrialTest; required by `.ai/rules/tests.md` and the spec), `editDistance*` (EditDistanceTest).
  - Constants: `DRIFT_FIXTURE_*`, `DRIFT_TRIAL_*`, `EDIT_DISTANCE_*`.
  - Names already taken include `liveTrial*`, `exemplarRetrieval*`, `reviewRubric*`, `distributionKit*`, `inputContract*`, `voiceLint*`, `lintWorkflow*` and `packaging*`, and the constants `REVIEW_RUBRIC_LENSES`, `EXEMPLAR_RETRIEVAL_ENDPOINT` and `VOICE_CANON_RULE_IDS`.
- **Fixture location**
  - `tests/Fixtures/Publishing/drift/passages.json`, beside the existing `tests/Fixtures/Publishing/archive/`.
  - Read it with `json_decode((string) file_get_contents(base_path(...)), true, flags: JSON_THROW_ON_ERROR)`.
  - Narrow the shape with guards that fail the test (PHPStan level 7; no `@phpstan-ignore`, no inline `@var`).
- **Finding kinds**
  - Values: `voice_drift`, `clarity`, `framework`, `register` (snake_case, like `claim_outside_manifest`).
  - Keep them in one constant (for example `EditorialOutput::VOICE_FINDING_KINDS`) that the schema, the validator and the trial all use.
- **Errors**
  - Domain refusals throw `RuntimeException` with the reason. The trial's refusals start "Refusing the drift trial ...".
  - The job reports nothing and never throws for a missing or already-measured revision.
- **Strict Eloquent**: the command and the job must select every column they read, and write non-fillable columns with `forceFill` or a query-builder update.
- **Run after PHP edits**: `vendor/bin/pint --dirty --format agent`, then `env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress`. PHPStan runs at level 7 over app, config, database, routes and tests, including tests/Manual.

## Risks (Phase 9)

- **`walkthrough-link` makes every bare passage dirty and can fake a catch.**
  - `WalkthroughLink::check` returns a hit on the **last block** of any BlogPost target with no /walkthrough link. A 1-3 paragraph passage never has one.
  - **Real passages**: the fixture test's zero-hits assertion fails. The trial would also mark all 10 as `drift_flagged` (any lint hit counts), which fails G3's limit of 2.
  - **Slopped passages**: the hit counts as `caught_at_injected_span` whenever the last block is injected, which it usually is for a "generic conclusion" tell. That inflates the caught count.
  - **Fix**: lint passages without `walkthrough-link` (an excerpt cannot hold the whole post's closing invitation). Do it in both DriftFixtureTest and the trial, through one shared builder, and record the choice.
  - **Why not append a CTA paragraph instead**: the reviewer would see the same added paragraph in all 20 manuscripts, and a `voice_drift` finding on it would flag a real passage.
  - **Metadata**: most metadata rules return nothing when the field is missing (`MeasuresMetadataLength` and `SlugShape` on null), but `title-sentence-case` fires on a Title Case `title`. Save trial revisions with no title or a sentence-case one.
- **Lint alone can pass G3.**
  - A slopped passage counts as caught when lint or the reviewer hits an injected block.
  - The spec requires 2-3 tells per passage with at least one non-mechanical, but nothing stops every slopped passage from also carrying a mechanical tell. Lint could then catch all 10, and the gate would pass even if the reviewer caught nothing.
  - That matches G3's literal "lint plus VoiceReviewer", but not the spec's claim that the trial "measures the VoiceReviewer and not only the linter".
  - Record `caught_by_lint` and `caught_by_reviewer` per attempt (extra keys don't affect the `jq` check), and consider giving several slopped passages only non-mechanical tells. This is the owner's call; record it.
- **The reviewer sees each real passage in its own exemplars.**
  - The real passages come verbatim from the four canon essays and the site copy, and `review_voice` receives those in full as `input.canon.exemplars`.
  - So the real-flagged count is partly a recognition test, and each slopped variant sits next to its original.
  - This follows the contract (G3 says "canon or site-copy passages"), so it does not contradict the decision log. Note it in the implementation notes for the owner.
- **Reviewing an excerpt as if it were a whole post.**
  - The rubric judges sections, headings, first and last sentences, and frameworks. On a 1-3 paragraph excerpt the reviewer may report structural gaps. If those come back as `voice_drift`, they flag real passages.
  - Leave `framework` out of the trial's plan and `post_type` out of its brief. Then `input.canon.framework` and `post_type` are absent, and the rubric skips framework adherence (voice-reviewer.md: "When input.canon.framework is absent ... skip this section"; `StartEditorialActivity::structureCanon` 172-208).
  - Define `voice_drift` narrowly in the rubric (voice alignment, soul check, swap test, final audit), and give clarity, framework and register their own kinds.
  - Don't tailor the rubric to the trial.
- **Enforced enum or description only.**
  - The spec changes only the rubric and the schema description. A description alone lets the model return other strings (stored as given, or as `editorial` when null), but `drift_flagged` matches `voice_drift` exactly.
  - Recommended: a required enum for ReviewVoice through `finding($schema, VOICE_FINDING_KINDS)`, plus a validator arm (the validator-to-schema audit).
  - That makes `ReviewRubricTest::reviewRubricFinding` (no `kind`) fail the voice runs at 187 and 215. Give it a `kind`: either one per lens, or `voice_drift` for all three, since the facts and buyer lenses accept any string.
  - Either way, record the choice and add a schema assertion to EditorialAgentsTest.
- **Autosave makes the G6 measurement nearly trivial.**
  - The editor autosaves after 250 ms idle (`resources/js/admin/publishing/autosave.js:3`), and every save is a revision.
  - So the "owner's next saved revision" after an agent revision is usually the first burst of typing, and later human saves have a human parent and record nothing. Most recorded distances will be 1-5 words, not the owner's total edit.
  - The contract (G6) and the spec say exactly "the owner's next saved revision", so implement it as written. Flag this for the owner in the implementation notes; changing the baseline is a contract change.
  - One option for the owner (not the builder): have the command report the distance from the latest agent revision to the current working revision.
- **The job runs inline in tests.**
  - Under the sync queue, any exception in `RecordEditDistance` propagates out of `WriteArticle::save`, and out of workspace autosaves as `saveError`, across many Feature files.
  - Make the job never throw:
    - Return when the revision is missing, its parent is not agent-authored, or the column is already set.
    - Treat a malformed document as having no words.
- **Canon hash after Phase 8.**
  - The trial runs `migrate:fresh` on `birdcar_test`. After Phase 8, the versions table there is empty, so the snapshot falls back to the repo files.
  - The `jq` check runs `php artisan publishing:canon-hash` against the development database, which returns the active Admin version once one exists. The two match only while the active version is the repo version.
  - Read `canon_hash` through `app(VoiceCanon::class)->snapshot()->version` (the same source as the command), and note the limitation in the trial's header comment.
- **Cost and spend.**
  - One run makes about 20 VoiceReviewer calls, about 20 query embeddings, and the section embeddings for the 6 archive essays outside the canon.
  - Per `.ai/rules/ai.md`, the app reserves no spend; the owner confirms the OpenRouter workspace first.
  - Keep VoiceReviewer's `#[MaxTokens(16000)]` and medium effort; don't lower them.
- **Decision log checked against the code; no contradictions.**
  - "Recomputed from recorded attempts and pinned to the current canon hash": `publishing:canon-hash` exists and prints the same value activities freeze.
  - "The drift trial runs against local Postgres": the suite already runs on `pgsql` and `birdcar_test`, and the trial guard already requires them (PublishingModelTrialTest:85-100). Retrieval needs vector columns, which SQLite cannot hold.
  - "Learning from the owner's edits is out of scope": nothing in the app clusters edits or updates the canon from them, and the spec only measures.

## Edge Cases (Phase 9)

- **Golden-set fixture** (`passages.json`)
  - **Counts and pairing**:
    - Exactly 10 real and 10 slopped passages, with ids unique across both lists.
    - Every slopped `real_id` names a real id, and each real id has exactly one slopped variant.
  - **Real sources**:
    - `source` is an exemplar id from `app(VoiceCanon::class)->snapshot()->exemplars()`: one of the four archive slugs, or `exemplars/site-copy-2026-10-02.md`.
    - Seven come from the essays and three from the site copy (spec step 1).
  - **Blocks**:
    - Each passage has 1-3 blocks of non-empty plain text.
    - Optionally assert that each real block appears verbatim in its source exemplar, after collapsing whitespace. The essays keep Markdown (1-3 `[text](url)` links each), so pick paragraphs without links or the check fails.
  - **Injected tells (slopped)**:
    - Every `block_index` is an in-range integer, every `tell` is non-empty, and `mechanical` is a boolean.
    - Each passage has 2-3 injected tells, at least one with `mechanical: false` (spec step 2).
    - Name non-mechanical tells from a fixed list: `flattened_rhythm`, `no_turn`, `synonym_cycling`, `generic_conclusion`, `neutral_reporting`.
    - Name each mechanical tell after the lint rule id it injects, so the fixture test can assert that the rule fires on its block.
  - **Honest records**: optionally assert that every lint hit on a slopped passage lands on an injected block.
  - **Lint-clean real passages**:
    - Real passages must be lint-clean under the shared passage builder, without `walkthrough-link` (see Risks).
    - What the sources contain (checked this session): no em dashes or curly quotes in the four essays or the site copy; "we/our" appears 1-2 times in each; one "!" in `your-ai-wrote-a-bug`; and the site copy has "we agree on scope".
    - Skip paragraphs with those, plus "10x", "kinda", profanity and practitioner jargon (spec).
  - **Spec experiments**: a real passage containing "10x", a slopped passage with only mechanical tells, and an out-of-range block index each fail one assertion.
- **Passage document**
  - One paragraph per block, with id `blk_` plus the first 16 hex of `sha256('drift:<passage_id>:<index>')`.
  - Ids must match `/^(blk|imp)_[0-9a-f]{16}$/` and be unique. Otherwise `WriteArticle::ensureNodeIds` (282-318) silently replaces them, and injected indexes no longer map to the right blocks.
  - Save with metadata `[]` or a sentence-case `title`.
- **Trial, per passage**
  - **Build the attempt while paused**:
    - `captureIdea(owner, <neutral idea>)`, then `save(owner, piece, null, passageDocument, metadata, 'drift-<passage_id>')`.
    - `develop(owner, piece, revisionId, ['summary' => ..., 'target_query' => ...])`, then approve Angle and add a completed ResearchChallenge row.
    - `rethink(plan: ['outline' => [...], 'visualPlan' => [...], 'argument' => ...])` with no `framework`.
  - **Then unpause**:
    - Approve Plan. Retrieval runs and a Pending Draft is created; `Queue::fake()` stops it from being dispatched.
    - Force-complete that Draft.
    - `start(owner, attempt, ReviewVoice, ['draft_activity_id' => $draft->id], 'drift-review')`, then run it.
  - **Lint**: lint the working revision through the shared builder, or use `LintRevision::hits(article, revision)` and drop `walkthrough-link` hits.
  - **Neutral text**: give the brief and plan neutral text, so retrieval has a query and nothing hints whether the passage is real or slopped.
  - **Isolation**: each passage gets its own idea and piece, so no state leaks between passages. Retrieval also excludes the piece's own idea (`ExemplarRetriever::forAttempt` 43-44).
- **Recording**
  - **File shape**: one object, `{canon_hash, run_id, started_at, attempts}`. Rewrite the whole file after each attempt so a crash keeps the evidence so far. A partial run fails the `jq` check (it needs 20 distinct passage ids), as intended.
  - **Each attempt**:
    - Fields: `{passage_id, variant: real|slopped, caught_at_injected_span, drift_flagged, lint_hits: [{rule_id, block_id}], findings: [{kind, block_id, severity, statement}], model, status, error}`.
    - Optional fields: `retrieval_status`, `caught_by_lint` and `caught_by_reviewer`.
  - **Flags by variant**: set `caught_at_injected_span` only for slopped passages and `drift_flagged` only for real ones. Set the other flag to `false`; the `jq` check filters by variant.
  - **Model**: take `model` from `model_snapshot.returned_model`, falling back to `requested_model`.
  - **Failures**:
    - `status = completed` only when the activity completed.
    - A provider error, validation failure or pause records `failed`, with `pause_reason ?? error_reason` as the error. With `max_retries` 0 the first failure goes straight to `failOrPause` (RunEditorialActivity 1096-1104).
    - A failed passage counts as not caught (slopped) or not flagged (real). No retries.
  - **Error handling**:
    - Wrap each passage in `try/catch (Throwable)` so one failure is recorded and the run continues (spec Error Handling).
    - Refusals (isolation, either OpenRouter key, the URL) throw before `migrate:fresh` and before any request.
  - **Evidence citations**: a reviewer finding that cites `supporting_source_ids` fails validation, because the trial attempts have no evidence. The activity then fails and is recorded as failed. The rubric already forbids citing.
- **Edit-distance dispatch** (`WriteArticle::save`)
  - **Condition**: dispatch only when `$origin === 'human'`, the parent (`$previousRevisionId`) is not null, and the parent's origin is in `['agent-initial', 'agent-repair', 'agent-accepted']`. Decide whether to include the legacy `'agent'`.
  - **How**: call `RecordEditDistance::dispatch($revision->id)->afterCommit()` inside the transaction, after `create`. Never dispatch on a mutation-id replay or a rolled-back save.
  - **Cases**:
    - A human save after a human save dispatches nothing.
    - A human save after `agent-initial` dispatches once.
    - A human save after `agent-accepted` (the owner accepted a proposal) dispatches.
    - An agent save after a human save dispatches nothing.
- **Job** (`RecordEditDistance(int $revisionId)`)
  - **Load**: the revision (`id, article_id, parent_revision_id, origin, document, agent_edit_distance`) and its parent (`id, origin, document`).
  - **Return early** when either is missing, when the revision is not `human`, when the parent is not agent-authored, or when `agent_edit_distance` is already set. This makes the job idempotent.
  - **Write**: use `ArticleRevision::query()->whereKey($id)->whereNull('agent_edit_distance')->update([...])`, which is atomic and safe if the job is dispatched twice. Alternatively, `forceFill()->save()` through the guard. An Eloquent builder update also bumps `updated_at`.
  - **Scope**: write only to the revision it was dispatched for, even when later revisions exist (spec Error Handling).
- **Guard** (`ArticleRevision::booted`)
  - **Rule**: keep the existing immutable list. Add: if `agent_edit_distance` or `agent_baseline_revision_id` is dirty and its original value is not null, throw (for example "Edit distance is recorded once.").
  - **"Only from the job"** is a convention: the job is the only writer. Say so in the docblock.
  - **Tests**: the job sets both columns; a later `forceFill(['agent_edit_distance' => 1])->save()` throws; editing `document` still throws `append-only`.
  - **Strict mode**: on a partially selected model, select both columns before calling `getOriginal`, or check `array_key_exists` on `getRawOriginal()`.
- **`EditDistance::between(array $baseline, array $revised): int`**
  - **Text**:
    - Walk every text node in document order: paragraphs, headings, list items, blockquotes, code blocks, notes and callouts.
    - Treat `hardBreak` and block boundaries as whitespace, so words in adjacent blocks don't fuse.
    - Decide whether note and callout `attrs.title` and chart and diagram `attrs.caption` count, and record the choice.
  - **Tokens**: lowercase with `mb_strtolower`, then match words as `[\p{L}\p{N}]+` with inner apostrophes (accept both ' and ’), using the `/u` flag. Punctuation-only edits then measure 0; record that.
  - **Performance**: trim the common prefix and suffix, then run a two-row DP over the shorter middle part. Typical small edits become almost free, and a full 4,000-word rewrite is at most 16M cells with only two arrays in memory.
  - **Experiments**:
    - Identical documents give 0, and one replaced word gives 1.
    - An inserted paragraph of n words gives n, and an empty document against n words gives n.
    - The distance is symmetric.
- **Command** (`publishing:edit-distances {idea}`)
  - **Bad input**: a non-numeric or missing idea id prints an error and returns FAILURE.
  - **Pieces**:
    - Query: `Article::query()->select(['id', 'kind', 'content_idea_id'])->where('content_idea_id', $id)->orderBy('id')`.
    - This includes siblings and kit pieces. acceptance.json needs exactly the four kinds, so the owner picks which pieces to copy.
  - **`edit_distance` per piece**:
    - The spec's shape has one value, but a piece can record several (for example `agent-initial` then human, and later `agent-accepted` then human).
    - Pick one meaning (the latest recorded value is the simplest). Optionally add `distances: [{revision_id, baseline_revision_id, edit_distance}]`.
    - The value is null when nothing was recorded. Record the choice.
  - **Field values**: `kind` is the enum value (`blog_post`, `linkedin_post`, `bluesky_thread`, `email_blurb`), matching the acceptance `jq` check. `summary_title` comes from `content_ideas.summary_title`.
  - **Access**: read-only, with no authorization, like the other owner-run publishing commands.

## Test Strategy (Phase 9)

- **Inner loop**: `php artisan test --compact tests/Feature/Publishing/EditDistanceTest.php tests/Feature/Publishing/DriftFixtureTest.php`
- **Paid trial skip check**: `vendor/bin/pest tests/Manual/VoiceDriftTrialTest.php --compact` must report the test as skipped. Never set `DRIFT_TRIAL=1`.
- **Regression files** (they consume the changed code):
  ```
  php artisan test --compact tests/Feature/Publishing/ReviewRubricTest.php tests/Feature/Publishing/EditorialAgentsTest.php tests/Feature/Publishing/EditorialInputContractTest.php tests/Feature/Publishing/VoiceCanonTest.php tests/Feature/Publishing/VoiceLintTest.php tests/Feature/Publishing/VoiceLintWorkflowTest.php tests/Feature/Publishing/DistributionKitTest.php tests/Feature/Publishing/PackagingTest.php tests/Feature/Publishing/EditorialReviewTest.php tests/Feature/Publishing/ReleaseIntegrityTest.php tests/Feature/Publishing/AdminPublishingEditorTest.php tests/Feature/Publishing/AdminPublishingWorkspaceTest.php tests/Feature/Publishing/PublishingSessionTest.php tests/Feature/Publishing/DocumentRoundTripTest.php tests/Feature/Publishing/PublishingJourneyTest.php tests/Feature/Publishing/ExemplarRetrievalTest.php tests/Feature/AdminHomeTest.php
  ```
- **Phase check**: `php artisan test --compact tests/Feature/Publishing`
- **Final**: `vendor/bin/pint --dirty --format agent`, then `env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress`, then `composer test`.
- **DriftFixtureTest** (no HTTP; the database is touched only through RefreshDatabase)
  - Load `passages.json` and assert the counts, pairing, sources (against the ids from `app(VoiceCanon::class)->snapshot()->exemplars()`), injected block indexes and non-mechanical tells.
  - Lint each real passage through the shared builder and expect `[]`: `LintTarget::fromDocument($document, [], PieceKind::BlogPost)` passed to `VoiceLinter::lint`, without `walkthrough-link`. Use one dataset row per passage id so a failure names the passage.
  - Optionally assert that each mechanical tell's rule fires on its injected block in the slopped variant.
- **EditDistanceTest**
  - **Math**: call `app(EditDistance::class)->between(...)` with the spec's experiments, plus a 4,000-word case built from a generated word list.
  - **Setup**: `beforeEach` runs `forgetCachedPermissions()` and `authorization:sync`; the actor has the Author role.
  - **Dispatch**:
    - `Queue::fake([RecordEditDistance::class])`, `captureIdea`, then `save(..., 'agent-initial')` followed by `save(..., 'human')`.
    - Assert `Queue::assertPushedTimes(RecordEditDistance::class, 1)` with the matching `revisionId`.
    - Assert that a human save after a human save pushes nothing, and that a mutation-id replay pushes nothing more.
    - A save with an agent origin needs only `publishing.write`; no attempt is needed.
  - **Job**:
    - Without a fake (sync queue), the human save runs the job at commit. Assert `agent_edit_distance` and `agent_baseline_revision_id` on the fresh revision.
    - Assert that a second `handle()` changes nothing, and that the guard throws on a later change.
  - **Command**:
    - Build an idea with a flagship (`captureIdea`) and three kit pieces (`addPiece($actor, $idea, PieceKind::LinkedinPost|BlueskyThread|EmailBlurb, $flagship)`), each saved as `agent-initial` and then `human`.
    - Run `Artisan::call('publishing:edit-distances', ['idea' => $idea->id])` and decode with `json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)`. Assert the shape, the four kinds and that every distance is non-null.
    - Cover a missing idea with `$this->artisan(...)->assertFailed()`.
- **Voice finding kinds**
  - Assert that the VoiceReviewer schema's `kind` (enum or description) lists the four values. Add this to EditorialAgentsTest beside line 121, or to DriftFixtureTest.
  - Assert that `roles/voice-reviewer.md` names all four kinds.
  - If the validator enforces them, add one rejection case: `EditorialOutput::validate(ReviewVoice, ['findings' => [['statement' => 'x', 'kind' => 'style', ...]]])` throws.
- **Environment**
  - `birdcar_test` and `birdcar_development` were confirmed on 127.0.0.1:5433 (`psql -l`) this session; Laravel is 13.33.0. No `--parallel`.
  - Never run `tests/Manual` with its flag set. PHPStan still analyses it, so the trial must pass level 7: narrow the results of `json_decode`, `file_put_contents` and `getenv`.
  - `QUEUE_CONNECTION=sync` runs the edit-distance job inline at commit unless it is faked.

---

## Key Patterns (Phase 6)

- **Lint rule base** (spec pattern: "Phase 3 lint rules")
  - `VoiceRule.php` gives `appliesTo()` (BlogPost only), `blockHit`, `metadataHit` and `excerptAround`.
  - `PatternRule.php` handles regex rules and can also check metadata (`checksMetadata()`).
  - `MeasuresMetadataLength.php` counts with `mb_strlen` and reports the measured length in the message ("SEO title is 48 characters; it must be 50-60."). Kit length rules should follow it.
  - `WalkthroughLink.php` shows a structural rule that reads `$target->blocks` and `$block['links']`. Its HREF regex is `~^(?:https?://(?:www\.)?birdcar\.dev)?/walkthrough/?(?:[?#].*)?$~i`; reuse it for `kit-no-walkthrough-cta`.
- **Registration**
  - All 26 rules are tagged in `AppServiceProvider::register()` 52-79 with `VoiceLinter::TAG`. Add the 9 kit rules to that list.
  - The two-way check compares `VoiceLinter::ruleIds()` with `CanonSnapshot::ruleIds()`, which scans every canon file for `[rule:id]` (CanonSnapshot.php ruleIds). Tags in `voice/kit-rules.md` therefore join the check automatically.
  - `channels/kit.md` and `exemplars/short-form.md` must contain no `[rule:` tags.
- **`LintTarget`** (`final readonly`)
  - Signature: `fromDocument(array $document, array $metadata, PieceKind $kind, ?int $articleId)`.
  - It produces one block per paragraph and heading, plus note and callout titles and chart and diagram captions.
  - The LintBlock shape is `{id,type,level,text,bold_spans,links,in_blockquote,protected}`.
  - `links` comes only from link marks; a bare URL typed as text is not a link. `hardBreak` becomes a space.
  - `METADATA_FIELDS = ['title','seo_title','description']`.
  - Kit rules need two things LintTarget lacks: a field role for each block (LinkedIn text or first comment; Bluesky post i; email subject, preview or body) and lint context (the flagship canonical URL and target query).
- **`VoiceLinter::lint`**: orders hits by `METADATA_ORDER` and then block order, and numbers repeated hashes as occurrences.
- **Canon files**
  - `roles.json` entries have the shape `{"files": [...], "exemplars": bool}`. Setting `exemplars: true` adds the 4 essays and the site copy.
  - For `draft_kit` and `review_kit`, keep `exemplars: false` and list `exemplars/short-form.md` under `files` for draft_kit, as the spec says.
  - Never add short-form.md to `exemplars/manifest.json`: that would change `INPUT_CONTRACT_EXEMPLAR_IDS` (EditorialInputContractTest:28) and the VoiceCanonTest exemplar test (160-186).
  - The rubric file model is `roles/lint-repairer.md` or `roles/fact-reviewer.md`: a `# X rubric` heading, a one-line job, then `##` sections. FactReviewer already says: "When a piece derives from a flagship post, any claim outside the flagship's approved claims is a blocking finding."
- **Role agents**
  - KitDrafter: copy `Drafter.php` (gemini-3.8-flash, medium).
  - KitReviewer: copy `FactReviewer.php` (deepseek-v4-pro-0813, high).
  - Both get `#[MaxTokens(16000)]` per the spec. Both models are allowlisted in `config/publishing_agents.php:9-10`.
  - `.ai/rules/ai.md` says MaxTokens is a runaway guard, not a cost lever.
- **Schema** (`EditorialAgent::schema` 204-301)
  - Kit fields are plain strings and arrays, so they can be native schema types (`$schema->string()->max(n)->description(...)`, `$schema->array()->items(...)`). Only nested Tiptap JSON travels as an encoded string.
  - Put every limit the validator enforces into the description; this is the validator-to-schema audit from contract line 154.
  - Read the piece kind from frozen input (for example `input.piece_kind`). `recommendedModelFor()` (EditorialAgent:72-75) and EditorialAgentsTest's `editorialActivity()` build activities with no article, and `sentEditorialAgentRequest` sends the schema, so the schema must not throw when the piece kind is missing.
  - For review_kit, the finding builder (`finding()` 367-380) needs a `kind` enum of `claim_outside_manifest`, `register` and `channel`.
- **Output validation** (`EditorialOutput::validate(kind, payload, knownEvidenceIds, evidenceTexts, frozenInput)` 31-47)
  - `allowedKeys(kind)` (79-92) cannot see the piece kind. Pass frozen input to it, or allow the union of keys and check the exact set in `validateDraftKit`.
  - `validateFindings` (488-514) calls `ensureEvidenceIdsExist` against the frozen `evidence_sources`. Kit attempts have none, so KitReviewer must return `supporting_source_ids: []` and `supporting_quotations: []`. Any manifest source id it cites fails the activity.
  - Keep EditorialOutput constructor-free (EditorialAgentsTest:247 calls `new EditorialOutput`).
- **Post-draft chain** (RunEditorialActivity)
  - `applyResult` 445-480 branches on kind.
  - `applyDraft` 610-631 writes an `agent-initial` revision only into an empty document; otherwise it stores a proposal.
  - Then `lintThenQueueReviews` (1123-1160), `finishLintAfterUnusableRepair` (1169-1205) and `applyLintRepair` (1211-1270).
  - `agentWrittenBlockHashes` (1278-1330) treats the whole applied Draft as agent-written, then each block a repair changed.
  - `storeFindings` (872-900) stores `lens` as the kind value and `input_hash` as the activity's `revision_hash`.
- **Creating an attempt directly**
  - `AdvancePublishingAttempt::develop` 28-69 shows the fields: `PublishingAttempt::create([... 'stage', 'input_version' => working_revision_id, 'brief', 'angle' => [], 'plan' => [], 'interview_context' => [], 'review_cycle' => 1, 'recheck_used' => false])`, then `current_attempt_id` is set on the locked article.
  - SpawnDistributionKit does the same with `stage => InReview`. `input_version` is null because a kit has no revision yet.
- **Adding a piece**: `WriteArticle::addPiece` 54-63 requires `publishing.write` and a parent from the same idea. `createPiece` (205-214) sets `author_id = $actor->id`, a provisional `idea-xxxxxxxxxxxx` slug and an explicit kind.
- **Release approval and the flagship URL**
  - `ManageArticleRelease::approve` 301-357 runs in one `DB::transaction`. Call the spawn after it returns ("after ... commits"); calling it inside would roll the approval back on a spawn error.
  - The canonical slug is `payload.canonical_slug`. Build the public URL like the sitemap does (routes/web.php:77): `app(MarketingSite::class)->url(route('public.article', ['slug' => $slug], absolute: false))`. `/writing` paths get a trailing slash (MarketingSite.php:11-20).
  - The host comes from `config('marketing.url')` (BIRDCAR_MARKETING_URL, falling back to APP_URL). Freeze the URL once at spawn time.
- **Writing a disposition immediately**
  - Precedents: the workspace `overrideVoiceRule` (740-764) with `LintRevision::override` (265-290).
  - `decideFinding` (692-711) only buffers until `finishReview`, and `FinishEditorialReview` requires all three lenses (lines 66-77). Kit findings therefore need a decision action that writes at once (approve gate; a trimmed reason for rejected or false_positive).
- **Starting an interview**
  - `⚡dashboard.blade.php:39-59` (`developIdea`) and the workspace `startDevelopment` (108-122) both use the `['idea' => $body]` brief and input.
  - CreateSiblingPiece should mirror them: `addPiece(BlogPost, no parent)`, then `develop(actor, piece, null, ['idea' => body])`, then `start(Interview, ['idea' => body, <note key> => note], 'interview-1')`.
- **Workspace**
  - `with()` is at 1061. `sessionMode` (1088-1092) maps InReview to `write` and Approved, Scheduled or Published to `release`.
  - The tabs are in the template (`<flux:tab.group>` develop/write/release).
  - Finding cards: `partials/write.blade.php` 50-118 (Voice rules group and editorial findings). Reuse them for the kit panel.
  - `refreshVoiceRuleFindings()` (around 785) is the save-time lint hook. Every kit field save must call it.
- **Test templates**
  - Claim manifest research setup: `PackagingTest.php:443-464` (EvidenceSource rows plus `response.claims` with quotations).
  - Release prerequisites: `ReleaseIntegrityTest.php:666-680` (`approveReleasePrerequisites`), 687-741, and `releaseDocument` and `releaseMetadata` (783-830). The SEO title must be 50-60 characters, the description 150-160, and the document must close with exactly one /walkthrough link.
  - Fake responses routed by the decoded `messages.1.content.kind`: PublishingJourneyTest:30-105 and the `lintWorkflowFakeAgents` helper in VoiceLintWorkflowTest.
  - Running one activity: `app(RunEditorialActivity::class, ['activityId' => $id])->handle(app(EditorialOutput::class), app(WriteArticle::class))`.
  - Run-and-repause pattern: `packagingRun` (PackagingTest:162-176).
- **Canon sources** (local, read)
  - bat-kol `channels/blog.md:185-194`, "Distribution kit":
    - LinkedIn: front-load the target phrase; about 210 characters show before "more" and 3,000 is the ceiling; the link goes in the first comment; no broetry, engagement bait, emoji or hype hook; at most three plain hashtags, at the end.
    - Bluesky: the mechanics of 300 characters per post, `1/n` numbering and each post readable alone, written in the public register.
    - Email: a subject of 50 characters or fewer, one preview line, and 2-4 sentences with the claim and a link to the post; the Walkthrough CTA lives in the post.
    - "Do not link to a post in a social post before the post is published."
  - `channels/bluesky.md:17-22,92-103` covers thread format.
  - `registers/public.md:205-212` has "A short LinkedIn-length line" and "The CTA to The Walkthrough".
  - The repo's `channels/blog.md` has no distribution kit section (Phase 2 left it out), and `voice/core.md:112-134` has the CTA example but not the LinkedIn line.

## Dependencies (Phase 6)

- **New `EditorialActivityKind` cases (`DraftKit`, `ReviewKit`)**: these exhaustive matches throw `UnhandledMatchError` and fail PHPStan level 7 until arms are added.
  - `app/Ai/Agents/EditorialAgent.php:48-62` (`forActivity`) and `:204` (`schema`).
  - `app/Services/Publishing/EditorialOutput.php:35-47` (`validate`) and `:81-92` (`allowedKeys`). **Not in the spec.**
  - `app/Actions/Publishing/RunEditorialActivity.php:1460-1467` (`prerequisiteApprovalFor`).
  - `app/Actions/Publishing/StartEditorialActivity.php:266-279` (`ensureEligible`) and `:824-831` (`activityMatchesCurrentApproval`).
  - `app/Actions/Publishing/ApprovePublishingStage.php:380-386` (`activityMatchesCurrentApproval`).
  - `resources/views/components/admin/settings/⚡publishing.blade.php:213-228` (`roleLabel`) and `:230-245` (`rolePurpose`). **Not in the spec.** The page iterates `cases()` (78, 92, 149, 188), so model overrides for the new kinds appear automatically.
  - These have a `default` arm, but labels are worth adding: `⚡dashboard.blade.php:139-151`, `partials/session.blade.php:52` (working message), and `app/Actions/Admin/ReadAdminHome.php:161-165`. ReadAdminHome's `default => ApprovalKind::Plan` means kit activities are checked against a Plan approval that does not exist, so they never count as current on the Admin home.
  - Tests, **not automatic despite the spec**:
    - `EditorialInputContractTest.php:174-202`: `inputContractResponse` is an exhaustive match.
    - The `->with([...])` dataset at 224-284 is an explicit list.
    - 286-290 asserts the exact `cases()` list.
    - `inputContractStart` (122-150) needs kit prerequisites: a flagship with an approved release, then a kit piece and its attempt.
  - `EditorialAgentsTest.php:38-50` (the `forActivity` chain) and the model dataset at 150-162 are explicit lists. Add `'kit drafter' => [DraftKit, 'google/gemini-3.8-flash', 'medium', 16000]` and `'kit reviewer' => [ReviewKit, 'deepseek/deepseek-v4-pro-0813', 'high', 16000]`.
  - `VoiceCanonTest.php:125-133` iterates `cases()`, so roles.json needs both entries, each starting with a file that begins with `# `. `voice/core.md` does.
- **New `ApprovalKind::Kit`**: more exhaustive matches.
  - `ApprovePublishingStage::snapshotInputHashFor` 124-145, `nextStage` 193-200 (Kit maps to Approved), and `ensureApprovalIsAllowed` 202-213. `approve()` rejects only Release (38-40), so Kit flows through `approve()`.
  - `RunEditorialActivity::approvalInputHashFor` 1504-1528 and `StartEditorialActivity::approvalInputHashFor` 844-866. Their Release arms throw; add a Kit arm that throws.
  - `WriteArticle::save:155`: `invalidateApprovals($attemptId, [ApprovalKind::Release])`. Add Kit so an edit after kit approval revokes the approval.
- **LintRepair is tied to Plan approval, which blocks kit repairs.** Make each of these depend on the piece kind:
  - `StartEditorialActivity::ensureLintRepairEligible` 374-394 requires an active Plan approval and the InReview stage, so it throws "Voice-rule repair requires an approved plan..." for kit attempts.
  - `RunEditorialActivity::staleReason` 1414-1458 calls `prerequisiteApprovalFor(LintRepair)`, which returns Plan. A kit repair would go Stale with "The prerequisite approval changed before the activity could run."
  - `StartEditorialActivity::completedLintRepairProducedRevision` 742-753 and `ApprovePublishingStage::activityMatchesCurrentApproval` use the same Plan mapping.
  - The fix: freeze `piece_kind` (or read the article kind), and give kit kinds no prerequisite approval or a flagship-release check.
- **The post-draft chain is Draft-only**
  - `RunEditorialActivity::lintThenQueueReviews` 1123-1160 continues only after Draft or LintRepair.
  - `agentWrittenBlockHashes` 1297 uses `whereIn('kind', [Draft, LintRepair])`, and the full-document branch is at 1310. Without DraftKit, every kit block looks owner-written, so repair patches are never applied and become findings instead.
  - `actorCanRunKind` 1109-1116: add DraftKit to the kinds that require `publishing.write`.
  - `applyResult` 445-480: add a DraftKit branch (applyDraftKit) and a ReviewKit branch (storeFindings).
  - `LintRevision::finishAndQueueReviews` 206-219 always starts the three lenses (**not in the spec**). For kit kinds, start `review_kit` instead, with `draft_activity_id` and a batch key such as `review-kit-{cycle}-{revisionId}`. It is also reached from `finishLintAfterUnusableRepair` and `skipRepair`.
  - `LintRevision::skipRepair` 221-262 looks up the latest Draft; include DraftKit.
  - `LintRevision::UNREPAIRABLE_RULES` (38) holds only `unresolved-markers`. Add the kit rules no single-block patch can fix (see Risks).
  - `StartEditorialActivity::idempotencyPayload` 706-735 makes review kinds revision-based. Add ReviewKit there. DraftKit keeps `prompt_hash`, with a batch key that includes the flagship release id.
- **Where lint rules apply**
  - Every rule inherits `VoiceRule::appliesTo` (BlogPost only, VoiceRule.php:12-15). Change that one method so body rules apply to every kind.
  - Override it back to BlogPost in `HeadingOrder`, `LabelHeadings`, `SentenceCaseHeadings` and `WalkthroughLink`.
  - The spec does not mention the Phase 4 metadata rules (`SeoTitleLength`, `MetaDescriptionLength`, `SlugShape`, `SlugArchiveReuse`, `TitleSentenceCase`); keep them blog-only too.
  - Decide whether `BoldLimit` and `InlineHeaderList` apply to kits; either is harmless on plain paragraphs.
  - Consumers of the linter:
    - `LintRevision::hits` 52-61 (owner saves, the post-draft step, release).
    - `CheckArticleRelease::voiceRuleState` 429-440.
    - `EditorialOutput::validatePackage` 377-388 (BlogPost target).
    - The workspace `packageLintMessages`.
    - `IndexExemplarSections:131` (BlogPost, reads blocks only).
- **Callers of `ManageArticleRelease::approve`**
  - The workspace `approveRelease` (468-489).
  - ReleaseIntegrityTest (many sites), ReleaseReadinessTest, `EditorialWorkflowTest:62,445`, `PackagingTest:343` and AdminPublishingEditorTest.
  - From Phase 6 on, every flagship approval in these tests spawns 3 kit pieces and their attempts. Agents are paused by default (the PackagingTest helper re-pauses at line 175), so nothing is dispatched.
  - No test counts Articles after approving. The approving users have the Author role, which holds every publishing permission (`app/Authorization/Publishing/Catalog.php`).
- **Public readers**
  - `ReadPublishedWriting.php:20,42` filters only on `whereNotNull('published_release_id')`. The spec's open item: add `where('kind', PieceKind::BlogPost->value)`.
  - It feeds the sitemap and RSS (routes/web.php:69-83) and `pages/writing/*`.
  - Exemplar indexing already filters to blog posts (`PublishingIndexExemplars:30`, `IndexExemplarSections:107`).
  - `ExemplarRetrievalTest:412-434` delivers an *imported* release on a LinkedIn piece, so do not make `deliver()` refuse kit kinds.
- **Article model** (`app/Models/Article.php`)
  - The `#[Fillable]` attribute is at line 15; `casts()` at 24-30.
  - Add `posted_at` (datetime) and `posted_url`.
  - `isKit()` is `$this->kind->isKit()`. `kind` is always set explicitly (Phase 1 lesson).
- **Workspace consumers**: AdminPublishingWorkspaceTest, AdminPublishingEditorTest, PublishingSessionTest, ContentIdeaTest, PublishingJourneyTest and PackagingTest:420 render the workspace. Keep the blog-post layout byte-for-byte where tests assert on it, and branch on `$article->kind->isKit()`.
- **Interviewer rubric**: `roles/interviewer.md` is frozen into every interview; editing it changes the canon version (expected). `ReviewRubricTest` covers only the lens rubrics.
- **Phase 7 and Phase 8 depend on this phase**
  - Phase 7 (spec-phase-7.md:15-23) reads `draft_kit` status and the `approved`/`published` stages, labelled "Ready to post" and "Posted", and nests kit cards under `parent_article_id`.
  - Phase 8 (spec-phase-8.md:101) expects kit ids tagged in the `draft_kit` rubric, so `voice/kit-rules.md` must be listed in `draft_kit`.

## Conventions (Phase 6 additions)

- **Ids and classes**
  - Kit rule ids: `linkedin-length`, `linkedin-hashtags`, `linkedin-link-in-comment`, `bluesky-post-length`, `bluesky-numbering`, `email-subject-length`, `email-blurb-length`, `kit-no-walkthrough-cta` and `kit-links-post`.
  - Classes go in `App\Services\Publishing\Lint\Rules\*` with no suffix (spec names).
  - Tag each id exactly once, in `voice/kit-rules.md` only.
- **Actions**
  - `App\Actions\Publishing\{SpawnDistributionKit, MarkKitPiecePosted, CreateSiblingPiece}`.
  - The spec sketches them as `final class`. Sibling actions are plain `class`, but either is acceptable.
  - Resolve them with `app()`; nothing calls `new` on actions.
- **Migration**
  - Anonymous class: `$table->timestamp('posted_at')->nullable(); $table->string('posted_url', 2048)->nullable();`. `down()` drops both.
  - Use the spec's filename `2026_10_02_300000_...`, which sorts after `2026_10_02_200000_create_exemplar_sections_table`. Phase 5 renamed its `make:migration` output to the spec name the same way.
- **Hashing and errors**
  - Kit approval hash: `PublishingFingerprint::hash([...])`. Never compare jsonb arrays with `===`.
  - Domain errors throw `RuntimeException`.
  - "Mark posted too early" must name the unmet condition. Use `ValidationException::withMessages(['posted_url' => ...])` for an https violation, matching the `renameIdea` precedent.
- **Tests**
  - Helper prefixes: `distributionKit*` and `siblingPiece*`. Constants: `DISTRIBUTION_KIT_*` and `SIBLING_PIECE_*`.
  - Names already taken (global across files): `releaseDocument`, `releaseMetadata`, `approveReleasePrerequisites`, `editorialAuthor`, `inputContract*`, `voiceLint*`, `lintWorkflow*`, `packaging*` and `exemplarRetrieval*`. Copy what you need under the new prefix, because a file run alone does not load other files' helpers.
- **Statuses and labels**: kit stage `approved` shows as "Ready to post" and `published` as "Posted". The kit attempt stays an ordinary `PublishingAttempt`.
- **Run after PHP edits**: Pint `--dirty --format agent`, and PHPStan level 7 across `app`, `config`, `database`, `routes` and `tests`, with no `@phpstan-ignore`.

## Risks (Phase 6)

- **The spec is wrong that EditorialInputContractTest extends itself.**
  - It needs dataset rows, `inputContractResponse` arms, kit prerequisites in `inputContractStart`, and an updated exact-cases list (see Dependencies).
  - The spec also asks for assertions that kit inputs carry `flagship.claim_manifest` and the flagship text.
- **Where kit fields live is ambiguous.**
  - The LintTarget row says "map ... to blocks". The Data Model says "document (one paragraph block per field or post) and metadata (subject, preview, first_comment)".
  - If subject, preview or first_comment live only in metadata, their hits have `blockId = null`. `LintRevision::repairable` (69-72) then never sends them to repair, and `ApplyEditorialProposal` cannot patch metadata.
  - Recommendation: one paragraph block per field and per post, as the single source of truth for the text. Put a role map in metadata (for example `kit_fields: {block_id: role}`) or derive roles from deterministic block ids such as `blk_` plus 16 hex of `sha256('kit:<role>:<n>')`.
  - `ArticleDocument::canonicalizeAttrs` (363-435) keeps only `id` and `protected` on a paragraph, so a custom attr cannot carry the role.
  - Do not store the same text in both places; they would drift on edit. Record the choice.
- **Kit rules need context the linter cannot see.**
  - `linkedin-length` needs the flagship's `brief.target_query`.
  - `kit-links-post` and `linkedin-link-in-comment` need the flagship canonical URL.
  - `LintRevision::hits(Article, ArticleRevision)` builds a target only from the revision and the kind.
  - Options: freeze the context into the kit attempt's `brief` at spawn and have `hits()` pass it into an optional `LintTarget` context property, or store it in kit revision metadata. Every lint call site (save-time, post-draft, kit approval) must see the same context, or hit hashes differ between them.
  - When `target_query` is null, skip the target-query half of `linkedin-length`.
- **Some kit rules cannot be fixed by one bounded block patch.**
  - `bluesky-numbering` (n must equal the post count, which touches every post).
  - `bluesky-post-length` when the fix is splitting a post (that needs a new block).
  - `kit-links-post` with zero or two URLs spread across blocks.
  - Either add them to `UNREPAIRABLE_RULES` or point the hit at the one block where a single patch fixes it (for example the final post or the first comment), with the needed URL in the hit message.
  - The repair loop otherwise uses up the 2-pass cap. It does stop early when a pass applies nothing (`passChangedNothing`, RunEditorialActivity 1154).
- **LintRepairer does not know the kit rules.**
  - The `lint_repair` rubric files are `voice/core.md`, `voice/mechanical-rules.md` and `roles/lint-repairer.md`, and roles.json is keyed by activity kind, not piece kind.
  - Either rely on hit messages carrying the exact fix, or add `voice/kit-rules.md` to `lint_repair`. The second option changes the EditorialInputContractTest "lint repair" row (264-268). Record the choice.
- **The short-form exemplar conflicts with the no-CTA rule.**
  - The spec says to use "the register's LinkedIn-length line and CTA examples". The CTA example (public.md:211-212) is a Walkthrough invitation, but `kit-no-walkthrough-cta` forbids that in kit pieces.
  - Label it in `short-form.md` as the post's closing invitation that kits link to and never repeat, or leave it out. Record the choice.
- **The Drafter schema and test now list kit ids.**
  - The Drafter's `document` description lists all of `VoiceLinter::ruleIds()` (EditorialAgent:240-245), so after Phase 6 it tells the blog Drafter about LinkedIn rules.
  - Filter it to rules that apply to `PieceKind::BlogPost`, and change `VoiceLintTest:276-303`, which asserts every registered id appears, to filter the same way.
  - Add the equivalent KitDrafter audit (spec-phase-8.md:101 expects kit ids in the `draft_kit` rubric).
- **`VoiceLintTest:266-270` goes red.** "only blog posts are linted in this phase" asserts a LinkedIn target returns no hits; under Phase 6 it hits em-dash, corporate-we and the vocabulary rules. Replace it with an applicability test (body rules fire on kits; heading, walkthrough and metadata rules do not).
- **VoiceCanonTest needs updates.**
  - `VOICE_CANON_RULE_IDS` (9-15) must add the 9 kit ids.
  - The mechanical (21) and metadata (5) tag counts at 189-196 stay correct only if kit tags live solely in `kit-rules.md`.
  - Canon files must not mention `channels/bluesky.md`, `registers/public.md` or any `*.md` that is not in the canon (115-123 regex), nor `~/.config`, `/Users/`, `FW/` or "voice sample" (109-113).
- **Restarting a kit on a new flagship release can overwrite owner edits.**
  - The spec restarts unapproved kit pieces with a new `draft_kit`. If the owner has edited the kit, an `agent-initial` save would replace their text, which breaks the contract's "agents never silently change the owner's prose".
  - Mirror `applyDraft`: save directly only when no human revision follows the last agent revision; otherwise store a proposal.
  - Also stale pending or running `draft_kit`, `lint_repair` and `review_kit` work from the old release.
  - `agent-initial` saves throw "Agent edits cannot remove/modify protected blocks." (WriteArticle 332-366) if the owner protected a kit block. That surfaces after a paid request.
- **Kit approval after edits**
  - `WriteArticle::save` (155) must also invalidate Kit approvals.
  - Decide whether a kit save after approval moves the stage back to InReview. The Phase 7 board columns come from the stage.
  - `MarkKitPiecePosted` should require an active, non-invalidated Kit approval whose `revision_id` is the working revision, not just `stage = approved`.
- **"Flagship attempt stage published" is the wrong check.**
  - After publishing, `develop()` creates a new current attempt for the flagship (EditorialWorkflowTest:46-72), while `published_release_id` still points at the live release.
  - Check `published_release_id` not null, that release `status = published`, and that release's own attempt (`release.attempt_id`) at the published stage, not the current attempt.
- **Which blog posts spawn a kit?**
  - `ContentIdea::flagship()` is the oldest parentless `blog_post`, but siblings are also parentless blog posts.
  - Decide whether a sibling's release approval also spawns a kit (parent = sibling) and record it. Phase 7 nests kits under "their flagship".
  - Decide whether a new release of an *archive* essay (`LintRevision::isArchiveImport`) spawns a kit; the canon does not apply to archive essays.
- **Kit author**: `createPiece` sets `author_id = $actor` (WriteArticle:208). ArticlePolicy (22-60) is owner-only, so spawn with the flagship's author. In practice the approver is the author, but be explicit.
- **No retry if spawning fails after the approval commits.** The approval cannot be repeated (it is already approved). Make `spawn()` idempotent and callable again (for example a "Create distribution kit" fallback, or calling it on workspace load for approved flagships with missing kit kinds), or report the failure without hiding it.
- **Paused spawn: decide whether to create activities.**
  - The spec says "pieces spawn, drafts wait for unpause".
  - `StartEditorialActivity::start` creates a Pending activity and skips dispatch while paused (110-112); `publishing:recover-activities` dispatches it after unpause (RecoverEditorialActivities 50-61).
  - `ApprovePublishingStage` (82-89) instead creates nothing while paused.
  - Either satisfies experiment (f). Pending activities are closer to "drafts wait for unpause". Record the choice.
- **The claim check can block falsely.**
  - The manifest holds only research-grounded claims with quotations (often few; `[]` with no research), while the flagship text carries opinions, hypothetical scenes and approved offer facts.
  - The KitReviewer rubric must limit `claim_outside_manifest` to factual claims, and treat offer facts and the approved proof list from `fact-reviewer.md` consistently. The owner can decide the finding (spec mitigation).
- **Common rules contradict the kit reviewer's job.** The shared instructions say "Reviewers cite only input.evidence_sources" (EditorialAgent:139-152), but kit attempts have no evidence sources. The KitReviewer rubric must say to cite the manifest by statement text in `rationale` and keep `supporting_source_ids` empty, or the activity fails validation.
- **Dashboard labels**: `⚡dashboard.blade.php::statusFor` shows "Approved for release" for an approved kit. Phase 7 owns the board, so this is cosmetic here.
- **Decision log checked against the code; no contradictions.**
  - Pieces, `addPiece` and `parent_article_id` exist (Phase 1).
  - The claim manifest exists and is frozen into the release payload at prepare, and approval approves that exact payload, which matches "release approval freezes the claim manifest".
  - `agentWrittenBlockHashes` already restricts repairs to agent-authored blocks; it only needs DraftKit added.
  - Overrides are article-wide (`LintRevision::overrides` 294-317), so kit approval can reuse them.
  - Nothing posts automatically.
  - Retrieval already excludes kit pieces (`frozenCanon`, `exemplarContextFor`), which matches "retrieved short-form exemplars are Stretch".
  - The one spec premise that does not hold, "EditorialInputContractTest extended automatically", is a test claim, not a decision-log entry.

## Edge Cases (Phase 6)

- **Spawn** (`SpawnDistributionKit::spawn(actor, flagship, release)`)
  - Idempotent per flagship and kind: look up `Article::where('parent_article_id', $flagship->id)->where('kind', $kind)`. Lock the flagship row so a retried approval cannot create duplicates; there is no unique index (`(content_idea_id, kind)` is a plain index).
  - Return all three pieces in LinkedIn, Bluesky, Email order, and return existing ones unchanged.
  - A second release: restart unapproved kits (new `draft_kit` with the new `flagship.release_id`). Leave approved or posted kits alone, and flag them "flagship changed since approval" by comparing the frozen release id with the flagship's latest approved release.
  - Only for parentless `blog_post` flagships. Decide siblings and archive essays (see Risks).
  - Scheduled approvals (status `scheduled`, stage Scheduled) spawn too, because approval committed.
  - The kit attempt starts at `InReview` with `input_version` null and `review_cycle` 1. Set `current_attempt_id`.
- **Frozen kit input** (StartEditorialActivity)
  - Set `piece_kind` and `flagship` in the second `array_merge` argument (69-87) so a caller cannot override them.
  - `flagship` holds `{article_id, release_id, text, title, canonical_url, claim_manifest, target_query}`. Take `text` from `payload.document`, for example the joined `LintTarget::fromDocument(...)->blocks` text (Phase 5 reused that walker). Take `title` from `payload.metadata.title`.
  - Eligibility for `draft_kit`: a kit piece kind, the parent's release approval still active (not invalidated or withdrawn), and the attempt at InReview.
  - Eligibility for `review_kit`: a completed `draft_kit` or `lint_repair` produced the current revision.
  - Refuse `draft_kit` if the flagship release was withdrawn or its approval invalidated after a flagship edit (WriteArticle 155-157).
- **Applying a kit draft**
  - Build the document from the validated fields: one paragraph per field or post, deterministic ids, plain-text URLs (not link marks, so copied text keeps the URL).
  - Origin `agent-initial`, mutation id `'agent-initial-'.$activity->id`. Set `proposal.applied_revision_id` so the lint chain and `agentWrittenBlockHashes` see it.
  - On a restart, `revision_id` is frozen. If the owner saved in between, the result goes Stale (applyResult 395-405); that is the guard against overwriting.
- **Validating kit output** (structure only; limits belong to lint)
  - LinkedIn must be exactly `{text, first_comment}`, Bluesky `{posts: non-empty list<string>}`, and email `{subject, preview, body}`.
  - Reject unknown or missing fields and empty strings, and respect `max_field_bytes`.
  - Retries follow `failOrPause` (1070-1088): `run_count` up to `max_retries` (2) means Failed and retryable, beyond that Paused.
- **Kit lint rules**
  - Lengths in code points (`mb_strlen`, matching the packaging panel). Bluesky counts graphemes; decide whether to use `grapheme_strlen`. Say that the count includes the `i/n` prefix.
  - LinkedIn hashtags: count `#word` tokens, never a `#` inside a URL fragment (`/walkthrough#choose-a-time`) or `#` followed by a digit. "Only at the end" means no non-hashtag text after the first hashtag in the final line.
  - LinkedIn body URLs: detect bare `https?://` and `www.` text as well as link marks; `LintTarget.links` covers only marks.
  - `kit-links-post`: normalize the scheme, `www.` and a trailing slash before comparing with the canonical URL (which has a trailing slash). Count exactly once across the piece, in the required field (LinkedIn first comment, Bluesky final post, email body).
  - `email-blurb-length`: split sentences on `[.?!]` followed by whitespace or the end, but never inside a URL (`birdcar.dev/writing/...`) or a decimal number.
  - `bluesky-numbering`: accept `i/n` at the start or end of a post, `i` must equal the post's position, and every `n` must equal the post count.
  - `kit-no-walkthrough-cta`: a link mark matching the WalkthroughLink HREF regex, or bare text `/walkthrough` or `birdcar.dev/walkthrough`. The word "Walkthrough" alone is fine.
  - LinkedIn: any URL in the body fires both `linkedin-link-in-comment` and possibly `kit-links-post`. Decide whether that double hit is acceptable.
  - Body rules on kits: `stock-openers` checks the first sentence of each kit document; `exclamation-limit` allows one per piece; `unresolved-markers` stays unrepairable.
  - Dataset fixtures: one violation row and one clean row per kit id, built with a kit-kind `LintTarget` plus context.
- **Kit approval** (`ApprovalKind::Kit` through `ApprovePublishingStage::approve`)
  - Input hash covers: kind, attempt id, working revision id and content hash, the lint state (hit hashes plus override reasons), and `review_kit` finding dispositions.
  - Allowed when:
    - the stage is InReview and the piece is a kit;
    - a completed `review_kit` exists;
    - a re-lint through `LintRevision::hits` plus `overrides()` leaves no hit without a reasoned override;
    - no non-stale blocking `review_kit` finding is undecided.
  - Store `revision_id` on the approval. The stage moves to Approved.
  - Advisory `register` findings never block. Decide whether `channel` findings are advisory or blocking and enforce it in the validator.
- **Mark posted**
  - Requires `publishing.publish`, an active Kit approval on the working revision, and the flagship published (see Risks for the exact check).
  - `posted_url` is optional; when given it must be `https://` (reject http, javascript: and the like) and at most 2048 characters. It is never fetched.
  - Sets `posted_at = now()` and moves the kit attempt to Published. Marking twice should be idempotent or refused.
  - When the flagship is unpublished, the refusal names the condition ("the flagship post is not published yet").
- **Copy-out**: render each field's plain text with newlines for hardBreaks. Copy buttons use Alpine `navigator.clipboard`. No JS build is needed unless `resources/js` changes; Blade changes don't need `bun run build`.
- **Siblings**
  - `CreateSiblingPiece::create(actor, idea, ?note)` needs `publishing.write` (addPiece) and `publishing.develop` (develop and start). The Livewire side calls `Gate::authorize('develop', $article)`.
  - The new piece is a `blog_post` with no parent.
  - `sibling_context` lists the idea's other `blog_post` pieces (excluding self and kit kinds) as `{article_id, title, angle_thesis}`.
    - `title` comes from working revision `metadata.title`, else the angle's title, else null. Never use the idea's summary title (contract G4).
    - `angle_thesis` comes from `currentAttempt.angle.thesis`, else null.
    - Eager-load `workingRevision` and `currentAttempt` with every column read (strict mode).
  - Compute `sibling_context` inside StartEditorialActivity for Interview, so workspace restarts (`startInterview` 593-623, `startDevelopment`) keep it and a caller cannot forge it.
  - The sibling cannot plan without its own angle approval (natural from `ensurePlanEligible`).
  - The interviewer rubric must require each angle's `rationale` to state how it differs from every listed angle.
- **Workspace kit panel**
  - No develop or plan tabs. Show fields with copy buttons, lint and `review_kit` findings (Accept fix, override with reason, decide with reason), approve, and Mark posted (disabled with the reason until the flagship is published).
  - A field edit saves through `WriteArticle::save(..., 'human')` and then `refreshVoiceRuleFindings()`.
  - Show the "flagship changed since approval" and "flagship unpublished" warnings.
  - Show "New post from this idea" on blog-post pieces.

## Test Strategy (Phase 6)

- **Inner loop**: `php artisan test --compact tests/Feature/Publishing/DistributionKitTest.php`, then `php artisan test --compact tests/Feature/Publishing/SiblingPieceTest.php`.
- **Spec validation**: `php artisan test --compact tests/Feature/Publishing/DistributionKitTest.php tests/Feature/Publishing/SiblingPieceTest.php tests/Feature/Publishing/VoiceLintTest.php`
- **Regression files** (they consume the changed code):
  ```
  php artisan test --compact tests/Feature/Publishing/VoiceCanonTest.php tests/Feature/Publishing/EditorialInputContractTest.php tests/Feature/Publishing/EditorialAgentsTest.php tests/Feature/Publishing/VoiceLintWorkflowTest.php tests/Feature/Publishing/ReleaseIntegrityTest.php tests/Feature/Publishing/ReleaseReadinessTest.php tests/Feature/Publishing/EditorialWorkflowTest.php tests/Feature/Publishing/PackagingTest.php tests/Feature/Publishing/AdminPublishingEditorTest.php tests/Feature/Publishing/AdminPublishingWorkspaceTest.php tests/Feature/Publishing/PublishingSessionTest.php tests/Feature/Publishing/PublishingAgentSettingsTest.php tests/Feature/Publishing/ContentIdeaTest.php tests/Feature/Publishing/ExemplarRetrievalTest.php tests/Feature/Publishing/PublicWritingTest.php tests/Feature/Publishing/PublishingJourneyTest.php tests/Feature/Publishing/EditorialReviewTest.php tests/Feature/Publishing/ReviewRubricTest.php tests/Feature/Publishing/NarrativeFrameworkTest.php
  ```
  Add `tests/Feature/AdminHomeTest.php` if ReadAdminHome changes.
- **Phase check**: `php artisan test --compact tests/Feature/Publishing`
- **Final**: `vendor/bin/pint --dirty --format agent`, then `env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress`, then `composer test`.
- **Canon checks**
  - `test "$(grep -o '\[rule:[a-z0-9-]*\]' resources/publishing/canon/voice/kit-rules.md | sort -u | wc -l | tr -d ' ')" = 9`
  - `! grep -rnE 'bluesky\.md|public\.md|FW/|~/.config|/Users/|[Vv]oice sample' resources/publishing/canon`
  - `! grep -n '\[rule:' resources/publishing/canon/channels/kit.md resources/publishing/canon/exemplars/short-form.md`
- **DistributionKitTest setup**
  - `beforeEach`: `forgetCachedPermissions()` and `authorization:sync`.
  - Actor: an Author-role user (every publishing permission).
  - Flagship: `captureIdea`, then `save(releaseDocument-style doc, releaseMetadata-style metadata)` with an SEO title of 50-60 characters, a description of 150-160, and exactly one closing /walkthrough link. Then `develop(..., ['goal' => ..., 'post_type' => 'essay', 'target_query' => 'do I need another admin hire'])`, the angle and plan approvals, completed reviews, and research with claims (the PackagingTest:443-464 pattern). Then `prepare` and `approve`.
  - Agents stay paused until the spawn assertions. Then run `setPublishingAgentsPaused(false)`, `config()->set('ai.providers.openrouter.key', 'test-key')`, `Queue::fake()` (follow-on activities are created but not dispatched) and `Http::preventStrayRequests()`.
  - Route `Http::fake` by the decoded `messages.1.content.kind`, choosing the `draft_kit` payload by `input.piece_kind`.
  - Run each activity with `app(RunEditorialActivity::class, ['activityId' => $id])->handle(app(EditorialOutput::class), app(WriteArticle::class))`.
  - Map the spec experiments:
    - (a) Three pieces with `parent_article_id` = flagship, the same `content_idea_id`, attempts at `in_review`, and three `draft_kit` activities.
    - (b) A Bluesky post of 320 characters: a `lint_repair` starts; when the fake returns `{"patches": []}` the hit stays as a blocking lint finding, and `review_kit` then starts.
    - (c) A `claim_outside_manifest` finding with blocking severity: Kit approval throws until it is decided.
    - (d) `MarkKitPiecePosted` before `deliver()` throws, naming the condition; it succeeds after `deliver()`. Also cover the develop-after-publish case.
    - (e) Prepare and approve a second flagship release: still three kit pieces; unapproved ones get a new `draft_kit`.
    - (f) Paused: three pieces, no request (`Http::assertNothingSent()`), and drafts Pending or absent per the recorded choice.
  - Also cover copy-out rendering, `posted_url` https validation, a kit edit after approval invalidating the approval, and ReadPublishedWriting excluding kit kinds.
- **SiblingPieceTest**
  - `CreateSiblingPiece::create` returns a `blog_post` with `parent_article_id` null and the same idea.
  - Its Interview input has `sibling_context` with every other blog piece's title and angle thesis, and never the summary title.
  - Plan approval refuses without the sibling's own angle approval ("Plan approval requires an active angle approval.").
  - Run the interview with a fake whose `angleOptions` rationale differs, and assert that `messages.0.content` contains the interviewer rubric's new sibling sentence.
- **VoiceLintTest**: one violation row and one clean row per kit id, built from a kit-kind target with context. Replace the "only blog posts" test. Filter the Drafter id audit to blog-applicable rules and add a KitDrafter audit. The two-way check stays as is.
- **EditorialInputContractTest**: add `draft_kit` and `review_kit` dataset rows (rubric heading `# Core voice`; the files per roles.json), response arms, prerequisites, the updated exact-cases list, and an assertion that the kit input has `flagship.claim_manifest` and the flagship text.
- **Environment**
  - `birdcar_test` was confirmed on 127.0.0.1:5433 this session. No `--parallel`.
  - Never run `tests/Manual` (paid); PHPStan still analyses it.
  - `QUEUE_CONNECTION=sync` in phpunit.xml, so anything dispatched while unpaused runs inline. Use `Queue::fake()` when unpaused.

---

## Key Patterns (Phase 3)

- **`app/Actions/Publishing/CheckArticleRelease.php:231-297`** (spec pattern)
  - `unsafeDocumentLinks` and `documentText` are recursive closures (`$walk = function (mixed $node, string $path) use (&$walk, &$acc)`).
  - They read `$node['marks']`, `data_get($mark, 'attrs.href')` and `$node['content']`.
  - Both are private. `LintTarget::fromRevision()` needs its own walker that also tracks block id, heading level, ancestor flags and marks.
  - Release findings use the shape `['severity', 'code', 'location', 'message', 'resolution']` (`finding()` at 176-179).
- **`app/Services/Publishing/ArticleDocument.php`** (spec pattern)
  - **Block nodes** (`BLOCK_NODES` 23-36): paragraph, heading, blockquote, bulletList, orderedList, listItem, codeBlock, horizontalRule, note, callout, chart, diagram. Inline nodes are `text` and `hardBreak`.
  - **Marks** (332-355): only bold, italic, strike, code and link (`attrs.href`).
  - **Heading level** must be 2 or 3 (`canonicalizeAttrs`, about 391-398). A level-1 heading can never be saved, and drafts are clamped to 2-3 by `RunEditorialActivity::withArticleHeadingLevels` (523-534).
  - **Ids and protection**: every non-text node has `attrs.id` (`blk_` or `imp_` plus 16 hex) and an optional boolean `attrs.protected`.
  - **codeBlock** text has no marks.
  - **Attribute text**: note and callout carry `attrs.title`; chart and diagram carry `attrs.caption`. None of these is text content.
  - Revisions are stored canonical, so the legacy paragraph `text` key has already been converted into `content[]`.
- **`PublishingFingerprint::hash($node)`** is the subtree hash. `ApplyEditorialProposal::validatedPatchForBase` (168-184) compares it with a patch's `expected_hash`.
  - Use the same hash for the "agent-authored" check and for the `expected_hash` in the repair input.
  - jsonb reorders keys, so never compare block arrays with `===` (Phase 1 lesson; `WriteArticle:362` uses `canonicalJson`).
- **`ApplyEditorialProposal.php:58-112`**: the block-patch pipeline (`findBlockInNodes`, `rejectOverlappingPatches`, `replaceBlockAtPath`, then `WriteArticle::save`).
  - `applyLintRepair()` needs the same steps, and they are private.
  - Prefer a small shared helper over a copy.
- **Role agent**: copy `app/Ai/Agents/Drafter.php` by hand (`#[MaxTokens(n)]`, `model()` returning `$this->modelOverride ?? '...'`, `roleInstructions()`, `roleReasoningEffort()`).
  - `php artisan make:agent` uses `stubs/agent.stub`, which builds a standalone SDK agent, not an `EditorialAgent` subclass.
- **Rubric file**: `resources/publishing/canon/roles/reconciler.md` is the model: a `# Reconciler rubric` heading, a one-line job, and `## Output contract` bullets.
  - Every `*.md` path a canon file mentions must exist inside the canon (`VoiceCanonTest:110-119`).
  - No canon file may contain `~/.config`, `/Users/`, `FW/` or "voice sample".
- **Schema** (`EditorialAgent::schema()` 198-270): nested JSON objects travel as `$schema->string()` whose description states the encoded shape (`document` at 242, `proposed_patch` at 318).
  - `EditorialOutput::normalizeJsonEncodedFields` (69-90) decodes only top-level `document`, `metadataProposals` and `proposed_patch`, plus `findings[*].proposed_patch`.
  - So `patches[*].replacement` needs its own decode in the `lint_repair` validator.
- **Validation errors**: `EditorialOutput::validate()` throws `InvalidArgumentException('...reason')`, and `RunEditorialActivity::handle` routes it to `handleFailure` and then `failOrPause` (935-1012).
  - `EditorialAgentsTest.php:207` runs `new EditorialOutput`, so keep EditorialOutput constructor-free.
  - Pass the frozen hits and blocks in as arguments (an optional `validate()` parameter or a dedicated method).
- **`#[Tag]`**: `vendor/laravel/framework/src/Illuminate/Container/Attributes/Tag.php` resolves to `$container->tagged($tag)`, a rewindable generator.
  - Register the rules with `$this->app->tag([EmDash::class, ...], 'publishing.lint-rules')` in `AppServiceProvider::register()` (23-26), which already holds the scoped `VoiceCanon` binding.
  - `tagged()` is used nowhere else yet. Never tag the abstract `BannedPhrasesRule`.
- **Rule class names**: later specs name rules without a suffix (`SeoTitleLength.php` in Phase 4, `LinkedinLength.php` in Phase 6), so use `EmDash.php`, `CurlyQuotes.php` and so on.
  - Phase 4 adds metadata rules (`blockId = null`) that `EditorialOutput` and `CheckArticleRelease` call.
  - Phase 6 changes `appliesTo()` per kind and maps kit payloads in `LintTarget`.
  - Phase 8 calls `VoiceLinter::ruleIds()`.
  - Keep `VoiceLinter` able to lint one target with all applicable rules, and keep `LintTarget` easy to construct from a plain document and metadata array.
- **Workspace (`⚡article-workspace.blade.php`)**
  - **Human saves** go through three paths: `saveDocument` (164-219, autosave, origin `human`), `saveDetails` (110-124, via `saveDocument`) and `setBlockProtection` (670-723, a direct `WriteArticle::save` with origin `human`). `applyProposal` (534-558) is an `agent-accepted` save.
  - **`decideFinding`** (513-532) checks the false-positive reason with `$this->addError('findingReasons.'.$id, 'Explain why this finding does not apply.')`. It only buffers dispositions in `$findingDispositions`, which are written by `finishReview` (560-578).
  - **Errors** surface through `$this->saveError`. Gates: `update` for edits and proposals, `approve` for dispositions.
- **Finding card (`partials/write.blade.php:59-91`)**: severity badge, lens label, statement, the "Suggested replacement" disclosure (from `proposalTexts`), "Accept change" (`applyProposal`), and the false-positive disclosure with `flux:textarea wire:model="findingReasons.{id}"` and `flux:error`. Reuse it for the "Voice rules" group.
- **Test templates**
  - `EditorialReviewTest.php:122-150`: `Queue::fake()`, one `Http::fake` response, start and run the Draft, then assert which lens activities were created. This is the template for the post-draft loop.
  - `PublishingJourneyTest.php:30-84`: routes fake responses by the decoded `messages.1.content.kind`. This is the template for Draft-then-LintRepair sequences.
  - `EditorialInputContractTest.php:166-186`: `inputContractRun` captures the system message and the input.
  - `ReleaseReadinessTest.php`: `readinessPrerequisites` (363-412), `readinessFinding($attempt, $revision, $overrides)` (487-501) and `readinessDocument` (515-524).

## Dependencies (Phase 3)

- **`EditorialActivityKind::LintRepair`**: these exhaustive `match`es throw `UnhandledMatchError`, and fail PHPStan level 7, until a `LintRepair` arm is added.
  - `app/Ai/Agents/EditorialAgent.php:46` (`forActivity`) and `:200` (`schema`).
  - `app/Services/Publishing/EditorialOutput.php:23` (`validate`) and `:54` (`allowedKeys`).
  - `app/Actions/Publishing/RunEditorialActivity.php:1170` (`prerequisiteApprovalFor`, returns `ApprovalKind::Plan`).
  - `app/Actions/Publishing/StartEditorialActivity.php:128` (`ensureEligible`) and `:577` (`activityMatchesCurrentApproval`, returns Plan).
  - `app/Actions/Publishing/ApprovePublishingStage.php:328` (`activityMatchesCurrentApproval`). **Not in the spec.**
  - `resources/views/components/admin/settings/⚡publishing.blade.php:215` (`roleLabel`) and `:230` (`rolePurpose`). **Not in the spec.** The page iterates `cases()` (78, 92, 149, 188), and `PublishingAgentSettingsTest` renders it. Model overrides per kind just work.
  - Already covered by a `default` arm: `⚡dashboard.blade.php:139`, `partials/session.blade.php:52` (consider adding a `lint_repair` status line), `app/Actions/Admin/ReadAdminHome.php:161`.
- **`RunEditorialActivity::actorCanRunKind`** (1033-1040) requires `publishing.write` only for Draft. LintRepair saves revisions, so add it there. Otherwise `WriteArticle::authorize` throws inside `applyResult`, after the request was paid for.
- **`EditorialAgent::recommendedModelFor`** (72-75) builds `new EditorialActivity(['kind' => $kind])`, so `LintRepairer::model()` must not read its input.
- **`VoiceCanonTest.php:122-132`** iterates `cases()`, so `roles.json` needs a `lint_repair` entry whose first file starts with `# `. `voice/core.md` does.
- **`EditorialInputContractTest.php` is not covered automatically**, contrary to the spec.
  - The dataset is an explicit 9-row `->with([...])` (212-234). Add `'lint repair' => [LintRepair, '# Core voice', [voice/core.md, voice/mechanical-rules.md, roles/lint-repairer.md]]`.
  - `inputContractResponse` (152-163) is exhaustive. Add a `['patches' => []]` arm.
  - `inputContractStart` (111-139) needs LintRepair prerequisites: a completed Draft on the revision, plus `hits` and `expected_revision_id` input.
  - The test at 236-240 asserts the exact list of 9 case values.
  - The spec adds an assertion that the `lint_repair` input holds the hits and the blocks they touch.
- **`EditorialAgentsTest.php`**: extend the `forActivity` chain at 36-46, and add `'lint repairer' => [EditorialActivityKind::LintRepair, 'google/gemini-3.8-flash', 'low', 16000]` to the model dataset at 146-157.
- **Post-draft chain** (`RunEditorialActivity::applyResult`, 353-479)
  - `queueReviewLensesAfterCompletedDraft` (1042-1063) runs inside the `applyResult` transaction, only when `proposal.applied_revision_id` is set. `applyDraft` (537-555) applies a draft only to an empty document; otherwise it stores a proposal and no lenses queue.
  - Lenses start with input `['draft_activity_id' => ...]` and batch key `review-{cycle}-{revisionId}`. The review kinds' idempotency ignores `prompt_hash` (`idempotencyPayload` 478-503); LintRepair uses `prompt_hash` like Draft.
- **Review eligibility after a repair (not in the spec)**
  - `ensureReviewEligible` (170-179) calls `completedDraftExistsForRevision` (505-524). That only accepts `draft.revision_id` or `draft.proposal.applied_revision_id` equal to the working revision.
  - After an `agent-repair` revision, starting the lenses throws "Review lenses can only start after the draft has completed for the current revision."
  - Accept a completed same-cycle `lint_repair` whose `proposal.applied_revision_id` is the working revision, with matching plan approval.
  - The workspace `startReviews` button (493-511) and the spec's "Skip repair and review" action go through the same check.
- **`WriteArticle::save`** (89-165)
  - `article_revisions.origin` is a free string with no constraint.
  - The protected-block guard at 337 lists `agent`, `agent-initial` and `agent-accepted`; add `agent-repair` (spec).
  - Leave `agent-repair` out of `markReviewedRevisionStale` (167-183, which stales lens work only for `human` and `agent-accepted`).
  - Every save invalidates Release approvals and withdraws scheduled releases (155-159).
- **Readers of lint findings** (`lens = 'lint'`, `severity = 'blocking'`)
  - **`FinishEditorialReview.php:87-97`** throws on undisposed blocking findings. Exclude `lens = 'lint'` (spec).
  - **`StartEditorialActivity::reviewFindings`** (246-273) freezes every lens's findings for reconciliation and recheck. Exclude `lint` (not in the spec). Otherwise the reconciler groups lint findings, and `ensureRecheckAssessedEveryFinding` (RunEditorialActivity 705-723) makes the rechecker classify them.
  - **`CheckArticleRelease::materialEditorialBlockers`** (410-435) reports every blocking finding as `editorial.material_unresolved`. Exclude `lint`, so an unresolved hit blocks once as `lint.<id>` and the re-lint stays the source of truth.
  - **`ApplyEditorialProposal.php:68`** requires `finding.input_hash === baseRevision.content_hash`. Lint findings keep the hit hash there, so "Accept fix" always throws "Editorial proposal output is stale." (not in the spec). For `lens = 'lint'`, skip that comparison; the patch `expected_hash` at 179-181 already guards drift. Line 176-178 rejects patches on protected blocks ("Protected passages must be unlocked before accepting this proposal."), which is the expected outcome for spec case (c).
  - **`ApplyEditorialProposal::markOnlyInvalidatedFindingsStale`** (121-140) keeps findings whose `expected_subtree_hash` is null. Set it on lint findings, or run `LintRevision` after `applyProposal`.
  - **`ReleaseFreshnessManifest::reviewManifest`** hashes findings together with their dispositions, so an override changes the release input hash. That is expected: a prepared release goes stale after an override.
  - **Workspace `with()`** (822-886) lists non-stale findings for the current `review_cycle` (831-836) and builds `proposalTexts` from `proposed_patch['replacement']` (867).
- **Consumers of `CheckArticleRelease`**: `ManageArticleRelease::prepare` (128) and `ensureReleaseReadinessIsCurrent` (375, used by approve and deliver), `PublishDueArticles`, and `findingsForRelease` (148). It is built only by DI, so adding a `VoiceLinter` constructor argument is safe.
- **Callers of `RunEditorialActivity::handle(EditorialOutput, WriteArticle, ?PublicSourceFetcher)`**: about 20 test sites pass the first two arguments positionally, and `app()->call([new RunEditorialActivity($id), 'handle'])` appears in 4 files. Add a new dependency as a trailing nullable parameter, or resolve it with `app()`.
- **Tests that go red, beyond the spec's list**
  - **Why**: `walkthrough-link` fires on every BlogPost without exactly one /walkthrough link, and every test article is `blog_post` (ArticleFactory:28, `captureIdea`).
  - **Release fixtures**: `ReleaseIntegrityTest::releaseDocument` (794), `ReleaseReadinessTest::readinessDocument` (515) and `readinessAssetDocument` (418), `EditorialWorkflowTest::editorialDocument` (592, with releases at 61 and 434), the `AdminPublishingEditorTest` release at 300, and `PublicWritingTest:76` (deliver). Their texts are otherwise clean ("Draft", "Supported body.", "Reviewed" and similar). Add one closing paragraph with a single `/walkthrough` link mark to each helper.
  - **Draft-to-lens fixtures**: `EditorialReviewTest.php:122-150` ('Draft for review', which expects 3 lenses), `EditorialWorkflowTest.php:280-370` ('Agent drafted manuscript.' with `drainEditorialActivities(3)`, which expects 3 completed lenses), and `PublishingJourneyTest.php:56-63`, whose `match` default at 84 throws "Unexpected journey kind lint_repair". Make these draft fixtures lint-clean so they keep testing the lens path.
  - **Metadata titles**: fixtures use Title Case titles ("Agent Plan Pipeline"). Do not apply sentence case to metadata; Phase 4 owns `title-sentence-case`.
- **Tests that stay green**
  - AdminPublishingEditorTest:243-262 asserts "No findings have been recorded" after workspace saves, but that article has no attempt, so `LintRevision` must be a no-op there.
  - `PublishingSessionTest` saves likewise run without an attempt.

## Conventions (Phase 3 additions)

- **Namespaces**: `App\Services\Publishing\Lint\{LintRule, LintTarget, LintHit, VoiceLinter}`, `App\Services\Publishing\Lint\Rules\*`, `App\Actions\Publishing\LintRevision`, `App\Ai\Agents\LintRepairer`.
- **Classes**: `final readonly class` for value objects (precedent `CanonSnapshot`). There is no app interface precedent in `app/Services`, so `LintRule` is the first.
- **Generics**: use `@param iterable<LintRule> $rules` and `@return list<LintHit>`. PHPStan level 7 covers `tests/` with no baseline and no ignores.
- **Lint finding row**:
  - Values: `lens = 'lint'`, `kind = 'lint:<id>'`, `severity = 'blocking'`, `block_id`, `statement = message`, `input_hash = hit hash` (`string(64)`), `revision_id`, `review_cycle` set to the attempt's current cycle, and `activity_id` null for save-time findings.
  - `proposed_patch` must be `{block_id, expected_hash, replacement}`, the shape ApplyEditorialProposal reads.
  - `editorial_findings.attempt_id` is NOT NULL (`2026_09_22_230000_create_editorial_activity_tables.php:102`), so `LintRevision` must no-op when the article has no current attempt.
- **Override**: `disposition = 'false_positive'` plus a trimmed, non-empty `disposition_reason`, `disposition_actor_id` and `disposed_at`. This is the rule `CheckArticleRelease::materialFindingResolved` (440-444) already applies.
- **Test names**
  - Helper prefixes: use `voiceLint*` in VoiceLintTest and a different prefix in VoiceLintWorkflowTest (for example `lintWorkflow*`). The spec's example `voiceLintDocument()` must exist in only one file, or Pest fatals on redeclare.
  - The global constants `VOICE_CANON_RULE_IDS` (VoiceCanonTest:9) and `INPUT_CONTRACT_EXEMPLAR_IDS` already exist, so prefix any new ones `VOICE_LINT_`.
- **Schema descriptions**: every rule `EditorialOutput` enforces for `lint_repair` (`hit_id` from the input, block must exist, `expected_hash` echoed, replacement keeps the block id and type, byte limit `publishing_agents.limits.max_field_bytes` = 32768) is stated in its description. This is the validator-to-schema audit from contract line 154.
- **Agent limits** (`.ai/rules/ai.md`): `#[MaxTokens(16000)]` with low effort is a runaway guard. Do not lower it to save cost.

## Risks (Phase 3)

- **The spec's word lists differ from the canon statements the Drafter is told** (`voice/mechanical-rules.md:5-25`). The two-way test compares ids only.
  - `public-vocabulary`: the canon has bare "stack" and "scale" as a hype verb; the spec has "tech stack" and "solutions". `voice/core.md:41` itself says "a stack of promises".
  - `filler-phrases`: the canon has "it is important to note that"; the spec has "it is worth noting".
  - `chatbot-artifacts`: the canon adds "here is an overview of", "while details are limited" and "based on available information".
  - `stock-openers`: the canon adds "In this article", "Let's dive in", "In conclusion" and "Ultimately,".
  - `label-headings`: the canon adds "Bottom line" and "Key takeaways"; the spec adds background, final thoughts and wrapping up.
  - `corporate-we`: the canon adds "ourselves".
  - `experience-claim`: the canon adds "over fifteen years".
  - `em-dash`: the canon also covers titles and metadata.
  - Judgment-only qualifiers: "landscape (in the abstract sense)", "leverage (as a verb)", "bandwidth (meaning capacity)".
  - Recommendation: union the lists, leave out judgment-only items (bare "stack", hype "scale"), and record each choice in the implementation notes.
- **Rule semantics that conflict with the canon. Pick one reading for each and record it.**
  - `bold-limit`: the spec counts more than two bold spans per piece; the canon says at most one per H2 section.
  - `exclamation-limit`: the canon also bans any "!" in a heading, title or meta description.
  - `walkthrough-link`: the canon and `channels/blog.md:75,89` place it in the closing invitation paragraph; the spec says after the final H2, or in the last three blocks.
  - `heading-order`: a level-1 heading cannot be saved, so only "H3 before any H2" can fire.
- **Unresolved markers.** `roles/drafter.md:21` tells the Drafter to write `[NEED: proof]` and `[TODO: Nick to supply]`, so most drafts produce `unresolved-markers` hits.
  - Sending them to the repairer invites invented proof, which breaks the never-invent rule.
  - Leave non-repairable hits out of the repair request: `unresolved-markers`, and metadata hits (null block id, which cannot be patched). Store them straight as findings.
  - If a pass would carry only non-repairable hits, skip repair.
- **"Accept fix" conflict**: see `ApplyEditorialProposal.php:68` under Dependencies.
- **Review-eligibility gap**: see Dependencies.
  - An owner edit while a repair is pending makes the repair Stale (`passesExecutionChecks` 254-262), and the chain stops; lenses never queue.
  - The spec's "Skip repair and review" action (Failure Modes table) is not in File Changes. Implement it in the workspace with a `develop` or `approve` gate: sync findings on the current revision and queue lenses. It also needs the eligibility fix.
- **Validation-failure path.** The Error Handling table says that when repair output is invalid, "the activity fails ... and the lenses still queue with lint findings stored". Failures reach `handleFailure` (935-971) outside `applyResult`, and nothing hooks that today. Add a `lint_repair` branch, or decide that the owner uses Retry or "Skip repair and review", and record it.
- **Several hits in one block.** Each patch carries the original block's `expected_hash`. After the first is applied, the rest mismatch and are skipped, which burns the 2-pass cap. Group hits by block in the input and accept one patch per block (its `hit_id` can be any hit in that block). Stop the loop early if a pass applied nothing.
- **`expected_hash`.** The model cannot compute sha256. Freeze `{block_id, hash, node}` for every touched block in the `lint_repair` input so the model echoes the hash. The whole manuscript is already frozen under `input.manuscript` (StartEditorialActivity:72-77).
- **Inherited protection.**
  - A paragraph inside a protected blockquote, list, note or callout is not itself `attrs.protected`.
  - Patching it changes the protected ancestor's subtree, so `WriteArticle` throws "Agent edits cannot modify protected blocks." (362-364).
  - `LintTarget` must set `protected` when any ancestor is protected.
  - `ApplyEditorialProposal::validatedPatchForBase` checks only the target node, so owner acceptance hits the same WriteArticle guard.
- **Finding the "agent-authored" baseline.** Revisions have no `attempt_id`. Find the attempt's agent revisions through its activities (`draft.proposal.applied_revision_id`, then each `lint_repair.proposal.applied_revision_id`), and compare `PublishingFingerprint::hash` of the block. The Drafter may itself set `attrs.protected: true`; the schema at 242 requires the flag.
- **Archive essays.**
  - All 10 essays in `resources/writing` use "we" or "our", and two contain em dashes. They are `BlogPost` kind.
  - Any new revision of an imported essay will be blocked by many hits, each needing a reasoned override.
  - `channels/blog.md:5` says the canon does not apply to archive essays.
  - This is the owner's decision. Note it in the implementation notes, and consider skipping articles whose published release has `origin = 'import'`.
- **Override persistence and scope**
  - `decideFinding` only buffers until `finishReview`, but release reads the database. Add an action that writes the override immediately: `Gate::authorize('approve')`, `lens = 'lint'`, current article and attempt, and a non-empty reason (else `addError`).
  - Override lookup must not filter on `stale_at`, because `LintRevision` stales earlier revisions' findings.
  - Scope the lookup to the article or the attempt, and record which.
  - Copy an existing override forward onto the new revision's finding with the same hash, so the panel does not show it again.
- **Hash collisions.** The same excerpt twice in one block yields one `input_hash`. Dedupe per hash, or add an occurrence index. For `exclamation-limit` and `bold-limit`, the excerpt needs surrounding words, not just "!".
- **False positives verified with PCRE 10.44.** `corporate-we` with `/i` matches "US" (the country). `\p{Extended_Pictographic}` matches © ® ™ and ↔ (but not →). `sentence-case-headings` needs to exempt "I", I'm, I'd, I've, I'll, "Nick", all-caps acronyms and the allowlist.
- **Wide test blast radius.** See the red-tests list. Fix the fixtures in each helper rather than per test.
- **The decision log matches the code; no contradictions.**
  - No linter exists: `rg -i lint app` finds only `VoiceReviewer.php:17`, which says "the linter owns the mechanical rules".
  - `roles/voice-reviewer.md:3,42` already defers mechanical rules to the linter and keeps voice findings advisory.
  - Vale is not installed.
  - All 21 `[rule:` ids are tagged once in `mechanical-rules.md` (VoiceCanonTest:186-197).
  - One caveat, not a Phase 3 blocker: "LLM style findings stay advisory" rests on rubric wording only. `materialEditorialBlockers` blocks any LLM finding the model marks `blocking`.

## Edge Cases (Phase 3)

- **LintTarget**
  - **Blocks**: one block per text-bearing node (paragraph, heading, codeBlock), with its own `attrs.id`, in document order.
  - **Flags**: `in_blockquote` is true under any blockquote ancestor. `protected` is true when the node or any ancestor is protected. Mark list-item paragraphs too (for `inline-header-list`).
  - **Text**: skip `code`-marked text and every `codeBlock`. Replace a skipped span with a space so words do not fuse, and turn `hardBreak` into a space.
  - **Merging**: adjacent text nodes that are both bold form one bold span (bold+italic splits nodes). Adjacent nodes with the same link `href` form one link; otherwise one anchor counts as two links.
  - **Attribute text**: decide whether note/callout `attrs.title` and chart/diagram `attrs.caption` are checked; at least em dash, curly quotes and emoji apply.
  - **Metadata**: check `title` and `description` with the character rules only (em dash, curly quotes, emoji, excluded names, unresolved markers, "!" in title or description). Skip `date` and `tags`. Never apply sentence case to metadata.
  - **Kind**: Phase 3 rules apply only to `PieceKind::BlogPost`.
  - **Order**: hits follow document position.
- **Rules**
  - **Word lists**: match on word boundaries, case-insensitive, with `/u`. Accept both `'` and `’` in "it's", "we're" and so on (curly forms also trip `curly-quotes`). `align with` fires both `ai-vocabulary` and `corporate-vocabulary` (`align`); decide whether that is acceptable.
  - **`negative-parallelism`**: use the spec's two regexes. Check clean prose such as "It's a memory problem" (core.md:116).
  - **`stock-openers`**: check the first sentence of the document and the first sentence after every heading.
  - **`sentence-case-headings`**: count capitalized words after the first, excluding the allowlist, "I" and its contractions, all-caps acronyms, and words inside straight quotes. A single capitalized word (for example "What a Walkthrough report covers", from blog.md:28) passes.
  - **`label-headings`**: compare the trimmed, case-insensitive heading text, ignoring trailing punctuation.
  - **`inline-header-list`**: the colon can sit inside the bold ("**Performance:**") or right after it.
  - **`corporate-we`**: skip blockquotes and straight double-quoted spans. An unbalanced quote should not swallow the rest of the block.
  - **`walkthrough-link`**
    - Matches `/walkthrough`, with or without a trailing slash, query or fragment (`/walkthrough#choose-a-time` is a real anchor per `.ai/rules/resources.md`), and `http(s)://(www.)birdcar.dev/walkthrough`.
    - `/assessment`, `/contact` and `birdcar.test` do not count.
    - Zero links, two links, or one link in the opening section each fail (spec test).
  - **`experience-claim`**: "fifteen years" and "more than 12 years" pass (site copy uses "fifteen years").
- **Post-draft loop**
  - **Cap**: count completed `lint_repair` activities in this attempt and `review_cycle`. Stop at 2. Store the remaining hits with their carried unapplied patches as findings, then queue lenses with batch key `review-{cycle}-{revisionId}`.
  - **Repair batch key**: `lint-repair-{cycle}-{revisionId}`. Its idempotency includes `prompt_hash`, which covers the hits and the revision.
  - **Paused agents**: `StartEditorialActivity` creates the activity but does not dispatch it; recovery dispatches it once unpaused (`RecoverEditorialActivities`, every 5 minutes).
  - **Draft applied as a proposal** (document not empty): no lint step and no lenses, as today.
  - **Patch validation**: reject a `hit_id` that is not in the input, a block that is not in the hits, or a replacement whose `attrs.id` or type differs from the target. A hash mismatch skips that patch and keeps the hit.
  - **Applying patches**: apply only to agent-authored, unprotected blocks, saved as a new revision through `WriteArticle::save(..., 'agent-repair-'.$activity->id, 'agent-repair')`. Patches for owner-edited or protected blocks go onto the matching findings.
- **LintRevision**
  - No current attempt: do nothing.
  - Sync by `input_hash` per revision: create findings for new hits, and set `stale_at` on vanished hits and on lint findings of earlier revisions.
  - Run it after `saveDocument`, `saveDetails`, `setBlockProtection` and `applyProposal`. It must never throw into the save response.
  - After `restartReview` bumps the cycle, the panel shows no lint findings until the next save, but release still re-lints.
- **Release**
  - Re-lint the exact revision. Codes are `lint.<id>`, and the location is the block id or `$.metadata.title`/`$.metadata.description`.
  - A hit is cleared only by a matching `input_hash` with `false_positive` and a trimmed, non-empty reason. A bare false positive does not clear it, and changed text yields a new hash that blocks again.
  - The override recorded on revision N must still clear revision N+1 when the text is unchanged.

## Test Strategy (Phase 3)

- **Inner loop**: `php artisan test --compact tests/Feature/Publishing/VoiceLintTest.php`
- **Workflow**: `php artisan test --compact tests/Feature/Publishing/VoiceLintWorkflowTest.php`
- **Spec validation**: `php artisan test --compact tests/Feature/Publishing/VoiceLintTest.php tests/Feature/Publishing/VoiceLintWorkflowTest.php tests/Feature/Publishing/ReleaseReadinessTest.php`
- **Regression files** (they consume the changed code):
  ```
  php artisan test --compact tests/Feature/Publishing/EditorialInputContractTest.php tests/Feature/Publishing/EditorialAgentsTest.php tests/Feature/Publishing/VoiceCanonTest.php tests/Feature/Publishing/EditorialReviewTest.php tests/Feature/Publishing/EditorialWorkflowTest.php tests/Feature/Publishing/PublishingJourneyTest.php tests/Feature/Publishing/ReleaseIntegrityTest.php tests/Feature/Publishing/AdminPublishingEditorTest.php tests/Feature/Publishing/PublicWritingTest.php tests/Feature/Publishing/PublishingAgentSettingsTest.php tests/Feature/Publishing/PublishingSessionTest.php tests/Feature/Publishing/AgentBudgetTest.php tests/Feature/Publishing/EditorialToolApprovalTest.php
  ```
- **Phase check**: `php artisan test --compact tests/Feature/Publishing`
- **Final**: `vendor/bin/pint --dirty --format agent`, then `env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress`, then `composer test`.
- **VoiceLintTest setup**
  - Datasets: one violation row and one clean row per id.
  - Build targets from plain arrays: a `LintTarget::fromDocument(array $document, array $metadata, PieceKind $kind)` constructor wrapped by `fromRevision()` avoids database rows. Run a single rule by resolving `app(VoiceLinter::class)` and filtering by id, or by constructing the rule directly.
  - Clean rows use the register examples in `voice/core.md:116-134`.
  - Two-way check: `expect(app(VoiceLinter::class)->ruleIds())->toBe(app(VoiceCanon::class)->snapshot()->ruleIds())`. Both lists are sorted and unique.
  - Drafter audit: every line of `mechanical-rules.md` containing `[rule:` must appear in the Draft system message `messages.0.content`. Capture it with an `inputContractRun`-style helper and a Draft started with prerequisites.
  - Every id must appear in `(new Drafter($activity))->schema(new JsonSchemaTypeFactory)['document']->toArray()['description']`. In the HTTP body the path is `response_format.json_schema.schema.properties.document.description` (`vendor/laravel/ai/src/Gateway/OpenAiCompatible/Concerns/BuildsTextRequests.php:68-80`).
- **VoiceLintWorkflowTest setup**
  - Run `setPublishingAgentsPaused(false)`, `config()->set('ai.providers.openrouter.key', 'test-key')`, `Queue::fake()` (follow-on activities are created but not dispatched) and `Http::preventStrayRequests()`.
  - Route `Http::fake` responses by the decoded `messages.1.content.kind`.
  - Run each activity with `app(RunEditorialActivity::class, ['activityId' => $id])->handle(app(EditorialOutput::class), app(WriteArticle::class))`.
  - The attempt must have an empty working document so the draft applies; use the `reviewAttempt` pattern from EditorialReviewTest:557.
  - Map the spec experiments (a) through (e) to:
    - (a) two em dashes fixed by repair 1; three lenses queue.
    - (b) repairs that never fix the hit: exactly two `lint_repair` activities, then one blocking `lint` finding and the lenses.
    - (c) a protected block containing "leverage": the patch is attached to the finding, never applied, and Accept shows the unlock message.
    - (d) a workspace `saveDocument` that adds a curly quote creates one finding.
    - (e) is covered in ReleaseReadinessTest.
  - Also: review finish is not blocked by lint findings, and reconciliation input excludes `lint`.
- **ReleaseReadinessTest**: create lint findings with `readinessFinding($attempt, $revision, ['lens' => 'lint', 'kind' => 'lint:em-dash', 'block_id' => ..., 'input_hash' => $hit->inputHash(), 'disposition' => 'false_positive', 'disposition_reason' => '...'])` on a document with an em dash. Add the /walkthrough closing paragraph to `readinessDocument` so the other tests stay unblocked.
- **Environment**
  - `birdcar_test` is confirmed on 127.0.0.1:5433.
  - No `--parallel`.
  - Never run `tests/Manual` (paid); PHPStan still analyses it.
  - The workspace UI needs `bun run build` only if assets change, and Blade changes don't need it.

---

## Key Patterns (Phase 2)

- `app/Services/Publishing/ArchiveSourceManifest.php:17-46` (the spec's pattern):
  - A plain `class` in `App\Services\Publishing` that hashes with `hash_file('sha256', $path)`.
  - The spec injects `Illuminate\Filesystem\Filesystem` instead. Use `$files->hash($path, 'sha256')` (Filesystem.php:189) and `$files->allFiles($dir)` (600). `allFiles` skips hidden files by default; `.DS_Store` is gitignored but does exist on the owner's Mac.
- `app/Services/Publishing/PublishingFingerprint.php`: `canonicalJson()` ksorts keys; use it if you hash structured data. The manifest itself is plain `path\thash` lines.
- `app/Services/Publishing/ArchiveMarkdownImporter.php:50-60`: frontmatter split `preg_split('/\A---\R|\R---\R/', $content, 3)` plus `Symfony\Component\Yaml\Yaml::parse($parts[1])`, where the title is `$metadata['title']`. Reuse this for exemplar titles and bodies.
- `app/Actions/ReadWriting.php:14,28,45`: the canonical directive shapes.
  - `@figure ...\R@endfigure`
  - `/^@(aside|callout)(?: title="..."| type=key)\R(.*?)\R@end\1[\t ]*$/ms`
  - Match these when dropping blocks.
- `app/Actions/Admin/InviteAdministrator.php:355`: `final readonly class AdminInvitationResult` is the precedent for `final readonly class CanonSnapshot`. Other services and actions are non-final `class`, and the `Catalog` classes are `final`, so `final class VoiceCanon` per the spec is fine.
- Commands (`app/Console/Commands/PublishingImportArchive.php`, `RecoverEditorialActivities.php`):
  - `protected $signature` and `$description` properties.
  - `handle(...)` takes DI and returns `Command::SUCCESS`, writing output with `$this->line()`.
  - Command tests use `$this->artisan('x')->expectsOutput(...)->assertSuccessful()` (`tests/Feature/Authorization/AuthorizationSyncCommandTest.php:26-28`).
- Role agents (`app/Ai/Agents/{Interviewer,Researcher,Planner,Drafter,FactReviewer,VoiceReviewer,BuyerReviewer,ReviewReconciler,RevisionRechecker}.php`): each has `#[MaxTokens(n)]`, `model()`, `roleInstructions()` and `roleReasoningEffort()`. Only `roleInstructions()` changes. EditorialAgentsTest:144-152 pins the models, efforts and MaxTokens.
- Message constants:
  - `EditorialActivity::*_PAUSE_REASON`, lines 31-42.
  - `RunEditorialActivity::OUTPUT_LIMIT_ERROR` and `MODEL_CONTINUITY_ERROR`, lines 43-45.
- Outbound-request tests:
  - `EditorialWorkflowTest.php:326-331`: `Http::preventStrayRequests(); Http::fake(['https://openrouter.ai/api/v1/chat/completions' => fn () => Http::response(array_shift($responses))])`. The response shape is `['id' => ..., 'choices' => [['message' => ['content' => json_encode($payload)]]]]`.
  - `messages.0.content` is the system message (instructions). `messages.1.content` is the user prompt JSON (`kind`, `activity_id`, `input`).
  - `PublishingJourneyTest.php:30-80` routes each fake response by the decoded `kind`, which is the better template for a per-kind dataset.
- Minimal valid structured payloads per kind: `EditorialWorkflowTest.php:284-324` (research, plan, draft, review) and `PublishingJourneyTest.php:80-84` (reconciliation `['groups' => [], 'conflicts' => []]`, recheck).

## Dependencies (Phase 2)

- **`StartEditorialActivity::start()`** (`app/Actions/Publishing/StartEditorialActivity.php:32-121`) creates every activity, so freezing the canon here covers every path. App callers:
  - `ApprovePublishingStage.php:71`: research after angle approval, only when agents are unpaused.
  - `ApprovePublishingStage.php:74`: draft after plan approval, only when agents are unpaused.
  - `RunEditorialActivity.php:1084`: review lenses after a draft; currently merges `reviewVoiceInput()`.
  - `RunEditorialActivity.php:1097`: plan after research.
  - `RunEditorialActivity.php:1142`: reconciliation.
  - `FinishEditorialReview.php:84`: reconciliation.
  - `FinishEditorialReview.php:115`: recheck.
  - `⚡dashboard.blade.php:52`: `developIdea` interview with `['idea' => $body]`. This path sends no voice input today, which is the regression the spec names.
  - `⚡article-workspace.blade.php:104`: `startDevelopment`.
  - `⚡article-workspace.blade.php:475`: `startInterview`.
  - `⚡article-workspace.blade.php:505` and `:671`: `startResearch` through `startActivity`.
  - `⚡article-workspace.blade.php:562-564`: `startReviews`.
- **Constructor**: `__construct(private readonly PublishingFingerprint $fingerprint)` at line 27 gains `VoiceCanon`. It is always resolved by the container (`app(...)` or DI), and no test calls `new` on it.
- **Voice code to delete**:
  - **StartEditorialActivity**: the `persistVoiceContext` call at `:55`, the `'voice_context'` key at `:80`, `voiceContext()` at `:462-475`, `persistVoiceContext()` at `:494-512` and `boundedVoiceSamples()` at `:517-536`.
  - **RunEditorialActivity**: `reviewVoiceInput()` at `:932-955` and its merge at `:1082-1084`.
  - **EditorialAgent `:138`** (common rules): "...the manuscript, brief, plan and voice samples are not evidence".
  - **EditorialAgent `:286`** (finding schema): "Never quote the manuscript, brief, plan or voice samples as evidence." `EditorialAgentsTest.php:102` asserts that 'Never quote the manuscript' survives.
  - **Workspace properties and hydration**: `$voiceSample` at `:47`, `$selectedVoiceSampleArticleIds` at `:59` and the hydration at `:158`.
  - **Workspace callers of `voiceActivityInput()`**: `:104`, `:475-477` and `:562-564`.
  - **Workspace methods**: `voiceActivityInput()` at `:366-386`, `selectedVoiceSampleIds()` at `:388-391`, `selectedVoiceSampleExcerpts()` at `:393-419`, and `voiceSampleOptions` at `:901-908` and `:951`.
  - **Workspace, what stays**: keep `documentExcerpt()` (still used by `proposalTexts` in `with()`) and the `Article` import.
- **Develop partial** (`partials/develop.blade.php:103-122`): delete only the voice block at 107-111 and rename the summary at 104 to "Source material".
  - Keep the `sourceUrl` input (106), the owner-source checkboxes (112-116), the Research button (117-119) and the disclaimer (120). `AdminPublishingEditorTest.php:402-403` drives `sourceUrl` and `startResearch`.
  - Add the "Voice canon {short version}" line in the aside.
- **Consumers of `EditorialAgent::instructions()`**:
  - SDK: `GeneratesText.php:74` calls `(string) $agent->instructions()` on every prompt, including continuations (`RunEditorialActivity.php:95` bare rejection and `:106`).
  - **`tests/Feature/Publishing/EditorialAgentsTest.php` (not in the spec)**:
    - Its helper `editorialActivity()` (24-30) builds `['frozen' => ...]` input with no canon.
    - These call `instructions()` or `prompt()`: lines 52 and 61-63, line 107, `sentEditorialAgentRequest` (111-123) used by the 9-row dataset at 125, the 3-row dataset at 155, and the tests at 163 and 172.
    - Give the helper a real canon block, ideally through a shared builder such as `CanonSnapshot::frozenFor($kind)`, so the tests do not reimplement it.
    - Phrases the file asserts must survive: 'untrusted evidence', 'AskAuthor', 'never join separate passages with ellipses', 'contradicting quotation must be severity "blocking"'.
  - Safe: `recommendedModelFor()` (73) and `RunEditorialActivity::configurationProblem()` (294) build agents without input but only call `model()`.
- **Manual trial (`tests/Manual/PublishingModelTrialTest.php`)**:
  - Voice-sample setup: the voice article at 141-148, `->set('selectedVoiceSampleArticleIds', ...)` at 163 and 234, and the fixture key `voice_sample` at 571 and 588. All match the sweep regex, so rename or delete them.
  - Replay modes (`liveTrialRecordedActivity` 363-393 feeding `liveTrialStandalone` 294-313) build `new EditorialActivity(['input' => $recorded])` with no canon, so `instructions()` throws. Either merge a freshly frozen canon into the replayed input or let replays refuse. Don't run the trial; it is paid.
- **Tests the spec names**:
  - `EditorialWorkflowTest.php:339-343`: forceFills `voice_sample_ids`/`voice_samples`.
  - `EditorialWorkflowTest.php:374-381`: asserts a voice excerpt is in the prompt.
  - `EditorialWorkflowTest.php:399-400`: asserts `input.voice_context.samples.0.excerpt`.
  - `AdminPublishingEditorTest.php:371-408`: sets `selectedVoiceSampleArticleIds` and asserts `input['voice_sample_ids']`.
  - `PublishingJourneyTest.php:90-98`: archive voice article setup.
  - `PublishingJourneyTest.php:117`: sets `selectedVoiceSampleArticleIds`.
  - `PublishingJourneyTest.php:177-185`: asserts the archive voice text reaches review_facts.
  - Any `->set()` on a deleted Livewire property throws, so all of these must change.
- **Readers of activity input** (the frozen canon makes rows larger):
  - `EditorialAgent::promptText()` (143-150) JSON-encodes the whole input into the user message.
  - `ReadAdminHome.php:64-66` selects the full `input` for the latest activity per kind; works unchanged, rows are just bigger.
  - `ReleaseFreshnessManifest.php:262` hashes `$activity->input`; it is recorded, never compared with the live canon.
  - `RunEditorialActivity.php:704,756` read `review_findings` and `evidence_sources`; unaffected.
- **Restart paths for activities created before the canon**:
  - `RetryEditorialActivity` moves only Failed activities to Pending and does not refreeze input.
  - `AdvancePublishingAttempt.php:304` resumes only `RESUMABLE_PAUSE_REASONS`.
  - `RecoverEditorialActivities.php:27,34` releases only `CONFIGURATION_PAUSE_REASONS`, through the static `configurationProblem()`.
  - `partials/activity.blade.php:10-11` shows `pause_reason ?? error_reason`, plus a Retry button for Failed activities.
- **Dev data** (read-only query on `birdcar_development`): 6 completed activities and 1 **failed `review_buyer`**, all carrying `voice_context`. That failed activity will hit the pre-canon gate after deploy.
- **PRODUCT.md `:295-331`** Written Voice. The "Voice references" list at `:318-331` includes "Your metrics are bullshit", which is not one of the four exemplars, and the public register drops profanity. Replace the list with a pointer to `resources/publishing/canon/` and the four exemplar titles.

## Conventions (Phase 2 additions)

- **Config**: no canon path config exists. `config/publishing_agents.php` is where publishing-agent config lives. `EditorialAgentsTest.php:181-182` asserts that config has no `routes`, `role_routes` or `enabled` keys and contains no "price" text, so adding a `canon` key is safe.
- **Bindings**: `AppServiceProvider::register()` is empty; add `$this->app->scoped(VoiceCanon::class)` there. Scoped instances are flushed per request (Octane) and per job (queue worker).
- **Test helper prefixes**: `voiceCanon*` and `inputContract*`. Running a file alone does not load another file's global helpers (EditorialWorkflowTest's `approvedEditorialAttempt` and so on), so copy the ones you need under the new prefix.
- **Line endings**: `.gitattributes` sets `* text=auto eol=lf`, so file hashes match across the Mac, CI and production.
- **Command creation**: `php artisan make:command PublishingCanonHash --no-interaction`. The custom stubs in `stubs/` cover only agents and tools.

## Risks (Phase 2)

- **The spec misses EditorialAgentsTest.** Once `instructions()` throws without `input.canon`, about 16 cases fail. See Dependencies.
- **"Paths relative to the repo root" conflicts with the temp-directory override.**
  - When VoiceCanonTest points the config at a temporary copy, a real path is no longer under `base_path()`. Build logical manifest paths that don't depend on where the files sit: `resources/publishing/canon/<rel>` and `resources/writing/<slug>.md`. A byte-identical copy then yields the same version, and Phase 8's seeded database versions can match hashes.
  - The "archive source changed" test also needs an override for the archive directory, because `resources/writing/**` must not be edited.
- **Tension with a project rule.**
  - `.ai/rules/actions.md` says "Preserve the original archive files only as explicit import/parity inputs". The exemplar canon reads `resources/writing/*.md` at runtime.
  - The rule is scoped to `app/Actions/**` and to public writing readers, while VoiceCanon lives in `app/Services` and serves agents, not the public. The spec's own archive-hashing design (which the contract's fixed-exemplar decision assumes) depends on these reads.
  - Not blocking. Note it in the implementation notes for the owner.
- **Duplicate tokens.**
  - `promptText()` sends the whole input, so the rubric and goals text appear in both the system and the user message (about 15-20k extra tokens for Drafter).
  - Consider leaving `canon.rubric` and `canon.goals` out of `promptText()` while keeping `version`, `role`, `rubric_files`, `exemplars` and `exemplar_label`. The system message still satisfies the contract.
  - Either way, record the choice.
- **Restart semantics for activities created before the canon.**
  - Put the check in `handle()` before the agent and conversation are built (`RunEditorialActivity.php:80-89`). Don't put it in the static `configurationProblem()`, which RecoverEditorialActivities reuses.
  - Throwing inside the `try` before `$invoked = true` goes through `handleFailure` (957-963), which **pauses** the activity with `pause_reason` set to the message; it does not fail it. Marking it Failed would show Retry, and Retry would fail the same way forever.
  - Keep the new message out of `RESUMABLE_PAUSE_REASONS` and `CONFIGURATION_PAUSE_REASONS`.
  - The spec says "fails". Decide what the test asserts (status and reason) and record the choice.
  - For review, reconciliation and recheck kinds, `idempotencyPayload()` (541-566) leaves out `prompt_hash`. Restarting in the same cycle, batch key and revision therefore returns the old row; a new review cycle or a different batch key is the way to restart.
- **Shortening role instructions can drop output contracts.**
  - The `ReviewReconciler` instructions say to use only finding `id` values and to choose `canonical_finding_id`. The `RevisionRechecker` instructions say to classify every finding exactly once by `finding_id` and `block_id`.
  - `RunEditorialActivity` enforces both: `ensureRecheckAssessedEveryFinding` (702-720) and `canonicalFindingId` (908).
  - Move this wording into `roles/reconciler.md` and `roles/rechecker.md` (both always frozen) or into the common rules. Do not drop it.
- **Frameworks are hashed but no role receives them.**
  - No `roles.json` entry lists `frameworks/*.md`. The 13 source files total about 200 KB; Phase 4 owns delivering them, so don't add them to `plan` now.
  - Lines that need restating:
    - `channels/blog.md:93,102-149` uses `FW/` paths.
    - `channels/blog.md:107` names `FW/sisyphean-arc.md` and `FW/comedians-set.md`, which are not copied. Name them without a path.
    - `channels/blog.md:222` points at `~/.config/.../writer-editor-prompt.md`.
    - `registers/public.md` refers to `personal.md` and `style.md`.
  - A test that every `*.md` path a canon file mentions resolves inside the canon would catch these.
- **`ruleIds()` must scan canon files only.** `CanonSnapshot::$files` includes the archive sources, so leave those out. Assert 21 unique ids, and that the tag count equals the unique count, so each tag appears exactly once.
- **The contract asks for "version id and content hash".** In Phase 2 the two are the same manifest hash. Consider a `hash` key equal to `version` so Phase 8 can split them, or record why one key is enough.
- **Site copy source.**
  - `~/.config/bat-kol/samples/site-copy-raw.md` is Blade markup: homepage at lines 5-178, Walkthrough at 179-281.
  - `resources/views/pages/index.blade.php` and `walkthrough.blade.php` haven't changed since 2026-10-01, so extract text nodes from either.
  - The snapshot must keep the approved hero, "Better work, faster. Without another hire.", and the "fifteen years" wording.
- **`EditorialOutput::VERSION`** (2) is `prompt_version`. The spec doesn't bump it; bumping is optional, so record the choice.
- **Open item from the spec**: `.ai/rules/resources.md` still says "Use PRODUCT.md for … written voice". Do not call `record-rule`.
- **The decision log matches the code; no contradictions.**
  - No canon table or `VoiceCanon` exists, so the Phase 8 database-versions premise holds.
  - All four exemplar slugs exist in `resources/writing/`, all are dated 2026 (2026-04-03 to 2026-05-26) and none is a draft.
  - No `[rule:` tags exist anywhere yet, which fits the Phase 3 lint premise.
  - Every bat-kol source in the spec's Sources list exists locally, as do the 13 allowed framework files and `new-post-skill/SKILL.md` (with "Step 3A").
  - No source contains the phrase "voice sample", and `resources/writing` has 0 sweep hits.

## Edge Cases (Phase 2)

- **VoiceCanon manifest**
  - Use `allFiles()` with the default `hidden = false` so `.DS_Store` is never hashed; otherwise the local version differs from CI and production. Optionally include only `*.md` and `*.json`.
  - Sort `path\thash` lines with byte order (`sort($lines, SORT_STRING)`), not locale order.
  - Throw naming the path when a file listed in `roles.json` is missing. Throw naming the slug when an archive exemplar has no file. Throw on invalid `roles.json` or `manifest.json`. `rubricFor()` throws a `RuntimeException` when a kind has no entry.
  - `goals.md` is always included and never listed per role.
  - Version is stable across two snapshots and changes when any canon file or referenced archive source changes. Test both through the config override with a temporary copy.
- **Exemplar extraction**
  - Strip the YAML frontmatter.
  - Drop `@figure…@endfigure`, `@aside…@endaside` and `@callout…@endcallout` blocks.
  - Decide whether block contents go too. The epigraph in `the-tools-that-build-the-tools.md:11-13` is a quotation, not the owner's voice. The callout in `your-ai-wrote-a-bug.md:58-60` is the owner's own prose. Figures (`your-ai-wrote-a-bug.md:39-40`, `six-months-talking-to-a-machine.md:13-14`) carry only a caption.
  - Test that no line starts with `@figure`, `@aside`, `@callout` or `@end`, and that no `---` frontmatter remains.
  - Ids are the archive slug, plus the site-copy path. Titles come from frontmatter.
- **Rubric text**: concatenate in `roles.json` order with a per-file heading, so "the rubric's first heading" can be asserted. `rubric_files` maps path to hash.
- **Freezing**
  - `canon` belongs in the second `array_merge` argument (`StartEditorialActivity.php:61-81`), so a caller-supplied `$input['canon']` can't override it.
  - The canon changes `prompt_hash` and the idempotency key for non-review kinds. Both stay deterministic, so starting the same activity twice still returns one row.
  - `exemplars` are filled only for `draft` and `review_voice`; every other kind gets `[]`.
- **`instructions()`** renders only from `$this->activity->input['canon']`, never from live files. It throws a clear exception when the block is missing or malformed. The order is role instructions, `## Business goals`, `## Your rubric`, then the common rules.
- **Pre-canon gate**: no provider request (`Http::assertNothingSent()`) and no conversation row. The message is exactly "Created before the voice canon; restart this step."
- **Workspace**
  - "Voice canon {short}" (for example `Str::substr($version, 0, 12)`) comes from the live snapshot in `with()`. It renders for view-only users too and links nowhere.
  - The scoped binding reads the canon once per request.
- **Sweep**: `! grep -rniE 'voice[_ ]?samples?' app resources tests/Manual` must exit 0. It also catches comments and test fixture names in `tests/Manual`; canon files must never contain the phrase.
- **PRODUCT.md**: keep the owner-authoritative principles, point at the canon, list the four exemplars by title ("Just build it twice", "Your AI wrote a bug", "Six months of talking to a machine", "The tools that build the tools"), and remove guidance that contradicts the public register.

## Test Strategy (Phase 2)

- **Inner loop**: `php artisan test --compact tests/Feature/Publishing/VoiceCanonTest.php tests/Feature/Publishing/EditorialInputContractTest.php`
- **Canon tag check**: `test "$(grep -o '\[rule:[a-z0-9-]*\]' resources/publishing/canon/voice/mechanical-rules.md | sort -u | wc -l | tr -d ' ')" = 21`. Also assert the total tag count is 21, so no tag is duplicated.
- **Contract test setup**
  1. Agents are paused by default (`database/settings/2026_09_24_230000_create_publishing_agent_settings.php:9`). Build eligibility while paused so `ApprovePublishingStage` doesn't start activities on its own.
  2. Use prefixed copies of `approvedEditorialAttempt` (`EditorialWorkflowTest.php:498-516`), `publishingAttemptWithReviewedPlan` (518-542) and `publishingAttemptWithCompletedReviews` (544-570).
  3. Eligibility rules are in `StartEditorialActivity.php:126-209`. Recheck needs `recheck_used` set and completed facts, voice, buyer and reconciliation activities on `reviewed_revision_id`.
  4. Call `start()` for the kind.
  5. Run `setPublishingAgentsPaused(false)` and `config()->set('ai.providers.openrouter.key', 'test-key')`. `claimActivity` returns null while paused.
  6. Run the activity with `app(RunEditorialActivity::class, ['activityId' => $id])->handle(app(EditorialOutput::class), app(WriteArticle::class))`.
  7. Assert on `Http::recorded()`:
     - `messages.0.content` contains `## Business goals` and the frozen `input.canon.rubric`.
     - The decoded `messages.1.content` has `input.canon.version` equal to `app(VoiceCanon::class)->snapshot()->version`.
     - Exemplars: 4 archive entries plus site copy, with the label, for draft and review_voice only.
- **Pre-canon test**: create the activity with `EditorialActivity::factory()` or `::create` using valid prerequisites and input without `canon`, run it, then assert the status and reason and `Http::assertNothingSent()`.
- **Dashboard path**: `Livewire::actingAs($u)->test('admin.publishing.dashboard')->set('idea', '…')->call('developIdea')`, following `AdminPublishingWorkspaceTest.php:255,304`. Then assert that the interview activity's input has `canon.version`.
- **Command test**: `$this->artisan('publishing:canon-hash')->expectsOutput($version)->assertSuccessful()`.
- **Regression files**: `php artisan test --compact tests/Feature/Publishing/EditorialAgentsTest.php tests/Feature/Publishing/EditorialWorkflowTest.php tests/Feature/Publishing/AdminPublishingEditorTest.php tests/Feature/Publishing/PublishingJourneyTest.php tests/Feature/Publishing/EditorialToolApprovalTest.php tests/Feature/Publishing/AgentBudgetTest.php`
- **Phase check**: `php artisan test --compact tests/Feature/Publishing`, then `! grep -rniE 'voice[_ ]?samples?' app resources tests/Manual`.
- **Final**:
  - `vendor/bin/pint --dirty --format agent`
  - `env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress`. Level 7 also covers `tests/Manual` and the new tests; `json_decode` of `roles.json` needs shape guards.
  - `composer test`
- **Environment**:
  - `birdcar_test` exists on 127.0.0.1:5433 (confirmed this session). No `--parallel`.
  - Never run `tests/Manual` (paid).
  - Run the experiments in `resources/publishing/canon/` (count `[rule:` tags; grep for `~/.config`, `/Users/`, `FW/`, "voice sample") after writing each canon file.

---

## Key Patterns (Phase 1)

- `app/Models/Article.php:14-16`:
  - Class-level attributes `#[RouteKey('slug')]`, `#[Sluggable(...)]` (remove this one) and `#[Fillable([...])]`.
  - `casts()` returns `array<string, string>`.
  - Relations carry generic docblocks (`@return BelongsTo<User, $this>`, `@return HasMany<ArticleRevision, $this>`).
  - Models use `/** @use HasFactory<XFactory> */ use HasFactory;`.
  - Every sibling model is a plain non-final `class`. The spec sketch says `final class ContentIdea`, so pick one deliberately (siblings favour non-final).
- `app/Models/PublishingAttempt.php:13-35`: enum cast `'stage' => EditorialStage::class`. Use the same form for `'kind' => PieceKind::class`.
- `app/Models/Publishing/EditorialStage.php`, `ApprovalKind.php`: backed string enums with TitleCase cases and no methods so far. `PieceKind` becomes the first enum with methods (`label()`, `isKit()`) in `App\Models\Publishing`.
- `app/Actions/Publishing/WriteArticle.php`:
  - Constructor-promoted private dependencies.
  - `authorize(User, string)` (176-181) throws `AuthorizationException('This user is not allowed to mutate publishing records.')`.
  - Typed transactions (60-117) use `/** @var T $x */ $x = DB::transaction(function () use (...): T {...});`.
  - `capture()` (29-38) builds the provisional slug as `Str::slug($slug ?: 'idea-'.Str::lower(Str::random(12))) ?: 'article'`.
- `database/factories/PublishingAttemptFactory.php`: `@extends Factory<Model>`, `definition(): array` with nested `Model::factory()` for foreign keys, and named states that return `static` via `$this->state(fn (array $attributes): array => [...])`.
- `database/migrations/2026_09_24_230100_remove_publishing_budget_storage.php`: anonymous migration class. Data work uses the query builder (`DB::table()->orderBy('id')->chunkById(200, ...)`), not Eloquent. It guards with `Schema::hasTable`/`hasColumn`, and a class docblock states what `down()` cannot restore.
- `tests/Feature/Publishing/PublishingBudgetRemovalTest.php:22-36,88-118`: tests a migration by `require database_path('migrations/...')`, calling `down()`, seeding legacy rows, then calling `up()`. It declares a **global** `interface RunnableMigration`. Do not redeclare that name in ContentIdeaTest, because Pest loads every file into one process. Use a prefixed name such as `ContentIdeaRunnableMigration`.
- `tests/Feature/Publishing/ArticleSlugTest.php`: `beforeEach` forgets the permission cache and runs `authorization:sync`. File-local helpers carry a prefix (`articleSlugAuthor`).
- `tests/Feature/Publishing/AdminPublishingWorkspaceTest.php:25-120`: global helpers `publishingUser()`, `publishingWriteOnlyUser()`, `activeArticleFor()`, `publishedArticleFor()` and `workspaceXpath*`. Don't redeclare them. ContentIdeaTest needs its own `contentIdea*` helpers, because those globals are not loaded when the file runs alone.
- Livewire action pattern in `⚡article-workspace.blade.php:110-125` (`saveDetails`): `Gate::authorize('update', $this->article)`, then `$this->validate([...])`, then call the action. Copy this for inline idea rename.

## Dependencies (Phase 1)

- **`articles.idea` readers.** Every one must change; with `preventAccessingMissingAttributes`, any missed one throws in tests.
  - `app/Models/Article.php:15-16` (Sluggable source and fillable)
  - `app/Actions/Publishing/WriteArticle.php:35`
  - `app/Actions/Publishing/ImportWritingArchive.php:77`
  - `app/Actions/Publishing/ReadPublishedWriting.php:75`
  - `⚡dashboard.blade.php:51-52` (brief and interview input), `:91` (`titleFor`), `:207-211` and `:245` (preview and "View full idea")
  - `⚡article-workspace.blade.php:100` (develop input) and `:911` (`articleTitle`)
  - `partials/develop.blade.php:101` ("The original idea") and `:109` (voice-sample label fallback; **not in spec**)
  - `⚡published.blade.php:36` and `:119-120`
  - `components/admin/⚡index.blade.php:87,90` (aria-label) and `:110`
  - `admin/publishing/preview.blade.php:7,15`
  - `partials/release-checklist.blade.php:30`
  - `database/factories/ArticleFactory.php:27`
- **`$articleTitle` render site.** `resources/views/components/admin/publishing/partials/session.blade.php:5` (`<flux:heading level="1">`). The labeled idea line and the inline rename UI go here, not in the workspace component markup.
- **`WriteArticle::capture()` callers (100 in Feature, by file).** ReleaseIntegrityTest 23, AdminPublishingEditorTest 21, ReleaseReadinessTest 14, EditorialWorkflowTest 14, PublishingSessionTest 9, PublishingAuthorizationTest 4, ArticleSlugTest 4, DocumentRoundTripTest 2, PublishingJourneyTest 2, and 1 each in EditorialToolApprovalTest, PublishingAgentSettingsTest, EditorialReviewTest, AgentBudgetTest and EvidenceResearchTest.
  - Also `tests/Manual/PublishingModelTrialTest.php:140,149`. `phpstan.neon` analyses `tests/`, so PHPStan fails if these aren't updated.
  - App callers: `⚡dashboard.blade.php:32,50`.
  - Only ArticleSlugTest depends on the slug that `capture()` returns. Release tests pass slugs to `ManageArticleRelease::prepare()` directly, and AdminPublishingEditorTest uses `$article->slug` dynamically.
- **`Article::factory()` with `idea` overrides.**
  - `tests/Feature/AdminHomeTest.php:45,92`
  - `tests/Feature/Publishing/AdminPublishingWorkspaceTest.php:57,98,126,313,315,336,357`, plus `Article::query()->where('idea', ...)` at 212, 225, 243, 258 and 306
  - `ReleaseReadinessTest.php:473`
  - `PublicWritingTest.php:94,116`
  - `ArticleSlugTest.php:36`
- **Factories that nest `Article::factory()`.** Each will now also create a ContentIdea: `EditorialFindingFactory:18`, `EvidenceSourceFactory:19`, `EditorialActivityFactory:20`, `ArticleReleaseFactory:113`, `ArticleRevisionFactory:37`, `PublishingAttemptFactory:24`.
- **`ImportWritingArchive::write`.** Called by `app/Console/Commands/PublishingImportArchive.php`, `tests/Feature/MarketingSiteTest.php:447`, `tests/Feature/MarketingDiagramTest.php:216` and `ArchiveMigrationTest.php` (85, 101-126, production command test).
- **`ReadPublishedWriting`.** Consumed by `routes/web.php`, `resources/views/pages/writing/index.blade.php`, `resources/views/pages/writing/[slug].blade.php`, `PublicWritingTest`, `MarketingSiteTest`.
- **List queries that must eager-load `contentIdea`** (strict mode throws on N+1 in multi-model results):
  - `⚡dashboard.blade.php:63-82`, both paginators
  - `⚡published.blade.php:22-28`
  - `app/Actions/Admin/ReadAdminHome.php:37-60` (feeds `⚡index`; **not in spec**). Its eager loads use column-constrained selects such as `workingRevision:id,article_id,...`. If you constrain `contentIdea`, include every column you read, or `preventAccessingMissingAttributes` throws.
  - Workspace `voiceSampleOptions` (`⚡article-workspace.blade.php:~899-905`). The simplest fix is to drop the idea fallback at `develop.blade.php:109`; Phase 2 deletes that checklist anyway.
- **`ArticlePolicy`** (`app/Policies/ArticlePolicy.php`): view, update, develop, approve and publish all require the permission **and** `article.author_id === user.id`. `WriteArticle` checks only the permission, so the Livewire rename must call `Gate::authorize('update', $this->article)`.
- **Slug rules after `#[Sluggable]` is removed** (the package stays; `app/Models/Organization.php` still uses it). Collisions are enforced only at release: `CheckArticleRelease.php:86-90` (`slug.required`, `slug.unique`, `slug.immutable`) and `ManageArticleRelease.php:433-451` (`canonicalSlug` throws). `ManageArticleRelease.php:171-172` rewrites the provisional slug to the canonical one at prepare.
- **`phpunit.xml`.** Used by `php artisan test`, `vendor/bin/pest` and composer `test` (`config:clear`, `pint --test`, `phpstan`, `artisan test`), and by CI through `composer ci:check`.
- **`.github/workflows/tests.yml`.** "Setup Application" runs `composer setup`, which runs `php artisan migrate --force` against the new service DB. Job-level env overrides `.env`, which is copied from `.env.example` and has `DB_CONNECTION=sqlite` at line 23, because Dotenv is immutable.

## Conventions

- **Naming**:
  - Actions live in `App\Actions\Publishing\*`; enums in `App\Models\Publishing\*`; models in `App\Models\*`.
  - Migrations are `YYYY_MM_DD_HHMMSS_snake.php`.
  - Test helpers are prefixed with the file's subject.
  - Livewire SFCs are named like `⚡dashboard.blade.php` and resolve to components such as `admin.publishing.dashboard`, `admin.publishing.article-workspace`, `admin.publishing.published` and `admin.index`.
- **Imports**: fully-qualified `use` statements, no barrels. Permissions are aliased (`use App\Authorization\Publishing\Permission as PublishingPermission;`). Livewire SFCs import at the top of the PHP block.
- **Error handling**:
  - Actions throw `AuthorizationException` for permission failures and `RuntimeException` or `InvalidArgumentException` for domain errors.
  - Livewire calls `$this->validate()` and `Gate::authorize()`; the workspace catches `Throwable` into `$this->saveError`.
  - For rename, the spec wants a `ValidationException`; use `ValidationException::withMessages([...])`.
- **Types**:
  - Return types are explicit; generics go in PHPDoc (`HasMany<Article, $this>`), with array shapes in docblocks.
  - PHPStan runs at level 7 over `app/`, `config/`, `database/`, `routes/` and `tests/`, with no baseline and no `@phpstan-ignore`.
  - Run PHPStan as `env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress` to get full output.
- **Testing**:
  - Pest with `RefreshDatabase` applied to `tests/Feature` in `tests/Pest.php`.
  - Chained `expect()->and()` assertions.
  - Rendering is tested with `$this->actingAs($u)->get('http://admin.birdcar.test/...')` or `Livewire\Livewire::actingAs($u)->test('admin.publishing.dashboard')`.
  - `beforeEach` runs `authorization:sync`.
  - Run `vendor/bin/pint --dirty --format agent` after PHP edits.
- **Agent-input key**: brief and interview inputs use the key `'idea'` (`['idea' => ...]` in `⚡dashboard.blade.php:51-52` and `⚡article-workspace.blade.php:100`). Keep the key and change only its source to `contentIdea->body`. Likewise keep the Livewire `$idea` property and `flux:error name="idea"`. The spec's sweep (`grep -rn "'idea'"`) will flag these too; they are not column reads.

## Risks (Phase 1)

- **CI "Load published writing" step is already broken, and the spec misreads it.** `tests.yml:58-72` calls `App\Actions\ReadWriting::all()`, but `app/Actions/ReadWriting.php` has only `render()`. Run 36761682308 logged `Call to undefined method App\Actions\ReadWriting::all()` and the step still finished `success`. The spec also assumes an "archive data migration" stays applied; none exists in `database/migrations`, and the archive only arrives through `publishing:import-archive`. Pointing the step at `ReadPublishedWriting::all()` would make it fail for real (`birdcar_test` has no published rows after the suite). Leave the step alone and report it to the owner rather than "fixing" it in Phase 1.
- **FK conflict in the spec's data model.** The spec has `content_ideas.author_id ... ON DELETE CASCADE` and `articles.content_idea_id ... ON DELETE RESTRICT`, while `articles.author_id` is nullable with `nullOnDelete` (`create_publishing_tables.php:17`). Deleting a User who has articles would then fail at the restrict FK; `User::booted()` has a deleting hook. No test covers this today. Decide on purpose: accept it, or make `content_ideas.author_id` nullable with `nullOnDelete` to match articles.
- **Backfill nulls.** `articles.idea` and `articles.author_id` are both nullable, but the new `body` and `author_id` are NOT NULL. Coalesce idea to `''` (which `deriveSummaryTitle` turns into "Untitled idea") and decide how to handle a null author. The dev DB has 11 articles, none with a null idea or author; 10 are archive imports with titles of 15-38 characters, and the longest idea is 259 characters. Identify archive articles by an `article_releases` row with `origin = 'import'` (the convention `ManageArticleRelease.php:421` uses).
- **Postgres jsonb reorders object keys, so Step A will probably expose a real bug.** `WriteArticle.php:306` checks `$incomingBlocks[$id] !== $block`, comparing a block read from jsonb with canonicalized incoming arrays. Strict `!==` is order-sensitive, so this likely raises a false "Agent edits cannot modify protected blocks." in `DocumentRoundTripTest.php:113`. Fix it in app code, for example by comparing `PublishingFingerprint::canonicalJson()` of both sides (`PublishingFingerprint` already ksorts, so hashes are safe). Also watch the `toBe` checks on jsonb-read arrays at `AdminPublishingEditorTest.php:150-151`, `PublishingSessionTest.php:91` and `DocumentRoundTripTest.php:24`.
- **Manual trial guard requires SQLite.** `tests/Manual/PublishingModelTrialTest.php:83-98` (`liveTrialRefuseUnlessIsolated`) refuses unless the connection is SQLite `:memory:`, so after Step A it always refuses. The check grep only covers `phpunit.xml` and `tests.yml`, so it won't catch this. Phase 9's `VoiceDriftTrialTest` copies the guard and expects Postgres. Consider changing it to require `pgsql` plus `birdcar_test` now, and update its `capture()` calls in any case (PHPStan).
- **`kind` default isn't on the in-memory model.** The DB default `'blog_post'` is not loaded back after `Article::create()`. Set `kind` explicitly in `captureIdea`, `addPiece`, `ImportWritingArchive` and `ArticleFactory`, or `$article->kind` is null on the just-created model.
- **ArticleFactory must give the idea the same author as the article.** Use a closure such as `'content_idea_id' => fn (array $a) => ContentIdea::factory()->state(['author_id' => $a['author_id']])`, ordered after `author_id`. Otherwise owner-scoped tests break: `->for($user, 'author')`, `['author_id' => ...]`, and the dashboard `where('author_id', ...)`.
- **Removing `#[Sluggable]` removes collision suffixing.** `ArticleSlugTest.php:13-22` (expects `shared-slug-1`) and `:24-31` (`'!!!'` becomes `article`) both depend on `capture()`'s slug parameter, so rewrite them around `captureIdea`, which takes no slug, and release canonicalization. `ImportWritingArchive` already refuses collisions itself (`conflicts()`).
- **Dashboard test expectations change.** `AdminPublishingWorkspaceTest.php:310-330` asserts `Str::limit($longIdea, 100)` (now an at-most-81-character summary) and `'Final accessible sentence.'`. Keep the "View full idea" disclosure reading `contentIdea->body`.
- **Herd environment is flaky.** Herd Postgres refused connections once during this scout and came back (PID 5150, listening on 5433). The `herd` CLI phar reports internal corruption ("actual filesize mismatch"), so confirm the database with `psql -h 127.0.0.1 -p 5433 -U root -l` or `lsof -nP -iTCP:5433 -sTCP:LISTEN`, not `herd services:*`. Locally it is PG 18.0; CI uses `pgvector/pgvector:pg17`.
- **No `--parallel`.** `php artisan test --parallel` creates `birdcar_test_N` databases, and the spec forbids creating databases.
- **Placeholder test.** `AgentBudgetTest.php:383` ("lock contention needs a non-sqlite transactional fixture") is a placeholder. Leave it as is; expanding it is out of scope.
- **Decision log checked against the code; no contradictions.** SQLite does throw on vector columns (`Illuminate/Database/Schema/Grammars/Grammar.php:377-380`, Laravel 13.33.0), so the Postgres-switch rationale holds. `Article` is still the piece model that revisions, releases and attempts hang off. `birdcar_test` exists, and dev already runs on pgsql (`birdcar_development`).

## Edge Cases (Phase 1)

- **`deriveSummaryTitle`**:
  - A one-line body returns the line.
  - A 2,000-character body returns at most 81 characters ending in "…" at a word boundary.
  - A single word longer than 80 characters has no boundary, so hard-cut at 80 and add "…".
  - `# Heading\nmore` returns "Heading".
  - Strip the leading markers `-`, `*`, `>` and `N.`. A line that is only a marker counts as empty, so use the next line.
  - Skip leading lines that are only whitespace; a whitespace-only body returns "Untitled idea".
  - Handle CRLF line endings.
  - For a non-Latin first line, use `mb_*` and `Str` functions so a cut never splits a codepoint.
  - Decide whether inline Markdown (`**bold**`, `[text](url)`) is stripped: the Technical Approach says "Markdown stripped", but the sketch strips only leading markers.
- **`captureIdea`**: creates one idea and one `blog_post` piece in a single transaction. `parent_article_id` is null, the slug matches `/^idea-[a-z0-9]{12}$/`, and the idea's author is the actor. Without `publishing.write` it throws `AuthorizationException`.
- **`addPiece`**: a parent from a different idea should be rejected; `kind` is set explicitly; the slug is provisional.
- **`ContentIdea::flagship()`**: the oldest `blog_post` with no parent, or null when there is none. Query it; don't lazy-load a relation on a collection.
- **`renameIdea`**: trims input. Empty or longer than 120 characters (`mb_strlen`) throws a `ValidationException` and writes nothing. Sets `summary_title_source = owner` and never touches revision metadata. Without the permission it throws `AuthorizationException`; the Livewire side checks ownership through `Gate::authorize('update', $article)`.
- **Migration**:
  - Backfill in chunks of 200.
  - Archive rows get `summary_title = idea` (all 10 dev titles are at most 41 characters).
  - Make `content_idea_id` NOT NULL only after the backfill, then add the FKs and the `(content_idea_id, kind)` index.
  - `down()` adds `idea` back, fills it from `body`, drops the FKs, index and columns on `articles`, and only then drops `content_ideas`.
  - Test it with the require, `down()`, raw `DB::table('articles')->insert`, `up()` pattern. Postgres DDL is transactional inside `RefreshDatabase`.
- **Archive import twice**: the second run reports `already_imported` and creates no second idea. The clobber or conflict path is unchanged.
- **Labels on every surface**: the dashboard (both lists), workspace header (session partial), published list, and Admin index (attention list, the aria-label at line 90, and the continue list) all show "Idea" plus the summary, never the summary as a bare heading. `ReadPublishedWriting` falls back to `'Untitled'`, the preview to "Untitled draft", and the release checklist to metadata only.

## Test Strategy (Phase 1)

- **Step A**:
  1. Confirm the database: `psql -h 127.0.0.1 -p 5433 -U root -l | grep birdcar_test` (confirmed to exist).
  2. Edit `phpunit.xml` to set `DB_CONNECTION=pgsql` and `DB_DATABASE=birdcar_test`, keep `DB_URL=""`, and remove `:memory:`. Without `force`, PHPUnit `<env>` values win over `.env` but lose to process env. Locally, `.env` supplies host 127.0.0.1, port 5433, user root and the password.
  3. Run `php artisan test --compact`, then rerun each failing file alone.
  4. Check: `php artisan test --compact && ! grep -nE 'sqlite|:memory:' phpunit.xml .github/workflows/tests.yml`.
  5. Commit separately as `test(publishing): Run the suite on Postgres`.
- **Step B inner loop**: `php artisan test --compact tests/Feature/Publishing/ContentIdeaTest.php`.
- **Phase check**: `php artisan test --compact tests/Feature/Publishing/ContentIdeaTest.php tests/Feature/Publishing/ArticleSlugTest.php tests/Feature/Publishing/ArchiveMigrationTest.php`.
- **Sweep**: `rg -n "'idea'|->idea\b|->capture\(" app resources database tests` (remember the agent-input key `'idea'` stays).
- **Final**:
  - `vendor/bin/pint --dirty --format agent`
  - `env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress` (covers `tests/Manual`)
  - `composer test`
- **Manual**: capture an idea at `admin.birdcar.test/publishing` and confirm the workspace header shows it labeled as an idea.
