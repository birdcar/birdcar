# Implementation Spec: Consistent Voice Publishing - Phase 6

**Contract**: ./contract.md
**Estimated Effort**: L

## Technical Approach

This phase turns one approved flagship post into its distribution kit, and lets the owner start sibling posts from the same idea.

**Kit pieces.** When the owner approves a flagship blog post's release, `SpawnDistributionKit` creates three child pieces through `WriteArticle::addPiece()`: `linkedin_post`, `bluesky_thread` and `email_blurb`. Each has `parent_article_id` set to the flagship and the same `content_idea_id`. Each piece gets its own `PublishingAttempt` that starts at `in_review`. A kit piece has no angle or plan of its own: its source of truth is the flagship's approved release.

Each kit attempt runs:

1. a `draft_kit` activity (`KitDrafter`);
2. the Phase 3 lint step, which applies the existing self-repair loop with its two-pass cap;
3. a `review_kit` activity (`KitReviewer`, a different model family from the drafter, like FactReviewer), in place of the three lenses.

The KitDrafter's input is the flagship release payload (document text, metadata title, canonical URL, `claim_manifest` from Phase 4) and the channel rules for its kind. The KitReviewer judges two things. First, whether every factual claim in the kit is inside the flagship's claim manifest; a claim outside it is a `blocking` finding. Second, whether the piece holds the public register in short form; that is advisory.

The owner approves a kit piece through a new `ApprovalKind::Kit`, which moves it to `approved` (shown as "Ready to post"). The owner copies the text out by hand. The app never posts anywhere. `MarkKitPiecePosted` moves it to `published` (shown as "Posted") with an optional URL, and it refuses until the flagship itself is published, per the blog channel rule against linking a post before it is live.

**Kit canon.** The canon gains three new files:

- `channels/kit.md`: the blog channel's distribution kit section, plus the mechanics-only subset of bat-kol's `channels/bluesky.md` (300 characters per post, `1/n` numbering, each post readable alone) restated in the public register. It omits Bluesky's social-register conventions: lowercase, politics, profanity.
- `roles/kit-drafter.md` and `roles/kit-reviewer.md`.
- `voice/kit-rules.md`: kit rule statements tagged by id. These rules are implemented in PHP, so the Phase 3 two-way id check covers them.

