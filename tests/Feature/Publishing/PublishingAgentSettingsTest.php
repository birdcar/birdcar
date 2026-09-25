<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
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
