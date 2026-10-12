<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\MarkKitPiecePosted;
use App\Actions\Publishing\ReadPublishedWriting;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\SpawnDistributionKit;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\Publishing\PieceKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\EditorialOutput;
use App\Services\Publishing\KitDocument;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

const DISTRIBUTION_KIT_TARGET_QUERY = 'do I need another admin hire';

const DISTRIBUTION_KIT_SLUG = 'another-admin-hire-will-not-fix-it';

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function distributionKitAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $user;
}

/**
 * A release-ready flagship body: one paragraph and the closing Walkthrough invitation.
 *
 * @return array<string, mixed>
 */
function distributionKitDocument(string $text): array
{
    return ['version' => 1, 'type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'attrs' => ['id' => 'blk_000000000000d0a1'], 'content' => [['type' => 'text', 'text' => $text]]],
        ['type' => 'paragraph', 'attrs' => ['id' => 'blk_000000000000d0a2'], 'content' => [
            ['type' => 'text', 'text' => 'If this sounds like your week, bring it to '],
            ['type' => 'text', 'text' => 'a Walkthrough', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/walkthrough']]]],
            ['type' => 'text', 'text' => '.'],
        ]],
    ]];
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function distributionKitMetadata(array $extra = []): array
{
    return [
        'title' => 'Another admin hire will not fix it',
        'seo_title' => 'Do you need another admin hire? What to check first',
        'description' => 'When the team is full and the work keeps coming back to you, a new hire often adds a person to the same chores. Here is what to check before you post the job.',
        'date' => '2026-10-01',
        'tags' => [],
        ...$extra,
    ];
}

/** @param array<string, mixed> $response */
function distributionKitCompletedActivity(User $actor, PublishingAttempt $attempt, EditorialActivityKind $kind, ?ArticleRevision $revision, array $response): EditorialActivity
{
    $approve = app(ApprovePublishingStage::class);
    $approval = $kind === EditorialActivityKind::ResearchChallenge ? ApprovalKind::Angle : ApprovalKind::Plan;

    return EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => $kind,
        'status' => EditorialActivityStatus::Completed,
        'stage' => $kind === EditorialActivityKind::ResearchChallenge ? 'drafting' : 'in_review',
        'input_version' => $attempt->input_version,
        'revision_id' => $revision?->id,
        'revision_hash' => $revision?->content_hash,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => $kind->value.'-'.Str::random(8),
        'idempotency_key' => $kind->value.'-'.Str::random(16),
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', Str::random(16)),
        'input' => ['approval_hashes' => [$approval->value => $approve->inputHashFor($attempt, $approval)]],
        'model_snapshot' => [],
        'response' => $response,
        'completed_at' => now(),
    ]);
}

/**
 * A flagship post through plan approval and a complete review, with one grounded research claim, built while agents are
 * paused (the default) so nothing runs on its own.
 */
function distributionKitFlagship(User $actor): PublishingAttempt
{
    $write = app(WriteArticle::class);
    $approve = app(ApprovePublishingStage::class);
    $article = $write->captureIdea($actor, 'Why another admin hire will not fix the Friday report');
    $revision = $write->save($actor, $article, null, distributionKitDocument('A new hire often inherits the same chores.'), distributionKitMetadata(), 'kit-flagship-'.Str::random(8));
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id, ['goal' => 'Show owners what to check first', 'post_type' => 'essay', 'target_query' => DISTRIBUTION_KIT_TARGET_QUERY]);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');

    $research = distributionKitCompletedActivity($actor, $attempt, EditorialActivityKind::ResearchChallenge, null, ['claims' => [
        ['statement' => 'A new hire often inherits the same chores.', 'supporting_source_ids' => [], 'supporting_source_refs' => ['hiring'], 'supporting_quotations' => [['source_ref' => 'hiring', 'quote' => 'Teams add people to the same chores.']]],
    ], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []]);
    EvidenceSource::create(['article_id' => $attempt->article_id, 'attempt_id' => $attempt->id, 'activity_id' => $research->id, 'source_type' => 'public', 'url' => 'https://example.com/hiring', 'title' => 'Hiring', 'extracted_text' => 'Teams add people to the same chores.', 'origin_metadata' => ['local_id' => 'hiring', 'url' => 'https://example.com/hiring'], 'publication_permission' => true]);

    $attempt = app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, plan: ['outline' => ['What to check first'], 'visualPlan' => ['The Friday report']]);
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');

    foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer] as $lens) {
        distributionKitCompletedActivity($actor, $attempt, $lens, $revision, ['findings' => []]);
    }
    distributionKitCompletedActivity($actor, $attempt, EditorialActivityKind::Reconciliation, $revision, ['groups' => [], 'conflicts' => []]);

    return $attempt;
}

