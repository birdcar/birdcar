<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\EditorialActivity;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
    config()->set('ai.providers.openrouter.key', 'test-key');
    setPublishingAgentsPaused(false);
    Bus::fake([RunEditorialActivity::class]);
    Http::preventStrayRequests();
});

/** @return array<string, mixed> */
function agentInterviewCompletion(array $extra = []): array
{
    return array_merge([
        'id' => 'gen-test',
        'model' => 'google/gemini-3.8-flash',
        'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode(['questions' => ['What changed?'], 'brief' => ['summary' => 'Brief'], 'angleOptions' => []])]]],
    ], $extra);
}

function runAgentJob(EditorialActivity $activity): void
{
    app()->call([new RunEditorialActivity((int) $activity->id), 'handle']);
}

/** @return array{User, PublishingAttempt} */
function approvedAngleAttempt(): array
{
    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $approve = app(ApprovePublishingStage::class);
    setPublishingAgentsPaused(true);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    setPublishingAgentsPaused(false);

    return [$actor, $attempt->fresh()];
}

test('valid output completes without cost metadata or a billing lookup and duplicate delivery is idempotent', function (): void {
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response(agentInterviewCompletion())]);
    $actor = agentBudgetAuthor();
    $activity = app(StartEditorialActivity::class)->start($actor, agentBudgetAttempt($actor), EditorialActivityKind::Interview, [], 'no-cost');

    runAgentJob($activity);
    runAgentJob($activity);

    $activity->refresh();
    expect($activity->status)->toBe(EditorialActivityStatus::Completed)
        ->and($activity->run_count)->toBe(1)
        ->and($activity->generation_id)->toBe('gen-test')
        ->and($activity->model_snapshot)->toBe([
            'requested_model' => 'google/gemini-3.8-flash',
            'model' => 'google/gemini-3.8-flash',
            'reasoning_effort' => 'low',
            'returned_model' => 'google/gemini-3.8-flash',
        ]);
    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/generation'));
});

test('queued activity with invalidated approval becomes stale before any request', function (): void {
    [$actor, $attempt] = approvedAngleAttempt();
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::ResearchChallenge, [], 'stale-before-call');
    app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, angle: ['thesis' => 'Changed after queueing']);

    runAgentJob($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Stale);
    Http::assertNothingSent();
});

test('activity revalidates frozen inputs between claim and the provider request', function (): void {
    [$actor, $attempt] = approvedAngleAttempt();
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::ResearchChallenge, [], 'stale-after-claim');
    EditorialActivity::saved(function (EditorialActivity $claimed) use ($actor, $attempt): void {
        if ($claimed->wasChanged('ai_conversation_id')) {
            app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, angle: ['thesis' => 'Changed after claim']);
        }
    });

    runAgentJob($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Stale)
        ->and($activity->fresh()->error_reason)->toContain('changed before the activity could run');
    Http::assertNothingSent();
});

test('permissions revoked between claim and the provider request pause without a request', function (): void {
    $actor = agentBudgetAuthor();
    $activity = app(StartEditorialActivity::class)->start($actor, agentBudgetAttempt($actor), EditorialActivityKind::Interview, [], 'revoked-after-claim');
    EditorialActivity::saved(function (EditorialActivity $claimed) use ($actor): void {
        if ($claimed->wasChanged('ai_conversation_id')) {
            $actor->syncRoles([]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    });

    runAgentJob($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($activity->fresh()->pause_reason)->toContain('can no longer run');
    Http::assertNothingSent();
});

test('output is not applied when inputs change during the provider request', function (): void {
    [$actor, $attempt] = approvedAngleAttempt();
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::ResearchChallenge, [], 'stale-during-call');
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => function () use ($actor, $attempt) {
            app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, angle: ['thesis' => 'Changed during call']);

            return Http::response(agentInterviewCompletion([
                'id' => 'gen-stale',
                'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode(['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []])]]],
            ]));
        },
    ]);

    runAgentJob($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Stale)
        ->and($activity->fresh()->generation_id)->toBe('gen-stale');
});

test('revoked initiating actor cannot have output applied after the call', function (): void {
    $actor = agentBudgetAuthor();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => function () use ($actor) {
            $actor->syncRoles([]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return Http::response(agentInterviewCompletion(['id' => 'gen-revoked']));
        },
    ]);
    $activity = app(StartEditorialActivity::class)->start($actor, agentBudgetAttempt($actor), EditorialActivityKind::Interview, [], 'revoked-completion');

    runAgentJob($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($activity->fresh()->response)->toBeNull()
        ->and($activity->fresh()->generation_id)->toBe('gen-revoked');
});

test('draft activities pause before any request when the actor loses write access', function (): void {
    [$actor, $attempt] = approvedAngleAttempt();
    $approve = app(ApprovePublishingStage::class);
    EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => EditorialActivityKind::ResearchChallenge,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'drafting',
        'input_version' => $attempt->input_version,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'precall-research',
        'idempotency_key' => 'precall-research-'.$attempt->id,
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', 'precall-research-'.$attempt->id),
        'input' => ['approval_hashes' => [ApprovalKind::Angle->value => $approve->inputHashFor($attempt, ApprovalKind::Angle)]],
        'model_snapshot' => [],
        'response' => ['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []],
        'completed_at' => now(),
    ]);
    $attempt = app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, plan: ['outline' => ['Draft plan'], 'argument' => 'Draft argument', 'visualPlan' => ['Visual']]);
    setPublishingAgentsPaused(true);
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    setPublishingAgentsPaused(false);
    $draft = app(StartEditorialActivity::class)->start($actor, $attempt->fresh(), EditorialActivityKind::Draft, [], 'draft-lost-write');

    $actor->syncRoles([]);
    $actor->syncPermissions([
        PublishingPermission::View->value,
        PublishingPermission::Develop->value,
        PublishingPermission::Approve->value,
        PublishingPermission::Publish->value,
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    runAgentJob($draft);

    expect($draft->fresh()->status)->toBe(EditorialActivityStatus::Paused);
    Http::assertNothingSent();
});

