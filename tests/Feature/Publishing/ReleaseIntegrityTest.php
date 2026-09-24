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
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use Carbon\CarbonInterface;
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
    $revision = $write->save($actor, $article, null, releaseDocument('Published draft'), releaseMetadata('Published Draft'), 'draft-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $release = $releases->prepare($actor, $attempt, $revision->id, 'live-isolation');
    $releases->approve($actor, $release, $release->release_hash);

    $delivered = $releases->deliver($actor, $release, null);
    $newRevision = $write->save($actor, $article, $revision->id, releaseDocument('Working replacement'), releaseMetadata('Replacement'), 'draft-2');

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
    $revision = $write->save($actor, $article, null, releaseDocument('Frozen'), releaseMetadata('Frozen'), 'draft-1');
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
    $revision = $write->save($actor, $article, null, releaseDocument('First'), releaseMetadata('First'), 'first-1');
    $otherArticle = $write->capture($actor, 'Second.', 'second-release');
    $otherRevision = $write->save($actor, $otherArticle, null, releaseDocument('Second'), releaseMetadata('Second'), 'second-1');
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
    $revision = $write->save($actor, $article, null, releaseDocument('Draft'), releaseMetadata('Draft'), 'draft-1');
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
    $revision = $write->save($actor, $article, null, releaseDocument('Scheduled'), releaseMetadata('Scheduled'), 'draft-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $schedule = releaseSchedule($releases, now()->addMinute());
    $release = $releases->prepare($actor, $attempt, $revision->id, 'schedule-withdrawal', $schedule['scheduled_at'], $schedule['delivery_intent']);
    $approval = $releases->approve($actor, $release, $release->release_hash);

    $write->save($actor, $article, $revision->id, releaseDocument('Changed'), releaseMetadata('Changed'), 'draft-2');

    expect($approval->fresh()?->invalidated_at)->not->toBeNull()
        ->and($release->fresh()?->status)->toBe('withdrawn')
        ->and($release->fresh()?->withdrawn_at)->not->toBeNull();
});

test('approving a replacement scheduled release withdraws the older schedule for the attempt', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Schedule replacement.', 'schedule-replacement');
    $revision = $write->save($actor, $article, null, releaseDocument('Scheduled'), releaseMetadata('Scheduled'), 'schedule-replacement-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $oldSchedule = releaseSchedule($releases, now()->addHour());
    $oldRelease = $releases->prepare($actor, $attempt, $revision->id, 'schedule-replacement', $oldSchedule['scheduled_at'], $oldSchedule['delivery_intent']);
    $releases->approve($actor, $oldRelease, $oldRelease->release_hash);

    $newSchedule = releaseSchedule($releases, now()->addHours(2));
    $newRelease = $releases->prepare($actor, $attempt->fresh(), $revision->id, 'schedule-replacement', $newSchedule['scheduled_at'], $newSchedule['delivery_intent']);
    $releases->approve($actor, $newRelease, $newRelease->release_hash);

    expect($oldRelease->fresh()?->status)->toBe('withdrawn')
        ->and($oldRelease->fresh()?->withdrawn_at)->not->toBeNull()
        ->and($newRelease->fresh()?->status)->toBe('scheduled')
        ->and($newRelease->fresh()?->withdrawn_at)->toBeNull();
});