/** Prepares and approves a release of the flagship's working revision, which spawns its distribution kit. */
function distributionKitApproveRelease(User $actor, PublishingAttempt $attempt): ArticleRelease
{
    $releases = app(ManageArticleRelease::class);
    $revisionId = (int) Article::query()->whereKey($attempt->article_id)->value('working_revision_id');
    $release = $releases->prepare($actor, $attempt->fresh() ?? $attempt, $revisionId, DISTRIBUTION_KIT_SLUG);
    $releases->approve($actor, $release, $release->release_hash);

    return $release->fresh() ?? $release;
}

/** @return Collection<string, Article> the flagship's kit pieces keyed by kind */
function distributionKitPieces(int $flagshipId): Collection
{
    return Article::query()->where('parent_article_id', $flagshipId)->orderBy('id')->get()->keyBy(fn (Article $piece): string => $piece->kind->value);
}

function distributionKitAttempt(Article $piece): PublishingAttempt
{
    return PublishingAttempt::query()->findOrFail($piece->fresh()?->current_attempt_id);
}

/** @return Collection<int, EditorialActivity> */
function distributionKitActivities(Article $piece, EditorialActivityKind $kind): Collection
{
    return EditorialActivity::query()->where('article_id', $piece->id)->where('kind', $kind->value)->orderBy('id')->get();
}

/**
 * A clean draft of each kit kind, built from the frozen flagship URL and target phrase.
 *
 * @param  array<string, mixed>  $prompt
 * @return array<string, mixed>
 */
function distributionKitCleanDraft(array $prompt): array
{
    $url = (string) data_get($prompt, 'input.flagship.canonical_url');

    return match (data_get($prompt, 'input.piece_kind')) {
        'linkedin_post' => [
            'text' => 'Do I need another admin hire? It is the question I hear most from owners whose team is full. My answer is to check the chores first, because a new hire often inherits the same chores.',
            'first_comment' => 'The full post: '.$url,
        ],
        'bluesky_thread' => ['posts' => [
            '1/3 A new hire often inherits the same chores.',
            '2/3 Check the chores first, before you post the job.',
            '3/3 The full post: '.$url,
        ]],
        default => [
            'subject' => 'Another admin hire will not fix it',
            'preview' => 'Check the chores before you post the job.',
            'body' => 'A new hire often inherits the same chores. Check the chores first, before you post the job. Read the post: '.$url,
        ],
    };
}

/**
 * Unpauses agents and routes each faked provider answer by the activity kind in the user message.
 *
 * @param  array<string, array<string, mixed>|Closure(array<string, mixed>): array<string, mixed>>  $responses
 */
function distributionKitFake(array $responses): void
{
    setPublishingAgentsPaused(false);
    config()->set('ai.providers.openrouter.key', 'test-key');
    Queue::fake([RunEditorialActivity::class]);
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => function (Request $request) use ($responses) {
        $prompt = json_decode((string) data_get($request->data(), 'messages.1.content'), true, flags: JSON_THROW_ON_ERROR);
        $prompt = is_array($prompt) ? $prompt : [];
        $kind = (string) ($prompt['kind'] ?? '');
        $payload = $responses[$kind] ?? throw new RuntimeException('Unexpected kit kind '.$kind);
        $payload = $payload instanceof Closure ? $payload($prompt) : $payload;

        return Http::response(['id' => 'gen-kit-'.Str::random(6), 'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode($payload, JSON_THROW_ON_ERROR)]]]]);
    }]);
}

