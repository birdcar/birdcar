<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\Publishing\ApprovalKind;
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

    $freshRelease = $releases->prepare($actor, $attempt, $newRevision->id, 'stale-package');
    $releases->approve($actor, $freshRelease, $freshRelease->release_hash);
    $delivered = $releases->deliver($actor, $freshRelease, null);

    expect($delivered->published_at)->not->toBeNull()
        ->and($article->fresh()?->published_release_id)->toBe($freshRelease->id);
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
    $newAttempt = approveReleasePrerequisites($actor, PublishingAttempt::factory()->create([
        'article_id' => $article->id,
        'input_version' => $revision->id,
    ]));
    $article->forceFill(['current_attempt_id' => $newAttempt->id])->save();
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
    $attempt = $attempt->fresh();
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));

    return $attempt->fresh();
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
