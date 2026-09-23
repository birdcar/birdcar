<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('prepared release packages are immutable while new working revisions stay isolated from live content', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Live isolation.', 'live-isolation');
    $revision = $write->save($actor, $article, null, releaseDocument('Published draft'), ['title' => 'Published Draft'], 'draft-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $release = $releases->prepare($actor, $attempt, $revision->id, 'live-isolation');
    $releases->approve($actor, $release, $release->release_hash);

    $delivered = $releases->deliver($actor, $release, null);
    $newRevision = $write->save($actor, $article, $revision->id, releaseDocument('Working replacement'), ['title' => 'Replacement'], 'draft-2');

    expect($delivered->published_at)->not->toBeNull()
        ->and($article->fresh()?->published_release_id)->toBe($release->id)
        ->and($article->fresh()?->working_revision_id)->toBe($newRevision->id)
        ->and($release->fresh()?->payload['document']['content'][0]['text'])->toBe('Published draft');
});

test('revision and release package snapshots reject direct mutation', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Immutable snapshots.', 'immutable-snapshots');
    $revision = $write->save($actor, $article, null, releaseDocument('Frozen'), ['title' => 'Frozen'], 'draft-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $release = $releases->prepare($actor, $attempt, $revision->id, 'immutable-snapshots');

    expect(fn () => tap($revision->fresh(), fn (ArticleRevision $revision) => $revision->forceFill(['document' => releaseDocument('Changed')])->save()))
        ->toThrow(RuntimeException::class, 'append-only');
    expect(fn () => tap($release->fresh(), fn (ArticleRelease $release) => $release->forceFill(['payload' => ['changed' => true]])->save()))
        ->toThrow(RuntimeException::class, 'immutable');
});

test('release preparation rejects cross article revisions', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'First.', 'first-release');
    $revision = $write->save($actor, $article, null, releaseDocument('First'), ['title' => 'First'], 'first-1');
    $otherArticle = $write->capture($actor, 'Second.', 'second-release');
    $otherRevision = $write->save($actor, $otherArticle, null, releaseDocument('Second'), ['title' => 'Second'], 'second-1');
    $attempt = $advance->develop($actor, $article, $revision->id);

    expect(fn () => $releases->prepare($actor, $attempt, $otherRevision->id, 'first-release'))
        ->toThrow(RuntimeException::class, 'same article');
});

test('release approval is closed outside release management and requires a prepared package hash', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);
    $article = $write->capture($actor, 'Bare revision release.', 'bare-revision-release');
    $revision = $write->save($actor, $article, null, releaseDocument('Draft'), ['title' => 'Draft'], 'draft-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));

    expect(fn () => $approve->approve($actor, $attempt, ApprovalKind::Release, 'bare-revision-hash', $revision->id))
        ->toThrow(RuntimeException::class, 'managed through release packages');
    expect(fn () => $approve->inputHashFor($attempt, ApprovalKind::Release, $revision->id))
        ->toThrow(RuntimeException::class, 'prepared release package');
});

test('editing an approved scheduled package invalidates release approval and withdraws the schedule', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Schedule withdrawal.', 'schedule-withdrawal');
    $revision = $write->save($actor, $article, null, releaseDocument('Scheduled'), ['title' => 'Scheduled'], 'draft-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $release = $releases->prepare($actor, $attempt, $revision->id, 'schedule-withdrawal', now()->subMinute());
    $approval = $releases->approve($actor, $release, $release->release_hash);

    $write->save($actor, $article, $revision->id, releaseDocument('Changed'), ['title' => 'Changed'], 'draft-2');

    expect($approval->fresh()?->invalidated_at)->not->toBeNull()
        ->and($release->fresh()?->status)->toBe('withdrawn')
        ->and($release->fresh()?->withdrawn_at)->not->toBeNull();
});

