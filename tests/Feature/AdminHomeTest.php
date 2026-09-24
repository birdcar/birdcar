<?php

use App\Actions\Admin\ReadAdminHome;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\EditorialFinding;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function adminHomeUser(): User
{
    $user = User::factory()->create();
    $user->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $user;
}

/** @param array<string, mixed> $attributes */
function adminHomeAttempt(User $user, string $idea, array $attributes = []): PublishingAttempt
{
    $article = Article::factory()->for($user, 'author')->create(['idea' => $idea]);
    $attempt = PublishingAttempt::factory()->for($article)->for($user, 'initiator')->create($attributes);
    $article->update(['current_attempt_id' => $attempt->id]);

    return $attempt;
}

/** @param array<string, mixed> $attributes */
function adminHomeActivity(PublishingAttempt $attempt, array $attributes = []): EditorialActivity
{
    return EditorialActivity::factory()->create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $attempt->user_id,
        'stage' => $attempt->stage->value,
        'review_cycle' => $attempt->review_cycle,
        'input_version' => $attempt->input_version,
        ...$attributes,
    ]);
}

test('home is a real destination with owner scoped attention and ongoing work', function (): void {
    $user = adminHomeUser();
    $paused = adminHomeAttempt($user, 'Paused piece', ['paused_at' => now()]);
    adminHomeAttempt($user, 'Angle decision', ['angle' => ['source' => 'human', 'thesis' => 'One argument']]);
    adminHomeAttempt($user, 'Ongoing piece');
    adminHomeAttempt(adminHomeUser(), 'Someone else’s private work', ['paused_at' => now()]);
    $revision = ArticleRevision::factory()->for($paused->article)->create();
    $release = ArticleRelease::factory()->create(['article_id' => $paused->article_id, 'revision_id' => $revision->id, 'attempt_id' => $paused->id, 'status' => 'published']);
    $paused->article->update(['published_release_id' => $release->id]);
    Http::preventStrayRequests();
    Queue::fake();

    $response = $this->actingAs($user)->get('http://admin.birdcar.test/');

    $response->assertOk()->assertSeeInOrder(['Needs attention', 'Blocked', 'Paused piece', 'Your decision', 'Angle decision', 'Continue working', 'Ongoing piece'])
        ->assertSee(route('admin.publishing.articles.show', $paused->article), false)
        ->assertDontSee('Someone else’s private work')
        ->assertDontSee('Nothing needs your attention');
    Queue::assertNothingPushed();
    expect($paused->fresh()->paused_at)->not->toBeNull();
    expect($paused->article->fresh()->published_release_id)->toBe($release->id);
});

test('home distinguishes an empty attention list from empty active work', function (): void {
    $user = adminHomeUser();
    adminHomeAttempt($user, 'An unfinished brief');
    Article::factory()->for($user, 'author')->create(['idea' => 'Passive idea']);

    $response = $this->actingAs($user)->get('http://admin.birdcar.test/');
    $response->assertSee('Nothing needs your attention')
        ->assertSee('An unfinished brief')
        ->assertDontSee('Passive idea')
        ->assertDontSee('No other active work.');
});

test('terminal and superseded attempts do not enter the attention list', function (): void {
    $user = adminHomeUser();
    adminHomeAttempt($user, 'Published terminal', ['stage' => EditorialStage::Published, 'paused_at' => now()]);
    adminHomeAttempt($user, 'Abandoned terminal', ['abandoned_at' => now(), 'paused_at' => now()]);
    $current = adminHomeAttempt($user, 'Current active piece');
    PublishingAttempt::factory()->paused()->create(['article_id' => $current->article_id, 'user_id' => $user->id]);

    $this->actingAs($user)->get('http://admin.birdcar.test/')
        ->assertSee('Nothing needs your attention')
        ->assertSee('Current active piece')
        ->assertDontSee('Published terminal')
        ->assertDontSee('Abandoned terminal');
});

test('current failed activities appear without exposing provider errors', function (): void {
    $user = adminHomeUser();
    $attempt = adminHomeAttempt($user, 'Failed interview');
    adminHomeActivity($attempt, ['status' => EditorialActivityStatus::Failed, 'error_reason' => 'SECRET provider payload']);

    $this->actingAs($user)->get('http://admin.birdcar.test/')
        ->assertSee('An agent activity failed.')
        ->assertSee('Inspect workspace')
        ->assertDontSee('SECRET provider payload');
});

