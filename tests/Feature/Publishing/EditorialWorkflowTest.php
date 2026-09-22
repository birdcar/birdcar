<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Role as PublishingRole;
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

test('captured ideas stay idle until deliberate develop creates one allowance backed attempt', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);

    $article = $write->capture($actor, 'Investigate agent publishing.', 'agent-publishing');
    $revision = $write->save($actor, $article, null, editorialDocument('First draft'), ['title' => 'Agent Publishing'], 'draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Publish safely']);
    $duplicateAttempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Publish safely']);

    expect($article->current_attempt_id)->toBeNull()
        ->and($attempt->is($duplicateAttempt))->toBeTrue()
        ->and($attempt->stage)->toBe(EditorialStage::Developing)
        ->and($attempt->allowance_nano_usd)->toBe(5_000_000_000)
        ->and($article->fresh()?->current_attempt_id)->toBe($attempt->id);
});

test('develop after publication creates a fresh working attempt without replacing the live release', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);
    $releases = app(ManageArticleRelease::class);

    $article = $write->capture($actor, 'Published revisions keep going.', 'published-revisions');
    $revision = $write->save($actor, $article, null, editorialDocument('Published body'), ['title' => 'Published'], 'draft-1');
    $publishedAttempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Ship it']);
    $approve->approve($actor, $publishedAttempt, ApprovalKind::Angle, $approve->inputHashFor($publishedAttempt, ApprovalKind::Angle));
    $publishedAttempt = $publishedAttempt->fresh();
    $approve->approve($actor, $publishedAttempt, ApprovalKind::Plan, $approve->inputHashFor($publishedAttempt, ApprovalKind::Plan));
    $publishedAttempt = $publishedAttempt->fresh();
    $release = $releases->prepare($actor, $publishedAttempt, $revision->id, 'published-revisions');
    $releases->approve($actor, $release, $release->release_hash);
    $releases->deliver($actor, $release, null);

    $nextAttempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Develop a follow-up']);
    $duplicateNextAttempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Develop a follow-up']);

    expect($nextAttempt->id)->not->toBe($publishedAttempt->id)
        ->and($duplicateNextAttempt->is($nextAttempt))->toBeTrue()
        ->and($article->fresh()?->published_release_id)->toBe($release->id)
        ->and($article->fresh()?->working_revision_id)->toBe($revision->id);
});

test('human approvals advance gates and context changes invalidate downstream gates', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);

    $article = $write->capture($actor, 'Approval gates.', 'approval-gates');
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), ['title' => 'Approval Gates'], 'draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Gate automatic work']);

    $angleApproval = $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $advance->rethink($actor, $attempt, plan: ['outline' => ['intro', 'body']]);
    $planApproval = $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $advance->rethink($actor, $attempt, brief: ['goal' => 'Changed premise']);

    expect($angleApproval->fresh()?->invalidated_at)->not->toBeNull()
        ->and($planApproval->fresh()?->invalidated_at)->not->toBeNull()
        ->and($attempt->fresh()?->stage)->toBe(EditorialStage::Developing);
});

