<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\ArticleRelease;
use App\Models\EditorialApproval;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

function simpleDocument(string $text = 'Hello'): array
{
    return ['version' => 1, 'type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'attrs' => ['id' => 'blk_0000000000000001'], 'content' => [['type' => 'text', 'text' => $text]]],
    ]];
}

test('article workspace authorizes and renders preview iframe after a revision exists', function (): void {
    $user = editorUser();
    $article = app(WriteArticle::class)->capture($user, 'Workspace idea');
    app(WriteArticle::class)->save($user, $article, null, simpleDocument(), [], 'mut_first');

    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing/articles/'.$article->slug)
        ->assertOk()
        ->assertSee('Workspace idea')
        ->assertSee('sandbox="allow-same-origin"', false);
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
        ->assertSee('No agent proposals are available yet')
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
    $planAttempt->refresh()->forceFill(['plan' => ['outline' => ['Rendered']]])->save();
    $planComponent = Livewire\Livewire::actingAs($user)->test('admin.publishing.article-workspace', ['article' => $planArticle->fresh()]);
    $oldPlanHash = $planComponent->get('planInputHash');
    $planAttempt->forceFill(['plan' => ['outline' => ['Changed elsewhere']]])->save();
    $planComponent->call('approvePlan', $oldPlanHash)->assertSet('saveError', 'The approval input is stale.');
    expect(EditorialApproval::query()->where('attempt_id', $planAttempt->id)->where('kind', ApprovalKind::Plan)->exists())->toBeFalse();

    $releaseArticle = $write->capture($user, 'Release stale');
    $releaseRevision = $write->save($user, $releaseArticle, null, simpleDocument('Release'), [], 'mut_release_seed');
    $releaseAttempt = $advance->develop($user, $releaseArticle, $releaseRevision->id, ['summary' => 'Brief']);
    $approve->approve($user, $releaseAttempt, ApprovalKind::Angle, $approve->inputHashFor($releaseAttempt, ApprovalKind::Angle));
    $releaseAttempt->refresh()->forceFill(['plan' => ['outline' => ['Plan']], 'stage' => EditorialStage::Drafting])->save();
    $approve->approve($user, $releaseAttempt->fresh(), ApprovalKind::Plan, $approve->inputHashFor($releaseAttempt->fresh(), ApprovalKind::Plan));
    $release = $releases->prepare($user, $releaseAttempt->fresh(), $releaseRevision->id, 'release-stale');
    $releaseComponent = Livewire\Livewire::actingAs($user)->test('admin.publishing.article-workspace', ['article' => $releaseArticle->fresh()]);
    $oldReleaseHash = $releaseComponent->get('releaseInputHash');
    DB::table('article_releases')->where('id', $release->id)->update(['release_hash' => 'changed-release-hash']);
    $releaseComponent->call('approveRelease', $oldReleaseHash)->assertSet('saveError', 'The release package changed before approval.');
    expect(EditorialApproval::query()->where('attempt_id', $releaseAttempt->id)->where('kind', ApprovalKind::Release)->exists())->toBeFalse();
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
