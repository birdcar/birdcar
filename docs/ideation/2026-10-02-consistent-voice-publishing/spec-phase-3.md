# Implementation Spec: Consistent Voice Publishing - Phase 3

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

This phase enforces the owner's mechanical voice rules in code. A `VoiceLinter` runs a set of small `LintRule` classes, one per `[rule:<id>]` tag in the canon. Each rule walks a `LintTarget` (the revision's Tiptap blocks with text, heading level, marks, link hrefs, blockquote and protected flags, plus metadata and piece kind) and returns `LintHit`s that point at a block. Rules are registered through the container with a tag, so the linter's constructor receives them through Laravel's `#[Tag]` contextual attribute and no custom registry is needed.

Lint runs in three places:

1. **After drafting**, inside the existing post-draft chain in `RunEditorialActivity`. Before the three review lenses are queued, the applied draft revision is linted. If there are hits and fewer than two `lint_repair` activities have completed in this review cycle, a new `lint_repair` activity asks a `LintRepairer` agent for block patches. Patches for agent-authored, unprotected blocks are applied as a new revision with origin `agent-repair`, and the loop lints again. Patches for owner-edited or protected blocks are never applied. They are attached to that hit's finding as `proposed_patch` for the owner to accept through the existing `ApplyEditorialProposal` path. When lint is clean or the cap is reached, remaining hits are stored as `EditorialFinding` rows with lens `lint` and severity `blocking`, and the three lenses are queued as today.
2. **When the owner saves a revision** from the workspace, `LintRevision` refreshes lint findings for the new revision. It is deterministic and synchronous, with no LLM.
3. **At release**, `CheckArticleRelease` re-lints the exact revision. Every hit without a matching override blocks release with code `lint.<rule-id>`.

An override is the owner marking a lint finding `false_positive` with a non-empty reason. It matches later revisions by `input_hash = sha256(rule id | block id | excerpt)`, so an override survives unrelated edits but not a change to the offending text.

The Drafter is told every rule it will be held to. The rule statements arrive through `voice/mechanical-rules.md` in its frozen rubric, and its `document` schema description names the rule ids from the registry, per the validator-to-schema learning. A two-way test proves that the canon's tagged ids and the registered rule ids are the same set.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **Deterministic lint findings block release unless each is overridden with a recorded reason; LLM style findings stay advisory**: rejected lint as advisory only (the 2026-09-22 decision exactly) and a hard block with no override. The em dash ban is absolute, but quoted sources can legitimately contain a banned word.
- **Native PHP rules with every mechanical canon rule tagged by id and checked in both directions**: rejected Vale, and a lint dataset that only lists implemented rules.
- **Self-repair rewrites only agent-authored blocks; owner-edited blocks get findings with proposed patches that are never auto-applied**: preserves "agents never silently change the owner's prose".
- **Refine the existing stages: lint with at most two self-repair passes before review**: rejected merging stages or dropping gates.
- **Several reviewers plus deterministic lint ahead of them**: rejected one general reviewer.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/VoiceLintTest.php`

**Playground**: Pest dataset tests, one row per rule, building Tiptap documents with a test helper.

**Why this approach**: Each rule is a pure function of a document, so dataset rows give an instant pass/fail per rule.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `app/Services/Publishing/Lint/LintRule.php` | Interface: `id(): string`, `appliesTo(PieceKind $kind): bool`, `check(LintTarget $target): list<LintHit>` |
| `app/Services/Publishing/Lint/LintTarget.php` | Readonly: `kind`, `metadata`, `blocks` (list of `{id, type, level, text, bold_spans, links, in_blockquote, protected}`), built by `LintTarget::fromRevision()` |
| `app/Services/Publishing/Lint/LintHit.php` | Readonly: `ruleId`, `blockId` (nullable for metadata), `excerpt`, `message`, `inputHash()` |
| `app/Services/Publishing/Lint/VoiceLinter.php` | `lint(LintTarget): list<LintHit>`; `ruleIds(): list<string>`; constructed with `#[Tag('publishing.lint-rules')] iterable $rules` |
| `app/Services/Publishing/Lint/Rules/BannedPhrasesRule.php` | Abstract base for list-based rules (word-boundary, case-insensitive, skips code marks) |
| `app/Services/Publishing/Lint/Rules/*.php` | One class per id: `EmDash`, `CurlyQuotes`, `AiVocabulary`, `CorporateVocabulary`, `PublicVocabulary`, `FillerPhrases`, `NegativeParallelism`, `ChatbotArtifacts`, `StockOpeners`, `SentenceCaseHeadings`, `LabelHeadings`, `HeadingOrder`, `InlineHeaderList`, `BoldLimit`, `ExclamationLimit`, `Emoji`, `CorporateWe`, `ExcludedNames`, `ExperienceClaim`, `WalkthroughLink`, `UnresolvedMarkers` |
| `app/Actions/Publishing/LintRevision.php` | Lint a revision and sync its `lint` findings (create new, mark vanished ones stale) |
| `app/Ai/Agents/LintRepairer.php` | Role for `lint_repair` (model `google/gemini-3.8-flash`, effort `low`, `#[MaxTokens(16000)]`) |
| `resources/publishing/canon/roles/lint-repairer.md` | Rubric: fix only the listed hits, keep meaning and voice, change the smallest span, return block patches |
| `tests/Feature/Publishing/VoiceLintTest.php` | Per-rule datasets, two-way id check, Drafter instruction audit |
| `tests/Feature/Publishing/VoiceLintWorkflowTest.php` | Post-draft lint and repair loop, cap, protected and owner blocks, findings, re-lint on save |