function distributionKitRun(EditorialActivity $activity): EditorialActivity
{
    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialOutput::class), app(WriteArticle::class));

    return $activity->fresh() ?? throw new RuntimeException('The activity disappeared.');
}

/** Drafts and reviews a kit piece with clean answers, then approves it as written. */
function distributionKitApprovedPiece(User $actor, Article $piece): Article
{
    distributionKitRun(distributionKitActivities($piece, EditorialActivityKind::DraftKit)->last() ?? throw new RuntimeException('No kit draft.'));
    distributionKitRun(distributionKitActivities($piece, EditorialActivityKind::ReviewKit)->sole());
    $approve = app(ApprovePublishingStage::class);
    $attempt = distributionKitAttempt($piece);
    $approve->approve($actor, $attempt, ApprovalKind::Kit, $approve->inputHashFor($attempt, ApprovalKind::Kit));

    return $piece->fresh() ?? $piece;
}

test('approving a flagship release spawns its three kit pieces with drafts queued from that release', function () {
    $actor = distributionKitAuthor();
    $attempt = distributionKitFlagship($actor);

    $release = distributionKitApproveRelease($actor, $attempt);

    $pieces = distributionKitPieces($attempt->article_id);
    expect($pieces->keys()->all())->toBe(['linkedin_post', 'bluesky_thread', 'email_blurb']);
    foreach ($pieces as $piece) {
        $kitAttempt = distributionKitAttempt($piece);
        $draft = distributionKitActivities($piece, EditorialActivityKind::DraftKit)->sole();

        expect($piece->content_idea_id)->toBe(Article::query()->findOrFail($attempt->article_id)->content_idea_id)
            ->and($piece->author_id)->toBe($actor->id)
            ->and($piece->working_revision_id)->toBeNull()
            ->and($kitAttempt->stage)->toBe(EditorialStage::InReview)
            ->and($kitAttempt->brief)->toMatchArray([
                'flagship_article_id' => $attempt->article_id,
                'flagship_release_id' => $release->id,
                'canonical_url' => 'https://birdcar.test/writing/'.DISTRIBUTION_KIT_SLUG.'/',
                'target_query' => DISTRIBUTION_KIT_TARGET_QUERY,
            ])
            ->and($draft->status)->toBe(EditorialActivityStatus::Pending)
            ->and($draft->input['piece_kind'] ?? null)->toBe($piece->kind->value)
            ->and($draft->input['flagship']['release_id'] ?? null)->toBe($release->id)
            ->and($draft->input['flagship']['claim_manifest'] ?? null)->toBe($release->payload['claim_manifest'])
            ->and($draft->input['flagship']['text'] ?? '')->toContain('A new hire often inherits the same chores.')
            ->and($draft->input['flagship']['title'] ?? null)->toBe('Another admin hire will not fix it');
    }
});

test('paused agents spawn the kit with pending drafts and send nothing', function () {
    Http::preventStrayRequests();
    Http::fake();
    $actor = distributionKitAuthor();

    distributionKitApproveRelease($actor, distributionKitFlagship($actor));

    expect(Article::query()->where('kind', '!=', PieceKind::BlogPost->value)->count())->toBe(3)
        ->and(EditorialActivity::query()->where('kind', EditorialActivityKind::DraftKit->value)->pluck('status')->all())->toBe(array_fill(0, 3, EditorialActivityStatus::Pending));
    Http::assertNothingSent();
});