test('approved unscheduled packages cannot be reapproved or delivered after a newer manuscript save', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Stale package.', 'stale-package');
    $revision = $write->save($actor, $article, null, releaseDocument('Approved'), releaseMetadata('Approved'), 'draft-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $release = $releases->prepare($actor, $attempt, $revision->id, 'stale-package');
    $approval = $releases->approve($actor, $release, $release->release_hash);

    $newRevision = $write->save($actor, $article, $revision->id, releaseDocument('Changed'), releaseMetadata('Changed'), 'draft-2');

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

test('approved packages cannot be manually delivered after evidence manifests change in place', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Evidence stale delivery.', 'evidence-stale-delivery');
    $revision = $write->save($actor, $article, null, releaseDocument('Draft'), releaseMetadata('Draft'), 'evidence-stale-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $source = EvidenceSource::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'source_type' => 'url',
        'url' => 'https://example.com/evidence',
        'final_url' => 'https://example.com/evidence',
        'title' => 'Evidence',
        'extracted_text' => 'Original source text.',
        'content_hash' => hash('sha256', 'evidence-v1'),
        'publication_permission' => true,
        'retrieved_at' => now(),
    ]);
    $release = $releases->prepare($actor, $attempt, $revision->id, 'evidence-stale-delivery');
    $releases->approve($actor, $release, $release->release_hash);

    $source->forceFill([
        'extracted_text' => 'Changed source text.',
        'content_hash' => hash('sha256', 'evidence-v2'),
        'publication_permission' => false,
    ])->save();

    expect(fn () => $releases->deliver($actor, $release, null))
        ->toThrow(RuntimeException::class, 'blocking findings');
    expect($article->fresh()?->published_release_id)->toBeNull()
        ->and($release->fresh()?->published_at)->toBeNull();

    $source->forceFill(['publication_permission' => true])->save();
    $freshRelease = $releases->prepare($actor, $attempt->fresh(), $revision->id, 'evidence-stale-delivery');
    $releases->approve($actor, $freshRelease, $freshRelease->release_hash);
    $delivered = $releases->deliver($actor, $freshRelease, null);

    expect($delivered->published_at)->not->toBeNull()
        ->and($article->fresh()?->published_release_id)->toBe($freshRelease->id);
});

test('scheduled delivery refuses approved packages after finding dispositions change in place', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Finding stale due.', 'finding-stale-due');
    $revision = $write->save($actor, $article, null, releaseDocument('Draft'), releaseMetadata('Draft'), 'finding-stale-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $finding = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $revision->id,
        'input_hash' => $revision->content_hash,
        'lens' => 'review_facts',
        'kind' => 'claim',
        'severity' => 'blocking',
        'statement' => 'The claim looked unsupported.',
        'supporting_source_ids' => [],
        'disposition' => 'false_positive',
        'disposition_reason' => 'The sentence is a clearly labeled hypothetical.',
        'disposition_actor_id' => $actor->id,
        'disposed_at' => now(),
    ]);
    $schedule = releaseSchedule($releases, now()->addMinute());
    $scheduledAt = $schedule['scheduled_at'];
    $release = $releases->prepare($actor, $attempt, $revision->id, 'finding-stale-due', $scheduledAt, $schedule['delivery_intent']);
    $releases->approve($actor, $release, $release->release_hash);

    $finding->forceFill(['disposition_reason' => 'Changed after approval.'])->save();

    $this->travelTo($scheduledAt->copy()->addMinute());
    $this->artisan('publishing:publish-due')->assertFailed();
    $this->travelBack();

    expect($article->fresh()?->published_release_id)->toBeNull()
        ->and($release->fresh()?->status)->toBe('scheduled')
        ->and($release->fresh()?->published_at)->toBeNull();
});

test('write current attempt guard fails before revision or release invalidation side effects', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);

    $article = $write->capture($actor, 'Current attempt save guard.', 'current-attempt-save-guard');
    $revision = $write->save($actor, $article, null, releaseDocument('Approved'), releaseMetadata('Approved'), 'guard-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $release = $releases->prepare($actor, $attempt, $revision->id, 'current-attempt-save-guard');
    $approval = $releases->approve($actor, $release, $release->release_hash);
    $staleArticle = $article->fresh();

    $replacementAttempt = PublishingAttempt::factory()->create([
        'article_id' => $article->id,
        'input_version' => $revision->id,
    ]);
    Article::query()->whereKey($article->id)->update(['current_attempt_id' => $replacementAttempt->id]);

    expect(fn () => $write->save($actor, $staleArticle, $revision->id, releaseDocument('Should not save'), releaseMetadata('Changed'), 'guard-2'))
        ->toThrow(RuntimeException::class, 'current publishing attempt changed');

    expect(ArticleRevision::query()->where('article_id', $article->id)->count())->toBe(1)
        ->and($article->fresh()?->working_revision_id)->toBe($revision->id)
        ->and($approval->fresh()?->invalidated_at)->toBeNull()
        ->and($release->fresh()?->status)->toBe('approved');
});

