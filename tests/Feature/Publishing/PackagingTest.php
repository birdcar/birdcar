<?php

use App\Actions\Publishing\AcceptPackageProposal;
use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\CheckArticleRelease;
use App\Actions\Publishing\FinishEditorialReview;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
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
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\EditorialOutput;
use App\Services\Publishing\PublishingFingerprint;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

const PACKAGING_SEO_TITLE = 'Do you need another admin hire? What to check first';

const PACKAGING_DESCRIPTION = 'When the team is full and the work keeps coming back to you, a new hire often adds a person to the same chores. Here is what to check before you post the job.';

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function packagingAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $user;
}

/** @return array<string, mixed> */
function packagingDocument(string $text): array
{
    return ['version' => 1, 'type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'attrs' => ['id' => 'blk_00000000000000e1'], 'content' => [['type' => 'text', 'text' => $text]]],
        ['type' => 'paragraph', 'attrs' => ['id' => 'blk_00000000000000e2'], 'content' => [
            ['type' => 'text', 'text' => 'If this sounds like your week, bring it to '],
            ['type' => 'text', 'text' => 'a Walkthrough', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/walkthrough']]]],
            ['type' => 'text', 'text' => '.'],
        ]],
    ]];
}

/**
 * A valid package answer, with any field replaced.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function packagingPayload(array $overrides = []): array
{
    return [
        'titleOptions' => [
            'Another admin hire will not fix it',
            'Check the chores before you hire',
            'Do you need another admin hire?',
            'What to check before the next hire',
            'The hire that adds a person to the same chores',
            'Why the work keeps coming back to you',
            'Before you post the job',
            'A full team and the same chores',
            'The Friday report is not a hiring problem',
            'Hiring for chores you could stop',
        ],
        'title' => 'Another admin hire will not fix it',
        'seoTitle' => PACKAGING_SEO_TITLE,
        'description' => PACKAGING_DESCRIPTION,
        'slug' => 'check-the-chores-before-hiring',
        'greatestHitsTag' => 'the admin hire question',
        ...$overrides,
    ];
}

/** @return array{title: string, seo_title: string, description: string, slug: string, greatest_hits_tag: string} */
function packagingAccepted(string $title = 'Check the chores before you hire', string $slug = 'check-the-chores-before-hiring'): array
{
    return ['title' => $title, 'seo_title' => PACKAGING_SEO_TITLE, 'description' => PACKAGING_DESCRIPTION, 'slug' => $slug, 'greatest_hits_tag' => 'the admin hire question'];
}

/** @param array<string, mixed> $response */
function packagingCompletedActivity(User $actor, PublishingAttempt $attempt, EditorialActivityKind $kind, ?ArticleRevision $revision, array $response): EditorialActivity
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
 * An in-review attempt whose working revision has three completed lenses and a completed reconciliation, built while
 * agents are paused so finishing review creates work without running it.
 */
function packagingReviewedAttempt(User $actor): PublishingAttempt
{
    setPublishingAgentsPaused(true);
    $write = app(WriteArticle::class);
    $approve = app(ApprovePublishingStage::class);
    $article = $write->captureIdea($actor, 'Why another admin hire will not fix the Friday report');
    $revision = $write->save($actor, $article, null, packagingDocument('The Friday report takes an afternoon.'), ['title' => 'The Friday report', 'date' => '2024-01-01', 'tags' => []], 'packaging-'.Str::random(8));
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id, ['goal' => 'Show owners what to check first', 'post_type' => 'essay']);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');
    packagingCompletedActivity($actor, $attempt, EditorialActivityKind::ResearchChallenge, null, ['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []]);
    $attempt = app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, plan: ['outline' => ['What to check first'], 'visualPlan' => ['The Friday report']]);
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');

    foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer] as $lens) {
        packagingCompletedActivity($actor, $attempt, $lens, $revision, ['findings' => []]);
    }
    packagingCompletedActivity($actor, $attempt, EditorialActivityKind::Reconciliation, $revision, ['groups' => [], 'conflicts' => []]);

    return $attempt;
}

