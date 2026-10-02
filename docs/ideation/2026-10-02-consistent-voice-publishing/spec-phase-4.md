# Implementation Spec: Consistent Voice Publishing - Phase 4

**Contract**: ./contract.md
**Estimated Effort**: XL

## Technical Approach

This phase gives each post a structure chosen on purpose, sharpens the three review lenses, and moves the real title to a packaging step.

**Post type and framework.** The Interviewer's brief gains `postType` (`essay`, `playbook`, `teardown`, `work_story`), `targetQuery` (the one search query the post answers) and `ideaTitle` (a better summary title for the idea, applied only when the owner has not renamed it). A machine-readable `frameworks/catalog.json` in the canon lists each post type's word range and allowed frameworks, plus each framework's complexity and minimum length. The Planner proposes a primary framework, a runner-up, one-sentence reasons for each, and an optional secondary framework for a single named section. `FrameworkCatalog` validates the proposal in PHP: the framework must be allowed for the brief's post type, the length range must not exclude it, and the secondary must not appear on the guide's avoid list. At the plan gate the owner keeps the primary, picks the runner-up, or picks another allowed framework. A different choice re-runs planning with that framework fixed before the plan can be approved. Draft and VoiceReviewer inputs then carry the chosen framework file and the post-type template in full.

**Sharper lenses.** The three lens rubric files from Phase 2 gain explicit section markers. The voice rubric adds framework adherence (phases present in order, sentence modes, the turn at section boundaries). `ReviewRubricTest` proves the three rubrics are distinct files with distinct hashes and the expected sections.

**Packaging.** A new `package` activity runs a `Packager` role after review finishes (after the recheck when one was queued). It proposes ten title options and picks an H1 title, a 50-60 character SEO title, a 150-160 character meta description, a 4-6 word slug and a greatest-hits tag. The proposal is stored on the activity, as the Drafter's metadata proposal already is. The owner accepts it, optionally editing any field or choosing another of the ten titles, through a new `AcceptPackageProposal` action. That action saves a metadata-only revision (origin `agent-accepted`). The Drafter stops proposing metadata. Metadata rules are implemented once as lint rules (`seo-title-length`, `meta-description-length`, `slug-shape`, `slug-archive-reuse`, `title-sentence-case`). Packager output validation and `CheckArticleRelease` both call them, and their statements are tagged in a new canon file so Phase 3's two-way check keeps holding.

**Claim manifest.** When a release is prepared, its payload gains `claim_manifest`: the grounded, non-contradicted claims from the attempt's completed research, with their source ids. Phase 6's kit checks read it.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **The Planner selects a primary framework and a runner-up from the blog channel's allowed list; the owner chooses at the plan gate; PHP validates the allowed list and length exclusions**: rejected leaving structure implicit, or scoring with framework-guide's topic tables as written (tuned for the old practitioner audience).
- **Packaging runs as a Packager activity whose metadata goes through proposal acceptance, with metadata rules implemented once as lint rules enforced by CheckArticleRelease**: rejected a separate accepted-package gate, a new `EditorialStage` value, and validating length rules twice.
- **Several reviewers: keep the three lenses with sharper, distinct rubrics plus deterministic lint**: rejected one general reviewer or a single critic.
- **Release approval freezes the flagship's approved claim manifest; kit pieces may not add claims outside it**: the hidden-dependency critic found no claim-set artifact existed.
- **Refine existing stages (Ideas column, lint, packaging step, kit spawn); keep every current stage and gate**: rejected merging research into planning or dropping the angle gate.
- **Deterministic lint findings block release unless overridden with a reason**: applies to the new metadata rules too.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/NarrativeFrameworkTest.php tests/Feature/Publishing/PackagingTest.php`

**Playground**: Pest feature tests with `Http::fake` OpenRouter responses per kind.

**Why this approach**: Framework validation, plan-gate switching and packaging acceptance are workflow state transitions best exercised end to end with faked agents.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `resources/publishing/canon/frameworks/catalog.json` | Post types (word range, allowed frameworks) and framework metadata (name, complexity, minimum words, avoid-combinations) |
| `resources/publishing/canon/voice/metadata-rules.md` | Metadata rule statements tagged `[rule:seo-title-length]`, `[rule:meta-description-length]`, `[rule:slug-shape]`, `[rule:slug-archive-reuse]`, `[rule:title-sentence-case]` |
| `resources/publishing/canon/roles/packager.md` | Rubric from the blog channel "Titles" section: ten options first, one voice-y H1, one search title phrased the way an owner searches, meta as a reason to click, slug rules, greatest-hits tag in the owner's words |
| `app/Services/Publishing/FrameworkCatalog.php` | Reads `catalog.json` from the frozen canon; `allowedFor(postType, words)`, `validate(array $framework, string $postType): list<string>` |
| `app/Ai/Agents/Packager.php` | Role for `package` (model `google/gemini-3.8-flash`, effort `medium`, `#[MaxTokens(16000)]`) |
| `app/Actions/Publishing/AcceptPackageProposal.php` | Owner accepts or edits the package; saves a metadata-only revision |
| `app/Services/Publishing/Lint/Rules/SeoTitleLength.php`, `MetaDescriptionLength.php`, `SlugShape.php`, `SlugArchiveReuse.php`, `TitleSentenceCase.php` | Metadata lint rules (`blockId = null`) |
| `tests/Feature/Publishing/NarrativeFrameworkTest.php` | Brief fields, framework validation, plan-gate switching, frozen framework text |
| `tests/Feature/Publishing/ReviewRubricTest.php` | Distinct lens rubrics with section markers; reconciliation and recheck inputs carry findings and reviewed text |
| `tests/Feature/Publishing/PackagingTest.php` | Package trigger, schema limits, acceptance, metadata lint at release, review lineage kept |

