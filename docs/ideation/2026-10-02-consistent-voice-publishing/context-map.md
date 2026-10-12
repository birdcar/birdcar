# Context Map: Consistent Voice Publishing

**Phase**: 2 (Voice and goals canon in every prompt)
**Gates**: 5/5 ready
**Verdict**: GO

## Gates (Phase 2)

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