test('spawning again for the same release returns the same pieces, and a second release restarts only unapproved ones', function () {
    $actor = distributionKitAuthor();
    $attempt = distributionKitFlagship($actor);
    $first = distributionKitApproveRelease($actor, $attempt);
    $pieces = distributionKitPieces($attempt->article_id);

    $again = app(SpawnDistributionKit::class)->spawn($actor, Article::query()->findOrFail($attempt->article_id), $first);

    expect(array_map(fn (Article $piece): int => $piece->id, $again))->toBe($pieces->pluck('id')->values()->all())
        ->and(EditorialActivity::query()->where('kind', EditorialActivityKind::DraftKit->value)->count())->toBe(3);

    distributionKitFake(['draft_kit' => fn (array $prompt): array => distributionKitCleanDraft($prompt), 'review_kit' => ['findings' => []]]);
    $linkedin = distributionKitApprovedPiece($actor, $pieces['linkedin_post']);

    // A metadata-only edit keeps the flagship's review lineage, so it can be released again.
    $flagship = Article::query()->findOrFail($attempt->article_id);
    $working = $flagship->workingRevision()->firstOrFail();
    app(WriteArticle::class)->save($actor, $flagship, $working->id, $working->document ?? [], distributionKitMetadata(['tags' => ['hiring']]), 'kit-flagship-tags');
    $second = distributionKitApproveRelease($actor, $attempt);

    expect(distributionKitPieces($attempt->article_id)->pluck('id')->values()->all())->toBe($pieces->pluck('id')->values()->all())
        ->and(distributionKitAttempt($linkedin)->stage)->toBe(EditorialStage::Approved)
        ->and(distributionKitAttempt($linkedin)->brief['flagship_release_id'] ?? null)->toBe($first->id)
        ->and(distributionKitActivities($linkedin, EditorialActivityKind::DraftKit))->toHaveCount(1);

    foreach (['bluesky_thread', 'email_blurb'] as $kind) {
        $drafts = distributionKitActivities($pieces[$kind], EditorialActivityKind::DraftKit);
        expect($drafts)->toHaveCount(2)
            ->and($drafts->first()?->status)->toBe(EditorialActivityStatus::Stale)
            ->and($drafts->last()?->input['flagship']['release_id'] ?? null)->toBe($second->id)
            ->and(distributionKitAttempt($pieces[$kind])->review_cycle)->toBe(2)
            ->and(distributionKitAttempt($pieces[$kind])->brief['flagship_release_id'] ?? null)->toBe($second->id);
    }

    Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => $linkedin])
        ->assertSee('The flagship changed since approval');
});

test('a clean kit draft lands as the piece text and goes straight to kit review', function () {
    $actor = distributionKitAuthor();
    $attempt = distributionKitFlagship($actor);
    distributionKitApproveRelease($actor, $attempt);
    $email = distributionKitPieces($attempt->article_id)['email_blurb'];
    distributionKitFake(['draft_kit' => fn (array $prompt): array => distributionKitCleanDraft($prompt)]);

    $draft = distributionKitRun(distributionKitActivities($email, EditorialActivityKind::DraftKit)->sole());

    $revision = ArticleRevision::query()->findOrFail($email->fresh()?->working_revision_id);
    expect($draft->status)->toBe(EditorialActivityStatus::Completed)
        ->and($revision->origin)->toBe('agent-initial')
        ->and(KitDocument::fields(PieceKind::EmailBlurb, $revision->document ?? []))->toBe([
            'subject' => 'Another admin hire will not fix it',
            'preview' => 'Check the chores before you post the job.',
            'body' => 'A new hire often inherits the same chores. Check the chores first, before you post the job. Read the post: https://birdcar.test/writing/'.DISTRIBUTION_KIT_SLUG.'/',
        ])
        ->and(distributionKitActivities($email, EditorialActivityKind::LintRepair))->toBeEmpty()
        ->and(distributionKitActivities($email, EditorialActivityKind::ReviewKit)->sole()->revision_id)->toBe($revision->id)
        ->and(EditorialFinding::query()->where('article_id', $email->id)->where('lens', 'lint')->exists())->toBeFalse();
});