test('deterministic provider rejections pause with an actionable error that never echoes secrets', function (int $status, string $expected): void {
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => ['message' => 'Rejected key test-key Authorization: Bearer test-key']], $status)]);
    $actor = agentBudgetAuthor();
    $activity = app(StartEditorialActivity::class)->start($actor, agentBudgetAttempt($actor), EditorialActivityKind::Interview, [], 'rejected-'.$status);

    runAgentJob($activity);
    runAgentJob($activity);

    $activity->refresh();
    expect($activity->status)->toBe(EditorialActivityStatus::Paused)
        ->and($activity->pause_reason)->toContain($expected)
        ->and($activity->error_reason)->toBe($activity->pause_reason)
        ->and($activity->pause_reason)->not->toContain('test-key')
        ->and($activity->pause_reason)->not->toContain('Bearer');
    Http::assertSentCount(1);
})->with([
    'unauthorized' => [401, 'Check the OpenRouter API key'],
    'no credit' => [402, 'no remaining credit or limit'],
    'unsupported parameter' => [400, 'supports the requested parameters'],
]);

test('a pre-generation rate limit fails with the bounded retry state instead of pausing', function (): void {
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => ['message' => 'Slow down']], 429)]);
    $actor = agentBudgetAuthor();
    $activity = app(StartEditorialActivity::class)->start($actor, agentBudgetAttempt($actor), EditorialActivityKind::Interview, [], 'rate-limited');

    runAgentJob($activity);

    $activity->refresh();
    expect($activity->status)->toBe(EditorialActivityStatus::Failed)
        ->and($activity->error_reason)->toContain('HTTP 429')
        ->and($activity->available_at->isFuture())->toBeTrue();

    runAgentJob($activity);
    runAgentJob($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($activity->fresh()->run_count)->toBe(3);
    Http::assertSentCount(3);
});

test('a timeout pauses as an uncertain outcome and is not blindly retried', function (): void {
    $attempts = 0;
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('timeout after send');
    }]);
    $actor = agentBudgetAuthor();
    $activity = app(StartEditorialActivity::class)->start($actor, agentBudgetAttempt($actor), EditorialActivityKind::Interview, [], 'timeout');

    runAgentJob($activity);
    runAgentJob($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($activity->fresh()->pause_reason)->toContain('outcome is uncertain')
        ->and($activity->fresh()->run_count)->toBe(1)
        ->and($attempts)->toBe(1);
});

test('a truncated answer pauses with the output limit reason instead of retrying', function (): void {
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response([
        'id' => 'gen-truncated',
        'model' => 'google/gemini-3.8-flash',
        'choices' => [['finish_reason' => 'length', 'message' => ['role' => 'assistant', 'content' => '{"questions": ["What chan']]],
    ])]);
    $actor = agentBudgetAuthor();
    $activity = app(StartEditorialActivity::class)->start($actor, agentBudgetAttempt($actor), EditorialActivityKind::Interview, [], 'truncated');

    runAgentJob($activity);
    runAgentJob($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($activity->fresh()->pause_reason)->toBe(RunEditorialActivity::OUTPUT_LIMIT_ERROR)
        ->and($activity->fresh()->response)->toBeNull();
    Http::assertSentCount(1);
});

test('missing credentials pause before any provider request', function (): void {
    config()->set('ai.providers.openrouter.key', '');
    $actor = agentBudgetAuthor();
    $activity = app(StartEditorialActivity::class)->start($actor, agentBudgetAttempt($actor), EditorialActivityKind::Interview, [], 'no-key');

    runAgentJob($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($activity->fresh()->pause_reason)->toBe(EditorialActivity::MISSING_CREDENTIALS_PAUSE_REASON);
    Http::assertNothingSent();
});

test('recovery re-dispatches pending work and pauses ambiguous running work without provider calls', function (): void {
    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $pending = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'recover-pending');
    $running = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, ['note' => 'running'], 'recover-running');
    $running->forceFill(['status' => 'running', 'started_at' => now()->subHour(), 'generation_id' => 'gen-interrupted', 'ai_conversation_id' => null])->save();
    Bus::fake([RunEditorialActivity::class]);

    $this->artisan('publishing:recover-activities')->assertSuccessful();

    expect($running->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($running->fresh()->pause_reason)->toContain('uncertain')
        ->and($running->fresh()->generation_id)->toBe('gen-interrupted');
    Bus::assertDispatched(RunEditorialActivity::class, fn (RunEditorialActivity $job): bool => $job->activityId === $pending->id);
    Bus::assertNotDispatched(RunEditorialActivity::class, fn (RunEditorialActivity $job): bool => $job->activityId === $running->id);
    Http::assertNothingSent();
});

test('lock contention needs a non-sqlite transactional fixture', function (): void {
    // SQLite's test driver cannot prove row-level lock contention; duplicate-delivery claim races need a transactional non-SQLite database.
    expect(config('database.default'))->not->toBe('production');
});

function agentBudgetAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

function agentBudgetAttempt(User $actor): PublishingAttempt
{
    $writer = app(WriteArticle::class);
    $article = $writer->capture($actor, 'Agent execution work.', 'agent-execution-work-'.uniqid());
    $revision = $writer->save($actor, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'Execution'], 'execution-draft-'.uniqid());

    return app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id, ['goal' => 'Execution']);
}