### Modified Files

| File Path | Changes |
| --- | --- |
| `app/Models/Publishing/EditorialActivityKind.php` | Add `LintRepair = 'lint_repair'` |
| `app/Ai/Agents/EditorialAgent.php` | `forActivity` maps `LintRepair` to `LintRepairer`; schema for `lint_repair`: `patches[]` of `{hit_id, block_id, expected_hash, replacement}`; the Draft `document` description appends "It must pass the mechanical rules in your rubric: {ids}" from `VoiceLinter::ruleIds()` |
| `resources/publishing/canon/roles.json` | Add `lint_repair` with `voice/core.md`, `voice/mechanical-rules.md`, `roles/lint-repairer.md` |
| `app/Providers/AppServiceProvider.php` | Tag every rule class with `publishing.lint-rules` |
| `app/Actions/Publishing/RunEditorialActivity.php` | Replace `queueReviewLensesAfterCompletedDraft` with a lint-then-review step; add `applyLintRepair()`; the lint-repair completion re-enters the step |
| `app/Actions/Publishing/StartEditorialActivity.php` | Eligibility for `LintRepair`: in review, plan approved, targets the current revision, fewer than two completed `lint_repair` activities in this review cycle |
| `app/Actions/Publishing/WriteArticle.php` | Accept origin `agent-repair`; include it in the protected-block guard |
| `app/Actions/Publishing/FinishEditorialReview.php` | Lens `lint` findings do not require a decision to finish review (they are enforced at release) |
| `app/Actions/Publishing/CheckArticleRelease.php` | Add lint checks: re-lint the revision; each hit without a matching override (same `input_hash`, disposition `false_positive`, non-empty reason) becomes a blocking `lint.<id>` finding |
| `app/Services/Publishing/EditorialOutput.php` | Validate the `lint_repair` payload (patch shape, `hit_id` must be one of the input hits, block must exist, byte limits) |
| `resources/views/components/admin/publishing/⚡article-workspace.blade.php` | After a human save, call `LintRevision`; overriding a lint finding requires a reason |
| `resources/views/components/admin/publishing/partials/write.blade.php` | Show lint findings grouped as "Voice rules", with "Accept fix" (when a patch exists) and "Override with reason" |

## Implementation Details

### Lint rules

**Pattern to follow**: `app/Actions/Publishing/CheckArticleRelease.php` (`documentText`, `unsafeDocumentLinks` show how the document is walked) and `app/Services/Publishing/ArticleDocument.php` for node types.

**Overview**: Each rule is small, deterministic, and returns hits with a block id and the offending excerpt.