test('a kit draft with the wrong fields fails validation so it can be retried', function () {
    $actor = distributionKitAuthor();
    $attempt = distributionKitFlagship($actor);
    distributionKitApproveRelease($actor, $attempt);
    $linkedin = distributionKitPieces($attempt->article_id)['linkedin_post'];
    distributionKitFake(['draft_kit' => ['posts' => ['1/1 The wrong shape for LinkedIn.']]]);

    $draft = distributionKitRun(distributionKitActivities($linkedin, EditorialActivityKind::DraftKit)->sole());

    expect($draft->status)->toBe(EditorialActivityStatus::Failed)
        ->and($draft->error_reason)->toContain('A LinkedIn post draft must return exactly text, first_comment')
        ->and($linkedin->fresh()?->working_revision_id)->toBeNull();
});

test('a Bluesky post over 300 characters is repaired once, then stays as a blocking lint finding before kit review', function () {
    $actor = distributionKitAuthor();
    $attempt = distributionKitFlagship($actor);
    distributionKitApproveRelease($actor, $attempt);
    $bluesky = distributionKitPieces($attempt->article_id)['bluesky_thread'];
    $long = '2/3 '.trim(str_repeat('The report waits for Friday. ', 11));
    distributionKitFake([
        'draft_kit' => fn (array $prompt): array => ['posts' => ['1/3 A new hire often inherits the same chores.', $long, '3/3 The full post: '.data_get($prompt, 'input.flagship.canonical_url')]],
        'lint_repair' => ['patches' => []],
    ]);

    distributionKitRun(distributionKitActivities($bluesky, EditorialActivityKind::DraftKit)->sole());
    $repair = distributionKitActivities($bluesky, EditorialActivityKind::LintRepair)->sole();

    expect(mb_strlen($long))->toBeGreaterThan(300)
        ->and(array_column($repair->input['hits'] ?? [], 'rule_id'))->toBe(['bluesky-post-length'])
        ->and(distributionKitActivities($bluesky, EditorialActivityKind::ReviewKit))->toBeEmpty();

    distributionKitRun($repair);

    $finding = EditorialFinding::query()->where('article_id', $bluesky->id)->where('lens', 'lint')->whereNull('stale_at')->sole();
    expect($repair->fresh()?->status)->toBe(EditorialActivityStatus::Completed)
        ->and($finding->kind)->toBe('lint:bluesky-post-length')
        ->and($finding->severity)->toBe('blocking')
        ->and($finding->block_id)->toBe(KitDocument::blockId(PieceKind::BlueskyThread, 'post:2'))
        ->and($finding->statement)->toBe('Post 2 is '.mb_strlen($long).' characters with its number; it must be at most 300. Shorten it.')
        ->and(distributionKitActivities($bluesky, EditorialActivityKind::ReviewKit))->toHaveCount(1)
        ->and(app(ApprovePublishingStage::class)->kitApprovalProblems(distributionKitAttempt($bluesky)))->toContain('1 voice-rule hit needs a fix or an override with a reason.');
});

