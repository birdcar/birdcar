<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\EditorialActivity;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\User;
use App\Settings\PublishingAgentSettings;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
    config()->set('ai.providers.openrouter.key', 'test-key');
    Http::preventStrayRequests();
});

function settingsAttempt(): array
{
    $author = User::factory()->create();
    $author->assignRole(PublishingRole::Author->value);
    $writer = app(WriteArticle::class);
    $article = $writer->capture($author, 'Settings-controlled agent work.', 'settings-agent-work-'.uniqid());
    $revision = $writer->save($author, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'Settings'], 'settings-draft-'.uniqid());

    return [$author, app(AdvancePublishingAttempt::class)->develop($author, $article, $revision->id, ['goal' => 'Settings'])];
}

function settingsPageOwner(): User
{
    $owner = User::factory()->create();
    $owner->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $owner;
}

/** @return array<string, mixed> */
function storedAgentSettings(): array
{
    return DB::table('settings')
        ->where('group', 'publishing_agents')
        ->pluck('payload', 'name')
        ->map(fn (string $payload): mixed => json_decode($payload, true))
        ->all();
}

function interviewResponse(string $model = 'google/gemini-3.8-flash'): array
{
    return [
        'id' => 'gen-settings', 'model' => $model,
        'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode(['questions' => [], 'brief' => ['summary' => 'Brief'], 'angleOptions' => []])]]],
    ];
}

test('a clean install starts paused with no model overrides', function (): void {
    $settings = app(PublishingAgentSettings::class);

    expect($settings->paused)->toBeTrue()
        ->and($settings->model_overrides)->toBe([])
        ->and($settings->modelOverrideFor(EditorialActivityKind::Draft))->toBeNull()
        ->and(DB::table('settings')->where('group', 'publishing_agents')->pluck('name')->sort()->values()->all())->toBe(['model_overrides', 'paused']);
});

test('overrides are saved per role and reset removes them instead of copying the recommendation', function (): void {
    app(PublishingAgentSettings::class)->overrideModel(EditorialActivityKind::Draft, 'deepseek/deepseek-v4-pro-0813')->save();
    app()->forgetScopedInstances();

    $settings = app(PublishingAgentSettings::class);
    expect($settings->modelOverrideFor(EditorialActivityKind::Draft))->toBe('deepseek/deepseek-v4-pro-0813')
        ->and($settings->modelOverrideFor(EditorialActivityKind::Plan))->toBeNull();

    $settings->resetModel(EditorialActivityKind::Draft)->save();
    app()->forgetScopedInstances();

    expect(app(PublishingAgentSettings::class)->model_overrides)->toBe([])
        ->and(json_decode((string) DB::table('settings')->where('name', 'model_overrides')->value('payload'), true))->toBe([]);
});