test('approved unscheduled packages cannot be reapproved or delivered after a newer manuscript save', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Stale package.', 'stale-package');
    $revision = $write->save($actor, $article, null, releaseDocument('Approved'), ['title' => 'Approved'], 'draft-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $release = $releases->prepare($actor, $attempt, $revision->id, 'stale-package');
    $approval = $releases->approve($actor, $release, $release->release_hash);

    $newRevision = $write->save($actor, $article, $revision->id, releaseDocument('Changed'), ['title' => 'Changed'], 'draft-2');

    expect(fn () => $approve->approve($actor, $attempt, ApprovalKind::Release, $release->release_hash, $revision->id, $release->id))
        ->toThrow(RuntimeException::class, 'managed through release packages');
    expect(fn () => $releases->approve($actor, $release, $release->release_hash))
        ->toThrow(RuntimeException::class, 'current working revision');
    expect(fn () => $releases->deliver($actor, $release, null))
        ->toThrow(RuntimeException::class, 'current working revision');
    expect($approval->fresh()?->invalidated_at)->not->toBeNull()
        ->and($article->fresh()?->published_release_id)->toBeNull();

    releaseAttemptWithCompletedReviews($actor, $attempt->fresh());
    $freshRelease = $releases->prepare($actor, $attempt, $newRevision->id, 'stale-package');
    $releases->approve($actor, $freshRelease, $freshRelease->release_hash);
    $delivered = $releases->deliver($actor, $freshRelease, null);

    expect($delivered->published_at)->not->toBeNull()
        ->and($article->fresh()?->published_release_id)->toBe($freshRelease->id);
});

test('write current attempt guard fails before revision or release invalidation side effects', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);

    $article = $write->capture($actor, 'Current attempt save guard.', 'current-attempt-save-guard');
    $revision = $write->save($actor, $article, null, releaseDocument('Approved'), ['title' => 'Approved'], 'guard-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $release = $releases->prepare($actor, $attempt, $revision->id, 'current-attempt-save-guard');
    $approval = $releases->approve($actor, $release, $release->release_hash);
    $staleArticle = $article->fresh();

    $replacementAttempt = PublishingAttempt::factory()->create([
        'article_id' => $article->id,
        'input_version' => $revision->id,
    ]);
    Article::query()->whereKey($article->id)->update(['current_attempt_id' => $replacementAttempt->id]);

    expect(fn () => $write->save($actor, $staleArticle, $revision->id, releaseDocument('Should not save'), ['title' => 'Changed'], 'guard-2'))
        ->toThrow(RuntimeException::class, 'current publishing attempt changed');

    expect(ArticleRevision::query()->where('article_id', $article->id)->count())->toBe(1)
        ->and($article->fresh()?->working_revision_id)->toBe($revision->id)
        ->and($approval->fresh()?->invalidated_at)->toBeNull()
        ->and($release->fresh()?->status)->toBe('approved');
});

test('release approval rejects a release revision that only has an older preserved review batch', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Stale review release.', 'stale-review-release');
    $revision = $write->save($actor, $article, null, releaseDocument('Reviewed'), ['title' => 'Reviewed'], 'stale-review-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $revision->id,
        'input_hash' => $revision->content_hash,
        'lens' => 'review_facts',
        'kind' => 'claim',
        'severity' => 'advisory',
        'statement' => 'Old review finding.',
        'supporting_source_ids' => [],
    ]);

    $newRevision = $write->save($actor, $article, $revision->id, releaseDocument('Edited after review'), ['title' => 'Edited'], 'stale-review-2');
    $release = $releases->prepare($actor, $attempt->fresh(), $newRevision->id, 'stale-review-release');

    expect(EditorialActivity::query()->where('attempt_id', $attempt->id)->whereIn('kind', ['review_facts', 'review_voice', 'review_buyer'])->where('status', 'completed')->where('revision_id', $revision->id)->count())->toBe(3)
        ->and(EditorialFinding::query()->where('attempt_id', $attempt->id)->where('revision_id', $revision->id)->whereNull('stale_at')->exists())->toBeTrue();
    expect(fn () => $releases->approve($actor, $release, $release->release_hash))
        ->toThrow(RuntimeException::class, 'same-revision review batch for the release revision');
});