test('a claim outside the manifest blocks kit approval until the owner decides it with a reason', function () {
    $actor = distributionKitAuthor();
    $attempt = distributionKitFlagship($actor);
    distributionKitApproveRelease($actor, $attempt);
    $linkedin = distributionKitPieces($attempt->article_id)['linkedin_post'];
    $textBlock = KitDocument::blockId(PieceKind::LinkedinPost, 'text');
    distributionKitFake([
        'draft_kit' => fn (array $prompt): array => distributionKitCleanDraft($prompt),
        'review_kit' => ['findings' => [
            ['statement' => 'The piece says owners lose ten hours a week to reports.', 'kind' => 'claim_outside_manifest', 'severity' => 'advisory', 'block_id' => $textBlock, 'rationale' => 'No manifest statement covers a ten-hour figure.', 'supporting_source_ids' => [], 'supporting_quotations' => []],
            ['statement' => 'The opening reads a little stiff.', 'kind' => 'register', 'severity' => 'blocking', 'block_id' => $textBlock, 'supporting_source_ids' => [], 'supporting_quotations' => []],
        ]],
    ]);
    distributionKitRun(distributionKitActivities($linkedin, EditorialActivityKind::DraftKit)->sole());
    $review = distributionKitRun(distributionKitActivities($linkedin, EditorialActivityKind::ReviewKit)->sole());
    $approve = app(ApprovePublishingStage::class);
    $kitAttempt = distributionKitAttempt($linkedin);
    $findings = EditorialFinding::query()->where('activity_id', $review->id)->orderBy('id')->get();

    expect($review->status)->toBe(EditorialActivityStatus::Completed)
        ->and($findings->pluck('severity')->all())->toBe(['blocking', 'advisory'])
        ->and($findings->pluck('lens')->unique()->all())->toBe(['review_kit']);
    expect(fn () => $approve->approve($actor, $kitAttempt, ApprovalKind::Kit, $approve->inputHashFor($kitAttempt, ApprovalKind::Kit)))
        ->toThrow(RuntimeException::class, 'Decide every blocking kit review finding before approving.');
    expect(fn () => $approve->decideKitFinding($actor, $findings[0], 'rejected', '  '))
        ->toThrow(ValidationException::class);

    $approve->decideKitFinding($actor, $findings[0], 'rejected', 'The post makes this claim in its second section.');
    $approval = $approve->approve($actor, $kitAttempt, ApprovalKind::Kit, $approve->inputHashFor($kitAttempt, ApprovalKind::Kit));

    expect($approval->revision_id)->toBe($linkedin->fresh()?->working_revision_id)
        ->and(distributionKitAttempt($linkedin)->stage)->toBe(EditorialStage::Approved);
});

test('a kit review that cites evidence it was never given fails instead of storing findings', function () {
    $actor = distributionKitAuthor();
    $attempt = distributionKitFlagship($actor);
    distributionKitApproveRelease($actor, $attempt);
    $email = distributionKitPieces($attempt->article_id)['email_blurb'];
    distributionKitFake([
        'draft_kit' => fn (array $prompt): array => distributionKitCleanDraft($prompt),
        'review_kit' => ['findings' => [['statement' => 'Unsupported.', 'kind' => 'claim_outside_manifest', 'supporting_source_ids' => [1], 'supporting_quotations' => []]]],
    ]);
    distributionKitRun(distributionKitActivities($email, EditorialActivityKind::DraftKit)->sole());

    $review = distributionKitRun(distributionKitActivities($email, EditorialActivityKind::ReviewKit)->sole());

    expect($review->status)->toBe(EditorialActivityStatus::Failed)
        ->and($review->error_reason)->toBe('Agent output referenced an unknown evidence source.')
        ->and(EditorialFinding::query()->where('activity_id', $review->id)->exists())->toBeFalse();
});

test('marking a piece posted waits for the flagship to be live, then records it, even after a new flagship attempt', function () {
    $actor = distributionKitAuthor();
    $attempt = distributionKitFlagship($actor);
    $release = distributionKitApproveRelease($actor, $attempt);
    distributionKitFake(['draft_kit' => fn (array $prompt): array => distributionKitCleanDraft($prompt), 'review_kit' => ['findings' => []]]);
    $bluesky = distributionKitApprovedPiece($actor, distributionKitPieces($attempt->article_id)['bluesky_thread']);
    $posts = app(MarkKitPiecePosted::class);

    expect(fn () => $posts->mark($actor, $bluesky))
        ->toThrow(RuntimeException::class, 'The flagship post is not published yet.');

    app(ManageArticleRelease::class)->deliver($actor, $release, null);
    $flagship = Article::query()->findOrFail($attempt->article_id);
    app(AdvancePublishingAttempt::class)->develop($actor, $flagship, $flagship->working_revision_id);

    expect(fn () => $posts->mark($actor, $bluesky, 'http://bsky.app/profile/die.fyi/post/1'))->toThrow(ValidationException::class)
        ->and(fn () => $posts->mark($actor, $bluesky, 'javascript:alert(1)'))->toThrow(ValidationException::class);

    $posts->mark($actor, $bluesky, ' https://bsky.app/profile/die.fyi/post/1 ');

    expect($bluesky->fresh()?->posted_at)->not->toBeNull()
        ->and($bluesky->fresh()?->posted_url)->toBe('https://bsky.app/profile/die.fyi/post/1')
        ->and(distributionKitAttempt($bluesky)->stage)->toBe(EditorialStage::Published)
        ->and(fn () => $posts->mark($actor, $bluesky))->toThrow(RuntimeException::class, 'already marked posted');
});

