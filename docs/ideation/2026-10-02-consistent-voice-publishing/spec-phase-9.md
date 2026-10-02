# Implementation Spec: Consistent Voice Publishing - Phase 9

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

This phase builds two measurement tools. Neither runs anything paid automatically.

**Golden-set drift trial.** The golden set is committed fixtures: 10 real passages drawn from the fixed exemplar canon and the site-copy snapshot, and 10 slopped variants. Each slopped variant rewrites one real passage generically and records its injected tells by block. Every slopped variant carries at least one non-mechanical tell (flattened rhythm, no turn, synonym cycling, generic conclusion, neutral reporting without a point of view), so the trial measures the VoiceReviewer and not only the linter. Real passages are chosen to be clean under the public-register lint, which a non-paid fixture test enforces.

The paid trial, `tests/Manual/VoiceDriftTrialTest.php`, follows the existing `PublishingModelTrialTest` pattern: outside the suites, skipped unless `DRIFT_TRIAL=1`, refusing to run unless isolated. It runs against the local Postgres test database so exemplar retrieval works. For each passage it creates a blog piece in review with a minimal brief and plan, writes the passage as the revision, runs the linter, and runs one real `review_voice` activity through `RunEditorialActivity`.

It records every attempt, including failures and with no retries, to `drift-validation.json` along with the current canon hash. A slopped passage is caught when a lint hit or a VoiceReviewer finding targets an injected block. A real passage is flagged when the VoiceReviewer returns a finding of kind `voice_drift` or lint hits it. The contract's `jq` check recomputes the totals from the attempts and compares the canon hash, so the evidence goes stale automatically when the canon changes.

**Edit distance.** Each time the owner saves a revision whose parent is agent-authored (`agent-initial`, `agent-repair` or `agent-accepted`), a queued job computes the word-level edit distance between the two documents' text. It stores the distance and the baseline revision id on the new revision. A small command prints, for an idea, each piece's kind and its recorded distances as JSON, which the owner pastes into `acceptance.json` at the gate.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **Measure drift with 10 real and 10 slopped passages, passing at 9 of 10 caught and at most 2 of 10 real flagged, recomputed from recorded attempts and pinned to the current canon hash**: rejected a pairwise "which is Nick" judge, owner acceptance alone, and trusting the harness's own summary fields.
- **The drift trial runs against local Postgres** (whole suite on Postgres): rejected an in-memory trial database, which cannot run retrieval.
- **Learning from the owner's edits is out of scope; edit distance is measured only**: no clustering, no automatic canon updates.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/EditDistanceTest.php tests/Feature/Publishing/DriftFixtureTest.php`

**Playground**: Pest feature tests (no paid calls). The paid trial is exercised only by the owner.

**Why this approach**: Fixture validity and edit-distance math are deterministic and fast. The paid trial's code path is the same `RunEditorialActivity` the suite already covers with fakes.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `tests/Fixtures/Publishing/drift/passages.json` | 10 real passages `{id, source, blocks[]}` and 10 slopped `{id, real_id, blocks[], injected: [{block_index, tell, mechanical: bool}]}` |
| `tests/Feature/Publishing/DriftFixtureTest.php` | Fixture integrity: counts, pairing, injected blocks exist, at least one non-mechanical tell per slopped passage, real passages lint-clean, sources are canon exemplars or site copy |
| `tests/Manual/VoiceDriftTrialTest.php` | Paid trial; writes `docs/ideation/2026-10-02-consistent-voice-publishing/drift-validation.json` |
| `app/Services/Publishing/EditDistance.php` | Word-level Levenshtein over tokenized document text (two-row dynamic programming) |
| `app/Jobs/RecordEditDistance.php` | Queued after a human save whose parent revision is agent-authored |
| `app/Console/Commands/PublishingEditDistances.php` | `php artisan publishing:edit-distances {idea}` prints `{idea_id, summary_title, pieces: [{article_id, kind, edit_distance}]}` |
| `database/migrations/2026_10_02_500000_add_edit_distance_to_article_revisions_table.php` | `agent_edit_distance` integer nullable, `agent_baseline_revision_id` FK nullable |
| `tests/Feature/Publishing/EditDistanceTest.php` | Distance math, job dispatch rules, command output |

### Modified Files

| File Path | Changes |
| --- | --- |
| `app/Actions/Publishing/WriteArticle.php` | After a `human` save whose parent revision has an agent origin, dispatch `RecordEditDistance` after commit |
| `app/Models/ArticleRevision.php` | Allow the two new columns. The immutability guard permits setting them once from null, and only from the job |
| `resources/publishing/canon/roles/voice-reviewer.md` | Finding kinds stated explicitly: `voice_drift`, `clarity`, `framework`, `register` |
| `app/Ai/Agents/EditorialAgent.php` | VoiceReviewer finding `kind` description lists those values |

## Implementation Details

### Golden set

**Overview**: Author the fixtures once, carefully. They are the yardstick, so their quality decides whether the trial means anything.

**Implementation steps**:

1. Pick 10 passages of 1-3 paragraphs: seven from the four exemplar essays, three from the site-copy snapshot. Choose passages that pass the public-register lint as-is. Skip passages with "10x", "kinda", profanity or practitioner-only jargon.
2. For each real passage, write a slopped variant that keeps the meaning. Rewrite it in generic LLM prose and inject 2-3 tells, at least one non-mechanical. Record each injected block index and tell name.
3. `DriftFixtureTest` asserts the structure and runs `VoiceLinter` over the real passages, expecting zero hits.

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/DriftFixtureTest.php`
- **Experiment**:
  - Add a real passage containing "10x": the lint-clean assertion fails.
  - Give a slopped passage only mechanical tells: the non-mechanical assertion fails.
  - Point an injected block index past the passage's blocks: the existence assertion fails.
  - Restore all three.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/DriftFixtureTest.php`

### Paid trial

**Pattern to follow**: `tests/Manual/PublishingModelTrialTest.php` (`liveTrialRefuseUnlessIsolated`, `liveTrialPrepareApplication`, recording every attempt, never retrying). Prefix helpers with `driftTrial` to avoid redeclare fatals (`.ai/rules/tests.md`).

```php
test('golden-set drift trial against OpenRouter', function (): void {
    if (getenv('DRIFT_TRIAL') !== '1') {
        $this->markTestSkipped('Paid trial: set DRIFT_TRIAL=1 after the owner confirms the OpenRouter workspace is ready.');
    }
    // refuse unless isolated; prepare app; import archive fixtures; run publishing:index-exemplars logic;
    // for each of 20 passages: build piece, write revision, lint, start + run review_voice; record attempt
    // write {canon_hash, run_id, started_at, attempts: [{passage_id, variant, caught_at_injected_span, drift_flagged, lint_hits, findings, model, status, error}]}
});
```

**Key decisions**:

- `caught_at_injected_span` is true when any lint hit's block id, or any VoiceReviewer finding's `block_id`, equals an injected block's id.
- `drift_flagged` is true for a real passage when any VoiceReviewer finding has `kind = voice_drift`, or any lint hit exists.
- A failed provider call records `status: failed` with the error, so the passage counts as not caught (slopped) or not flagged (real). The `jq` check needs all 20 distinct passage ids, so a run with failures must be rerun deliberately by the owner.

### Edit distance

```php
final class EditDistance
{
    /** Word-level Levenshtein over lowercase word tokens of the documents' text. */
    public function between(array $baselineDocument, array $revisedDocument): int;
}
```

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/EditDistanceTest.php`
- **Experiment**: identical documents give 0; one word changed gives 1; a paragraph inserted gives that paragraph's word count; a 4,000-word document completes inside the job without exceeding memory (two rows only). A human save after a human save dispatches nothing. A human save after `agent-initial` dispatches once. The command prints valid JSON for an idea with a flagship and three kit pieces.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/EditDistanceTest.php`

## Data Model

```sql
ALTER TABLE article_revisions
  ADD COLUMN agent_edit_distance INTEGER NULL,
  ADD COLUMN agent_baseline_revision_id BIGINT NULL REFERENCES article_revisions(id) ON DELETE SET NULL;
```

## Testing Requirements

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Publishing/DriftFixtureTest.php` | Fixture integrity and lint-clean real passages |
| `tests/Feature/Publishing/EditDistanceTest.php` | Math, dispatch rules, immutability exception for the two columns, command output |
| `tests/Manual/VoiceDriftTrialTest.php` | Skips without `DRIFT_TRIAL=1`; the suite never runs it |

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Trial run without isolation or key | Refuse with the reason before any request |
| Provider failure mid-trial | Record the attempt as failed and continue to the next passage; no retries |
| Edit-distance job runs after a later revision exists | It writes only to the revision it was dispatched for; idempotent when the column is already set |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Golden set | Slopped variants only carry mechanical tells | Lazy fixture authoring | Trial measures only lint | Fixture test requires one non-mechanical tell per slopped passage |
| Golden set | Real passages fail public-register lint | Archive vocabulary | Real flagged count inflated | Fixture test runs lint over real passages |
| Trial | Evidence stale after a canon edit | Canon changed after the run | False confidence | The `jq` check compares `canon_hash` with `publishing:canon-hash` |
| Edit distance | Huge documents slow the job | Long pillar posts | Delayed metric | Two-row DP; it is a queued job, not inline |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/EditDistanceTest.php tests/Feature/Publishing/DriftFixtureTest.php
vendor/bin/pest tests/Manual/VoiceDriftTrialTest.php --compact   # skips without DRIFT_TRIAL=1
php artisan test --compact tests/Feature/Publishing
vendor/bin/pint --dirty --format agent
env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress
composer test
```

## Rollout Considerations

- **Owner tasks (acceptance gate)**: `DRIFT_TRIAL=1 vendor/bin/pest tests/Manual/VoiceDriftTrialTest.php --compact` after confirming the OpenRouter workspace (20 VoiceReviewer calls plus about 30 embedding inputs). Then run `php artisan publishing:edit-distances {idea}` for each of the three acceptance ideas.
- **Rollback plan**: revert; the columns are nullable.
- **Rules in force**: `.ai/rules/tests.md` (manual tests bind to `Tests\TestCase`, helper prefixes, PHPStan level 7), `.ai/rules/ai.md`.

## Open Items

- [ ] None.