test('release preparation rejects a release revision that only has an older preserved review batch', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Stale review release.', 'stale-review-release');
    $revision = $write->save($actor, $article, null, releaseDocument('Reviewed'), releaseMetadata('Reviewed'), 'stale-review-1');
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

    $newRevision = $write->save($actor, $article, $revision->id, releaseDocument('Edited after review'), releaseMetadata('Edited'), 'stale-review-2');

    expect(EditorialActivity::query()->where('attempt_id', $attempt->id)->whereIn('kind', ['review_facts', 'review_voice', 'review_buyer'])->where('status', 'completed')->where('revision_id', $revision->id)->count())->toBe(3)
        ->and(EditorialFinding::query()->where('attempt_id', $attempt->id)->where('revision_id', $revision->id)->whereNull('stale_at')->exists())->toBeTrue();
    expect(fn () => $releases->prepare($actor, $attempt->fresh(), $newRevision->id, 'stale-review-release'))
        ->toThrow(RuntimeException::class, 'review.prerequisite.incomplete');
});

test('release preparation accepts completed targeted recheck tied to a reviewed base batch', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Targeted recheck release.', 'targeted-recheck-release');
    $reviewedRevision = $write->save($actor, $article, null, releaseDocument('Reviewed'), releaseMetadata('Reviewed'), 'targeted-recheck-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $reviewedRevision->id));
    $acceptedBaseFinding = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $reviewedRevision->id,
        'input_hash' => $reviewedRevision->content_hash,
        'lens' => 'review_facts',
        'kind' => 'claim',
        'severity' => 'blocking',
        'block_id' => 'accepted-base-claim',
        'statement' => 'Base review blocker that the targeted edit resolves.',
        'supporting_source_ids' => [],
        'disposition' => 'accepted',
        'disposition_reason' => 'Owner accepted the targeted edit.',
        'disposition_actor_id' => $actor->id,
        'disposed_at' => now(),
    ]);
    $targetRevision = $write->save($actor, $article, $reviewedRevision->id, releaseDocument('Edited after accepted patch'), releaseMetadata('Edited'), 'targeted-recheck-2');

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
        'response' => ['resolved' => [['finding_id' => $acceptedBaseFinding->id, 'block_id' => 'accepted-base-claim', 'status' => 'resolved']], 'unresolved' => [], 'newBlockingFindings' => []],
        'completed_at' => now(),
    ]);

    $release = $releases->prepare($actor, $attempt->fresh(), $targetRevision->id, 'targeted-recheck-release');
    $approval = $releases->approve($actor, $release, $release->release_hash);

    expect($approval->release_id)->toBe($release->id)
        ->and($release->revision_id)->toBe($targetRevision->id)
        ->and($release->payload['review_manifest']['lineage']['mode'])->toBe('targeted_recheck')
        ->and($release->payload['review_manifest']['lineage']['reviewed_revision_ids'])->toContain($reviewedRevision->id)
        ->and(array_column($release->payload['review_manifest']['findings'], 'revision_id'))->toContain($reviewedRevision->id)
        ->and(array_column($release->payload['review_manifest']['activities'], 'revision_id'))->toContain($reviewedRevision->id, $targetRevision->id);
});