### Modified Files

| File Path | Changes |
| --- | --- |
| `app/Models/Publishing/EditorialActivityKind.php` | Add `Package = 'package'` |
| `app/Ai/Agents/EditorialAgent.php` | Interview schema adds `postType` (enum), `targetQuery`, `ideaTitle` (max 80); Plan schema adds `framework {primary, runner_up, reasons {primary, runner_up}, secondary {framework, section_heading} nullable}` with descriptions that name the allowed slugs for the brief's post type; Draft schema drops `metadataProposals`; Package schema (`titleOptions` exactly 10, `title`, `seoTitle`, `description`, `slug`, `greatestHitsTag`) with the metadata rule statements in each field's description; `forActivity` maps `Package` to `Packager` |
| `resources/publishing/canon/roles.json` | `package`: `voice/core.md`, `voice/metadata-rules.md`, `channels/blog.md`, `roles/packager.md` |
| `resources/publishing/canon/roles/voice-reviewer.md` | Add framework adherence section and lens markers |
| `resources/publishing/canon/roles/fact-reviewer.md`, `buyer-reviewer.md` | Add lens markers (`<!-- lens-section: claims -->`, `exclusions`; `buyer`, `commercial-structure`, `discovery`; voice: `voice`, `craft`, `framework-adherence`) |
| `resources/publishing/canon/roles/interviewer.md`, `planner.md` | Post-type determination, target query and framework selection instructions |
| `app/Actions/Publishing/StartEditorialActivity.php` | Freeze `canon.post_type {slug, text}` and `canon.framework {slug, text, secondary {slug, text, section_heading}}` for Draft and ReviewVoice; freeze `framework_choice` into Plan input when re-planning; `Package` eligibility (in review, review finished, recheck complete or not used, current revision) |
| `app/Actions/Publishing/RunEditorialActivity.php` | `applyInterview` stores `brief.post_type`, `brief.target_query`, and applies `ideaTitle` to the ContentIdea when `summary_title_source !== 'owner'` (source becomes `interviewer`); `applyPlan` stores `plan.framework`; `applyDraft` no longer stores metadata proposals; new `applyPackage` stores the proposal; queue `package` after a completed recheck |
| `app/Actions/Publishing/FinishEditorialReview.php` | When no recheck is queued, start `package` |
| `app/Actions/Publishing/ApprovePublishingStage.php` | Plan approval requires `plan.framework.chosen`; the plan input hash includes the chosen framework |
| `app/Actions/Publishing/AdvancePublishingAttempt.php` | `chooseFramework(User, PublishingAttempt, string $slug)`: if equal to the plan's primary, records `chosen`; otherwise starts a re-plan with `framework_choice` |
| `app/Services/Publishing/EditorialOutput.php` | Validate interview `postType` and `ideaTitle`; validate plan `framework` through `FrameworkCatalog`; validate the package payload with the metadata lint rules and exact counts (fail with the measured length in the reason) |
| `app/Services/Publishing/ReleaseFreshnessManifest.php` | `reviewLineage()` treats a revision whose document content equals the reviewed revision's document (metadata-only change) as inside the reviewed lineage |
| `app/Actions/Publishing/CheckArticleRelease.php` | Run metadata lint rules on the revision metadata (blocking `lint.<id>`, overridable); release requires `metadata.seo_title` |
| `app/Actions/Publishing/ManageArticleRelease.php` | Add `claim_manifest` to the release payload; release dialog slug defaults to `metadata.slug` |
| `resources/views/components/admin/publishing/⚡article-workspace.blade.php` | Post type shown and editable before angle approval; framework choice at the plan gate (radio: primary, runner-up, other allowed); package review panel (ten options, editable fields with live character counts) |
| `resources/views/components/admin/publishing/partials/develop.blade.php`, `write.blade.php`, `details.blade.php` | Render the new brief fields, framework choice and package panel |
| `tests/Feature/Publishing/ContentIdeaTest.php` | Packaging sets the piece title and slug; the idea summary never changes because of packaging; `ideaTitle` applies only when not owner-renamed |

