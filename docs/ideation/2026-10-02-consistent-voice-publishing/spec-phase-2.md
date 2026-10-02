# Implementation Spec: Consistent Voice Publishing - Phase 2

**Contract**: ./contract.md
**Estimated Effort**: XL

## Technical Approach

This phase replaces the per-post voice sample checklist with a versioned voice-and-goals canon that every agent role receives on every run. The owner's bat-kol profile is decomposed once into repo-owned Markdown files under `resources/publishing/canon/`. Each file serves a purpose (shared goals, core voice, mechanical rules, blog channel structure, post types, frameworks, per-role rubrics, exemplars). `roles.json` maps every `EditorialActivityKind` to the ordered list of files that make up that role's rubric.

A small `VoiceCanon` service reads the directory and computes a sha256 per file. It also hashes the archive essay sources that the exemplar canon references, and derives the canon version as the sha256 of the sorted `path<TAB>hash` manifest. `StartEditorialActivity` freezes a `canon` block into every activity's input: the version, the role, the rubric file hashes, the rubric text, the shared goals text, and, for Drafter and VoiceReviewer, the fixed exemplar canon labeled voice-only. `EditorialAgent::instructions()` renders the goals and rubric from that frozen block, never from the live files, so a re-run or continuation sees the same canon it froze. There is no database table in this phase. The canon version is the manifest hash, and Phase 8 adds database versions seeded from these same files with matching hashes.