test('release approval accepts a completed targeted recheck tied to the reviewed base batch', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Targeted recheck release.', 'targeted-recheck-release');
    $reviewedRevision = $write->save($actor, $article, null, releaseDocument('Reviewed'), ['title' => 'Reviewed'], 'targeted-recheck-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $reviewedRevision->id));
    $targetRevision = $write->save($actor, $article, $reviewedRevision->id, releaseDocument('Edited after accepted patch'), ['title' => 'Edited'], 'targeted-recheck-2');

    EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => EditorialActivityKind::Recheck,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'in_review',
        'input_version' => $attempt->input_version,
        'revision_id' => $targetRevision->id,
        'revision_hash' => $targetRevision->content_hash,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'recheck-'.$attempt->id,
        'idempotency_key' => 'recheck-'.$attempt->id,
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', 'recheck-'.$attempt->id),
        'input' => [
            'reviewed_revision_id' => $reviewedRevision->id,
            'expected_revision_id' => $targetRevision->id,
            'reviewed_revision_hash' => $reviewedRevision->content_hash,
            'target_revision_hash' => $targetRevision->content_hash,
        ],
        'model_snapshot' => [],
        'response' => ['resolved' => [], 'unresolved' => [], 'newBlockingFindings' => []],
        'completed_at' => now(),
    ]);

    $release = $releases->prepare($actor, $attempt->fresh(), $targetRevision->id, 'targeted-recheck-release');
    $approval = $releases->approve($actor, $release, $release->release_hash);

    expect($approval->revision_id)->toBe($targetRevision->id);
});

test('release approval rejects preserved base blockers after a clean targeted recheck', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Preserved blocker release.', 'preserved-blocker-release');
    $reviewedRevision = $write->save($actor, $article, null, releaseDocument('Reviewed claim stays unchanged'), ['title' => 'Reviewed'], 'preserved-blocker-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $reviewedRevision->id));
    EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $reviewedRevision->id,
        'input_hash' => $reviewedRevision->content_hash,
        'lens' => 'review_facts',
        'kind' => 'claim',
        'severity' => 'blocking',
        'statement' => 'The unchanged material claim is unsupported.',
        'supporting_source_ids' => [],
    ]);
    $targetRevision = $write->save($actor, $article, $reviewedRevision->id, releaseDocument('Reviewed claim stays unchanged with a style edit'), ['title' => 'Edited'], 'preserved-blocker-2');
    releaseCompletedRecheck($actor, $attempt, $reviewedRevision, $targetRevision, ['resolved' => [], 'unresolved' => [], 'newBlockingFindings' => []]);

    $release = $releases->prepare($actor, $attempt->fresh(), $targetRevision->id, 'preserved-blocker-release');

    expect(fn () => $releases->approve($actor, $release, $release->release_hash))
        ->toThrow(RuntimeException::class, 'actual resolution');
});

test('release approval rejects unsuccessful targeted rechecks', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Unsuccessful recheck release.', 'unsuccessful-recheck-release');
    $reviewedRevision = $write->save($actor, $article, null, releaseDocument('Reviewed'), ['title' => 'Reviewed'], 'unsuccessful-recheck-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $reviewedRevision->id));
    $targetRevision = $write->save($actor, $article, $reviewedRevision->id, releaseDocument('Edited'), ['title' => 'Edited'], 'unsuccessful-recheck-2');
    releaseCompletedRecheck($actor, $attempt, $reviewedRevision, $targetRevision, ['resolved' => [], 'unresolved' => [['block_id' => 'affected-claim', 'reason' => 'Still unsupported']], 'newBlockingFindings' => []]);

    $release = $releases->prepare($actor, $attempt->fresh(), $targetRevision->id, 'unsuccessful-recheck-release');

    expect(fn () => $releases->approve($actor, $release, $release->release_hash))
        ->toThrow(RuntimeException::class, 'same-revision review batch');
});