test('unknown models are refused and malformed stored roles are dropped on the next save', function (): void {
    $settings = app(PublishingAgentSettings::class);

    expect(fn () => $settings->overrideModel(EditorialActivityKind::Draft, 'openai/gpt-unlisted'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $settings->overrideModel(EditorialActivityKind::Draft, ''))->toThrow(InvalidArgumentException::class);

    $settings->model_overrides = ['not_a_role' => 'google/gemini-3.8-flash', 'plan' => 'openai/gpt-unlisted', 'draft' => 'openrouter/auto'];
    $settings->save();
    $settings->overrideModel(EditorialActivityKind::ReviewFacts, 'deepseek/deepseek-v4.1-flash')->save();

    expect($settings->model_overrides)->toBe(['draft' => 'openrouter/auto', 'review_facts' => 'deepseek/deepseek-v4.1-flash']);
});

test('a stored override outside the allowlist pauses the activity with an actionable error before any request', function (): void {
    setPublishingAgentsPaused(false);
    DB::table('settings')->where('group', 'publishing_agents')->where('name', 'model_overrides')->update(['payload' => json_encode(['interview' => 'openai/gpt-unlisted'])]);
    app()->forgetScopedInstances();
    Bus::fake([RunEditorialActivity::class]);
    [$author, $attempt] = settingsAttempt();
    $activity = app(StartEditorialActivity::class)->start($author, $attempt, EditorialActivityKind::Interview);

    app()->call([new RunEditorialActivity($activity->id), 'handle']);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($activity->fresh()->pause_reason)->toContain('Reset it in the publishing agent settings');
    Http::assertNothingSent();
});

test('each job resolves settings fresh so a warm worker sees a save made in another scope', function (): void {
    Bus::fake([RunEditorialActivity::class]);
    setPublishingAgentsPaused(false);
    [$author, $attempt] = settingsAttempt();
    $first = app(StartEditorialActivity::class)->start($author, $attempt, EditorialActivityKind::Interview, [], 'first-job');
    $second = app(StartEditorialActivity::class)->start($author, $attempt, EditorialActivityKind::Interview, ['note' => 'second'], 'second-job');
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response(interviewResponse('deepseek/deepseek-v4.1-flash'))]);

    expect(app(PublishingAgentSettings::class)->paused)->toBeFalse();
    $otherScope = new PublishingAgentSettings;
    $otherScope->overrideModel(EditorialActivityKind::Interview, 'deepseek/deepseek-v4.1-flash')->save();
    app()->forgetScopedInstances();

    app()->call([new RunEditorialActivity($first->id), 'handle']);
    expect($first->fresh()->model_snapshot['requested_model'])->toBe('deepseek/deepseek-v4.1-flash');
    Http::assertSent(fn ($request): bool => $request['model'] === 'deepseek/deepseek-v4.1-flash');

    $pausingScope = new PublishingAgentSettings;
    $pausingScope->paused = true;
    $pausingScope->save();
    app()->forgetScopedInstances();

    app()->call([new RunEditorialActivity($second->id), 'handle']);
    expect($second->fresh()->status)->toBe(EditorialActivityStatus::Pending)
        ->and($second->fresh()->run_count)->toBe(0);
    Http::assertSentCount(1);
});

test('pause keeps new and queued work durable without dispatching or claiming it', function (): void {
    Bus::fake([RunEditorialActivity::class]);
    [$author, $attempt] = settingsAttempt();

    $activity = app(StartEditorialActivity::class)->start($author, $attempt, EditorialActivityKind::Interview);
    Bus::assertNotDispatched(RunEditorialActivity::class);
    app()->call([new RunEditorialActivity($activity->id), 'handle']);
    $this->artisan('publishing:recover-activities')->expectsOutputToContain('paused')->assertSuccessful();

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Pending)
        ->and($activity->fresh()->run_count)->toBe(0)
        ->and($activity->fresh()->started_at)->toBeNull();
    Bus::assertNotDispatched(RunEditorialActivity::class);
    Http::assertNothingSent();

    setPublishingAgentsPaused(false);
    $this->artisan('publishing:recover-activities')->assertSuccessful();
    Bus::assertDispatched(RunEditorialActivity::class, fn (RunEditorialActivity $job): bool => $job->activityId === $activity->id);
});

test('approval while paused does not create follow-up agent work', function (): void {
    Bus::fake([RunEditorialActivity::class]);
    [$author, $attempt] = settingsAttempt();
    $approve = app(ApprovePublishingStage::class);

    $approve->approve($author, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));

    expect(EditorialActivity::query()->where('attempt_id', $attempt->id)->count())->toBe(0);
    Bus::assertNotDispatched(RunEditorialActivity::class);

    setPublishingAgentsPaused(false);
    [$author, $unpausedAttempt] = settingsAttempt();
    $approve->approve($author, $unpausedAttempt, ApprovalKind::Angle, $approve->inputHashFor($unpausedAttempt, ApprovalKind::Angle));

    expect(EditorialActivity::query()->where('attempt_id', $unpausedAttempt->id)->where('kind', EditorialActivityKind::ResearchChallenge->value)->count())->toBe(1);
    Bus::assertDispatched(RunEditorialActivity::class);
});

test('guests are sent to sign in before the settings page', function (): void {
    $this->get('http://admin.birdcar.test/publishing/settings')->assertRedirect('/login');
});