| Id | Detection |
| --- | --- |
| `em-dash` | Any U+2014 in block text |
| `curly-quotes` | Any of U+2018, U+2019, U+201C, U+201D |
| `ai-vocabulary` | Word-boundary match of: additionally, furthermore, moreover, testament, landscape, showcasing, delve, tapestry, realm, pivotal, crucial, innovative, cutting-edge, game-changing, transformative, garner, underscore, interplay, intricate, intricacies, vibrant, enhance, fostering, enduring, align with |
| `corporate-vocabulary` | synergy, circle back, leverage, bandwidth, align, holistic, robust, seamless |
| `public-vocabulary` | agentic, AI-powered, solution, solutions, streamline, unlock, supercharge, 10x, pain points, tech stack, tech debt, SaaS |
| `filler-phrases` | in order to, due to the fact that, it is worth noting, it's worth noting, it should be noted, at this point in time, in the event that, has the ability to |
| `negative-parallelism` | `/\bnot (just|only|merely)\b[^.]{0,80}?\b(it'?s|but( also)?)\b/i` and `/\bit'?s not (just )?[^.,]{1,60}, it'?s\b/i` |
| `chatbot-artifacts` | I hope this helps, let me know if you have, feel free to reach out, great question, you're absolutely right |
| `stock-openers` | The first sentence of the document or of any section starts with "In this post", "In today's", "Let's explore", "Now that we've covered" |
| `sentence-case-headings` | A heading with two or more capitalized non-initial words that are not acronyms or in the proper-noun allowlist (Walkthrough, Craft & Communicate, Birdcar, GitHub, Heroku, Zapier, Twilio, Salesforce, LinkedIn, Bluesky, Google, weekday and month names) |
| `label-headings` | Heading text (trimmed, case-insensitive) is one of: introduction, conclusion, faq, frequently asked questions, pros, cons, verdict, overview, summary, background, final thoughts, wrapping up |
| `heading-order` | A level-1 heading in the body, or a jump of more than one level |
| `inline-header-list` | A paragraph or list item whose leading text is bold and matches `/^[^:]{1,40}:/` |
| `bold-limit` | More than two bold spans in the piece (one hit per extra span) |
| `exclamation-limit` | More than one `!` outside code marks |
| `emoji` | `\p{Extended_Pictographic}` |
| `corporate-we` | `\b(we|we're|we've|we'll|us|our|ours)\b` outside blockquotes and outside straight-quoted spans |
| `excluded-names` | WorkOS, GHX, DataDash (case-insensitive) |
| `experience-claim` | `more than fifteen years`, `more than 15 years`, `15+ years` |
| `walkthrough-link` | Blog posts only: exactly one link whose path is `/walkthrough` (relative or on birdcar.dev, with or without www), located after the final H2 (or in the last three blocks when there is no H2) |
| `unresolved-markers` | `[NEED:` or `[TODO:` anywhere |

**Key decisions**:

- Text inside `code` marks and `codeBlock` nodes is skipped by every rule.
- `appliesTo()` returns true for `PieceKind::BlogPost` for all body rules here. Phase 6 adds kit rules and sets applicability per kind.
- Hits are ordered by document position so the repair prompt and the UI read top to bottom.

### Post-draft lint and repair loop

**Overview**: Replace the direct review-lens queue after a completed draft with a lint step that may repair up to twice.

```php
private function lintThenQueueReviews(EditorialActivity $completed, User $actor): void
{
    // revision = applied draft or applied repair revision (the current working revision)
    // hits = VoiceLinter::lint(LintTarget::fromRevision(revision, article->kind))
    // if hits && completedRepairs(attempt, review_cycle) < 2:
    //     StartEditorialActivity::start(actor, attempt, LintRepair, ['hits' => [...], 'expected_revision_id' => revision->id], 'lint-repair-'.cycle.'-'.revision->id)
    //     return
    // LintRevision::sync(revision, hits, proposedPatches: carried from the last repair's unapplied patches)
    // queue the three lenses exactly as today (batch key review-{cycle}-{revision})
}
```

**Key decisions**:

- Agent-authored block means the block's subtree hash equals its hash in the most recent `agent-initial` or `agent-repair` revision of this attempt, and the block is not `attrs.protected`. Anything else is owner-edited.
- A repair revision is saved through `WriteArticle::save(..., origin: 'agent-repair')`, so the existing protected-block guard holds.
- `lint_repair` input carries each hit as `{hit_id, rule_id, block_id, excerpt, message}` plus the blocks it touches, so the repairer judges exactly what it is asked to fix (frozen-input learning).

**Implementation steps**:

1. Add the enum case, role class, rubric, `roles.json` entry, schema and output validation.
2. Implement rules, `LintTarget`, `LintHit`, `VoiceLinter`, and tag registration.
3. Implement `LintRevision` (sync findings: create a `lint` finding per hit with `kind = 'lint:<id>'`, `block_id`, `statement = message`, `input_hash`, and `proposed_patch` when available; set `stale_at` on findings of earlier revisions).
4. Wire the post-draft step and `applyLintRepair()` in `RunEditorialActivity`.
5. Wire `CheckArticleRelease`, `FinishEditorialReview` and the workspace save path.
6. Build the UI affordances in `write.blade.php`.

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/VoiceLintWorkflowTest.php` with `Http::fake` sequences for Draft then LintRepair responses.
- **Experiment**: (a) a draft with two em dashes that the first repair fixes, so lenses queue after one repair; (b) a draft whose repairs never fix a hit, so it stops after exactly two repairs and stores a blocking finding; (c) a protected block containing "leverage", so the patch is attached and never applied; (d) an owner save introducing a curly quote, so `LintRevision` creates one finding; (e) a release with one overridden hit and one unresolved hit, so only the unresolved one blocks.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/VoiceLintWorkflowTest.php`

## Data Model

No schema changes. Lint findings reuse `editorial_findings` (`lens = 'lint'`, `kind = 'lint:<id>'`, `input_hash` = hit hash, `disposition = 'false_positive'` plus `disposition_reason` as the override).

## Testing Requirements

### Feature Tests

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Publishing/VoiceLintTest.php` | Dataset: one violation row and one clean row per rule id; `CanonSnapshot::ruleIds()` equals `VoiceLinter::ruleIds()` in both directions; the Draft activity's outbound system message contains every rule id's statement and the `document` schema description lists every id |
| `tests/Feature/Publishing/VoiceLintWorkflowTest.php` | Repair cap, agent vs owner vs protected blocks, finding sync, re-lint on save, review finish unaffected by lint findings |
| `tests/Feature/Publishing/ReleaseReadinessTest.php` | Updated: lint hit blocks; override with reason clears; override without reason rejected; changed text re-blocks |
| `tests/Feature/Publishing/EditorialInputContractTest.php` | Automatically covers `lint_repair` (dataset from `cases()`); add the assertion that its input holds the hits and the touched blocks |

**Key test cases**:

- Clean fixtures are realistic owner prose (take a paragraph from the register examples in `voice/core.md`), not empty documents, so false positives surface.
- `corporate-we` ignores "we" inside a blockquote and inside straight double quotes.
- `walkthrough-link` fails with zero links, two links, and one link in the opening section.
- An override recorded on revision N still clears the same hit on revision N+1 when the block text is unchanged, and stops clearing it when the excerpt changes.

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Repair returns a patch for a block not in the hit list | `EditorialOutput` rejects the payload; the activity fails with the validation reason and the lenses still queue with lint findings stored |
| Repair patch hash mismatch (block changed) | Skip that patch and keep the hit as a finding |
| Repair provider failure | Same pause/fail handling as other kinds; owner can retry; lenses are not queued until lint resolves or the owner retries/finishes |
| Override without reason | Livewire validation error; no write |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Vocabulary rules | False positives on legitimate words ("landscape" in a gardening example) | Word lists are context-free | Owner overrides often | Override with reason persists by hash; review the lists at acceptance |
| `sentence-case-headings` | Flags proper nouns | Names missing from the allowlist | Noise | Allowlist constant; override |
| Repair loop | Repair degrades prose to satisfy a rule | Agent over-edits | Voice drift | Rubric says smallest span; VoiceReviewer runs after repair; cap of two |
| Post-draft chain | Lenses never queue | Repair activity stuck in pending/paused | Review stalls | Existing recovery command picks up stuck activities; the workspace shows the lint-repair activity and a "Skip repair and review" action that stores findings and queues lenses |
| Release check | Lint drift between UI and release | UI findings stale versus revision | Owner surprised at release | Release re-lints the exact revision; UI refreshes on every save |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/VoiceLintTest.php tests/Feature/Publishing/VoiceLintWorkflowTest.php tests/Feature/Publishing/ReleaseReadinessTest.php
php artisan test --compact tests/Feature/Publishing
vendor/bin/pint --dirty --format agent
env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress
composer test
```

## Rollout Considerations

- **Feature flag**: none.
- **Rollback plan**: revert; lint findings are ordinary finding rows and are ignored by older code paths.
- **Rules in force**: `.ai/rules/ai.md`, `.ai/rules/app.md`, `.ai/rules/tests.md` (PHPStan level 7; prefix file-local helpers such as `voiceLintDocument()`).

## Open Items

- [ ] None.