The checklist, its Livewire state, `voice_context`, the `voice_samples` plumbing and the instruction text that mentions voice samples are deleted outright. PRODUCT.md's Written Voice section is reconciled so it defers to the canon instead of contradicting it.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **Decompose bat-kol into repo-owned per-role rubric files as canon version 1; Admin-edited database versions win once the editor exists; a download of the active version is committed back**: rejected syncing from bat-kol by heading manifest, the repo always winning, or Admin-only storage after import. Production cannot read ~/.config, role decomposition is the point, and the canon will eventually replace bat-kol.
- **Canon version 1 in Phases 2-7 is the repo files frozen by sha256 manifest hash; database versions, the editor and deploy-time sync arrive in Phase 8**: rejected seeding immutable database versions in Phase 2. Later rubrics would be stranded outside already-seeded environments, and a one-row versions table does no work before the editor exists.
- **A fixed exemplar canon of four 2026 essays plus approved site copy, with retrieved sections added in Phase 5**: rejected a fixed canon only, per-post picking, and indexing material already frozen in full.
- **Several reviewers with sharper, distinct role rubrics plus deterministic lint ahead of them**: rejected one general reviewer, or a single agent with one advisory critic.
- **Native PHP linter with every mechanical canon rule tagged by id**: rejected Vale, and a lint dataset that only lists implemented rules. This phase writes the tags; Phase 3 implements the rules.
- **Self-repair rewrites only agent-authored blocks**: preserves the rule that agents never silently change the owner's prose.
- **Kit pieces cannot add claims outside the flagship's approved claim manifest**: noted for the fact-reviewer rubric's wording.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/VoiceCanonTest.php tests/Feature/Publishing/EditorialInputContractTest.php`

**Playground**: Pest feature suite with `Http::fake` on OpenRouter, reading outbound request bodies through `Http::recorded()`.

**Why this approach**: The contract is about what reaches the model, so the tightest loop asserts the actual outbound request body for each kind.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `resources/publishing/canon/roles.json` | Map of each activity kind value to `{"files": [...], "exemplars": bool}` |
| `resources/publishing/canon/goals.md` | Shared goals block: reader, offer, one Walkthrough invitation, outcome chain, claims and proof rules, exclusions |
| `resources/publishing/canon/voice/core.md` | Base voice: style.md craft plus public register voice, sentence, vocabulary, tone, openers/closers, "what public is not", examples |
| `resources/publishing/canon/voice/mechanical-rules.md` | Every mechanical rule, one per line, each ending with a `[rule:<id>]` tag |
| `resources/publishing/canon/channels/blog.md` | Blog channel structure: titles, headings, openings, section structure, length table, AEO and SEO rules, CTA rules, internal links |
| `resources/publishing/canon/post-types/essay.md`, `playbook.md`, `teardown.md`, `work-story.md` | One post-type template each (job, structure, allowed frameworks) from the blog channel |
| `resources/publishing/canon/frameworks/guide.md` | Framework selection rules restated for owner-audience posts |
| `resources/publishing/canon/frameworks/*.md` (13 files) | The blog-allowed framework files copied from new-post-skill with `~/.config` paths rewritten |
| `resources/publishing/canon/exemplars/manifest.json` | Fixed exemplar canon: four archive slugs plus the site-copy snapshot path |
| `resources/publishing/canon/exemplars/site-copy-2026-10-02.md` | Dated snapshot of approved homepage and Walkthrough copy |
| `resources/publishing/canon/roles/interviewer.md` … `rechecker.md` (9 files) | One rubric per existing role |
| `app/Services/Publishing/VoiceCanon.php` | Reads the canon directory, builds a `CanonSnapshot` |
| `app/Services/Publishing/CanonSnapshot.php` | Readonly value object: `version`, `files`, `roles`, `rubricFor()`, `goals()`, `exemplars()`, `ruleIds()` |
| `app/Console/Commands/PublishingCanonHash.php` | `php artisan publishing:canon-hash` prints the current canon version (used by the drift-trial check) |
| `tests/Feature/Publishing/VoiceCanonTest.php` | Manifest hashing, path hygiene, role coverage, snapshot contents, command output |
| `tests/Feature/Publishing/EditorialInputContractTest.php` | One dataset row per `EditorialActivityKind::cases()`, asserting the outbound body |

### Modified Files

| File Path | Changes |
| --- | --- |
| `app/Actions/Publishing/StartEditorialActivity.php` | Freeze `canon` from `VoiceCanon::snapshot()`; delete `voiceContext`, `persistVoiceContext`, `boundedVoiceSamples` and the `voice_context` key |
| `app/Ai/Agents/EditorialAgent.php` | `instructions()` = role instructions, then `## Business goals` (frozen goals), then `## Your rubric` (frozen rubric), then the common rules with "voice samples" replaced by "canon exemplars in input.canon.exemplars are voice-only and are never evidence"; the finding schema's quotation description drops "voice samples"; throw a clear exception when `input.canon` is absent |
| `app/Ai/Agents/*.php` (9 roles) | `roleInstructions()` shortened to the role's job in one or two sentences; the rubric carries the detail |
| `app/Actions/Publishing/RunEditorialActivity.php` | Delete `reviewVoiceInput()` and its merge into review lens input; an activity whose input lacks `canon` fails with "Created before the voice canon; restart this step." |
| `resources/views/components/admin/publishing/⚡article-workspace.blade.php` | Delete `$voiceSample`, `$selectedVoiceSampleArticleIds`, `voiceActivityInput()`, `selectedVoiceSampleExcerpts()`, `voiceSampleOptions` and their hydration; the start actions no longer pass voice input |
| `resources/views/components/admin/publishing/partials/develop.blade.php` | Remove the voice checklist; the disclosure becomes "Source material" with only the evidence-source checkboxes; add a read-only line "Voice canon {short version}" linking nowhere yet |
| `PRODUCT.md` | Written Voice section: keep the owner-authoritative principles, replace "Voice references" with a pointer to `resources/publishing/canon/` as the agents' voice source and list the four exemplar essays, and remove guidance that contradicts the public register |
| `tests/Manual/PublishingModelTrialTest.php` | Remove `selectedVoiceSampleArticleIds` usage; the trial now freezes the canon automatically (do not run it; it is paid) |
| `tests/Feature/Publishing/EditorialWorkflowTest.php`, `AdminPublishingEditorTest.php`, `PublishingJourneyTest.php` | Replace voice-sample assertions with canon assertions |

### Deleted Files

None. All removed code is inside modified files.

## Implementation Details

### Canon decomposition

**Overview**: Write the canon files from the owner's bat-kol sources. Where a source section is already rule-shaped, copy it with light edits. Otherwise restate it as instructions to the role. No canon file may contain a `~/.config` path or the phrase "voice sample".

**Sources** (read locally; production never reads them):

- `~/.config/bat-kol/style.md`
- `~/.config/bat-kol/registers/public.md`
- `~/.config/bat-kol/channels/blog.md`
- `~/.config/bat-kol/samples/public-reference/new-post-skill/references/framework-guide.md`, `humanizer-rules.md`, `writer-editor-prompt.md`, `frameworks/*.md`
- `~/.config/bat-kol/samples/public-reference/marketingskills-distilled.md`
- `~/.config/bat-kol/samples/public-reference/hormozi-content-principles.md`
- `~/.config/bat-kol/samples/site-copy-raw.md` (homepage and Walkthrough sections only)
- `PRODUCT.md` (repo)

**Mapping**:

| Canon file | Taken from |
| --- | --- |
| `goals.md` | public.md "Audience", "Commercial structure (Hormozi, applied)", "Claims and proof rules", the offer description; blog.md "CTA rules" (summary); PRODUCT.md "Users", "Positioning", "Offer and Working Relationship" (summary only) |
| `voice/core.md` | style.md (all craft sections, "What This Style Is Not"); public.md "Voice rules", "Sentence patterns", "Vocabulary profile", "Tone markers", "Spoken patterns that transfer", "Openers and closers", "What public is not", "Examples"; humanizer-rules.md judgment patterns (content and communication patterns) |
| `voice/mechanical-rules.md` | The mechanical subset of style.md "Anti-Slop", humanizer-rules.md, blog.md "Pre-publish checklist" items 4-5, 6-7 and 10-12, and public.md vocabulary bans, one rule per line with the ids listed below |
| `channels/blog.md` | blog.md "Titles", "Headings", "Openings", "Section structure", "Length by post type", "AEO and SEO rules", "CTA rules", the "Conflicts and how they resolve" table |
| `post-types/*.md` | blog.md "Post-type templates", one file per type, with `FW/` paths rewritten to `frameworks/` |
| `frameworks/guide.md` | framework-guide.md sections 3-4 (scoring, compatibility), with a note that the blog channel's per-type lists supersede its topic tables for owner-audience posts |
| `frameworks/*.md` | The 13 files blog.md allows: `the-false-start`, `the-sparkline`, `the-dialectic`, `the-jeremiad`, `the-montaigne`, `socratic-path`, `the-petal`, `three-act`, `in-medias-res`, `reverse-chronology`, `kafkaesque-labyrinth`, `strangers-report`, `story-circle` |
| `roles/interviewer.md` | public.md "Spoken patterns" (discovery before prescription, reflect back, name the limit); rules to ask only what Nick alone can supply and never invent anecdotes (`[TODO: Nick to supply]`) |
| `roles/researcher.md` | blog.md AEO "Sources" rules; marketingskills-distilled "Statistics, quotes, and sourcing" and the AEO-research-wins conflict rule; fan-out only for sub-questions |
| `roles/planner.md` | Hormozi section 2 (hook/retain/reward per section); blog.md section structure (alternate narrative and analytical sections, limit early, objection as its own H2, target query H2); visual plan with prose equivalents |
| `roles/drafter.md` | new-post SKILL.md "Step 3A" voice gates (opening test, perspective, header, sentence mode, first/last sentence weight, shape and ring, the turn); composite-scene labeling; marker rules; how to use exemplars (imitate craft, never their anecdotes, numbers or vocabulary the register bans) |
| `roles/fact-reviewer.md` | public.md "Claims and proof rules"; blog.md "Work story" approved facts; exclusions; archive-anecdote reuse is a finding; numbers must be sourced and dated or plainly hypothetical |
| `roles/voice-reviewer.md` | writer-editor-prompt.md passes 2-4 (voice alignment, soul check, final audit); marketingskills Seven Sweeps 1-2 and the swap test; "do not report mechanical rules, the linter owns them" |
| `roles/buyer-reviewer.md` | Hormozi value-equation checklist and section-2 publishing test; Seven Sweeps 3-7 with sweep 6 run as "make the before state concrete"; blog.md CTA rules; AEO judgment (answer-first 40-60 words, target phrase placement); expert panel personas (skeptical owner of a 30-person service firm, an editor, an SEO specialist) as advisory lenses |
| `roles/reconciler.md` | Priority order (blocking claims, then voice, then buyer) and the conflict precedence from blog.md "Conflicts and how they resolve" |
| `roles/rechecker.md` | Assess only the listed findings against the revised text; never introduce style findings; new blocking findings only for claims and integrity |

**Mechanical rule ids** (each appears exactly once as `[rule:<id>]` in `voice/mechanical-rules.md`; Phase 3 implements them, Phases 4 and 6 add metadata and kit ids in their own files):
`em-dash`, `curly-quotes`, `ai-vocabulary`, `corporate-vocabulary`, `public-vocabulary`, `filler-phrases`, `negative-parallelism`, `chatbot-artifacts`, `stock-openers`, `sentence-case-headings`, `label-headings`, `heading-order`, `inline-header-list`, `bold-limit`, `exclamation-limit`, `emoji`, `corporate-we`, `excluded-names`, `experience-claim`, `walkthrough-link`, `unresolved-markers`.

**`roles.json`** (Phase 2 content; Phases 4 and 6 add `package`, `draft_kit`, `review_kit`, and Phase 3 adds `lint_repair`):

```json
{
  "interview": {"files": ["roles/interviewer.md"], "exemplars": false},
  "research_challenge": {"files": ["roles/researcher.md"], "exemplars": false},
  "plan": {"files": ["channels/blog.md", "post-types/essay.md", "post-types/playbook.md", "post-types/teardown.md", "post-types/work-story.md", "roles/planner.md"], "exemplars": false},
  "draft": {"files": ["voice/core.md", "voice/mechanical-rules.md", "channels/blog.md", "roles/drafter.md"], "exemplars": true},
  "review_facts": {"files": ["roles/fact-reviewer.md"], "exemplars": false},
  "review_voice": {"files": ["voice/core.md", "roles/voice-reviewer.md"], "exemplars": true},
  "review_buyer": {"files": ["channels/blog.md", "roles/buyer-reviewer.md"], "exemplars": false},
  "reconciliation": {"files": ["roles/reconciler.md"], "exemplars": false},
  "recheck": {"files": ["roles/rechecker.md"], "exemplars": false}
}
```

`goals.md` is always included for every kind and is not listed per role.

**Exemplar manifest**:

```json
{
  "label": "Voice-only exemplars. Imitate sentence craft and rhythm. Never reuse their anecdotes, numbers, audience or banned vocabulary, and never cite them as evidence.",
  "archive": ["just-build-it-twice", "your-ai-wrote-a-bug", "six-months-talking-to-a-machine", "the-tools-that-build-the-tools"],
  "files": ["exemplars/site-copy-2026-10-02.md"]
}
```

Archive exemplar text comes from `resources/writing/{slug}.md`: drop the YAML frontmatter and BFM directive blocks (`@figure`, `@aside`, `@callout`) and keep headings and prose. The source file's sha256 is part of the canon manifest, so an edit to the archive source changes the canon version. Do not modify `resources/writing/**` (see `.ai/rules/writing.md`).

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/VoiceCanonTest.php` (path hygiene, role coverage, rule tags) plus a side-by-side read of each source section and its canon file.
- **Experiment**:
  - After writing each canon file, check that every rule-shaped line in the mapped source sections has a counterpart in the canon file.
  - Grep for `~/.config`, `/Users/`, `FW/` and "voice sample" across the canon directory.
  - Count `[rule:` tags in `voice/mechanical-rules.md` and compare with the 21 ids listed above.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/VoiceCanonTest.php && test "$(grep -o '\[rule:[a-z0-9-]*\]' resources/publishing/canon/voice/mechanical-rules.md | sort -u | wc -l | tr -d ' ')" = 21`

### VoiceCanon service

**Pattern to follow**: `app/Services/Publishing/ArchiveSourceManifest.php` (repo-file hashing with `hash_file('sha256', …)`).

```php
final class VoiceCanon
{
    public function __construct(private Filesystem $files) {}

    public function snapshot(): CanonSnapshot; // memoize per instance; bind as scoped (Octane)
}

final readonly class CanonSnapshot
{
    /**
     * @param  array<string, string>  $files   relative path => content (canon files + referenced archive sources)
     * @param  array<string, string>  $hashes  relative path => sha256
     * @param  array<string, array{files: list<string>, exemplars: bool}>  $roles
     */
    public function __construct(public string $version, public array $files, public array $hashes, public array $roles) {}

    /** @return array{files: array<string, string>, text: string} */
    public function rubricFor(EditorialActivityKind $kind): array;

    public function goals(): string;

    /** @return list<array{id: string, title: string, text: string}> */
    public function exemplars(): array;

    /** @return list<string> sorted unique rule ids found in any canon file */
    public function ruleIds(): array;
}
```

**Key decisions**:

- Bind `VoiceCanon` with `$this->app->scoped()`, never as a singleton (Octane rule). It reads files on first use per request or job.
- The version is `hash('sha256', implode("\n", sorted "path\thash" lines))`. Paths are relative to the repo root.
- `rubricFor()` throws `RuntimeException` when `roles.json` has no entry for the kind, so a new kind without a rubric fails loudly. `EditorialInputContractTest` covers every case.

**Implementation steps**:

1. Write the canon files per the mapping. Copy each framework file and rewrite any `~/.config` or `FW/` path.
2. Implement `VoiceCanon`, `CanonSnapshot` and the `publishing:canon-hash` command (`php artisan make:command PublishingCanonHash --no-interaction`).
3. In `StartEditorialActivity::start()`, replace `'voice_context' => …` with:

```php
'canon' => [
    'version' => $snapshot->version,
    'role' => $kind->value,
    'rubric_files' => $rubric['files'],
    'rubric' => $rubric['text'],
    'goals' => $snapshot->goals(),
    'exemplars' => $snapshot->roles[$kind->value]['exemplars'] ? $snapshot->exemplars() : [],
    'exemplar_label' => /* manifest label */,
],
```

4. Delete the voice methods and keys listed in File Changes.
5. Update `EditorialAgent::instructions()` to render the goals and rubric from `$this->activity->input['canon']`, and shorten each role's `roleInstructions()`.
6. Update the workspace component and develop partial.
7. Reconcile PRODUCT.md.
8. Write the two tests and update the three existing test files and the manual trial.

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/EditorialInputContractTest.php` with `Http::fake` returning a minimal valid structured response per kind (reuse existing fixtures in `EditorialWorkflowTest`).
- **Experiment**: For each kind, start the activity through its real eligibility path (factories put the attempt in the right stage and approvals). Then assert on `Http::recorded()`: the system message contains `## Business goals` and the rubric's first heading; the user message JSON has `input.canon.version` equal to `app(VoiceCanon::class)->snapshot()->version`; Drafter and VoiceReviewer carry four archive exemplars plus site copy and the label; other kinds carry `exemplars: []`.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/EditorialInputContractTest.php`