function packagingRevision(PublishingAttempt $attempt): ArticleRevision
{
    return ArticleRevision::query()->whereKey((int) Article::query()->whereKey($attempt->article_id)->value('working_revision_id'))->firstOrFail();
}

/** @param array<string, mixed> $payload */
function packagingRun(EditorialActivity $activity, array $payload): EditorialActivity
{
    setPublishingAgentsPaused(false);
    config()->set('ai.providers.openrouter.key', 'test-key');
    Queue::fake([RunEditorialActivity::class]);
    Http::swap(new HttpFactory(app(Dispatcher::class)));
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response([
        'id' => 'gen-packaging-'.Str::random(6),
        'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode($payload, JSON_THROW_ON_ERROR)]]],
    ])]);

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialOutput::class), app(WriteArticle::class));
    setPublishingAgentsPaused(true);

    return $activity->fresh() ?? throw new RuntimeException('The activity disappeared.');
}

/** Finishes review without a recheck and runs the package it starts. */
function packagingCompletedPackage(User $actor, PublishingAttempt $attempt): EditorialActivity
{
    app(FinishEditorialReview::class)->finish($actor, $attempt, packagingRevision($attempt)->id);
    $package = EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Package->value)->sole();

    return packagingRun($package, packagingPayload());
}

/** @return list<string> */
function packagingCodes(User $actor, PublishingAttempt $attempt, string $slug): array
{
    $check = app(CheckArticleRelease::class)->check($actor, $attempt->fresh() ?? $attempt, packagingRevision($attempt)->id, $slug);

    return array_column($check['findings'], 'code');
}

test('finishing review without a recheck starts packaging on the reviewed revision, once', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    $revision = packagingRevision($attempt);

    $finished = app(FinishEditorialReview::class)->finish($actor, $attempt, $revision->id);
    $again = app(FinishEditorialReview::class)->finish($actor, $attempt->fresh() ?? $attempt, $revision->id);
    $packages = EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Package->value)->get();

    expect($finished)->toBeNull()
        ->and($again)->toBeNull()
        ->and($packages)->toHaveCount(1)
        ->and($packages->sole()->status)->toBe(EditorialActivityStatus::Pending)
        ->and($packages->sole()->revision_id)->toBe($revision->id)
        ->and($packages->sole()->batch_key)->toBe('package-'.$attempt->review_cycle.'-'.$revision->id);
});

test('finishing review with a recheck waits for the recheck before packaging', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    $reviewed = packagingRevision($attempt);
    $revised = app(WriteArticle::class)->save($actor, $attempt->article()->firstOrFail(), $reviewed->id, packagingDocument('The Friday report takes twenty minutes now.'), $reviewed->metadata ?? [], 'packaging-revision');

    $recheck = app(FinishEditorialReview::class)->finish($actor, $attempt, $reviewed->id);

    expect($recheck?->kind)->toBe(EditorialActivityKind::Recheck)
        ->and(EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Package->value)->exists())->toBeFalse();

    packagingRun($recheck ?? throw new RuntimeException('No recheck.'), ['resolved' => [], 'unresolved' => [], 'newBlockingFindings' => []]);
    $package = EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Package->value)->sole();

    expect($recheck->fresh()?->status)->toBe(EditorialActivityStatus::Completed)
        ->and($package->revision_id)->toBe($revised->id)
        ->and($package->batch_key)->toBe('package-'.$attempt->review_cycle.'-'.$revised->id.'-after-recheck');
});

test('a recheck that fails starts no packaging', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    $reviewed = packagingRevision($attempt);
    app(WriteArticle::class)->save($actor, $attempt->article()->firstOrFail(), $reviewed->id, packagingDocument('The Friday report takes twenty minutes now.'), $reviewed->metadata ?? [], 'packaging-revision');
    $recheck = app(FinishEditorialReview::class)->finish($actor, $attempt, $reviewed->id) ?? throw new RuntimeException('No recheck.');

    packagingRun($recheck, ['resolved' => 'not a list']);

    expect($recheck->fresh()?->status)->toBe(EditorialActivityStatus::Failed)
        ->and(EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Package->value)->exists())->toBeFalse();
});