test('release preparation rejects unresolved preserved base blockers after targeted recheck', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Preserved blocker release.', 'preserved-blocker-release');
    $reviewedRevision = $write->save($actor, $article, null, releaseDocument('Reviewed claim stays unchanged'), releaseMetadata('Reviewed'), 'preserved-blocker-1');
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
    $targetRevision = $write->save($actor, $article, $reviewedRevision->id, releaseDocument('Reviewed claim stays unchanged with a style edit'), releaseMetadata('Edited'), 'preserved-blocker-2');
    releaseCompletedRecheck($actor, $attempt, $reviewedRevision, $targetRevision, ['resolved' => [], 'unresolved' => [], 'newBlockingFindings' => []]);

    expect(fn () => $releases->prepare($actor, $attempt->fresh(), $targetRevision->id, 'preserved-blocker-release'))
        ->toThrow(RuntimeException::class, 'editorial.material_unresolved');
});

test('release approval rejects unsuccessful targeted rechecks', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Unsuccessful recheck release.', 'unsuccessful-recheck-release');
    $reviewedRevision = $write->save($actor, $article, null, releaseDocument('Reviewed'), releaseMetadata('Reviewed'), 'unsuccessful-recheck-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $reviewedRevision->id));
    $targetRevision = $write->save($actor, $article, $reviewedRevision->id, releaseDocument('Edited'), releaseMetadata('Edited'), 'unsuccessful-recheck-2');
    releaseCompletedRecheck($actor, $attempt, $reviewedRevision, $targetRevision, ['resolved' => [], 'unresolved' => [['block_id' => 'affected-claim', 'reason' => 'Still unsupported']], 'newBlockingFindings' => []]);

    expect(fn () => $releases->prepare($actor, $attempt->fresh(), $targetRevision->id, 'unsuccessful-recheck-release'))
        ->toThrow(RuntimeException::class, 'review.prerequisite.incomplete');
});

test('release approval rejects new blocking findings reported by recheck until resolved', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'New blocker recheck release.', 'new-blocker-recheck-release');
    $reviewedRevision = $write->save($actor, $article, null, releaseDocument('Reviewed'), releaseMetadata('Reviewed'), 'new-blocker-recheck-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $reviewedRevision->id));
    $targetRevision = $write->save($actor, $article, $reviewedRevision->id, releaseDocument('Edited'), releaseMetadata('Edited'), 'new-blocker-recheck-2');
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

    expect(fn () => $releases->prepare($actor, $attempt->fresh(), $targetRevision->id, 'new-blocker-recheck-release'))
        ->toThrow(RuntimeException::class, 'blocking findings');
});

test('delivery compares the live pointer to the frozen package expectation before caller-supplied guards', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Frozen compare and swap.', 'frozen-compare-swap');
    $revision = $write->save($actor, $article, null, releaseDocument('Draft'), releaseMetadata('Draft'), 'frozen-cas-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $release = $releases->prepare($actor, $attempt, $revision->id, 'frozen-compare-swap');
    $releases->approve($actor, $release, $release->release_hash);
    $otherLiveRelease = ArticleRelease::factory()->imported()->create([
        'article_id' => $article->id,
        'revision_id' => $revision->id,
    ]);
    $article->forceFill([
        'published_release_id' => $otherLiveRelease->id,
        'first_published_at' => $otherLiveRelease->published_at,
    ])->save();

    expect($release->payload['expected_previous_live_release_id'])->toBeNull();
    expect(fn () => $releases->deliver($actor, $release, $otherLiveRelease->id))
        ->toThrow(RuntimeException::class, 'live article changed');
    expect($article->fresh()?->published_release_id)->toBe($otherLiveRelease->id)
        ->and($release->fresh()?->published_at)->toBeNull();
});