test('the settings page is forbidden without the agent configuration capability', function (User $user): void {
    $this->actingAs($user)
        ->get('http://admin.birdcar.test/publishing/settings')
        ->assertForbidden();

    Livewire::actingAs($user)->test('admin.publishing.settings')->assertForbidden();
})->with([
    'admin access only' => fn (): User => tap(User::factory()->create())->assignRole(AdminRole::Access->value),
    'publishing access without configuration' => function (): User {
        $user = User::factory()->create();
        $user->assignRole(AdminRole::Access->value);
        $user->givePermissionTo(PublishingPermission::View->value, PublishingPermission::Write->value, PublishingPermission::Develop->value);

        return $user;
    },
]);

test('agent configurers reach the settings page with its navigation current', function (): void {
    $response = $this->actingAs(settingsPageOwner())
        ->get('http://admin.birdcar.test/publishing/settings')
        ->assertOk()
        ->assertSee('Publishing agent settings')
        ->assertSee('Agent requests')
        ->assertSee('Models by task');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $current = (new DOMXPath($document))->query('//nav[@aria-label="Publishing navigation"]//a[@aria-current="page"]');
    expect($current->length)->toBe(1)
        ->and($current->item(0)->getAttribute('href'))->toBe('http://admin.birdcar.test/publishing/settings');
});

test('saving an override persists it and reset returns the task to its recommendation', function (): void {
    $owner = settingsPageOwner();

    Livewire::actingAs($owner)->test('admin.publishing.settings')
        ->assertSet('models.draft', '')
        ->set('models.draft', 'deepseek/deepseek-v4-pro-0813')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Settings saved. Agent requests are paused.');

    expect(storedAgentSettings())->toEqual(['paused' => true, 'model_overrides' => ['draft' => 'deepseek/deepseek-v4-pro-0813']]);

    app()->forgetScopedInstances();
    Livewire::actingAs($owner)->test('admin.publishing.settings')
        ->assertSet('models.draft', 'deepseek/deepseek-v4-pro-0813')
        ->assertSee('Saved: DeepSeek V4 Pro')
        ->call('resetRole', 'draft')
        ->assertSet('models.draft', '')
        ->assertSee('Draft now follows its recommended model.')
        ->assertDontSee('Saved: DeepSeek V4 Pro');

    expect(storedAgentSettings()['model_overrides'])->toBe([]);
});

test('pinning the current recommendation is stored separately from following it', function (): void {
    $page = Livewire::actingAs(settingsPageOwner())->test('admin.publishing.settings')
        ->assertSet('models.interview', '')
        ->set('models.interview', 'google/gemini-3.8-flash')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSeeHtml('data-override-state="pinned"');

    expect(storedAgentSettings()['model_overrides'])->toBe(['interview' => 'google/gemini-3.8-flash']);

    $page->set('models.interview', '')->call('save')->assertHasNoErrors();

    expect(storedAgentSettings()['model_overrides'])->toBe([]);
});

test('tampered settings are rejected without saving anything', function (string $property, mixed $value, string $error, string $message): void {
    Livewire::actingAs(settingsPageOwner())->test('admin.publishing.settings')
        ->set('paused', false)
        ->set($property, $value)
        ->call('save')
        ->assertHasErrors($error)
        ->assertSee($message)
        ->assertSee('Nothing was saved.');

    expect(storedAgentSettings())->toEqual(['paused' => true, 'model_overrides' => []]);
})->with([
    'unknown role' => ['models.not_a_role', 'google/gemini-3.8-flash', 'models', 'Choose models only for the listed agent tasks.'],
    'missing role' => ['models', ['draft' => ''], 'models', 'Choose a model setting for every agent task.'],
    'unlisted model' => ['models.draft', 'openai/gpt-unlisted', 'models.draft', 'Choose one of the listed models.'],
    'non-string model' => ['models.plan', ['google/gemini-3.8-flash'], 'models.plan', 'Choose one of the listed models.'],
]);

test('resetting an unknown role is rejected without saving', function (): void {
    app(PublishingAgentSettings::class)->overrideModel(EditorialActivityKind::Draft, 'openrouter/auto')->save();

    Livewire::actingAs(settingsPageOwner())->test('admin.publishing.settings')
        ->call('resetRole', 'not_a_role')
        ->assertHasErrors('models');

    expect(storedAgentSettings()['model_overrides'])->toBe(['draft' => 'openrouter/auto']);
});