test('a recheck started after packaging sets the earlier package aside', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    $revision = packagingRevision($attempt);
    $package = packagingCompletedPackage($actor, $attempt);
    $finding = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $revision->id,
        'input_hash' => $revision->content_hash,
        'lens' => 'review_facts',
        'kind' => 'clarity',
        'severity' => 'advisory',
        'statement' => 'Tighten the opening.',
    ]);

    $recheck = app(FinishEditorialReview::class)->finish($actor, $attempt->fresh() ?? $attempt, $revision->id, [$finding->id => ['disposition' => 'accepted']]);

    expect($recheck?->kind)->toBe(EditorialActivityKind::Recheck)
        ->and($package->fresh()?->status)->toBe(EditorialActivityStatus::Stale)
        ->and($package->fresh()?->error_reason)->toBe(FinishEditorialReview::PACKAGE_SUPERSEDED_REASON);
    expect(fn () => app(AcceptPackageProposal::class)->accept($actor, $package->fresh() ?? $package, packagingAccepted(), $revision->id))
        ->toThrow(RuntimeException::class, 'Only a completed package can be accepted.');
});

test('a package proposes ten titles and the search details for the owner', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);

    $package = packagingCompletedPackage($actor, $attempt);
    $body = Http::recorded()->sole()[0]->data();

    expect($package->status)->toBe(EditorialActivityStatus::Completed)
        ->and($package->proposal['title_options'] ?? [])->toHaveCount(10)
        ->and($package->proposal)->toMatchArray([
            'title' => 'Another admin hire will not fix it',
            'seo_title' => PACKAGING_SEO_TITLE,
            'description' => PACKAGING_DESCRIPTION,
            'slug' => 'check-the-chores-before-hiring',
            'greatest_hits_tag' => 'the admin hire question',
        ])
        ->and(data_get($body, 'response_format.json_schema.schema.properties.seoTitle.description'))->toContain('[rule:seo-title-length]')
        ->and(data_get($body, 'response_format.json_schema.schema.properties.titleOptions.minItems'))->toBe(10)
        ->and(packagingRevision($attempt)->metadata['title'] ?? null)->toBe('The Friday report');
});

test('a package that breaks a metadata rule fails the activity with the measured length', function (array $overrides, string $reason) {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    app(FinishEditorialReview::class)->finish($actor, $attempt, packagingRevision($attempt)->id);
    $package = EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Package->value)->sole();

    $failed = packagingRun($package, packagingPayload($overrides));

    expect($failed->status)->toBe(EditorialActivityStatus::Failed)
        ->and($failed->error_reason)->toContain($reason)
        ->and($failed->proposal)->toBeNull();
})->with([
    'a 48-character SEO title' => [['seoTitle' => 'Do you need another admin hire? Check this first'], 'SEO title is 48 characters; it must be 50-60'],
    'a short meta description' => [['description' => 'What to check before you hire.'], 'Meta description is 30 characters; it must be 150-160'],
    'a three-word slug' => [['slug' => 'check-the-chores'], 'The slug "check-the-chores" must be four to six lowercase words'],
    'a Title Case title' => [['title' => 'Another Admin Hire Will Not Fix It'], 'Write the title "Another Admin Hire Will Not Fix It" in sentence case'],
    'nine title options' => [['titleOptions' => array_fill(0, 9, 'One option')], 'exactly 10 title options; it had 9'],
    'repeated title options' => [['titleOptions' => [...array_map(fn (int $number): string => 'Option '.$number, range(1, 9)), 'option 1']], 'Package title options must be distinct; "option 1" appears twice.'],
    'a title outside the options' => [['title' => 'A brand new headline nobody proposed'], 'Package title "A brand new headline nobody proposed" must be copied exactly from one of the title options'],
    'an option title with different case' => [['title' => 'another admin hire will not fix it'], 'must be copied exactly from one of the title options'],
    'an em dash in the SEO title' => [['seoTitle' => 'Another admin hire — what to check before you post it'], 'Replace the em dash'],
]);

