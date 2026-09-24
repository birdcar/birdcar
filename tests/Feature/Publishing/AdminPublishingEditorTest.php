<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\ArticleRelease;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function editorUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(AdminRole::Access->value);
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

function makePublishingViewOnly(User $user): User
{
    $user->syncRoles([AdminRole::Access->value]);
    $user->givePermissionTo(PublishingPermission::View->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->refresh();
}

function simpleDocument(string $text = 'Hello'): array
{
    return ['version' => 1, 'type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'attrs' => ['id' => 'blk_0000000000000001'], 'content' => [['type' => 'text', 'text' => $text]]],
    ]];
}

function adminReleaseMetadata(string $title): array
{
    return [
        'title' => $title,
        'description' => $title.' description.',
        'date' => '2024-01-01',
        'tags' => [],
    ];
}

function adminCompletedActivity(User $actor, PublishingAttempt $attempt, EditorialActivityKind $kind, ?int $revisionId = null, ?string $revisionHash = null): EditorialActivity
{
    return EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => $kind,
        'status' => EditorialActivityStatus::Completed,
        'stage' => $kind->isReviewLens() || in_array($kind, [EditorialActivityKind::Reconciliation, EditorialActivityKind::Recheck], true) ? 'in_review' : 'drafting',
        'input_version' => $attempt->input_version,
        'revision_id' => $revisionId,
        'revision_hash' => $revisionHash,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => $kind->value.'-'.$attempt->id.'-'.uniqid(),
        'idempotency_key' => $kind->value.'-'.$attempt->id.'-'.uniqid(),
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', $kind->value.$attempt->id.uniqid()),
        'input' => ['approval_hashes' => array_filter([
            ApprovalKind::Angle->value => in_array($kind, [EditorialActivityKind::ResearchChallenge, EditorialActivityKind::Plan], true) ? app(ApprovePublishingStage::class)->inputHashFor($attempt, ApprovalKind::Angle) : null,
            ApprovalKind::Plan->value => $kind->isReviewLens() || in_array($kind, [EditorialActivityKind::Draft, EditorialActivityKind::Reconciliation, EditorialActivityKind::Recheck], true) ? app(ApprovePublishingStage::class)->inputHashFor($attempt, ApprovalKind::Plan) : null,
        ])],
        'model_snapshot' => [],
        'response' => match ($kind) {
            EditorialActivityKind::Reconciliation => ['groups' => [], 'conflicts' => []],
            EditorialActivityKind::Recheck => ['resolved' => [], 'unresolved' => [], 'newBlockingFindings' => []],
            default => $kind->isReviewLens() ? ['findings' => []] : ['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []],
        },
        'completed_at' => now(),
    ]);
}

function adminAttemptWithReviewedPlan(User $actor, PublishingAttempt $attempt, array $plan = ['outline' => ['intro', 'body'], 'visualPlan' => ['hero image']]): PublishingAttempt
{
    adminCompletedActivity($actor, $attempt, EditorialActivityKind::ResearchChallenge);

    return app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, plan: $plan);
}

function adminAttemptWithCompletedReviews(User $actor, PublishingAttempt $attempt): void
{
    $revision = $attempt->article()->firstOrFail()->workingRevision()->firstOrFail();
    foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer, EditorialActivityKind::Reconciliation] as $kind) {
        adminCompletedActivity($actor, $attempt, $kind, (int) $revision->id, (string) $revision->content_hash);
    }
}

test('article workspace authorizes and renders preview iframe after a revision exists', function (): void {
    $user = editorUser();
    $article = app(WriteArticle::class)->capture($user, 'Workspace idea');
    $revision = app(WriteArticle::class)->save($user, $article, null, simpleDocument(), [], 'mut_first');

    $response = $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing/articles/'.$article->slug)
        ->assertOk()
        ->assertSee('Workspace idea')
        ->assertSee('Brief & plan', false)
        ->assertSee('Manuscript')
        ->assertSee('Reviews')
        ->assertSee('aria-label="Budget mutation key"', false)
        ->assertSee('window.livewireScriptConfig', false)
        ->assertSee('sandbox="allow-same-origin"', false);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $editor = (new DOMXPath($document))->query('//*[@data-admin-editor]')->item(0);

    expect(json_decode($editor->getAttribute('data-document'), true))->toBe($revision->document)
        ->and(json_decode($editor->getAttribute('data-metadata'), true))->toBe($revision->metadata);
});

