<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Ai\Agents\Interviewer;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\AgentBudgetReservation;
use App\Models\EditorialActivity;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\AgentBudget;
use App\Services\Publishing\EditorialModelBudget;
use App\Services\Publishing\EditorialOutput;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('budget reservations are atomic idempotent and bounded by allowance', function (): void {
    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $budget = app(AgentBudget::class);

    $first = $budget->reserve($actor, $attempt, null, 4_000_000_000);
    expect(fn () => $budget->reserve($actor, $attempt, null, 1_000_000_001))->toThrow(RuntimeException::class, 'allowance');

    $settled = $budget->settle($first, 3_500_000_000, 'gen-1');
    expect($settled->state)->toBe(AgentBudgetReservation::STATE_SETTLED)
        ->and($budget->settle($settled, 3_500_000_000, 'gen-1')->id)->toBe($settled->id)
        ->and(fn () => $budget->settle($settled, 3_400_000_000))->toThrow(RuntimeException::class, 'settled once');

    $topUp = $budget->increaseAllowance($actor, $attempt, 1_000_000_000, 'top-up-1');
    $sameTopUp = $budget->increaseAllowance($actor, $topUp, 1_000_000_000, 'top-up-1');

    expect($sameTopUp->allowance_nano_usd)->toBe(6_000_000_000)
        ->and(fn () => $budget->increaseAllowance($actor, $sameTopUp, 2_000_000_000, 'top-up-1'))->toThrow(RuntimeException::class, 'mutation key');
});

test('openrouter quote converts per million prices and denies hidden fallback', function (): void {
    config()->set('publishing_agents.enabled', true);
    config()->set('ai.providers.openrouter.key', 'test-key');
    $client = app(EditorialModelBudget::class);

    $endpoint = $client->pricedEndpoint([
        'model' => 'test/model',
        'provider' => 'TestProvider',
        'max_completion_tokens' => 100,
        'context_tokens' => 1000,
        'pricing' => ['prompt' => '2.00', 'completion' => '4.00', 'unit' => 'per_million'],
    ]);
    $quote = $client->quote($endpoint, ['prompt_tokens' => 1000, 'max_completion_tokens' => 100, 'plugin_nano_usd' => 7_000_000]);
    $request = (new Interviewer(new EditorialActivity, $endpoint))->providerOptions(Lab::OpenRouter);

    expect($quote['reserved_nano_usd'])->toBe(9_400_000)
        ->and($request['provider']['only'])->toBe(['TestProvider'])
        ->and($request['provider']['allow_fallbacks'])->toBeFalse()
        ->and($request['provider']['max_price'])->toBe(['prompt' => '2.00', 'completion' => '4.00']);
});

test('activity execution reserves before fake http and settles exactly once', function (): void {
    config()->set('publishing_agents.enabled', true);
    config()->set('ai.providers.openrouter.key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '0.000001');
    config()->set('publishing_agents.routes.default.pricing.completion', '0.000002');
    config()->set('publishing_agents.routes.default.context_tokens', 100);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 50);

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'gen-test',
            'choices' => [['message' => ['content' => json_encode(['questions' => ['What changed?'], 'brief' => [], 'angleOptions' => []])]]],
            'usage' => ['cost' => '0.00005'],
        ]),
    ]);

    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'interview-test');

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));
    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));

    expect($activity->fresh()?->status->value)->toBe('completed')
        ->and(AgentBudgetReservation::query()->where('activity_id', $activity->id)->count())->toBe(1)
        ->and(AgentBudgetReservation::query()->first()?->actual_nano_usd)->toBe(50_000);
});

test('integer provider cost fields are interpreted as usd not nano usd', function (): void {
    config()->set('publishing_agents.enabled', true);
    config()->set('ai.providers.openrouter.key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '0.000001');
    config()->set('publishing_agents.routes.default.pricing.completion', '0.000002');
    config()->set('publishing_agents.routes.default.context_tokens', 100);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 50);

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'gen-integer-cost',
            'choices' => [['message' => ['content' => json_encode(['questions' => [], 'brief' => [], 'angleOptions' => []])]]],
            'usage' => ['cost' => 1],
        ]),
    ]);

    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'integer-cost');

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));

    expect(AgentBudgetReservation::query()->where('activity_id', $activity->id)->first()?->actual_nano_usd)->toBe(1_000_000_000);
});

test('queued activity with invalidated approval becomes stale before reservation or http', function (): void {
    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $approve = app(ApprovePublishingStage::class);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh();
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::ResearchChallenge, [], 'stale-before-spend');

    app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, angle: ['thesis' => 'Changed after queueing']);
    Http::preventStrayRequests();

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));

    expect($activity->fresh()?->status->value)->toBe('stale')
        ->and(AgentBudgetReservation::query()->where('activity_id', $activity->id)->exists())->toBeFalse();
});