## Implementation Details

### Framework catalog and selection

**Pattern to follow**: `VoiceCanon` from Phase 2. The catalog is read from the frozen canon files so a re-plan validates against the same canon version.

```json
{
  "post_types": {
    "essay": {"words": [1500, 2500], "frameworks": ["the-false-start", "the-sparkline", "the-dialectic", "the-jeremiad", "the-montaigne"]},
    "playbook": {"words": [1200, 2000], "frameworks": ["socratic-path", "the-petal", "three-act"]},
    "teardown": {"words": [1200, 2000], "frameworks": ["in-medias-res", "reverse-chronology", "kafkaesque-labyrinth", "strangers-report"]},
    "work_story": {"words": [800, 1500], "frameworks": ["story-circle", "three-act"]}
  },
  "frameworks": {
    "the-jeremiad": {"name": "The Jeremiad", "complexity": "high", "min_words": 1500},
    "...": {}
  },
  "avoid_combinations": [["the-dialectic", "the-false-start"], ["the-montaigne", "the-sparkline"], ["kafkaesque-labyrinth", "socratic-path"], ["reverse-chronology", "kishotenketsu"]]
}
```

Fill `frameworks` for all 13 from `framework-guide.md` complexity ratings. A framework is length-excluded when its `min_words` exceeds the post type's upper bound, or when it is `high` complexity and the upper bound is 1,500 or less.

**Implementation steps**:

1. Add the catalog and `FrameworkCatalog`, and validate in `EditorialOutput` for plan payloads (failure reason names the allowed slugs).
2. Extend the Interview and Plan schemas with descriptions that state the allowed values (validator-to-schema learning).
3. Add `chooseFramework` and the workspace control. A re-plan uses batch key `plan-{cycle}-{slug}` so idempotency does not return the old plan.
4. Freeze the post-type template and framework text into Draft and ReviewVoice inputs.

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/NarrativeFrameworkTest.php`
- **Experiment**: Plan proposals with (a) an allowed primary; (b) a framework allowed for another post type; (c) `the-jeremiad` for a `work_story`; (d) a secondary on the avoid list; (e) the owner picking the runner-up, which starts a re-plan whose input carries `framework_choice`.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/NarrativeFrameworkTest.php`

### Lens rubrics

**Overview**: Add markers and the framework adherence section, and prove distinctness.

**Implementation steps**:

1. Add markers to the three lens files and the framework adherence section to `roles/voice-reviewer.md`.
2. `ReviewRubricTest`: start the three lenses on a drafted revision and assert from `Http::recorded()`:
   - each request's frozen `canon.rubric_files` includes its own role file;
   - the three role files' hashes are pairwise different;
   - each carries its markers;
   - the VoiceReviewer input carries `canon.framework`.
   Also assert that the reconciliation input contains every lens finding id and the reviewed revision document, and that the recheck input contains `review_findings` and the revised document (frozen-input learning).

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/ReviewRubricTest.php`
- **Experiment**:
  - Temporarily copy `roles/voice-reviewer.md` content into `roles/buyer-reviewer.md` in a temp canon directory: the distinctness assertion fails.
  - Remove one marker: the marker assertion names it.
  - Restore both, then run with a reconciliation fake that ignores its input, and assert from the recorded request that the finding ids and the document were sent.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/ReviewRubricTest.php`

### Packager and acceptance

**Overview**: Package after review, then the owner accepts or edits.

```php
final class AcceptPackageProposal
{
    /**
     * @param  array{title: string, seo_title: string, description: string, slug: string, greatest_hits_tag: string}  $metadata
     */
    public function accept(User $actor, EditorialActivity $package, array $metadata, int $expectedRevisionId): ArticleRevision;
    // requires publishing.approve; merges into the current revision metadata (keeps date); saves origin agent-accepted
}
```