Kit pieces use the fixed short-form examples in a new `exemplars/short-form.md` (the register's LinkedIn-length line and CTA examples), with no retrieval.

**Siblings.** `CreateSiblingPiece` adds another `blog_post` piece to an idea (no parent) and starts development with the idea body, the owner's optional note, and `sibling_context`: the existing pieces' titles and angle theses, so the Interviewer proposes a distinct angle. A sibling runs the full post path, including its own angle gate.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **One idea owns many pieces: flagship, LinkedIn, Bluesky and email kit pieces, and owner-requested siblings, each with its own stage**: rejected a flagship plus kit only, siblings only, and the 2026-09-22 exclusion of derivatives.
- **Release approval freezes the claim manifest; kit pieces are drafted from the approved flagship, may not add claims outside it, and can be marked Posted only after the flagship is Published**.
- **Self-repair rewrites only agent-authored blocks**: kit pieces reuse the same rule.
- **Deterministic lint findings block approval unless overridden with a reason**: kit approval enforces kit lint like release enforces body lint.
- **Automated posting is out of scope**: the owner copies kit text out and marks it Posted.
- **Retrieved short-form exemplars for kit pieces are Stretch**: no published kit pieces exist to retrieve yet.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/DistributionKitTest.php`

**Playground**: Pest with `Http::fake` sequences for `draft_kit` and `review_kit` responses.

**Why this approach**: Spawning, gating and the Posted rule are state transitions across two attempts, exercised end to end with faked agents.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `resources/publishing/canon/channels/kit.md` | Kit channel rules (LinkedIn, Bluesky thread, email blurb) in the public register |
| `resources/publishing/canon/voice/kit-rules.md` | Tagged kit rule statements |
| `resources/publishing/canon/exemplars/short-form.md` | Fixed short-form examples from the public register |
| `resources/publishing/canon/roles/kit-drafter.md`, `roles/kit-reviewer.md` | Role rubrics |
| `app/Ai/Agents/KitDrafter.php` | Role for `draft_kit` (`google/gemini-3.8-flash`, effort `medium`, `#[MaxTokens(16000)]`) |
| `app/Ai/Agents/KitReviewer.php` | Role for `review_kit` (`deepseek/deepseek-v4-pro-0813`, effort `high`, `#[MaxTokens(16000)]`) |
| `app/Actions/Publishing/SpawnDistributionKit.php` | Idempotent kit creation after flagship release approval |
| `app/Actions/Publishing/MarkKitPiecePosted.php` | Owner marks a kit piece posted after the flagship is published |
| `app/Actions/Publishing/CreateSiblingPiece.php` | New blog piece on an existing idea, development started with sibling context |
| `app/Services/Publishing/Lint/Rules/LinkedinLength.php`, `LinkedinHashtags.php`, `LinkedinLinkInComment.php`, `BlueskyPostLength.php`, `BlueskyNumbering.php`, `EmailSubjectLength.php`, `EmailBlurbLength.php`, `KitNoWalkthroughCta.php`, `KitLinksPost.php` | Kit lint rules |
| `database/migrations/2026_10_02_300000_add_posted_fields_to_articles_table.php` | `posted_at`, `posted_url` on `articles` |
| `tests/Feature/Publishing/DistributionKitTest.php` | Spawn, drafting, lint, review, approval, Posted rule, copy-out |
| `tests/Feature/Publishing/SiblingPieceTest.php` | Sibling creation, sibling context, full path with its own angle gate |

### Modified Files

| File Path | Changes |
| --- | --- |
| `app/Models/Publishing/EditorialActivityKind.php` | Add `DraftKit = 'draft_kit'`, `ReviewKit = 'review_kit'` |
| `app/Models/Publishing/ApprovalKind.php` | Add `Kit = 'kit'` |
| `app/Ai/Agents/EditorialAgent.php` | Map new kinds; `draft_kit` schema per piece kind (LinkedIn `{text, first_comment}`, Bluesky `{posts[]}`, email `{subject, preview, body}`) with each limit stated in its description; `review_kit` uses the finding shape with `kind` in `claim_outside_manifest`, `register`, `channel` |
| `resources/publishing/canon/roles.json` | `draft_kit`: `voice/core.md`, `voice/mechanical-rules.md`, `voice/kit-rules.md`, `channels/kit.md`, `exemplars/short-form.md`, `roles/kit-drafter.md`; `review_kit`: `voice/core.md`, `channels/kit.md`, `roles/kit-reviewer.md` |
| `app/Services/Publishing/Lint/LintTarget.php` | Kit kinds map their payload to blocks (one block per Bluesky post; LinkedIn text and first comment; email subject, preview, body) |
| `app/Services/Publishing/Lint/Rules/*.php` (Phase 3 body rules) | `appliesTo()`: body vocabulary and punctuation rules apply to every kind; heading rules and `walkthrough-link` apply to `blog_post` only |
| `app/Actions/Publishing/StartEditorialActivity.php` | Eligibility for kit kinds (piece is a kit kind, parent release approved, attempt in review); freeze `flagship` (release payload text, title, canonical URL, `claim_manifest`) into kit inputs; freeze `sibling_context` into sibling interviews |
| `app/Actions/Publishing/RunEditorialActivity.php` | `applyDraftKit` saves the kit revision (origin `agent-initial`); after lint, queue `review_kit` for kit kinds instead of the three lenses |
| `app/Actions/Publishing/ManageArticleRelease.php` | After a flagship blog post's release approval commits, call `SpawnDistributionKit` |
| `app/Actions/Publishing/ApprovePublishingStage.php` | Approve `Kit` (requires no unresolved non-overridden kit lint, no undecided blocking `review_kit` finding; stage becomes `approved`) |
| `app/Models/Article.php` | Fillable and casts for `posted_at`, `posted_url`; `isKit()` helper |
| `resources/views/components/admin/publishing/⚡article-workspace.blade.php` | Kit pieces render a compact kit panel (no develop or plan tabs): the text per field with copy buttons, findings, approve, mark posted (disabled with the reason until the flagship is published); flagship and idea surfaces show "New post from this idea" |
| `resources/views/components/admin/publishing/partials/write.blade.php` | Kit findings list |
| `resources/publishing/canon/roles/interviewer.md` | When `sibling_context` is present, propose angles distinct from every listed angle and say how each differs |

## Implementation Details

### Kit channel rules

**Pattern to follow**: Phase 3 lint rules.

| Id | Rule |
| --- | --- |
| `linkedin-length` | Text at most 3,000 characters, and the target query (from the flagship brief) appears in the first 210 characters |
| `linkedin-hashtags` | At most three hashtags, only at the end |
| `linkedin-link-in-comment` | No URL in the body; `first_comment` contains the flagship URL |
| `bluesky-post-length` | Every post at most 300 characters including numbering |
| `bluesky-numbering` | Every post carries `i/n` and `n` equals the post count |
| `email-subject-length` | Subject at most 50 characters |
| `email-blurb-length` | Body has 2-4 sentences |
| `kit-no-walkthrough-cta` | No link to `/walkthrough` in any kit piece (the CTA lives in the post) |
| `kit-links-post` | The flagship's canonical URL appears exactly once (LinkedIn first comment, Bluesky final post, email body) |

### Spawn and approval

```php
final class SpawnDistributionKit
{
    /** @return list<Article> the three kit pieces (existing ones returned unchanged) */
    public function spawn(User $actor, Article $flagship, ArticleRelease $release): array;
}

final class MarkKitPiecePosted
{
    public function mark(User $actor, Article $kitPiece, ?string $postedUrl = null): void;
    // requires publishing.publish; kit piece attempt stage approved; flagship published_release_id not null
    // and flagship attempt stage published; sets posted_at, posted_url; kit attempt stage -> published
}
```

**Key decisions**:

- Spawning is idempotent per flagship and kind: approving a new release of the same flagship does not create duplicates. Existing kit pieces that are not yet approved are restarted (new `draft_kit` on the new release); approved or posted ones are left alone and flagged "flagship changed since approval" in the panel.
- Kit drafting starts automatically after spawn unless publishing agents are paused, the same as other agent work.
- `posted_url`, when given, must be an https URL. It is shown on the card and never fetched.

### Siblings

```php
final class CreateSiblingPiece
{
    public function create(User $actor, ContentIdea $idea, ?string $note = null): Article; // returns the new blog piece with its interview queued
}
```

**Implementation steps**:

1. Enum cases, approval kind, migration, model fields.
2. Canon files and `roles.json` entries. Run `VoiceLintTest` to keep the two-way id check green.
3. Kit lint rules and `LintTarget` mapping.
4. Roles, schemas, output validation, eligibility, frozen inputs.
5. `SpawnDistributionKit`, wiring after release approval, `RunEditorialActivity` kit branches, kit approval.
6. `MarkKitPiecePosted` and the kit panel.
7. `CreateSiblingPiece`, the interviewer rubric addition and the button.

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/DistributionKitTest.php`
- **Experiment**:
  - (a) Approve a flagship release: three kit pieces appear with drafts queued.
  - (b) A Bluesky draft with a 320-character post: lint repair runs, then a lint finding remains if still too long.
  - (c) A KitReviewer `claim_outside_manifest` finding blocks kit approval until decided.
  - (d) Mark Posted before the flagship publishes: refused with the reason. After publishing it succeeds.
  - (e) Approve a second flagship release: no duplicate pieces.
  - (f) A paused-agents setting: kit pieces spawn but no drafts run.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/DistributionKitTest.php tests/Feature/Publishing/SiblingPieceTest.php`

## Data Model

```sql
ALTER TABLE articles ADD COLUMN posted_at TIMESTAMP NULL, ADD COLUMN posted_url VARCHAR(2048) NULL;
```

Kit revisions store their channel fields in `document` (one paragraph block per field or post, block ids stable) and `metadata` (`subject`, `preview`, `first_comment`).

## Testing Requirements

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Publishing/DistributionKitTest.php` | New, per the experiments above |
| `tests/Feature/Publishing/SiblingPieceTest.php` | New: sibling shares the idea, has no parent, its interview input carries `sibling_context` with every existing piece's title and angle thesis, and its plan cannot draft without its own angle approval |
| `tests/Feature/Publishing/VoiceLintTest.php` | Extended: one dataset row pair per kit rule; two-way id check includes kit ids |
| `tests/Feature/Publishing/EditorialInputContractTest.php` | Extended automatically for `draft_kit` and `review_kit`; assert kit inputs carry `flagship.claim_manifest` and the flagship text |

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Flagship release approved while agents are paused | Pieces spawn, drafts wait for unpause (existing paused semantics) |
| Kit draft violates a structural limit (wrong field set) | Output validation fails the activity; retry |
| Mark Posted too early | Validation error naming the unmet condition |
| Flagship withdrawn after kit approval | Kit pieces stay approved and show a warning; Posted stays refused while the flagship is unpublished |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Claim check | Paraphrased claim misjudged as new | LLM judgment | False block | Finding is decidable by the owner (reject with reason) |
| Claim check | New claim slips through | Reviewer misses it | Unverified claim posted | Different model family from the drafter; owner reads before approving |
| Spawn | Duplicate kit pieces | Release approval retried | Board clutter | Idempotency per flagship and kind; test |
| Register drift | Bluesky mechanics pull in social-register habits (lowercase, profanity) | Source file is social-register | Off-brand posts | Kit canon restates mechanics only; lint keeps body rules on kit kinds |
| Sibling | Same angle as the flagship | Interviewer ignores context | Duplicate post | Rubric requires a stated difference per angle; owner approves the angle |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/DistributionKitTest.php tests/Feature/Publishing/SiblingPieceTest.php tests/Feature/Publishing/VoiceLintTest.php
php artisan test --compact tests/Feature/Publishing
vendor/bin/pint --dirty --format agent
env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress
composer test
```

## Rollout Considerations

- **Feature flag**: none.
- **Rollback plan**: revert; kit pieces are ordinary articles with kit kinds and can be deleted.
- **Rules in force**: `.ai/rules/ai.md`, `.ai/rules/app.md`, `.ai/rules/tests.md`, `.ai/rules/actions.md` (kit pieces never become public writing pages: public readers filter to `blog_post`).

## Open Items

- [ ] Confirm that public writing readers (`ReadPublishedWriting`, RSS, sitemap) filter to `kind = blog_post`. Kit pieces never get an `ArticleRelease`, but the reader should not depend on that.