test('activity revalidates frozen inputs immediately before budget reservation', function (): void {
    config()->set('publishing_agents.enabled', false);
    config()->set('ai.providers.openrouter.key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '0.000001');
    config()->set('publishing_agents.routes.default.pricing.completion', '0.000002');
    config()->set('publishing_agents.routes.default.context_tokens', 100);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 50);

    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $approve = app(ApprovePublishingStage::class);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh();
    config()->set('publishing_agents.enabled', false);
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::ResearchChallenge, [], 'stale-at-reserve');
    config()->set('publishing_agents.enabled', true);
    Http::preventStrayRequests();

    $client = new class($actor, $attempt) extends EditorialModelBudget
    {
        public function __construct(private User $actor, private PublishingAttempt $attempt) {}

        public function quote(array $endpoint, array $request): array
        {
            app(AdvancePublishingAttempt::class)->rethink($this->actor, $this->attempt, angle: ['thesis' => 'Changed after claim before reserve']);

            return parent::quote($endpoint, $request);
        }
    };

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle($client, app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));

    expect($activity->fresh()?->status->value)->toBe('stale')
        ->and(AgentBudgetReservation::query()->where('activity_id', $activity->id)->exists())->toBeFalse();
});

test('paid output is settled but not applied when inputs change during http', function (): void {
    config()->set('ai.providers.openrouter.key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '0.000001');
    config()->set('publishing_agents.routes.default.pricing.completion', '0.000002');
    config()->set('publishing_agents.routes.default.context_tokens', 100);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 50);

    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $approve = app(ApprovePublishingStage::class);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh();
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::ResearchChallenge, [], 'stale-after-http');
    config()->set('publishing_agents.enabled', true);

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => function () use ($actor, $attempt) {
            app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, angle: ['thesis' => 'Changed during paid call']);

            return Http::response([
                'id' => 'gen-stale-paid',
                'choices' => [['message' => ['content' => json_encode(['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []])]]],
                'usage' => ['cost' => '0.00005'],
            ]);
        },
    ]);

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));

    expect($activity->fresh()?->status->value)->toBe('stale')
        ->and(AgentBudgetReservation::query()->where('activity_id', $activity->id)->first()?->state)->toBe(AgentBudgetReservation::STATE_SETTLED);
});

test('revoked initiating actor cannot have paid output applied after the call', function (): void {
    config()->set('publishing_agents.enabled', true);
    config()->set('ai.providers.openrouter.key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '0.000001');
    config()->set('publishing_agents.routes.default.pricing.completion', '0.000002');
    config()->set('publishing_agents.routes.default.context_tokens', 100);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 50);

    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => function () use ($actor) {
            $actor->syncRoles([]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return Http::response([
                'id' => 'gen-revoked',
                'choices' => [['message' => ['content' => json_encode(['questions' => [], 'brief' => [], 'angleOptions' => []])]]],
                'usage' => ['cost' => '0.00005'],
            ]);
        },
    ]);

    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'revoked-completion');
    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));

    expect($activity->fresh()?->status->value)->toBe('paused')
        ->and($activity->fresh()?->response)->toBeNull()
        ->and(AgentBudgetReservation::query()->where('activity_id', $activity->id)->first()?->state)->toBe(AgentBudgetReservation::STATE_SETTLED);
});

test('draft activities pause before reservation and http when actor loses write access', function (): void {
    config()->set('publishing_agents.enabled', false);

    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $approve = app(ApprovePublishingStage::class);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh();

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
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $draft = app(StartEditorialActivity::class)->start($actor, $attempt->fresh(), EditorialActivityKind::Draft, [], 'draft-lost-write');

    $actor->syncRoles([]);
    $actor->syncPermissions([
        PublishingPermission::View->value,
        PublishingPermission::Develop->value,
        PublishingPermission::Approve->value,
        PublishingPermission::Publish->value,
        PublishingPermission::Budget->value,
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    config()->set('publishing_agents.enabled', true);
    config()->set('ai.providers.openrouter.key', 'test-key');
    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => fn () => throw new RuntimeException('Draft HTTP should not be called without write permission.'),
    ]);

    app(RunEditorialActivity::class, ['activityId' => $draft->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));

    expect($draft->fresh()?->status->value)->toBe('paused')
        ->and(AgentBudgetReservation::query()->where('activity_id', $draft->id)->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('timeout after reservation retains unknown outcome and is not blindly retried', function (): void {
    config()->set('publishing_agents.enabled', true);
    config()->set('ai.providers.openrouter.key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '0.000001');
    config()->set('publishing_agents.routes.default.pricing.completion', '0.000002');
    config()->set('publishing_agents.routes.default.context_tokens', 100);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 50);

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => fn () => throw new ConnectionException('timeout after send'),
    ]);

    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'timeout-test');

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));
    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));

    expect($activity->fresh()?->status->value)->toBe('paused')
        ->and(AgentBudgetReservation::query()->where('activity_id', $activity->id)->count())->toBe(1)
        ->and(AgentBudgetReservation::query()->first()?->state)->toBe(AgentBudgetReservation::STATE_UNKNOWN);
});