test('a package title matches an option after trimming surrounding whitespace', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    app(FinishEditorialReview::class)->finish($actor, $attempt, packagingRevision($attempt)->id);
    $package = EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Package->value)->sole();

    $completed = packagingRun($package, packagingPayload(['title' => '  Check the chores before you hire ']));

    expect($completed->status)->toBe(EditorialActivityStatus::Completed);
});

test('accepting an edited package saves a metadata-only revision that keeps the review lineage through approval', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    $reviewed = packagingRevision($attempt);
    $package = packagingCompletedPackage($actor, $attempt);
    $fingerprint = app(PublishingFingerprint::class);

    $accepted = app(AcceptPackageProposal::class)->accept($actor, $package, packagingAccepted('  Check the chores before you hire  '), $reviewed->id);
    $again = app(AcceptPackageProposal::class)->accept($actor, $package, packagingAccepted('  Check the chores before you hire  '), $reviewed->id);

    expect($accepted->origin)->toBe('agent-accepted')
        ->and($accepted->parent_revision_id)->toBe($reviewed->id)
        ->and($again->id)->toBe($accepted->id)
        ->and($fingerprint->hash($accepted->document ?? []))->toBe($fingerprint->hash($reviewed->document ?? []))
        ->and($accepted->metadata)->toMatchArray([
            'title' => 'Check the chores before you hire',
            'seo_title' => PACKAGING_SEO_TITLE,
            'description' => PACKAGING_DESCRIPTION,
            'slug' => 'check-the-chores-before-hiring',
            'greatest_hits_tag' => 'the admin hire question',
            'date' => '2024-01-01',
            'tags' => [],
        ])
        ->and(Article::query()->whereKey($attempt->article_id)->value('slug'))->toStartWith('idea-');

    expect(array_filter(packagingCodes($actor, $attempt, 'check-the-chores-before-hiring'), fn (string $code): bool => str_starts_with($code, 'review.prerequisite.')))->toBe([])
        ->and(packagingCodes($actor, $attempt, 'check-the-chores-before-hiring'))->toBe([]);

    $release = app(ManageArticleRelease::class)->prepare($actor, $attempt->fresh() ?? $attempt, $accepted->id, 'check-the-chores-before-hiring');
    $approval = app(ManageArticleRelease::class)->approve($actor, $release, $release->release_hash);

    expect($release->payload['review_manifest']['lineage']['mode'] ?? null)->toBe('metadata_only')
        ->and($release->payload['review_manifest']['lineage']['revision_ids'] ?? null)->toBe([$accepted->id, $reviewed->id])
        ->and($approval->release_id)->toBe($release->id);
});

test('metadata-only saves after an accepted package stay in the lineage, and a document edit leaves it', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    $reviewed = packagingRevision($attempt);
    $accepted = app(AcceptPackageProposal::class)->accept($actor, packagingCompletedPackage($actor, $attempt), packagingAccepted(), $reviewed->id);
    $write = app(WriteArticle::class);
    $article = $attempt->article()->firstOrFail();

    $details = $write->save($actor, $article, $accepted->id, $accepted->document ?? [], [...($accepted->metadata ?? []), 'tags' => ['hiring']], 'packaging-details');

    expect(packagingCodes($actor, $attempt, 'check-the-chores-before-hiring'))->toBe([]);

    $write->save($actor, $article->fresh() ?? $article, $details->id, packagingDocument('A different opening.'), $details->metadata ?? [], 'packaging-edit');

    expect(packagingCodes($actor, $attempt, 'check-the-chores-before-hiring'))->toContain('review.prerequisite.incomplete');
});