**Key decisions**:

- The owner may submit edited values. They are linted at release like everything else, and the panel shows live character counts plus lint hits before submit.
- The greatest-hits tag is packaging metadata (`metadata.greatest_hits_tag`) and is never rendered publicly.
- `slug-archive-reuse` compares against every other Article's slug, including archive imports, and `CheckArticleRelease` keeps its existing `slug.unique` check.

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/PackagingTest.php`
- **Experiment**: (a) finish review with no recheck, so `package` starts; (b) finish with recheck, so `package` starts only after the recheck completes; (c) a package payload with a 48-character SEO title, so the activity fails with "SEO title is 48 characters; it must be 50-60"; (d) accept with edits, so a metadata-only revision is saved and the release check sees no `review.prerequisite.*` finding; (e) accept a slug equal to an archive slug, so release is blocked with `lint.slug-archive-reuse`.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/PackagingTest.php`

### Claim manifest

**Implementation steps**:

1. In `ManageArticleRelease` payload construction, add `claim_manifest`: list of `{statement, supporting_source_ids}` from the latest completed `research_challenge` of the attempt whose claims have at least one quotation and no contradicting quotation and are not `unresolved`.
2. Include it in the release hash input so a different manifest is a different release.
3. Test in `PackagingTest` (or `ReleaseIntegrityTest`): manifest excludes unresolved and contradicted claims.

## Data Model

No schema changes. New keys: `publishing_attempts.brief.post_type`, `brief.target_query`; `plan.framework` (with `chosen`); revision `metadata.seo_title`, `metadata.slug`, `metadata.greatest_hits_tag`; release `payload.claim_manifest`.

## Testing Requirements

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Publishing/NarrativeFrameworkTest.php` | New, per the experiments above |
| `tests/Feature/Publishing/ReviewRubricTest.php` | New, per the steps above |
| `tests/Feature/Publishing/PackagingTest.php` | New, per the experiments above, plus the claim manifest |
| `tests/Feature/Publishing/ContentIdeaTest.php` | Extended: packaging sets the title and slug; idea title rules |
| `tests/Feature/Publishing/VoiceLintTest.php` | Extended automatically: the new metadata rule ids are tagged and registered (two-way check) |
| `tests/Feature/Publishing/EditorialInputContractTest.php` | Extended automatically for `package`; assert the package input carries the final document and brief |

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Planner proposes a disallowed framework | Output validation fails the activity with the allowed list; the owner retries (one paid call) |
| Owner picks a framework while a re-plan is running | Control disabled while a plan activity is pending or running |
| Package violates a length rule | Activity fails with the measured length; retry |
| Owner edits the manuscript after accepting a package | Release approval invalidates as today; metadata stays; lint re-checks at release |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Review lineage | Metadata-only revision invalidates review | Lineage compares revision ids or full content hashes | Release blocked after packaging | `reviewLineage` compares document content; explicit test |
| Plan gate | Approval hash ignores the chosen framework | Hash input unchanged | Owner approves one framework, draft uses another | Chosen framework in the plan input hash; test |
| Interviewer title | Overwrites an owner rename | Missing source check | Owner's idea name lost | Apply only when `summary_title_source !== 'owner'`; test |
| Catalog | Post type missing from catalog | Interviewer returns a new value | Validation crash | Enum in schema plus output validation |
| Packager | Ten near-identical titles | Model laziness | Weak choice set | Rubric requires distinct shapes (claim, provocation, question, search phrasing); advisory, owner picks |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/NarrativeFrameworkTest.php tests/Feature/Publishing/ReviewRubricTest.php tests/Feature/Publishing/PackagingTest.php tests/Feature/Publishing/ContentIdeaTest.php
php artisan test --compact tests/Feature/Publishing
vendor/bin/pint --dirty --format agent
env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress
composer test
```

## Rollout Considerations

- **Feature flag**: none.
- **Rollback plan**: revert; existing attempts without `post_type` or `framework` get the defaults below.
- **In-flight attempts**: attempts created before this phase have no `post_type`. The workspace asks the owner to pick one before angle approval, and plan approval requires a chosen framework. No backfill is needed beyond that UI prompt.
- **Rules in force**: `.ai/rules/ai.md`, `.ai/rules/app.md`, `.ai/rules/tests.md`.

## Open Items

- [ ] None.