test('rethink resets invalidated gates so brief angle and plan changes can be re-approved in order', function (): void {
    $actor = editorialAuthor();
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);

    $briefAttempt = approvedEditorialAttempt($actor, 'Brief rethink.', 'brief-rethink');
    $briefAttempt = $advance->rethink($actor, $briefAttempt, brief: ['goal' => 'Changed premise']);

    expect($briefAttempt->stage)->toBe(EditorialStage::Developing);
    expect(fn () => $approve->approve($actor, $briefAttempt, ApprovalKind::Plan, $approve->inputHashFor($briefAttempt, ApprovalKind::Plan)))
        ->toThrow(RuntimeException::class, 'Plan approval requires an active angle approval');
    $approve->approve($actor, $briefAttempt, ApprovalKind::Angle, $approve->inputHashFor($briefAttempt, ApprovalKind::Angle));
    $briefAttempt = $briefAttempt->fresh();
    $approve->approve($actor, $briefAttempt, ApprovalKind::Plan, $approve->inputHashFor($briefAttempt, ApprovalKind::Plan));

    expect($briefAttempt->fresh()?->stage)->toBe(EditorialStage::InReview);

    $angleAttempt = approvedEditorialAttempt($actor, 'Angle rethink.', 'angle-rethink');
    $angleAttempt = $advance->rethink($actor, $angleAttempt, angle: ['thesis' => 'Sharper angle']);

    expect($angleAttempt->stage)->toBe(EditorialStage::Developing);
    expect(fn () => $approve->approve($actor, $angleAttempt, ApprovalKind::Plan, $approve->inputHashFor($angleAttempt, ApprovalKind::Plan)))
        ->toThrow(RuntimeException::class, 'Plan approval requires an active angle approval');
    $approve->approve($actor, $angleAttempt, ApprovalKind::Angle, $approve->inputHashFor($angleAttempt, ApprovalKind::Angle));
    $angleAttempt = $angleAttempt->fresh();
    $approve->approve($actor, $angleAttempt, ApprovalKind::Plan, $approve->inputHashFor($angleAttempt, ApprovalKind::Plan));

    expect($angleAttempt->fresh()?->stage)->toBe(EditorialStage::InReview);

    $planAttempt = approvedEditorialAttempt($actor, 'Plan rethink.', 'plan-rethink');
    $planAttempt = $advance->rethink($actor, $planAttempt, plan: ['outline' => ['new opening']]);

    expect($planAttempt->stage)->toBe(EditorialStage::Drafting);
    expect(fn () => $approve->approve($actor, $planAttempt, ApprovalKind::Release, 'not-an-approved-package'))
        ->toThrow(RuntimeException::class, 'managed through release packages');
    $approve->approve($actor, $planAttempt, ApprovalKind::Plan, $approve->inputHashFor($planAttempt, ApprovalKind::Plan));

    expect($planAttempt->fresh()?->stage)->toBe(EditorialStage::InReview);
});

test('stale saves and conflicting mutation keys do not change the current manuscript', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Concurrent edits.', 'concurrent-edits');
    $firstRevision = $write->save($actor, $article, null, editorialDocument('First'), ['title' => 'First'], 'edit-1');
    $sameMutation = $write->save($actor, $article, $firstRevision->id, editorialDocument('Second'), ['title' => 'Second'], 'edit-2');

    expect(fn () => $write->save($actor, $article, $firstRevision->id, editorialDocument('Stale'), ['title' => 'Stale'], 'edit-3'))
        ->toThrow(RuntimeException::class, 'changed since this edit began');
    expect(fn () => $write->save($actor, $article, $sameMutation->id, editorialDocument('Different'), ['title' => 'Different'], 'edit-2'))
        ->toThrow(RuntimeException::class, 'mutation key');

    expect($write->save($actor, $article, $firstRevision->id, editorialDocument('Second'), ['title' => 'Second'], 'edit-2')->is($sameMutation))->toBeTrue()
        ->and($write->save($actor, $article, $sameMutation->id, editorialDocument('Second'), ['title' => 'Second'], 'edit-2')->is($sameMutation))->toBeTrue()
        ->and(ArticleRevision::query()->where('article_id', $article->id)->count())->toBe(2)
        ->and($article->fresh()?->working_revision_id)->toBe($sameMutation->id);
});

test('approvals cannot skip angle plan or current stage gates', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);

    $article = $write->capture($actor, 'Denied gates.', 'denied-gates');
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), ['title' => 'Denied Gates'], 'draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id);

    expect(fn () => $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan)))
        ->toThrow(RuntimeException::class, 'Plan approval requires an active angle approval');

    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh();

    expect(fn () => $approve->approve($actor, $attempt, ApprovalKind::Release, 'not-an-approved-package', $revision->id))
        ->toThrow(RuntimeException::class, 'managed through release packages');

    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $attempt = $attempt->fresh();

    expect(fn () => $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle)))
        ->toThrow(RuntimeException::class, 'Angle approval requires a developing attempt');
});