test('article workspace denies cross author access by slug', function (): void {
    $owner = editorUser();
    $other = editorUser();
    $article = app(WriteArticle::class)->capture($owner, 'Other author workspace idea');
    app(WriteArticle::class)->save($owner, $article, null, simpleDocument(), [], 'mut_owner_workspace');

    $this->actingAs($other)
        ->get('http://admin.birdcar.test/publishing/articles/'.$article->slug)
        ->assertForbidden();
});

test('preview denies cross author access by slug', function (): void {
    $owner = editorUser();
    $other = editorUser();
    $article = app(WriteArticle::class)->capture($owner, 'Other author preview idea');
    $revision = app(WriteArticle::class)->save($owner, $article, null, simpleDocument(), [], 'mut_owner_preview');

    $this->actingAs($other)
        ->get('http://admin.birdcar.test/publishing/articles/'.$article->slug.'/preview?revision='.$revision->id)
        ->assertForbidden();
});

test('livewire save document uses cas and mutation ids', function (): void {
    $user = editorUser();
    $article = app(WriteArticle::class)->capture($user, 'CAS idea');

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article])
        ->call('saveDocument', null, 'mut_cas_1', simpleDocument('Saved'), [])
        ->assertSet('saveState', 'saved');

    $revision = $article->fresh()->workingRevision;
    expect($revision)->not->toBeNull();

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->call('saveDocument', null, 'mut_cas_2', simpleDocument('Conflict'), [])
        ->assertSet('saveState', 'conflict');
});

test('two stale editor components expose latest revision without rebasing the losing tab', function (): void {
    $user = editorUser();
    $write = app(WriteArticle::class);
    $article = $write->capture($user, 'Two tabs');
    $base = $write->save($user, $article, null, simpleDocument('Base'), ['title' => 'Base'], 'mut_base_two_tabs');

    $firstTab = Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()]);
    $secondTab = Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()]);

    $firstTab->call('saveDocument', $base->id, 'mut_first_tab', simpleDocument('Saved by first tab'), ['title' => 'First'])
        ->assertSet('saveState', 'saved');
    $latest = $article->fresh()->workingRevision;

    $secondTab->call('saveDocument', $base->id, 'mut_second_tab', simpleDocument('Local losing tab'), ['title' => 'Second'])
        ->assertSet('saveState', 'conflict')
        ->assertSet('currentRevisionId', $base->id)
        ->assertSet('conflictLatestRevision.id', $latest?->id)
        ->assertSet('conflictLatestRevision.metadata.title', 'First');

    expect($article->fresh()?->working_revision_id)->toBe($latest?->id)
        ->and($latest?->document['content'][0]['content'][0]['text'])->toBe('Saved by first tab');
});

test('preview refuses revisions from other articles', function (): void {
    $user = editorUser();
    $first = app(WriteArticle::class)->capture($user, 'First');
    $second = app(WriteArticle::class)->capture($user, 'Second');
    $revision = app(WriteArticle::class)->save($user, $second, null, simpleDocument(), [], 'mut_second');

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing/articles/'.$first->slug.'/preview?revision='.$revision->id)
        ->assertNotFound();
});

test('duplicate mutation id with different payload surfaces conflict', function (): void {
    $user = editorUser();
    $article = app(WriteArticle::class)->capture($user, 'Mutation idea');
    app(WriteArticle::class)->save($user, $article, null, simpleDocument('One'), [], 'mut_repeat');

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->call('saveDocument', $article->fresh()->working_revision_id, 'mut_repeat', simpleDocument('Two'), [])
        ->assertSet('saveState', 'conflict');
});

test('workspace exposes review empty state and persists protected passage controls to canonical json', function (): void {
    $user = editorUser();
    $article = app(WriteArticle::class)->capture($user, 'Protected idea');
    app(WriteArticle::class)->save($user, $article, null, simpleDocument('Protect me'), [], 'mut_protect_seed');

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->assertSee('Reviews and proposals')
        ->assertSee('Insert note')
        ->assertSee('Insert callout')
        ->assertSee('Insert chart')
        ->assertSee('Insert diagram')
        ->assertSee('Protect selection')
        ->assertSee('Start three-lens review')
        ->assertSee('No findings have been recorded')
        ->call('protectFirstBlock')
        ->assertSet('saveState', 'saved')
        ->assertSee('blk_0000000000000001');

    expect($article->fresh()->workingRevision->document['content'][0]['attrs']['protected'])->toBeTrue();
});