test('an unsupported stored override is shown as reset required and reset removes it', function (): void {
    DB::table('settings')->where('group', 'publishing_agents')->where('name', 'model_overrides')
        ->update(['payload' => json_encode(['interview' => 'openai/gpt-retired', 'draft' => 'openrouter/auto'])]);
    app()->forgetScopedInstances();

    Livewire::actingAs(settingsPageOwner())->test('admin.publishing.settings')
        ->assertSet('models.interview', '')
        ->assertSet('models.draft', 'openrouter/auto')
        ->assertSeeHtml('data-override-state="unsupported"')
        ->assertSee('openai/gpt-retired')
        ->call('resetRole', 'interview')
        ->assertDontSeeHtml('data-override-state="unsupported"');

    expect(storedAgentSettings()['model_overrides'])->toBe(['draft' => 'openrouter/auto']);
});

test('the pause switch changes nothing until saved', function (): void {
    $page = Livewire::actingAs(settingsPageOwner())->test('admin.publishing.settings')
        ->assertSet('paused', true)
        ->set('paused', false)
        ->assertSee('Saved: Paused');

    app()->forgetScopedInstances();
    expect(app(PublishingAgentSettings::class)->paused)->toBeTrue();

    $page->call('save')
        ->assertSee('Saved: On')
        ->assertSee('Settings saved. Agent requests are on.');

    app()->forgetScopedInstances();
    expect(app(PublishingAgentSettings::class)->paused)->toBeFalse();
});

test('saving settings never dispatches jobs or sends requests for existing work', function (): void {
    Bus::fake();
    $snapshot = ['requested_model' => 'google/gemini-3.8-flash', 'model' => 'google/gemini-3.8-flash', 'reasoning_effort' => 'low'];
    [$author, $attempt] = settingsAttempt();
    $work = ['article_id' => $attempt->article_id, 'attempt_id' => $attempt->id, 'initiating_user_id' => $author->id];
    $pending = EditorialActivity::factory()->create([...$work, 'status' => EditorialActivityStatus::Pending]);
    $awaiting = EditorialActivity::factory()->create([...$work, 'status' => EditorialActivityStatus::AwaitingApproval, 'model_snapshot' => $snapshot]);

    Livewire::actingAs(settingsPageOwner())->test('admin.publishing.settings')
        ->set('paused', false)
        ->set('models.interview', 'deepseek/deepseek-v4.1-flash')
        ->call('save')
        ->assertHasNoErrors()
        ->set('paused', true)
        ->call('save')
        ->call('resetRole', 'interview')
        ->assertHasNoErrors();

    Bus::assertNothingDispatched();
    Http::assertNothingSent();
    expect($pending->fresh()->status)->toBe(EditorialActivityStatus::Pending)
        ->and($awaiting->fresh()->status)->toBe(EditorialActivityStatus::AwaitingApproval)
        ->and($awaiting->fresh()->model_snapshot)->toBe($snapshot);
});

test('revoked configuration access cannot save pause or reset settings', function (string $revokedRole): void {
    $owner = settingsPageOwner();
    app(PublishingAgentSettings::class)->overrideModel(EditorialActivityKind::Draft, 'openrouter/auto')->save();
    $savePage = Livewire::actingAs($owner)->test('admin.publishing.settings')
        ->set('paused', false)
        ->set('models.plan', 'deepseek/deepseek-v4.1-flash');
    $resetPage = Livewire::actingAs($owner)->test('admin.publishing.settings');

    $owner->removeRole($revokedRole);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $savePage->call('save')->assertForbidden();
    $resetPage->call('resetRole', 'draft')->assertForbidden();

    expect(storedAgentSettings())->toEqual(['paused' => true, 'model_overrides' => ['draft' => 'openrouter/auto']]);
})->with([
    'publishing author role' => PublishingRole::Author->value,
    'admin access role' => AdminRole::Access->value,
]);