test('stale activity context does not create false blockers', function (array $attributes): void {
    $user = adminHomeUser();
    $attempt = adminHomeAttempt($user, 'Still active');
    adminHomeActivity($attempt, ['status' => EditorialActivityStatus::Failed, ...$attributes]);

    $this->actingAs($user)->get('http://admin.birdcar.test/')
        ->assertSee('Nothing needs your attention')
        ->assertDontSee('An agent activity failed.');
})->with([
    'old cycle' => [['review_cycle' => 0]],
    'old stage' => [['stage' => EditorialStage::Drafting->value]],
    'old revision hash' => [['revision_hash' => 'old-hash']],
    'changed prerequisite' => [['kind' => EditorialActivityKind::ResearchChallenge]],
]);

test('a newer activity of the same kind supersedes a historical failure', function (): void {
    $user = adminHomeUser();
    $attempt = adminHomeAttempt($user, 'Recovered activity');
    adminHomeActivity($attempt, ['status' => EditorialActivityStatus::Failed]);
    adminHomeActivity($attempt, ['status' => EditorialActivityStatus::Running]);

    $this->actingAs($user)->get('http://admin.birdcar.test/')
        ->assertSee('Nothing needs your attention')
        ->assertSee('Agent work is in progress.')
        ->assertDontSee('An agent activity failed.');
});

test('current interview output and human plans appear as review decisions', function (): void {
    $user = adminHomeUser();
    $interview = adminHomeAttempt($user, 'Interview output');
    $activity = adminHomeActivity($interview, ['status' => EditorialActivityStatus::Completed]);
    $interview->update(['interview_context' => ['latest_interview_activity_id' => $activity->id, 'questions' => ['What is the argument?']]]);
    adminHomeAttempt($user, 'Human plan', ['stage' => EditorialStage::Drafting, 'plan' => ['outline' => ['Opening'], 'visualPlan' => ['No figure needed']]]);

    $this->actingAs($user)->get('http://admin.birdcar.test/')
        ->assertSee('Review brief')->assertSee('Review plan')->assertSee('Your decision');
});

test('generated plans need current completed provenance and active approval', function (bool $invalidated): void {
    $user = adminHomeUser();
    $attempt = adminHomeAttempt($user, 'Generated plan', ['stage' => EditorialStage::Drafting]);
    $hash = app(ApprovePublishingStage::class)->inputHashFor($attempt, ApprovalKind::Angle);
    EditorialApproval::factory()->create(['attempt_id' => $attempt->id, 'user_id' => $user->id, 'kind' => ApprovalKind::Angle, 'input_hash' => $hash, 'invalidated_at' => $invalidated ? now() : null]);
    $activity = adminHomeActivity($attempt, ['kind' => EditorialActivityKind::Plan, 'status' => EditorialActivityStatus::Completed, 'input' => ['approval_hashes' => ['angle' => $hash]]]);
    $attempt->update(['plan' => ['source' => 'agent', 'activity_id' => $activity->id, 'outline' => ['Opening'], 'visualPlan' => ['No figure needed']]]);

    $items = app(ReadAdminHome::class)->forUser($user);

    expect($items['decisions'])->toHaveCount($invalidated ? 0 : 1);
})->with(['approved context' => false, 'invalidated context' => true]);

test('fresh undisposed findings appear but stale or disposed findings do not', function (array $attributes, bool $expected): void {
    $user = adminHomeUser();
    $attempt = adminHomeAttempt($user, 'Finding decision', ['stage' => EditorialStage::InReview]);
    $revision = ArticleRevision::factory()->for($attempt->article)->create();
    $attempt->article->update(['working_revision_id' => $revision->id]);
    $activity = adminHomeActivity($attempt, ['status' => EditorialActivityStatus::Completed]);
    EditorialFinding::factory()->create(['article_id' => $attempt->article_id, 'attempt_id' => $attempt->id, 'activity_id' => $activity->id, 'revision_id' => $revision->id, 'input_hash' => $revision->content_hash, 'reconciliation_state' => 'representative', ...$attributes]);

    $items = app(ReadAdminHome::class)->forUser($user);

    expect($items['decisions'])->toHaveCount($expected ? 1 : 0);
})->with([
    'current finding' => [[], true],
    'old cycle' => [['review_cycle' => 0], false],
    'changed content' => [['input_hash' => 'old'], false],
    'disposed' => [['disposition' => 'rejected'], false],
    'duplicate' => [['reconciliation_state' => 'duplicate'], false],
]);

test('current releases invite the appropriate review without changing the release', function (EditorialStage $stage, string $status, string $action): void {
    $user = adminHomeUser();
    $attempt = adminHomeAttempt($user, 'Release decision', ['stage' => $stage]);
    $revision = ArticleRevision::factory()->for($attempt->article)->create();
    $attempt->article->update(['working_revision_id' => $revision->id]);
    $release = ArticleRelease::factory()->create(['article_id' => $attempt->article_id, 'attempt_id' => $attempt->id, 'revision_id' => $revision->id, 'status' => $status]);

    $this->actingAs($user)->get('http://admin.birdcar.test/')
        ->assertSee($action)->assertDontSee('wire:click="approve', false);

    expect($release->fresh()->status)->toBe($status);
})->with([
    'prepared' => [EditorialStage::InReview, 'prepared', 'Review release'],
    'approved' => [EditorialStage::Approved, 'approved', 'Review delivery'],
]);