test('delivery is idempotent for the same package and cannot let older jobs replace newer publications', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Compare and swap.', 'compare-swap');
    $revision = $write->save($actor, $article, null, releaseDocument('Draft'), releaseMetadata('Draft'), 'draft-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $oldSchedule = releaseSchedule($releases, now()->addDay());
    $oldRelease = $releases->prepare($actor, $attempt, $revision->id, 'compare-swap', $oldSchedule['scheduled_at'], $oldSchedule['delivery_intent']);
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
    $revision = $write->save($actor, $article, null, releaseDocument('Draft'), releaseMetadata('Draft'), 'draft-1');
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

test('explicit schedule timezone resolves wall time to UTC and freezes the offset', function (): void {
    $this->travelTo('2026-06-01 12:00:00 UTC');
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Timezone schedule.', 'timezone-schedule');
    $revision = $write->save($actor, $article, null, releaseDocument('Timezone body'), releaseMetadata('Timezone Body'), 'timezone-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $newYork = $releases->resolveSchedule('2026-07-01 09:30:00', 'America/New_York');
    $chicago = $releases->resolveSchedule('2026-07-01 09:30:00', 'America/Chicago');

    $release = $releases->prepare($actor, $attempt, $revision->id, 'timezone-schedule', $newYork['scheduled_at'], $newYork['delivery_intent']);
    $changedZoneRelease = $releases->prepare($actor, $attempt->fresh(), $revision->id, 'timezone-schedule', $chicago['scheduled_at'], $chicago['delivery_intent']);
    $this->travelBack();

    expect($release->scheduled_at?->toISOString())->toBe('2026-07-01T13:30:00.000000Z')
        ->and($release->payload['scheduled_at'])->toBe('2026-07-01T13:30:00.000000Z')
        ->and($release->payload['delivery_intent']['selected_timezone'])->toBe('America/New_York')
        ->and($release->payload['delivery_intent']['scheduled_wall_time'])->toBe('2026-07-01 09:30:00')
        ->and($release->payload['delivery_intent']['utc_offset'])->toBe('-04:00')
        ->and($release->payload['delivery_intent']['scheduled_utc'])->toBe('2026-07-01T13:30:00.000000Z')
        ->and($changedZoneRelease->scheduled_at?->toISOString())->toBe('2026-07-01T14:30:00.000000Z')
        ->and($changedZoneRelease->release_hash)->not->toBe($release->release_hash);
});

test('schedule resolution rejects invalid past nonexistent and ambiguous wall times', function (): void {
    $this->travelTo('2026-02-01 12:00:00 UTC');
    $releases = app(ManageArticleRelease::class);

    expect(fn () => $releases->resolveSchedule('2026-07-01 09:30:00', 'Not/AZone'))
        ->toThrow(InvalidArgumentException::class, 'valid IANA timezone');
    expect(fn () => $releases->resolveSchedule('not a time', 'America/New_York'))
        ->toThrow(InvalidArgumentException::class, 'valid local wall time');
    expect(fn () => $releases->resolveSchedule('2026-01-01 09:30:00', 'America/New_York'))
        ->toThrow(RuntimeException::class, 'future scheduled_at');
    expect(fn () => $releases->resolveSchedule('2026-03-08 02:30:00', 'America/New_York'))
        ->toThrow(RuntimeException::class, 'does not exist');
    expect(fn () => $releases->resolveSchedule('2026-11-01 01:30:00', 'America/New_York'))
        ->toThrow(RuntimeException::class, 'ambiguous');

    $this->travelBack();
});

test('scheduled release preparation requires explicit matching schedule intent without side effects', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Explicit schedule intent.', 'explicit-schedule-intent');
    $revision = $write->save($actor, $article, null, releaseDocument('Draft'), releaseMetadata('Draft'), 'explicit-schedule-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $existingRelease = $releases->prepare($actor, $attempt, $revision->id, 'explicit-schedule-intent');
    $approval = $releases->approve($actor, $existingRelease, $existingRelease->release_hash);
    $scheduledAt = now()->addHour()->utc()->startOfSecond();
    $matchingWallTime = $scheduledAt->copy()->format('Y-m-d H:i:s');
    $mismatchedWallTime = $scheduledAt->copy()->addMinute()->format('Y-m-d H:i:s');
    $releaseCount = ArticleRelease::query()->count();

    expect(fn () => $releases->prepare($actor, $attempt->fresh(), $revision->id, 'explicit-schedule-intent', $scheduledAt))
        ->toThrow(InvalidArgumentException::class, 'explicit scheduling intent');
    expect(fn () => $releases->prepare($actor, $attempt->fresh(), $revision->id, 'explicit-schedule-intent', $scheduledAt, ['selected_timezone' => 'UTC']))
        ->toThrow(InvalidArgumentException::class, 'explicit scheduling intent');
    expect(fn () => $releases->prepare($actor, $attempt->fresh(), $revision->id, 'explicit-schedule-intent', $scheduledAt, ['scheduled_wall_time' => $matchingWallTime]))
        ->toThrow(InvalidArgumentException::class, 'explicit scheduling intent');
    expect(fn () => $releases->prepare($actor, $attempt->fresh(), $revision->id, 'explicit-schedule-intent', $scheduledAt, ['selected_timezone' => 'UTC', 'scheduled_wall_time' => $mismatchedWallTime]))
        ->toThrow(RuntimeException::class, 'does not match');

    expect(ArticleRelease::query()->count())->toBe($releaseCount)
        ->and($approval->fresh()?->invalidated_at)->toBeNull()
        ->and($article->fresh()?->published_release_id)->toBeNull();
});

test('publish due command delivers scheduled releases with stored actor authorization', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Due command.', 'due-command');
    $revision = $write->save($actor, $article, null, releaseDocument('Due body'), releaseMetadata('Due Body'), 'due-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $schedule = releaseSchedule($releases, now()->addMinute());
    $scheduledAt = $schedule['scheduled_at'];
    $release = $releases->prepare($actor, $attempt, $revision->id, 'due-command', $scheduledAt, $schedule['delivery_intent']);
    $releases->approve($actor, $release, $release->release_hash);

    $this->travelTo($scheduledAt->copy()->addMinute());
    $this->artisan('publishing:publish-due')->assertSuccessful();
    $this->travelBack();

    expect($release->fresh()?->status)->toBe('published')
        ->and($article->fresh()?->published_release_id)->toBe($release->id);
});

test('publish due command leaves live content unchanged when the approving actor is revoked', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);
    $article = $write->capture($actor, 'Revoked due command.', 'revoked-due-command');
    $revision = $write->save($actor, $article, null, releaseDocument('Due body'), releaseMetadata('Revoked Due Body'), 'revoked-due-1');
    $attempt = approveReleasePrerequisites($actor, $advance->develop($actor, $article, $revision->id));
    $schedule = releaseSchedule($releases, now()->addMinute());
    $scheduledAt = $schedule['scheduled_at'];
    $release = $releases->prepare($actor, $attempt, $revision->id, 'revoked-due-command', $scheduledAt, $schedule['delivery_intent']);
    $releases->approve($actor, $release, $release->release_hash);
    $actor->removeRole(PublishingRole::Author->value);

    $this->travelTo($scheduledAt->copy()->addMinute());
    $this->artisan('publishing:publish-due')->assertFailed();
    $this->travelBack();

    expect($release->fresh()?->status)->toBe('scheduled')
        ->and($article->fresh()?->published_release_id)->toBeNull();
});

test('imported releases keep history without fabricated approvals and new work uses a normal attempt', function (): void {
    $actor = releaseAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $article = Article::factory()->create(['author_id' => $actor->id, 'slug' => 'imported-history']);
    $revision = $write->save($actor, $article, null, releaseDocument('Imported body'), releaseMetadata('Imported'), 'import-1', 'import');
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

/**
 * @return array{scheduled_at: CarbonInterface, delivery_intent: array<string, mixed>}
 */
function releaseSchedule(ManageArticleRelease $releases, CarbonInterface $scheduledAt, string $timezone = 'UTC'): array
{
    return $releases->resolveSchedule($scheduledAt->copy()->setTimezone($timezone)->format('Y-m-d H:i:s'), $timezone);
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
function releaseMetadata(string $title): array
{
    return [
        'title' => $title,
        'description' => $title.' description',
        'date' => '2024-01-01',
        'tags' => [],
    ];
}

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