test('SDK billing lookup failures retain the paid reservation and generation identity', function (): void {
    config()->set([
        'publishing_agents.enabled' => true,
        'ai.providers.openrouter.key' => 'test-key',
        'publishing_agents.routes.default.pricing.prompt' => '0.000001',
        'publishing_agents.routes.default.pricing.completion' => '0.000002',
        'publishing_agents.routes.default.context_tokens' => 100,
        'publishing_agents.routes.default.max_completion_tokens' => 50,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'gen-billing-pending',
            'choices' => [['message' => ['content' => json_encode(['questions' => [], 'brief' => [], 'angleOptions' => []])]]],
        ]),
        'https://openrouter.ai/api/v1/generation*' => Http::response(['error' => 'Not yet available'], 404),
    ]);
    $actor = agentBudgetAuthor();
    $activity = app(StartEditorialActivity::class)->start($actor, agentBudgetAttempt($actor), EditorialActivityKind::Interview);

    app()->call([new RunEditorialActivity($activity->id), 'handle']);

    $reservation = AgentBudgetReservation::where('activity_id', $activity->id)->sole();
    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($reservation->state)->toBe(AgentBudgetReservation::STATE_UNKNOWN)
        ->and($reservation->provider_generation_id)->toBe('gen-billing-pending');
    Http::assertSentCount(2);
});

test('native SDK insufficient credit rejection releases an unspent reservation', function (): void {
    config()->set([
        'publishing_agents.enabled' => true,
        'ai.providers.openrouter.key' => 'test-key',
        'publishing_agents.routes.default.pricing.prompt' => '0.000001',
        'publishing_agents.routes.default.pricing.completion' => '0.000002',
        'publishing_agents.routes.default.context_tokens' => 100,
        'publishing_agents.routes.default.max_completion_tokens' => 50,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => ['message' => 'Insufficient credits']], 402),
    ]);
    $actor = agentBudgetAuthor();
    $activity = app(StartEditorialActivity::class)->start($actor, agentBudgetAttempt($actor), EditorialActivityKind::Interview);

    app()->call([new RunEditorialActivity($activity->id), 'handle']);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and(AgentBudgetReservation::where('activity_id', $activity->id)->sole()->state)->toBe(AgentBudgetReservation::STATE_RELEASED);
    Http::assertSentCount(1);
});

test('recovery reconciles old running activities with recorded generation ids', function (): void {
    config()->set('ai.providers.openrouter.key', 'test-key');
    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/generation*' => Http::response(['data' => ['total_cost' => '0.000003']], 200),
    ]);

    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'recover-generation');
    $reservation = app(AgentBudget::class)->reserve($actor, $attempt, $activity, 10_000);
    $activity->forceFill([
        'status' => 'running',
        'started_at' => now()->subHour(),
        'generation_id' => 'gen-recoverable',
    ])->save();

    $this->artisan('publishing:recover-activities')->assertSuccessful();

    expect($reservation->fresh()?->state)->toBe(AgentBudgetReservation::STATE_SETTLED)
        ->and($reservation->fresh()?->actual_nano_usd)->toBe(3_000)
        ->and($activity->fresh()?->status->value)->toBe('paused')
        ->and($activity->fresh()?->pause_reason)->toContain('Recovery settled');
});

test('recovery treats integer generation costs as usd not nano usd', function (): void {
    config()->set('ai.providers.openrouter.key', 'test-key');
    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/generation*' => Http::response(['data' => ['total_cost' => 1]], 200),
    ]);

    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'recover-integer-generation');
    $reservation = app(AgentBudget::class)->reserve($actor, $attempt, $activity, 10_000);
    $activity->forceFill([
        'status' => 'running',
        'started_at' => now()->subHour(),
        'generation_id' => 'gen-integer-recoverable',
    ])->save();

    $this->artisan('publishing:recover-activities')->assertSuccessful();

    expect($reservation->fresh()?->state)->toBe(AgentBudgetReservation::STATE_SETTLED)
        ->and($reservation->fresh()?->actual_nano_usd)->toBe(1_000_000_000);
});

