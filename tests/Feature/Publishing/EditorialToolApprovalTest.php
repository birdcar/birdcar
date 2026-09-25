<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ResumeEditorialActivity;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\User;
use App\Settings\PublishingAgentSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Models\Conversation;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
    Bus::fake([RunEditorialActivity::class]);
    Http::preventStrayRequests();
    config()->set('ai.providers.openrouter.key', 'test-key');
    setPublishingAgentsPaused(false);
});

test('native SDK tool approval pauses durably and resumes with the author answer', function (): void {
    [$author, $activity] = pendingAuthorInterview();
    $conversationId = $activity->ai_conversation_id;
    expect($activity->status)->toBe(EditorialActivityStatus::AwaitingApproval)
        ->and($activity->pending_tool_approvals[0]['tool'])->toBe('AskAuthor')
        ->and($activity->attempt->brief)->toBe(['goal' => 'Explain support operations'])
        ->and(Conversation::findOrFail($conversationId)->messages()->whereNotNull('approval_state')->count())->toBe(1)
        ->and($activity->model_snapshot['model'])->toBe('google/gemini-3.8-flash');

    $this->actingAs($author)->get(route('admin.index'))->assertSee('Answer interview');

    app(ResumeEditorialActivity::class)->handle($author, $activity, $activity->pendingApprovalHash(), 'Support leaders who need a practical example.');
    Http::assertSentCount(1);
    Bus::assertDispatched(RunEditorialActivity::class);
    runApprovalActivity($activity);

    $activity->refresh();
    expect($activity->status)->toBe(EditorialActivityStatus::Completed)
        ->and($activity->ai_conversation_id)->toBe($conversationId)
        ->and($activity->pending_tool_approvals)->toBeNull()
        ->and($activity->tool_decisions)->toBeNull()
        ->and($activity->attempt->fresh()->interview_context['answers'])->toBe('Support leaders who need a practical example.')
        ->and($activity->attempt->fresh()->approvals()->count())->toBe(0)
        ->and($activity->generation_id)->toBe('gen-brief');
    Http::assertSent(fn ($request): bool => str_contains(json_encode($request['messages']), 'Support leaders who need a practical example.'));
    runApprovalActivity($activity);
    Http::assertSentCount(2);
});

test('declining a native tool request resolves it without another paid completion', function (): void {
    [$author, $activity] = pendingAuthorInterview();
    app(ResumeEditorialActivity::class)->handle($author, $activity, $activity->pendingApprovalHash(), null);
    runApprovalActivity($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Declined)
        ->and($activity->fresh()->pending_tool_approvals)->toBeNull()
        ->and(Conversation::findOrFail($activity->ai_conversation_id)->messages()->where('tool_results', '!=', '[]')->count())->toBeGreaterThan(0);
    Http::assertSentCount(1);
});

test('author approval rejects stale tokens empty answers replay and cross-author access', function (): void {
    [$author, $activity] = pendingAuthorInterview();
    $resume = app(ResumeEditorialActivity::class);
    $other = User::factory()->create();
    $other->assignRole(PublishingRole::Author->value);

    expect(fn () => $resume->handle($other, $activity, $activity->pendingApprovalHash(), 'Forged answer'))->toThrow(AuthorizationException::class)
        ->and(fn () => $resume->handle($author, $activity, 'old-request', 'Answer'))->toThrow(RuntimeException::class, 'changed')
        ->and(fn () => $resume->handle($author, $activity, $activity->pendingApprovalHash(), ' '))->toThrow(ValidationException::class);
    $resume->handle($author, $activity, $activity->pendingApprovalHash(), 'Actual answer');
    expect(fn () => $resume->handle($author, $activity, $activity->pendingApprovalHash(), 'Replay'))->toThrow(RuntimeException::class, 'already');
    Http::assertSentCount(1);
});