test('editing an approved piece revokes its approval, so it is not ready to post until approved again', function () {
    $actor = distributionKitAuthor();
    $attempt = distributionKitFlagship($actor);
    distributionKitApproveRelease($actor, $attempt);
    distributionKitFake(['draft_kit' => fn (array $prompt): array => distributionKitCleanDraft($prompt), 'review_kit' => ['findings' => []]]);
    $email = distributionKitApprovedPiece($actor, distributionKitPieces($attempt->article_id)['email_blurb']);
    $approval = EditorialApproval::query()->where('attempt_id', $email->current_attempt_id)->where('kind', ApprovalKind::Kit->value)->sole();

    Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => $email])
        ->set('kitFields.subject', 'Check the chores before you hire')
        ->call('saveKitFields')
        ->assertSet('saveError', null);

    expect($approval->fresh()?->invalidated_at)->not->toBeNull()
        ->and(distributionKitAttempt($email)->stage)->toBe(EditorialStage::InReview)
        ->and(app(MarkKitPiecePosted::class)->problem($email->fresh() ?? $email))->toBe('Approve this piece as written before marking it posted.')
        ->and(KitDocument::fields(PieceKind::EmailBlurb, ArticleRevision::query()->findOrFail($email->fresh()?->working_revision_id)->document ?? [])['subject'] ?? null)->toBe('Check the chores before you hire');
});

test('the kit panel shows each field to copy out, the approval it needs, and no develop or plan tabs', function () {
    $actor = distributionKitAuthor();
    $attempt = distributionKitFlagship($actor);
    distributionKitApproveRelease($actor, $attempt);
    $linkedin = distributionKitPieces($attempt->article_id)['linkedin_post'];
    distributionKitFake(['draft_kit' => fn (array $prompt): array => distributionKitCleanDraft($prompt)]);
    distributionKitRun(distributionKitActivities($linkedin, EditorialActivityKind::DraftKit)->sole());

    Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => $linkedin->fresh()])
        ->assertSee('data-kit-panel="linkedin_post"', false)
        ->assertSee('data-copy-field="text"', false)
        ->assertSee('data-copy-field="first_comment"', false)
        ->assertSee('Do I need another admin hire? It is the question I hear most from owners whose team is full.')
        ->assertSee('The full post: https://birdcar.test/writing/'.DISTRIBUTION_KIT_SLUG.'/')
        ->assertSee('Kit approval needs a completed kit review.')
        ->assertSee('Cut from')
        ->assertDontSee('data-session-tab="develop"', false)
        ->assertDontSee('New post from this idea');
});