test('gate approvals submit rendered stale hashes rather than recomputing fresh input', function (): void {
    $user = editorUser();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);
    $releases = app(ManageArticleRelease::class);

    $angleArticle = $write->capture($user, 'Angle stale');
    $angleRevision = $write->save($user, $angleArticle, null, simpleDocument('Angle'), [], 'mut_angle_seed');
    $angleAttempt = $advance->develop($user, $angleArticle, $angleRevision->id, ['summary' => 'Rendered brief']);
    $angleComponent = Livewire\Livewire::actingAs($user)->test('admin.publishing.article-workspace', ['article' => $angleArticle->fresh()]);
    $oldAngleHash = $angleComponent->get('angleInputHash');
    $angleAttempt->forceFill(['brief' => ['summary' => 'Changed elsewhere']])->save();
    $angleComponent->call('approveAngle', $oldAngleHash)->assertSet('saveError', 'The approval input is stale.');
    expect(EditorialApproval::query()->where('attempt_id', $angleAttempt->id)->where('kind', ApprovalKind::Angle)->exists())->toBeFalse();

    $planArticle = $write->capture($user, 'Plan stale');
    $planRevision = $write->save($user, $planArticle, null, simpleDocument('Plan'), [], 'mut_plan_seed');
    $planAttempt = $advance->develop($user, $planArticle, $planRevision->id, ['summary' => 'Brief']);
    $approve->approve($user, $planAttempt, ApprovalKind::Angle, $approve->inputHashFor($planAttempt, ApprovalKind::Angle));
    adminAttemptWithReviewedPlan($user, $planAttempt->fresh(), ['outline' => ['Rendered'], 'visualPlan' => ['Rendered visual']]);
    $planAttempt->refresh();
    $planComponent = Livewire\Livewire::actingAs($user)->test('admin.publishing.article-workspace', ['article' => $planArticle->fresh()]);
    $oldPlanHash = $planComponent->get('planInputHash');
    $planAttempt->forceFill(['plan' => ['outline' => ['Changed elsewhere'], 'visualPlan' => ['Rendered visual']]])->save();
    $planComponent->call('approvePlan', $oldPlanHash)->assertSet('saveError', 'The approval input is stale.');
    expect(EditorialApproval::query()->where('attempt_id', $planAttempt->id)->where('kind', ApprovalKind::Plan)->exists())->toBeFalse();

    $releaseArticle = $write->capture($user, 'Release stale');
    $releaseRevision = $write->save($user, $releaseArticle, null, simpleDocument('Release'), adminReleaseMetadata('Release'), 'mut_release_seed');
    $releaseAttempt = $advance->develop($user, $releaseArticle, $releaseRevision->id, ['summary' => 'Brief']);
    $approve->approve($user, $releaseAttempt, ApprovalKind::Angle, $approve->inputHashFor($releaseAttempt, ApprovalKind::Angle));
    $releaseAttempt = adminAttemptWithReviewedPlan($user, $releaseAttempt->fresh(), ['outline' => ['Plan'], 'visualPlan' => ['Visual plan']]);
    $approve->approve($user, $releaseAttempt->fresh(), ApprovalKind::Plan, $approve->inputHashFor($releaseAttempt->fresh(), ApprovalKind::Plan));
    adminAttemptWithCompletedReviews($user, $releaseAttempt->fresh());
    $release = $releases->prepare($user, $releaseAttempt->fresh(), $releaseRevision->id, 'release-stale');
    $releaseComponent = Livewire\Livewire::actingAs($user)->test('admin.publishing.article-workspace', ['article' => $releaseArticle->fresh()]);
    $oldReleaseHash = $releaseComponent->get('releaseInputHash');
    DB::table('article_releases')->where('id', $release->id)->update(['release_hash' => 'changed-release-hash']);
    $releaseComponent->call('approveRelease', $oldReleaseHash)->assertSet('saveError', 'The release package changed before approval.');
    expect(EditorialApproval::query()->where('attempt_id', $releaseAttempt->id)->where('kind', ApprovalKind::Release)->exists())->toBeFalse();
});