## Data Model

No schema changes. `editorial_activities.input` (jsonb) gains the `canon` block, and existing rows keep their old `voice_context` until they are completed or stale.

## Testing Requirements

### Feature Tests

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Publishing/VoiceCanonTest.php` | New: manifest and version stability, file-change sensitivity, path hygiene, role coverage, exemplar extraction, command output |
| `tests/Feature/Publishing/EditorialInputContractTest.php` | New: dataset from `EditorialActivityKind::cases()`; the outbound request body per kind |
| `tests/Feature/Publishing/EditorialWorkflowTest.php`, `AdminPublishingEditorTest.php`, `PublishingJourneyTest.php` | Updated: no voice selection; the canon is frozen on the dashboard path too (the regression the scout found) |

**Key test cases**:

- The version is identical across two snapshots and changes when any canon file or referenced archive source changes. Use a temporary copy of the canon directory through a config path override, never by editing the real files.
- No canon file contains `~/.config`, `/Users/`, `FW/` or the phrase "voice sample".
- Every `EditorialActivityKind::cases()` value has a `roles.json` entry.
- Exemplar text has no frontmatter and no `@figure`, `@aside` or `@callout` lines.
- `php artisan publishing:canon-hash` prints exactly the snapshot version and exits 0.
- A dashboard-path `developIdea` interview freezes the canon (the old path froze no voice at all).
- An activity row with no `canon` key fails with the restart message instead of calling the provider (`Http::assertNothingSent()`).

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| A canon file referenced in `roles.json` is missing | `VoiceCanon::snapshot()` throws with the missing path; tests catch it before deploy |
| An archive exemplar slug has no source file | Snapshot throws naming the slug |
| Pre-canon activity resumes after deploy | Fails with "Created before the voice canon; restart this step." and no provider call |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Canon decomposition | A rule silently dropped | Restating a source loses a constraint | Agents never see it | Phase 3's two-way id check covers mechanical rules; owner reads the canon at acceptance (PRODUCT.md judgment criterion) |
| Prompt size | Input grows by roughly 20-30k tokens for Drafter and VoiceReviewer | Four essays plus rubric | Higher spend per run | Accepted (quality over cost); providers cache stable instruction prefixes automatically |
| Freezing | Rubric read live instead of from the frozen input | Implementation shortcut in `instructions()` | Continuations change voice mid-activity | Contract test asserts instructions match `input.canon.rubric` |
| Exemplar contamination | Drafter reuses archive anecdotes or practitioner vocabulary | Exemplars read as content | Off-register drafts | Label in every input; fact-reviewer rubric flags archive anecdotes |
| Octane | Snapshot cached across requests after a deploy | Singleton binding | Stale canon | Scoped binding |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/VoiceCanonTest.php tests/Feature/Publishing/EditorialInputContractTest.php
php artisan test --compact tests/Feature/Publishing
! grep -rniE 'voice[_ ]?samples?' app resources tests/Manual
vendor/bin/pint --dirty --format agent
env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress
composer test
```

## Rollout Considerations

- **Feature flag**: none.
- **Monitoring**: watch the first real runs' token usage in OpenRouter; no app-side budget exists by design.
- **Rollback plan**: revert the commits; activities created under the canon keep their frozen input.
- **Rules in force**: `.ai/rules/ai.md` (native SDK agents, frozen inputs, token limits as runaway guards), `.ai/rules/writing.md` (never modify `resources/writing/**`), `.ai/rules/resources.md`, `.ai/rules/app.md`, `.ai/rules/tests.md`, Octane scoped bindings.

## Open Items

- [ ] `.ai/rules/resources.md` says "Use PRODUCT.md for product truth and written voice". The owner decides whether that rule should point agents at the canon. Do not call `record-rule` unless the owner asks.