test('pause resume park and abandon retain the attempt identity without creating allowances', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $article = $write->capture($actor, 'Interruptions.', 'interruptions');
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), ['title' => 'Interruptions'], 'draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id);

    $paused = $advance->pause($actor, $attempt, 'Waiting on source material.');
    $resumed = $advance->resume($actor, $paused);
    $parked = $advance->park($actor, $resumed, 'Not urgent.');
    $abandoned = $advance->abandon($actor, $parked, 'Killed by editor.');

    expect($paused->paused_at)->not->toBeNull()
        ->and($resumed->paused_at)->toBeNull()
        ->and($parked->parked_at)->not->toBeNull()
        ->and($abandoned->stage)->toBe(EditorialStage::Abandoned)
        ->and($abandoned->allowance_nano_usd)->toBe(5_000_000_000);
});

test('interruption transitions reject non current and terminal attempts', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);

    $publishedAttempt = approvedEditorialAttempt($actor, 'Published terminal.', 'published-terminal');
    $article = $publishedAttempt->article()->firstOrFail();
    $revision = $article->workingRevision()->firstOrFail();
    $release = $releases->prepare($actor, $publishedAttempt, $revision->id, 'published-terminal');
    $releases->approve($actor, $release, $release->release_hash);
    $releases->deliver($actor, $release, null);

    expect(fn () => $advance->pause($actor, $publishedAttempt, 'Too late.'))
        ->toThrow(RuntimeException::class, 'Terminal publishing attempts cannot be changed');

    $abandonedArticle = $write->capture($actor, 'Abandoned terminal.', 'abandoned-terminal');
    $abandonedRevision = $write->save($actor, $abandonedArticle, null, editorialDocument('Draft'), ['title' => 'Abandoned'], 'abandoned-draft-1');
    $abandonedAttempt = $advance->develop($actor, $abandonedArticle, $abandonedRevision->id);
    $advance->abandon($actor, $abandonedAttempt, 'Killed.');

    expect(fn () => $advance->resume($actor, $abandonedAttempt))
        ->toThrow(RuntimeException::class, 'Terminal publishing attempts cannot be changed');

    $currentArticle = $write->capture($actor, 'Current guard.', 'current-guard');
    $currentRevision = $write->save($actor, $currentArticle, null, editorialDocument('Draft'), ['title' => 'Current Guard'], 'current-guard-draft-1');
    $oldAttempt = $advance->develop($actor, $currentArticle, $currentRevision->id);
    $replacementAttempt = PublishingAttempt::factory()->create([
        'article_id' => $currentArticle->id,
        'input_version' => $currentRevision->id,
    ]);
    $currentArticle->forceFill(['current_attempt_id' => $replacementAttempt->id])->save();

    expect(fn () => $advance->park($actor, $oldAttempt, 'Not current.'))
        ->toThrow(RuntimeException::class, 'Only the current publishing attempt can be changed');
});

test('develop never reuses attempts marked with the abandoned stage', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $article = $write->capture($actor, 'Stage abandoned.', 'stage-abandoned');
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), ['title' => 'Stage Abandoned'], 'stage-abandoned-draft-1');
    $abandonedAttempt = $advance->develop($actor, $article, $revision->id);
    $abandonedAttempt->forceFill([
        'stage' => EditorialStage::Abandoned,
        'abandoned_at' => null,
    ])->save();

    $newAttempt = $advance->develop($actor, $article, $revision->id);

    expect($newAttempt->id)->not->toBe($abandonedAttempt->id)
        ->and($article->fresh()?->current_attempt_id)->toBe($newAttempt->id);
});

function approvedEditorialAttempt(User $actor, string $idea, string $slug): PublishingAttempt
{
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);

    $article = $write->capture($actor, $idea, $slug);
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), ['title' => $idea], $slug.'-draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id, ['goal' => $idea]);

    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh();
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));

    return $attempt->fresh();
}

function editorialAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function editorialDocument(string $text): array
{
    return [
        'version' => 1,
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'text' => $text],
        ],
    ];
}