test('workspace prepares scheduled releases with an explicit timezone confirmation', function (): void {
    $this->travelTo('2026-06-01 12:00:00 UTC');
    $user = editorUser();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);
    $article = $write->capture($user, 'Workspace scheduled release', 'workspace-scheduled-release');
    $revision = $write->save($user, $article, null, simpleDocument('Scheduled release'), adminReleaseMetadata('Scheduled Release'), 'mut_workspace_schedule');
    $attempt = $advance->develop($user, $article, $revision->id, ['summary' => 'Brief']);
    $approve->approve($user, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = adminAttemptWithReviewedPlan($user, $attempt->fresh(), ['outline' => ['Plan'], 'visualPlan' => ['Visual plan']]);
    $approve->approve($user, $attempt->fresh(), ApprovalKind::Plan, $approve->inputHashFor($attempt->fresh(), ApprovalKind::Plan));
    adminAttemptWithCompletedReviews($user, $attempt->fresh());

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->set('releaseScheduledAt', '2026-07-01 09:30:00')
        ->set('releaseScheduledTimezone', 'America/New_York')
        ->call('prepareRelease')
        ->assertSet('saveError', null);
    $this->travelBack();

    $release = ArticleRelease::query()->where('attempt_id', $attempt->id)->latest('id')->firstOrFail();
    expect($release->scheduled_at?->toISOString())->toBe('2026-07-01T13:30:00.000000Z')
        ->and($release->payload['delivery_intent']['selected_timezone'])->toBe('America/New_York')
        ->and($release->payload['delivery_intent']['utc_offset'])->toBe('-04:00');
});