test('accepting a slug an archive essay uses is blocked at release by the archive reuse rule', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    $archive = Article::factory()->create(['slug' => 'just-build-it-twice-for-owners']);
    ArticleRelease::factory()->imported()->create(['article_id' => $archive->id]);

    app(AcceptPackageProposal::class)->accept($actor, packagingCompletedPackage($actor, $attempt), packagingAccepted(slug: 'just-build-it-twice-for-owners'), packagingRevision($attempt)->id);

    expect(packagingCodes($actor, $attempt, 'just-build-it-twice-for-owners'))->toContain('lint.slug-archive-reuse', 'slug.unique');
    expect(fn () => app(ManageArticleRelease::class)->prepare($actor, $attempt->fresh() ?? $attempt, packagingRevision($attempt)->id, 'just-build-it-twice-for-owners'))
        ->toThrow(RuntimeException::class, 'The public slug is already in use.');
});

test('the archive reuse rule never counts the piece its own slug', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    app(AcceptPackageProposal::class)->accept($actor, packagingCompletedPackage($actor, $attempt), packagingAccepted(), packagingRevision($attempt)->id);
    Article::query()->whereKey($attempt->article_id)->update(['slug' => 'check-the-chores-before-hiring']);

    expect(packagingCodes($actor, $attempt, 'check-the-chores-before-hiring'))->toBe([]);
});

test('release requires an SEO title, and metadata hits are blocking but overridable', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);

    expect(packagingCodes($actor, $attempt, 'check-the-chores-before-hiring'))->toContain('metadata.seo_title.required', 'metadata.description.required');

    app(AcceptPackageProposal::class)->accept($actor, packagingCompletedPackage($actor, $attempt), [...packagingAccepted(), 'seo_title' => 'Do you need another admin hire'], packagingRevision($attempt)->id);
    $check = app(CheckArticleRelease::class)->check($actor, $attempt->fresh() ?? $attempt, packagingRevision($attempt)->id, 'check-the-chores-before-hiring');
    $hit = collect($check['findings'])->firstWhere('code', 'lint.seo-title-length');

    expect($hit)->toMatchArray(['severity' => 'blocking', 'location' => '$.metadata.seo_title', 'message' => 'SEO title is 30 characters; it must be 50-60.']);

    $finding = EditorialFinding::query()->where('attempt_id', $attempt->id)->where('kind', 'lint:seo-title-length')->whereNull('stale_at')->sole();
    $finding->forceFill(['disposition' => 'false_positive', 'disposition_reason' => 'The owner wants the short search title.', 'disposition_actor_id' => $actor->id, 'disposed_at' => now()])->save();

    expect(packagingCodes($actor, $attempt, 'check-the-chores-before-hiring'))->not->toContain('lint.seo-title-length');
});

test('accepting a package needs the approve and write permissions', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    $package = packagingCompletedPackage($actor, $attempt);
    $viewer = User::factory()->create();
    $viewer->assignRole(AdminRole::Access->value);

    expect(fn () => app(AcceptPackageProposal::class)->accept($viewer, $package, packagingAccepted(), packagingRevision($attempt)->id))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(AcceptPackageProposal::class)->accept($actor, $package, packagingAccepted(), packagingRevision($attempt)->id + 1000))
        ->toThrow(RuntimeException::class, 'The article revision changed before the package was accepted.');
});

test('the workspace shows the package with live counts and saves the owner edits', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    $package = packagingCompletedPackage($actor, $attempt);
    $article = Article::query()->findOrFail($attempt->article_id);

    Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => $article])
        ->assertSee('Titles and search')
        ->assertSee('The Friday report is not a hiring problem')
        ->assertSet('package.seo_title', PACKAGING_SEO_TITLE)
        ->set('package.slug', 'Check_The_Chores')
        ->assertSee('The slug &quot;Check_The_Chores&quot; must be four to six lowercase words joined by single hyphens.', false)
        ->set('package.slug', 'check-the-chores-before-hiring')
        ->set('package.title', 'Check the chores before you hire')
        ->call('acceptPackageProposal', $package->id)
        ->assertSet('saveError', null)
        ->assertSet('releaseSlug', 'check-the-chores-before-hiring')
        ->assertSet('details.title', 'Check the chores before you hire');

    expect(packagingRevision($attempt)->metadata['title'] ?? null)->toBe('Check the chores before you hire');
});