test('view only users are not asked to make decisions they cannot perform', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'publishing.reader', 'guard_name' => 'web']);
    $role->givePermissionTo(PublishingPermission::View->value);
    $user->assignRole([AdminRole::Access->value, $role]);
    adminHomeAttempt($user, 'Readable piece', ['angle' => ['thesis' => 'An argument']]);

    $this->actingAs($user)->get('http://admin.birdcar.test/')
        ->assertSee('Readable piece')->assertSee('Nothing needs your attention')->assertDontSee('Review brief');
});

test('home rechecks admission and publishing access on refresh', function (string $role, bool $forbidden): void {
    $user = adminHomeUser();
    adminHomeAttempt($user, 'Revoked private title', ['paused_at' => now()]);
    $component = Livewire::actingAs($user)->test('admin.index')->assertSee('Revoked private title');
    $user->removeRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $component->call('$refresh');

    if ($forbidden) {
        $component->assertForbidden();
    } else {
        $component->assertSee('Your workspace is ready')->assertDontSee('Revoked private title');
    }
})->with([
    'admin revoked' => [AdminRole::Access->value, true],
    'publishing revoked' => [PublishingRole::Author->value, false],
]);

test('attention is bounded without overlooking blockers beyond the first batch', function (): void {
    $this->freezeTime();
    $user = adminHomeUser();
    $blocked = adminHomeAttempt($user, 'An older blocker', ['paused_at' => now()]);
    $blocked->article->update(['updated_at' => now()->subMonth()]);
    for ($index = 0; $index < 51; $index++) {
        adminHomeAttempt($user, 'Active piece '.$index);
    }

    $items = app(ReadAdminHome::class)->forUser($user);

    expect($items['continuing'])->toHaveCount(5);
    expect($items['blocked'])->toHaveCount(1);
    expect($items['blocked'][0]['article']->id)->toBe($blocked->article_id);
});

test('a changed plan does not surface an obsolete agent failure', function (): void {
    $user = adminHomeUser();
    $attempt = adminHomeAttempt($user, 'Changed plan', ['stage' => EditorialStage::Drafting, 'plan' => ['outline' => ['New outline']]]);
    $hash = app(ApprovePublishingStage::class)->inputHashFor($attempt, ApprovalKind::Angle);
    EditorialApproval::factory()->create(['attempt_id' => $attempt->id, 'user_id' => $user->id, 'kind' => ApprovalKind::Angle, 'input_hash' => $hash]);
    adminHomeActivity($attempt, ['kind' => EditorialActivityKind::Plan, 'status' => EditorialActivityStatus::Failed, 'input' => ['plan' => [], 'approval_hashes' => ['angle' => $hash]]]);

    $items = app(ReadAdminHome::class)->forUser($user);

    expect($items['blocked'])->toBeEmpty();
});

test('home projects a batch without a per article query increase', function (): void {
    $user = adminHomeUser();
    for ($index = 0; $index < 6; $index++) {
        adminHomeAttempt($user, 'Blocked '.$index, ['paused_at' => now()]);
    }
    app(ReadAdminHome::class)->forUser($user);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $items = app(ReadAdminHome::class)->forUser($user);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($items['blocked'])->toHaveCount(5);
    expect($queryCount)->toBeLessThanOrEqual(6);
});

test('home escapes user supplied titles', function (): void {
    $user = adminHomeUser();
    $title = '<script>alert("title")</script>';
    adminHomeAttempt($user, $title, ['paused_at' => now()]);

    $this->actingAs($user)->get('http://admin.birdcar.test/')
        ->assertSee($title)->assertDontSee($title, false);
});

test('a publishing query failure is unavailable rather than an empty queue', function (): void {
    $user = adminHomeUser();
    Exceptions::fake();
    $failQuery = true;
    DB::connection()->beforeExecuting(function (string $query, array $bindings) use (&$failQuery): void {
        if ($failQuery && str_contains($query, 'from "articles"')) {
            throw new QueryException('testing', $query, $bindings, new RuntimeException('Simulated read failure'));
        }
    });

    try {
        $response = $this->actingAs($user)->get('http://admin.birdcar.test/');
    } finally {
        $failQuery = false;
    }

    $response->assertSee('Publishing work couldn’t be loaded')->assertDontSee('Nothing needs your attention')->assertDontSee('Simulated read failure');
    Exceptions::assertReported(QueryException::class);
});