test('stale manuscript and revoked author permissions cannot resume a paused agent', function (): void {
    [$author, $activity] = pendingAuthorInterview();
    $article = $activity->article;
    app(WriteArticle::class)->save($author, $article, $article->working_revision_id, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'Changed'], 'changed-before-approval');
    expect(fn () => app(ResumeEditorialActivity::class)->handle($author, $activity, $activity->pendingApprovalHash(), 'Old answer'))
        ->toThrow(RuntimeException::class, 'stale');

    $author->syncRoles([AdminRole::Access->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(fn () => app(ResumeEditorialActivity::class)->handle($author->fresh(), $activity, $activity->pendingApprovalHash(), 'Old answer'))
        ->toThrow(AuthorizationException::class);
    Http::assertSentCount(1);
});

test('foreign SDK conversation ownership is checked before resuming', function (): void {
    [$author, $activity] = pendingAuthorInterview();
    $other = User::factory()->create();
    Conversation::findOrFail($activity->ai_conversation_id)->update(['participant_id' => $other->id]);
    expect(fn () => app(ResumeEditorialActivity::class)->handle($author, $activity, $activity->pendingApprovalHash(), 'Answer'))
        ->toThrow(RuntimeException::class, 'does not belong');
    Http::assertSentCount(1);
});

test('saving a different model while the author is answering keeps the started model', function (): void {
    [$author, $activity] = pendingAuthorInterview();
    app(PublishingAgentSettings::class)->overrideModel(EditorialActivityKind::Interview, 'deepseek/deepseek-v4.1-flash')->save();
    app()->forgetScopedInstances();

    app(ResumeEditorialActivity::class)->handle($author, $activity, $activity->pendingApprovalHash(), 'Answer');
    runApprovalActivity($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Completed);
    $requests = Http::recorded();
    expect($requests)->toHaveCount(2)
        ->and($requests[1][0]['model'])->toBe('google/gemini-3.8-flash')
        ->and($requests[1][0]['reasoning'])->toBe(['effort' => 'low']);
});

test('pausing agents while the author answers keeps the decision durable without dispatching it', function (): void {
    [$author, $activity] = pendingAuthorInterview();
    Bus::fake([RunEditorialActivity::class]);
    setPublishingAgentsPaused(true);

    app(ResumeEditorialActivity::class)->handle($author, $activity, $activity->pendingApprovalHash(), 'Answer while paused');

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Pending)
        ->and($activity->fresh()->tool_decisions)->not->toBeEmpty();
    Bus::assertNotDispatched(RunEditorialActivity::class);
});

test('settings page saves while the author answers keep the started model and approve nothing', function (): void {
    [$author, $activity] = pendingAuthorInterview();
    Bus::fake([RunEditorialActivity::class]);
    $settings = Livewire::actingAs($author)->test('admin.publishing.settings')
        ->set('paused', true)
        ->set('models.interview', 'deepseek/deepseek-v4.1-flash')
        ->call('save')
        ->assertHasNoErrors();
    app()->forgetScopedInstances();

    app(ResumeEditorialActivity::class)->handle($author, $activity, $activity->pendingApprovalHash(), 'Answer while paused');

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Pending)
        ->and($activity->fresh()->tool_decisions)->not->toBeEmpty();

    $settings->set('paused', false)->call('save')->assertHasNoErrors();
    app()->forgetScopedInstances();
    Bus::assertNotDispatched(RunEditorialActivity::class);
    runApprovalActivity($activity);

    $requests = Http::recorded();
    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Completed)
        ->and($requests)->toHaveCount(2)
        ->and($requests[1][0]['model'])->toBe('google/gemini-3.8-flash')
        ->and($activity->attempt->fresh()->approvals()->count())->toBe(0);
});

test('auto router approvals continue on the concrete model that asked the question', function (): void {
    app(PublishingAgentSettings::class)->overrideModel(EditorialActivityKind::Interview, 'openrouter/auto')->save();
    [$author, $activity] = pendingAuthorInterview(returnedModel: 'deepseek/deepseek-v4.1-flash');

    expect($activity->status)->toBe(EditorialActivityStatus::AwaitingApproval)
        ->and($activity->model_snapshot)->toMatchArray(['requested_model' => 'openrouter/auto', 'model' => 'deepseek/deepseek-v4.1-flash', 'reasoning_effort' => null]);

    app(ResumeEditorialActivity::class)->handle($author, $activity, $activity->pendingApprovalHash(), 'Answer');
    runApprovalActivity($activity);

    $requests = Http::recorded();
    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Completed)
        ->and($requests[0][0]['model'])->toBe('openrouter/auto')
        ->and($requests[1][0]['model'])->toBe('deepseek/deepseek-v4.1-flash')
        ->and($requests[1][0])->not->toHaveKey('reasoning');
});