test('imported published articles show historical published release readiness without a current attempt', function (): void {
    $user = editorUser();
    $article = app(WriteArticle::class)->capture($user, 'Imported published idea');
    $revision = app(WriteArticle::class)->save($user, $article, null, simpleDocument('Published'), [], 'mut_imported_release_seed');
    $release = ArticleRelease::factory()->imported()->create([
        'article_id' => $article->id,
        'revision_id' => $revision->id,
    ]);
    $article->forceFill([
        'current_attempt_id' => null,
        'working_revision_id' => null,
        'published_release_id' => $release->id,
        'first_published_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing/articles/'.$article->slug)
        ->assertOk()
        ->assertSee('historical published #'.$release->id)
        ->assertSee('historical published revision')
        ->assertSee('already published from the historical release package');
});

test('workspace wires agent activity budget source voice and interview answer actions', function (): void {
    $user = editorUser();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);
    $article = $write->capture($user, 'Agent UI wiring');
    $revision = $write->save($user, $article, null, simpleDocument('Agent UI'), [], 'mut_agent_ui_seed');
    $attempt = $advance->develop($user, $article, $revision->id, ['summary' => 'Brief']);
    $archive = $write->capture($user, 'Published voice sample', 'published-voice-sample');
    $archiveRevision = $write->save($user, $archive, null, simpleDocument('Published archive voice.'), ['title' => 'Published voice sample'], 'mut_agent_ui_archive');
    $archiveRelease = ArticleRelease::factory()->imported()->create([
        'article_id' => $archive->id,
        'attempt_id' => null,
        'revision_id' => $archiveRevision->id,
    ]);
    $archive->forceFill(['published_release_id' => $archiveRelease->id, 'first_published_at' => $archiveRelease->published_at])->save();

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->set('budgetTopUpNanoUsd', 500_000_000)
        ->set('budgetMutationKey', 'ui-topup-test')
        ->set('selectedVoiceSampleArticleIds', [$archive->id])
        ->set('interviewAnswers', 'The best customer objected to onboarding time.')
        ->call('topUpBudget')
        ->call('startInterview')
        ->call('submitInterviewAnswers')
        ->assertSee('Agent budget')
        ->assertSee('Activity log');

    $approve->approve($user, $attempt->fresh(), ApprovalKind::Angle, $approve->inputHashFor($attempt->fresh(), ApprovalKind::Angle));

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->set('sourceUrl', 'https://example.com/source')
        ->call('startResearch');

    expect($attempt->fresh()?->allowance_nano_usd)->toBe(5_500_000_000)
        ->and(EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', 'interview')->first()?->input['voice_sample_ids'])->toBe([$archive->id])
        ->and(EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', 'research_challenge')->first()?->input['source_url'])->toBe('https://example.com/source')
        ->and($attempt->fresh()?->interview_context['answers'])->toBe('The best customer objected to onboarding time.');
});

test('view only workspace caller cannot start agent work or persist context selections', function (): void {
    config()->set('publishing_agents.enabled', true);
    Queue::fake();

    $user = editorUser();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $article = $write->capture($user, 'View only agent denial');
    $revision = $write->save($user, $article, null, simpleDocument('View only'), [], 'mut_view_only_seed');
    $attempt = $advance->develop($user, $article, $revision->id, ['summary' => 'Brief']);
    $user = makePublishingViewOnly($user);

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->set('selectedEvidenceSourceIds', [123])
        ->call('startInterview')
        ->assertForbidden();

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->set('selectedEvidenceSourceIds', [123])
        ->call('startResearch')
        ->assertForbidden();

    expect(EditorialActivity::query()->where('attempt_id', $attempt->id)->exists())->toBeFalse()
        ->and($attempt->fresh()?->interview_context)->toBe([]);
    Queue::assertNothingPushed();
});

test('stale workspace component after attempt replacement cannot persist source context or dispatch work', function (): void {
    config()->set('publishing_agents.enabled', true);
    Queue::fake();

    $user = editorUser();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $article = $write->capture($user, 'Stale attempt agent denial');
    $revision = $write->save($user, $article, null, simpleDocument('Stale attempt'), [], 'mut_stale_attempt_seed');
    $oldAttempt = $advance->develop($user, $article, $revision->id, ['summary' => 'Brief']);
    $source = EvidenceSource::create([
        'article_id' => $article->id,
        'attempt_id' => $oldAttempt->id,
        'source_type' => 'owner',
        'title' => 'Owner supplied source',
        'extracted_text' => 'Owner supplied source text.',
        'content_hash' => hash('sha256', 'Owner supplied source text.'),
    ]);
    $component = Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->set('selectedEvidenceSourceIds', [$source->id]);

    $replacementAttempt = PublishingAttempt::factory()->create([
        'article_id' => $article->id,
        'user_id' => $user->id,
        'input_version' => $revision->id,
        'brief' => ['summary' => 'Replacement'],
    ]);
    $article->forceFill(['current_attempt_id' => $replacementAttempt->id])->save();

    $component->call('startResearch')
        ->assertSet('saveError', 'Research requires an approved angle and drafting stage.');

    expect(EditorialActivity::query()->whereIn('attempt_id', [$oldAttempt->id, $replacementAttempt->id])->exists())->toBeFalse()
        ->and($oldAttempt->fresh()?->interview_context)->toBe([])
        ->and($replacementAttempt->fresh()?->interview_context)->toBe([]);
    Queue::assertNothingPushed();
});

test('changing a selected angle after approval invalidates downstream gates without starting agent work', function (): void {
    config()->set('publishing_agents.enabled', false);
    Queue::fake();

    $user = editorUser();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);
    $article = $write->capture($user, 'Changed approved angle');
    $revision = $write->save($user, $article, null, simpleDocument('Changed angle'), [], 'mut_changed_angle_seed');
    $attempt = $advance->develop($user, $article, $revision->id, ['summary' => 'Brief']);
    $attempt->forceFill(['interview_context' => [
        'angle_options' => [
            "a'quoted" => ['title' => 'Angle A', 'thesis' => 'Use A.'],
            'b' => ['title' => 'Angle B', 'thesis' => 'Use B.'],
        ],
    ]])->save();

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->assertSee("selectAngleOption('a\\u0027quoted')", false)
        ->call('selectAngleOption', "a'quoted")
        ->assertSet('saveError', null);

    $attempt = $attempt->fresh();
    $approval = $approve->approve($user, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    Queue::assertNothingPushed();

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->call('selectAngleOption', 'b')
        ->assertSet('saveError', null);

    expect($approval->fresh()?->invalidated_at)->not->toBeNull()
        ->and($attempt->fresh()?->angle['title'] ?? null)->toBe('Angle B')
        ->and($attempt->fresh()?->stage->value)->toBe('developing')
        ->and(EditorialActivity::query()->where('attempt_id', $attempt->id)->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

test('workspace wires proposal decisions and finish review to persisted dispositions and recheck', function (): void {
    $user = editorUser();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);
    $article = $write->capture($user, 'Review UI wiring');
    $revision = $write->save($user, $article, null, simpleDocument('Review UI'), [], 'mut_review_ui_seed');
    $attempt = $advance->develop($user, $article, $revision->id, ['summary' => 'Brief']);
    $approve->approve($user, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = adminAttemptWithReviewedPlan($user, $attempt->fresh());
    $approve->approve($user, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $attempt = $attempt->fresh();
    adminAttemptWithCompletedReviews($user, $attempt);

    $finding = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $revision->id,
        'input_hash' => $revision->content_hash,
        'lens' => 'review_facts',
        'kind' => 'claim',
        'severity' => 'blocking',
        'statement' => 'Needs human decision.',
        'supporting_source_ids' => [],
    ]);

    Livewire\Livewire::actingAs($user)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->call('decideFinding', $finding->id, 'accepted', 'Owner wants a targeted fix.')
        ->call('finishReview')
        ->assertSet('saveError', null);

    expect($finding->fresh()?->disposition)->toBe('accepted')
        ->and($finding->fresh()?->disposition_reason)->toBe('Owner wants a targeted fix.')
        ->and(EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', 'recheck')->exists())->toBeTrue();
});