test('the release payload freezes a claim manifest of grounded, uncontradicted, resolved research claims', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    $research = EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::ResearchChallenge->value)->sole();
    $grounded = EvidenceSource::create(['article_id' => $attempt->article_id, 'attempt_id' => $attempt->id, 'activity_id' => $research->id, 'source_type' => 'public', 'url' => 'https://example.com/hiring', 'title' => 'Hiring', 'extracted_text' => 'Teams add people to the same chores.', 'origin_metadata' => ['local_id' => 'hiring', 'url' => 'https://example.com/hiring'], 'publication_permission' => true]);
    $retained = EvidenceSource::create(['article_id' => $attempt->article_id, 'attempt_id' => $attempt->id, 'source_type' => 'owner', 'title' => 'Owner notes', 'extracted_text' => 'Friday reports take an afternoon.', 'publication_permission' => true]);
    $research->forceFill(['response' => ['claims' => [
        ['statement' => 'A new hire often inherits the same chores.', 'supporting_source_ids' => [], 'supporting_source_refs' => ['hiring'], 'supporting_quotations' => [['source_ref' => 'hiring', 'quote' => 'Teams add people to the same chores.']]],
        ['statement' => 'Hiring always fixes the backlog.', 'supporting_source_ids' => [], 'supporting_source_refs' => ['hiring'], 'supporting_quotations' => [['source_ref' => 'hiring', 'quote' => 'Teams add people to the same chores.', 'relationship' => 'contradicts']]],
        ['statement' => 'Most owners spend ten hours a week on reports.', 'supporting_source_ids' => [], 'supporting_source_refs' => [], 'supporting_quotations' => [], 'unresolved' => true, 'unresolved_reason' => 'No source found.'],
        ['statement' => 'The Friday report takes an afternoon.', 'supporting_source_ids' => [$retained->id], 'supporting_source_refs' => [], 'supporting_quotations' => [['source_id' => $retained->id, 'quote' => 'Friday reports take an afternoon.']]],
    ], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []]])->save();
    app(AcceptPackageProposal::class)->accept($actor, packagingCompletedPackage($actor, $attempt), packagingAccepted(), packagingRevision($attempt)->id);

    $release = app(ManageArticleRelease::class)->prepare($actor, $attempt->fresh() ?? $attempt, packagingRevision($attempt)->id, 'check-the-chores-before-hiring');

    expect($release->payload['claim_manifest'] ?? null)->toBe([
        ['statement' => 'A new hire often inherits the same chores.', 'supporting_source_ids' => [$grounded->id]],
        ['statement' => 'The Friday report takes an afternoon.', 'supporting_source_ids' => [$retained->id]],
    ])
        ->and($release->release_hash)->toBe(app(PublishingFingerprint::class)->hash($release->payload));
});

test('a release with no research freezes an empty claim manifest', function () {
    $actor = packagingAuthor();
    $attempt = packagingReviewedAttempt($actor);
    EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::ResearchChallenge->value)->update(['status' => EditorialActivityStatus::Stale->value]);
    app(AcceptPackageProposal::class)->accept($actor, packagingCompletedPackage($actor, $attempt), packagingAccepted(), packagingRevision($attempt)->id);

    $release = app(ManageArticleRelease::class)->prepare($actor, $attempt->fresh() ?? $attempt, packagingRevision($attempt)->id, 'check-the-chores-before-hiring');

    expect($release->payload['claim_manifest'] ?? null)->toBe([]);
});