test('release approval rejects new blocking findings reported by recheck until resolved', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'New blocker recheck release.', 'new-blocker-recheck-release');
    $reviewedRevision = $write->save($actor, $article, null, releaseDocument('Reviewed'), ['title' => 'Reviewed'], 'new-blocker-recheck-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $reviewedRevision->id));
    $targetRevision = $write->save($actor, $article, $reviewedRevision->id, releaseDocument('Edited'), ['title' => 'Edited'], 'new-blocker-recheck-2');
    $recheck = releaseCompletedRecheck($actor, $attempt, $reviewedRevision, $targetRevision, ['resolved' => [], 'unresolved' => [], 'newBlockingFindings' => [[
        'statement' => 'The edit introduced a new unsupported material claim.',
        'kind' => 'claim',
        'severity' => 'blocking',
        'supporting_source_ids' => [],
        'supporting_quotations' => [],
    ]]]);
    EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'activity_id' => $recheck->id,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $targetRevision->id,
        'input_hash' => $targetRevision->content_hash,
        'lens' => 'recheck',
        'kind' => 'claim',
        'severity' => 'blocking',
        'statement' => 'The edit introduced a new unsupported material claim.',
        'supporting_source_ids' => [],
    ]);

    $release = $releases->prepare($actor, $attempt->fresh(), $targetRevision->id, 'new-blocker-recheck-release');

    expect(fn () => $releases->approve($actor, $release, $release->release_hash))
        ->toThrow(RuntimeException::class, 'actual resolution');
});

test('delivery is idempotent for the same package and cannot let older jobs replace newer publications', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Compare and swap.', 'compare-swap');
    $revision = $write->save($actor, $article, null, releaseDocument('Draft'), ['title' => 'Draft'], 'draft-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $oldRelease = $releases->prepare($actor, $attempt, $revision->id, 'compare-swap', now()->subMinute(), ['channel' => 'scheduled']);
    $releases->approve($actor, $oldRelease, $oldRelease->release_hash);
    $newAttempt = PublishingAttempt::factory()->create([
        'article_id' => $article->id,
        'input_version' => $revision->id,
    ]);
    $article->forceFill(['current_attempt_id' => $newAttempt->id])->save();
    $newAttempt = approveReleasePrerequisites($actor, $newAttempt);
    $newRelease = $releases->prepare($actor, $newAttempt, $revision->id, 'compare-swap', null, ['channel' => 'manual']);
    $releases->approve($actor, $newRelease, $newRelease->release_hash);

    $delivered = $releases->deliver($actor, $newRelease, null);
    $duplicate = $releases->deliver($actor, $newRelease, null);

    expect($duplicate->id)->toBe($delivered->id)
        ->and($duplicate->published_at?->toISOString())->toBe($delivered->published_at?->toISOString());
    expect(fn () => $releases->deliver($actor, $oldRelease, null))
        ->toThrow(RuntimeException::class, 'live article changed');
});

test('delivery rejects blocked and non deliverable attempts inside the transaction', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Blocked delivery.', 'blocked-delivery');
    $revision = $write->save($actor, $article, null, releaseDocument('Draft'), ['title' => 'Draft'], 'draft-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $release = $releases->prepare($actor, $attempt, $revision->id, 'blocked-delivery');
    $releases->approve($actor, $release, $release->release_hash);

    $advance->pause($actor, $attempt, 'Unresolved source blocker.');

    expect(fn () => $releases->deliver($actor, $release, null))
        ->toThrow(RuntimeException::class, 'Blocked publishing attempts cannot be delivered');

    $advance->resume($actor, $attempt);
    $attempt->fresh()?->forceFill(['stage' => EditorialStage::InReview])->save();

    expect(fn () => $releases->deliver($actor, $release, null))
        ->toThrow(RuntimeException::class, 'Only approved or scheduled publishing attempts can be delivered');
});

test('imported releases keep history without fabricated approvals and new work uses a normal attempt', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $article = Article::factory()->create(['author_id' => $actor->id, 'slug' => 'imported-history']);
    $revision = $write->save($actor, $article, null, releaseDocument('Imported body'), ['title' => 'Imported'], 'import-1', 'import');
    $import = ArticleRelease::factory()->imported()->create([
        'article_id' => $article->id,
        'attempt_id' => null,
        'revision_id' => $revision->id,
    ]);
    $article->forceFill([
        'published_release_id' => $import->id,
        'first_published_at' => $import->published_at,
    ])->save();

    $attempt = $advance->develop($actor, $article, $revision->id);

    expect($import->origin)->toBe('import')
        ->and($import->attempt_id)->toBeNull()
        ->and($import->approvals()->count())->toBe(0)
        ->and($attempt->article_id)->toBe($article->id);
});