test('recovery reconciles reservation generation ids before pausing null generation activities', function (): void {
    config()->set('ai.providers.openrouter.key', 'test-key');
    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/generation*' => Http::response(['data' => ['total_cost' => '0.000004']], 200),
    ]);

    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'recover-reservation-generation');
    $reservation = app(AgentBudget::class)->reserve($actor, $attempt, $activity, 10_000);
    $reservation->forceFill([
        'state' => AgentBudgetReservation::STATE_UNKNOWN,
        'provider_generation_id' => 'gen-from-reservation',
    ])->save();
    $activity->forceFill([
        'status' => 'running',
        'started_at' => now()->subHour(),
        'generation_id' => null,
    ])->save();

    $this->artisan('publishing:recover-activities')->assertSuccessful();

    expect($reservation->fresh()?->state)->toBe(AgentBudgetReservation::STATE_SETTLED)
        ->and($reservation->fresh()?->actual_nano_usd)->toBe(4_000)
        ->and($activity->fresh()?->generation_id)->toBe('gen-from-reservation')
        ->and($activity->fresh()?->status->value)->toBe('paused')
        ->and($activity->fresh()?->pause_reason)->toContain('Recovery settled');
});

test('actual cost overrun is recorded truthfully and pauses the attempt', function (): void {
    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $reservation = app(AgentBudget::class)->reserve($actor, $attempt, null, 1_000);

    app(AgentBudget::class)->settle($reservation, 2_000, 'gen-overrun');

    expect($reservation->fresh()?->actual_nano_usd)->toBe(2_000)
        ->and($attempt->fresh()?->paused_at)->not->toBeNull()
        ->and($attempt->fresh()?->pause_reason)->toContain('exceeded');
});

test('missing prices fail before reserving and capability denial releases reservation', function (): void {
    config()->set('publishing_agents.enabled', true);
    config()->set('ai.providers.openrouter.key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '');

    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $missingPrice = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'missing-price');
    app(RunEditorialActivity::class, ['activityId' => $missingPrice->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));

    expect(AgentBudgetReservation::query()->where('activity_id', $missingPrice->id)->exists())->toBeFalse();

    config()->set('publishing_agents.routes.default.pricing.prompt', '0.000001');
    config()->set('publishing_agents.routes.default.pricing.completion', '0.000002');
    config()->set('publishing_agents.routes.default.context_tokens', 100);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 50);
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => ['message' => 'capability denied']], 403)]);

    $capabilityDenied = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'capability-denied');
    app(RunEditorialActivity::class, ['activityId' => $capabilityDenied->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));

    expect($capabilityDenied->fresh()?->status->value)->toBe('paused')
        ->and(AgentBudgetReservation::query()->where('activity_id', $capabilityDenied->id)->first()?->state)->toBe(AgentBudgetReservation::STATE_RELEASED);
});

test('pre-call configuration and allowance blockers pause without automatic retry', function (): void {
    config()->set('publishing_agents.enabled', true);
    config()->set('ai.providers.openrouter.key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '');

    $actor = agentBudgetAuthor();
    $attempt = agentBudgetAttempt($actor);
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'pre-call-blocker');

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));

    expect($activity->fresh()?->status->value)->toBe('paused')
        ->and($activity->fresh()?->run_count)->toBe(1)
        ->and(AgentBudgetReservation::query()->where('activity_id', $activity->id)->exists())->toBeFalse();

    config()->set('publishing_agents.routes.default.pricing.prompt', '100.00');
    config()->set('publishing_agents.routes.default.pricing.completion', '100.00');
    config()->set('publishing_agents.routes.default.context_tokens', 1_000_000);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 1_000_000);
    $allowanceBlocked = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'allowance-blocker');

    app(RunEditorialActivity::class, ['activityId' => $allowanceBlocked->id])->handle(app(EditorialModelBudget::class), app(AgentBudget::class), app(EditorialOutput::class), app(WriteArticle::class));

    expect($allowanceBlocked->fresh()?->status->value)->toBe('paused')
        ->and($allowanceBlocked->fresh()?->run_count)->toBe(1)
        ->and(AgentBudgetReservation::query()->where('activity_id', $allowanceBlocked->id)->exists())->toBeFalse();
});

test('lock contention needs a non-sqlite transactional fixture', function (): void {
    // SQLite's test driver cannot prove row-level lock contention; paid pilots must run the reservation race fixture against a transactional non-SQLite database.
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
    $article = $writer->capture($actor, 'Budgeted agent work.', 'budgeted-agent-work-'.uniqid());
    $revision = $writer->save($actor, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'Budget'], 'budget-draft-'.uniqid());

    return app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id, ['goal' => 'Budget']);
}