test('forged settings actions from users without the capability are forbidden', function (User $intruder): void {
    app(PublishingAgentSettings::class)->overrideModel(EditorialActivityKind::Draft, 'openrouter/auto')->save();
    $owner = settingsPageOwner();
    $savePage = Livewire::actingAs($owner)->test('admin.publishing.settings')
        ->set('paused', false)
        ->set('models.plan', 'deepseek/deepseek-v4.1-flash');
    $resetPage = Livewire::actingAs($owner)->test('admin.publishing.settings');

    Livewire::actingAs($intruder);

    $savePage->call('save')->assertForbidden();
    $resetPage->call('resetRole', 'draft')->assertForbidden();

    expect(storedAgentSettings())->toEqual(['paused' => true, 'model_overrides' => ['draft' => 'openrouter/auto']]);
})->with([
    'admin access only' => fn (): User => tap(User::factory()->create())->assignRole(AdminRole::Access->value),
    'publishing access without configuration' => function (): User {
        $user = User::factory()->create();
        $user->assignRole(AdminRole::Access->value);
        $user->givePermissionTo(PublishingPermission::View->value, PublishingPermission::Write->value, PublishingPermission::Develop->value);

        return $user;
    },
]);

test('the page and its livewire payloads never expose the provider key or url', function (): void {
    $secret = 'sk-or-v1-settings-secret-7f3a9c';
    $privateUrl = 'https://openrouter.example.test/private-route';
    config()->set('ai.providers.openrouter.key', $secret);
    config()->set('ai.providers.openrouter.url', $privateUrl);
    $owner = settingsPageOwner();

    $this->actingAs($owner)
        ->get('http://admin.birdcar.test/publishing/settings')
        ->assertOk()
        ->assertSee('An OpenRouter API key is configured for this deployment.')
        ->assertDontSee('sk-or')
        ->assertDontSee('7f3a9c')
        ->assertDontSee($privateUrl);

    $page = Livewire::actingAs($owner)->test('admin.publishing.settings')
        ->set('models.draft', 'openrouter/auto')
        ->call('save');
    $payloads = json_encode([$page->snapshot, $page->effects]);

    expect($payloads)->not->toContain('sk-or')
        ->and($payloads)->not->toContain('7f3a9c')
        ->and($payloads)->not->toContain('openrouter.example.test');
});

test('a missing provider key shows a configuration notice without provider details', function (): void {
    config()->set('ai.providers.openrouter.key', '');
    config()->set('ai.providers.openrouter.url', 'https://openrouter.example.test/private-route');

    $this->actingAs(settingsPageOwner())
        ->get('http://admin.birdcar.test/publishing/settings')
        ->assertOk()
        ->assertSee('No OpenRouter API key is configured')
        ->assertSee('OPENROUTER_API_KEY')
        ->assertDontSee('An OpenRouter API key is configured for this deployment.')
        ->assertDontSee('openrouter.example.test');
});

test('page saves reach queued work in the next job scope without a restart', function (): void {
    Bus::fake([RunEditorialActivity::class]);
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response(interviewResponse('deepseek/deepseek-v4.1-flash'))]);
    [$author, $attempt] = settingsAttempt();
    $queuedWhilePaused = app(StartEditorialActivity::class)->start($author, $attempt, EditorialActivityKind::Interview, [], 'queued-while-paused');
    $page = Livewire::actingAs(settingsPageOwner())->test('admin.publishing.settings');

    $page->set('paused', false)->set('models.interview', 'deepseek/deepseek-v4.1-flash')->call('save')->assertHasNoErrors();
    app()->forgetScopedInstances();
    app()->call([new RunEditorialActivity($queuedWhilePaused->id), 'handle']);

    expect($queuedWhilePaused->fresh()->model_snapshot['requested_model'])->toBe('deepseek/deepseek-v4.1-flash');
    Http::assertSent(fn ($request): bool => $request['model'] === 'deepseek/deepseek-v4.1-flash');

    $queuedBeforePause = app(StartEditorialActivity::class)->start($author, $attempt, EditorialActivityKind::Interview, ['note' => 'second'], 'queued-before-pause');
    $page->set('paused', true)->call('save')->assertHasNoErrors();
    app()->forgetScopedInstances();
    app()->call([new RunEditorialActivity($queuedBeforePause->id), 'handle']);

    expect($queuedBeforePause->fresh()->status)->toBe(EditorialActivityStatus::Pending)
        ->and($queuedBeforePause->fresh()->run_count)->toBe(0);
    Http::assertSentCount(1);
});