test('auto router approvals without a resolvable concrete model pause instead of re-routing', function (): void {
    app(PublishingAgentSettings::class)->overrideModel(EditorialActivityKind::Interview, 'openrouter/auto')->save();
    [, $activity] = pendingAuthorInterview(returnedModel: 'openrouter/auto');

    expect($activity->status)->toBe(EditorialActivityStatus::Paused)
        ->and($activity->pause_reason)->toBe(RunEditorialActivity::MODEL_CONTINUITY_ERROR);

    $activity->forceFill(['status' => EditorialActivityStatus::Pending, 'tool_decisions' => ['call_author' => ['action' => 'edit', 'arguments' => ['questions' => ['Who?'], 'answers' => 'Readers']]]])->save();
    runApprovalActivity($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($activity->fresh()->pause_reason)->toBe(RunEditorialActivity::MODEL_CONTINUITY_ERROR);
    Http::assertSentCount(1);
});

test('legacy budget-era snapshots continue on their recorded model without price fields', function (): void {
    [$author, $activity] = pendingAuthorInterview();
    $activity->forceFill(['model_snapshot' => ['model' => 'deepseek/deepseek-v4-pro-0813', 'provider' => 'DeepSeek', 'pricing' => ['prompt' => '1'], 'max_price' => ['prompt' => '1'], 'context_tokens' => 1000]])->save();

    app(ResumeEditorialActivity::class)->handle($author, $activity->fresh(), $activity->pendingApprovalHash(), 'Answer');
    runApprovalActivity($activity);

    $second = Http::recorded()[1][0];
    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Completed)
        ->and($second['model'])->toBe('deepseek/deepseek-v4-pro-0813')
        ->and($second['provider'])->toBe(['require_parameters' => true])
        ->and($activity->fresh()->model_snapshot)->not->toHaveKeys(['pricing', 'max_price', 'provider', 'context_tokens']);
});

test('a failed continuation pauses and does not replay the answered decision', function (): void {
    [$author, $activity] = pendingAuthorInterview(secondResponse: ['error' => ['message' => 'Upstream failed']], secondStatus: 500);

    app(ResumeEditorialActivity::class)->handle($author, $activity, $activity->pendingApprovalHash(), 'Answer');
    runApprovalActivity($activity);
    runApprovalActivity($activity);

    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($activity->fresh()->pause_reason)->toContain('approval continuation');
    Http::assertSentCount(2);
});

test('workspace displays escaped pending questions and queues human answers', function (): void {
    [$author, $activity] = pendingAuthorInterview('<script>alert(1)</script> Who is the reader?');
    $this->actingAs($author);
    $workspace = Livewire::test('admin.publishing.article-workspace', ['article' => $activity->article]);
    $workspace->assertSee('A few questions for you')
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false)
        ->assertDontSee('<script>alert(1)</script>', escape: false)
        ->set('agentAnswers.'.$activity->id, 'Support leaders')
        ->call('answerAgent', $activity->id, $activity->pendingApprovalHash())
        ->assertSet('saveError', null);
    expect($activity->fresh()->status)->toBe(EditorialActivityStatus::Pending);
    Http::assertSentCount(1);
});

/** @return array{User, EditorialActivity} */
function pendingAuthorInterview(string $question = 'Who is the reader?', string $returnedModel = 'google/gemini-3.8-flash', ?array $secondResponse = null, int $secondStatus = 200): array
{
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::sequence()
            ->push([
                'id' => 'gen-question', 'model' => $returnedModel,
                'choices' => [['finish_reason' => 'tool_calls', 'message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [[
                    'id' => 'call_author', 'type' => 'function', 'function' => ['name' => 'AskAuthor', 'arguments' => json_encode(['questions' => [$question], 'answers' => null])],
                ]]]]],
            ])
            ->push($secondResponse ?? [
                'id' => 'gen-brief', 'model' => $returnedModel,
                'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode([
                    'questions' => [], 'brief' => ['summary' => 'A practical support guide'], 'angleOptions' => [['title' => 'Practical support', 'thesis' => 'Use a concrete example']],
                ])]]],
            ], $secondStatus),
    ]);
    $author = User::factory()->create();
    $author->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);
    $article = app(WriteArticle::class)->capture($author, 'A support guide', 'capture-interview-'.uniqid());
    $revision = app(WriteArticle::class)->save($author, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'A support guide'], 'initial-interview-'.uniqid());
    $attempt = app(AdvancePublishingAttempt::class)->develop($author, $article, $revision->id, ['goal' => 'Explain support operations']);
    $activity = app(StartEditorialActivity::class)->start($author, $attempt, EditorialActivityKind::Interview);
    runApprovalActivity($activity);

    return [$author, $activity->fresh()];
}

function runApprovalActivity(EditorialActivity $activity): void
{
    app()->call([new RunEditorialActivity((int) $activity->id), 'handle']);
}