test('the flagship workspace lists its kit and offers to create it when spawning did not run', function () {
    $actor = distributionKitAuthor();
    $attempt = distributionKitFlagship($actor);
    $release = distributionKitApproveRelease($actor, $attempt);
    $flagship = Article::query()->findOrFail($attempt->article_id);

    Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => $flagship])
        ->assertSee('data-distribution-kit', false)
        ->assertSee('Bluesky thread')
        ->assertDontSee('Create distribution kit');

    distributionKitPieces($flagship->id)->each(function (Article $piece): void {
        EditorialActivity::query()->where('article_id', $piece->id)->delete();
        $attemptId = $piece->current_attempt_id;
        $piece->forceFill(['current_attempt_id' => null])->save();
        PublishingAttempt::query()->whereKey($attemptId)->delete();
        $piece->delete();
    });

    Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => $flagship])
        ->assertSee('Create distribution kit')
        ->call('createDistributionKit')
        ->assertSet('saveError', null);

    expect(distributionKitPieces($flagship->id)->keys()->all())->toBe(['linkedin_post', 'bluesky_thread', 'email_blurb'])
        ->and(distributionKitAttempt(distributionKitPieces($flagship->id)['email_blurb'])->brief['flagship_release_id'] ?? null)->toBe($release->id);
});

test('a restart never replaces text the owner edited; the new draft waits until the owner uses it', function () {
    $actor = distributionKitAuthor();
    $attempt = distributionKitFlagship($actor);
    distributionKitApproveRelease($actor, $attempt);
    $email = distributionKitPieces($attempt->article_id)['email_blurb'];
    distributionKitFake(['draft_kit' => fn (array $prompt): array => distributionKitCleanDraft($prompt), 'review_kit' => ['findings' => []]]);
    distributionKitRun(distributionKitActivities($email, EditorialActivityKind::DraftKit)->sole());
    $drafted = ArticleRevision::query()->findOrFail($email->fresh()?->working_revision_id);
    $owned = app(WriteArticle::class)->save($actor, $email->fresh() ?? $email, $drafted->id, KitDocument::document(PieceKind::EmailBlurb, [
        ...KitDocument::fields(PieceKind::EmailBlurb, $drafted->document ?? []),
        'subject' => 'My own subject line',
    ]), [], 'kit-owner-edit');

    $flagship = Article::query()->findOrFail($attempt->article_id);
    $working = $flagship->workingRevision()->firstOrFail();
    app(WriteArticle::class)->save($actor, $flagship, $working->id, $working->document ?? [], distributionKitMetadata(['tags' => ['hiring']]), 'kit-flagship-tags');
    distributionKitApproveRelease($actor, $attempt);
    $restart = distributionKitRun(distributionKitActivities($email, EditorialActivityKind::DraftKit)->last() ?? throw new RuntimeException('No restart draft.'));

    expect($restart->status)->toBe(EditorialActivityStatus::Completed)
        ->and($restart->proposal['applied_revision_id'] ?? null)->toBeNull()
        ->and($email->fresh()?->working_revision_id)->toBe($owned->id);

    Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => $email->fresh()])
        ->assertSee('A new draft is waiting')
        ->call('useKitDraft', $restart->id)
        ->assertSet('saveError', null);

    $used = ArticleRevision::query()->findOrFail($email->fresh()?->working_revision_id);
    expect($used->origin)->toBe('agent-accepted')
        ->and(KitDocument::fields(PieceKind::EmailBlurb, $used->document ?? [])['subject'] ?? null)->toBe('Another admin hire will not fix it')
        ->and(distributionKitActivities($email, EditorialActivityKind::ReviewKit)->last()?->revision_id)->toBe($used->id);
});

test('public writing readers list only blog posts, never a published kit piece', function () {
    $post = Article::factory()->create(['slug' => 'a-published-blog-post', 'kind' => PieceKind::BlogPost]);
    $kit = Article::factory()->create(['slug' => 'a-published-kit-piece', 'kind' => PieceKind::LinkedinPost]);
    foreach ([$post, $kit] as $article) {
        $release = ArticleRelease::factory()->imported()->create(['article_id' => $article->id]);
        $article->forceFill(['published_release_id' => $release->id, 'first_published_at' => now()])->save();
    }

    $reader = app(ReadPublishedWriting::class);

    expect($reader->all()->pluck('slug')->all())->toBe(['a-published-blog-post'])
        ->and($reader->find('a-published-kit-piece'))->toBeNull();
});