function approveReleasePrerequisites(User $actor, PublishingAttempt $attempt): PublishingAttempt
{
    $approve = app(ApprovePublishingStage::class);

    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = releaseAttemptWithReviewedPlan($actor, $attempt->fresh());
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $attempt = $attempt->fresh();
    releaseAttemptWithCompletedReviews($actor, $attempt);

    return $attempt->fresh();
}

function releaseAttemptWithReviewedPlan(User $actor, PublishingAttempt $attempt): PublishingAttempt
{
    EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => EditorialActivityKind::ResearchChallenge,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'drafting',
        'input_version' => $attempt->input_version,
        'revision_id' => null,
        'revision_hash' => null,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'research-'.$attempt->id.'-'.uniqid(),
        'idempotency_key' => 'research-'.$attempt->id.'-'.uniqid(),
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', 'research-'.$attempt->id.uniqid()),
        'input' => ['approval_hashes' => [ApprovalKind::Angle->value => app(ApprovePublishingStage::class)->inputHashFor($attempt, ApprovalKind::Angle)]],
        'model_snapshot' => [],
        'response' => ['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []],
        'completed_at' => now(),
    ]);

    return app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, plan: ['outline' => ['intro', 'body'], 'visualPlan' => ['hero image']]);
}

function releaseAttemptWithCompletedReviews(User $actor, PublishingAttempt $attempt): void
{
    $article = $attempt->article()->firstOrFail();
    $revision = $article->workingRevision()->firstOrFail();

    foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer, EditorialActivityKind::Reconciliation] as $kind) {
        EditorialActivity::create([
            'article_id' => $attempt->article_id,
            'attempt_id' => $attempt->id,
            'initiating_user_id' => $actor->id,
            'kind' => $kind,
            'status' => EditorialActivityStatus::Completed,
            'stage' => 'in_review',
            'input_version' => $attempt->input_version,
            'revision_id' => $revision->id,
            'revision_hash' => $revision->content_hash,
            'review_cycle' => $attempt->review_cycle,
            'batch_key' => 'review-'.$attempt->id.'-'.uniqid(),
            'idempotency_key' => 'review-'.$attempt->id.'-'.$kind->value.'-'.uniqid(),
            'prompt_version' => 1,
            'prompt_hash' => hash('sha256', 'review-'.$attempt->id.$kind->value.uniqid()),
            'input' => [],
            'model_snapshot' => [],
            'response' => $kind === EditorialActivityKind::Reconciliation ? ['groups' => [], 'conflicts' => []] : ['findings' => []],
            'completed_at' => now(),
        ]);
    }
}

/** @param array<string, mixed> $response */
function releaseCompletedRecheck(User $actor, PublishingAttempt $attempt, ArticleRevision $reviewedRevision, ArticleRevision $targetRevision, array $response): EditorialActivity
{
    return EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => EditorialActivityKind::Recheck,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'in_review',
        'input_version' => $attempt->input_version,
        'revision_id' => $targetRevision->id,
        'revision_hash' => $targetRevision->content_hash,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'recheck-'.$attempt->id.'-'.$targetRevision->id.'-'.uniqid(),
        'idempotency_key' => 'recheck-'.$attempt->id.'-'.$targetRevision->id.'-'.uniqid(),
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', 'recheck-'.$attempt->id.$targetRevision->id.uniqid()),
        'input' => [
            'reviewed_revision_id' => $reviewedRevision->id,
            'expected_revision_id' => $targetRevision->id,
            'reviewed_revision_hash' => $reviewedRevision->content_hash,
            'target_revision_hash' => $targetRevision->content_hash,
        ],
        'model_snapshot' => [],
        'response' => $response,
        'completed_at' => now(),
    ]);
}

function releaseAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function releaseDocument(string $text): array
{
    return [
        'version' => 1,
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'text' => $text],
        ],
    ];
}
