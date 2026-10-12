<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\EditorialActivity;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\EditorialOutput;
use App\Services\Publishing\FrameworkCatalog;
use App\Services\Publishing\VoiceCanon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function narrativeFrameworkAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $user;
}

function narrativeFrameworkCatalog(): FrameworkCatalog
{
    return FrameworkCatalog::fromSnapshot(app(VoiceCanon::class)->snapshot());
}

/**
 * The input a Plan activity is frozen with: the brief's post type and the catalog.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function narrativeFrameworkFrozenPlan(?string $postType, array $extra = []): array
{
    return [
        'brief' => $postType === null ? [] : ['post_type' => $postType],
        'canon' => ['catalog' => narrativeFrameworkCatalog()->toArray()],
        ...$extra,
    ];
}

/**
 * @param  array<string, mixed>  $framework
 * @return array<string, mixed>
 */
function narrativeFrameworkPlan(array $framework): array
{
    return [
        'outline' => [['heading' => 'Why another hire will not fix it', 'evidence_refs' => []], ['heading' => 'What to check first', 'evidence_refs' => []]],
        'argument' => 'Check the chores before posting the job.',
        'visualPlan' => [['slot' => 'hero', 'description' => 'The Friday report']],
        'framework' => [
            'reasons' => ['primary' => 'It fits the claim.', 'runner_up' => 'It is the next best fit.'],
            'secondary' => null,
            ...$framework,
        ],
    ];
}

/**
 * Runs one activity against a faked OpenRouter answer and leaves follow-on work queued.
 *
 * @param  array<string, mixed>  $payload
 */
function narrativeFrameworkRun(EditorialActivity $activity, array $payload): EditorialActivity
{
    setPublishingAgentsPaused(false);
    config()->set('ai.providers.openrouter.key', 'test-key');
    Queue::fake([RunEditorialActivity::class]);
    // A fresh client per run, so an earlier run's stubbed answer never answers this one.
    Http::swap(new HttpFactory(app(Dispatcher::class)));
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response([
        'id' => 'gen-narrative-framework-'.Str::random(6),
        'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode($payload, JSON_THROW_ON_ERROR)]]],
    ])]);

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialOutput::class), app(WriteArticle::class));
    setPublishingAgentsPaused(true);

    return $activity->fresh() ?? throw new RuntimeException('The activity disappeared.');
}

/**
 * An attempt in the drafting stage with completed research and an approved angle, built while agents are paused.
 *
 * @param  array<string, mixed>  $brief
 */
function narrativeFrameworkDraftingAttempt(User $actor, array $brief): PublishingAttempt
{
    setPublishingAgentsPaused(true);
    $write = app(WriteArticle::class);
    $approve = app(ApprovePublishingStage::class);
    $article = $write->captureIdea($actor, 'Why another admin hire will not fix the Friday report');
    $revision = $write->save($actor, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'The Friday report'], 'narrative-framework-'.Str::random(8));
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id, $brief);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');

    EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => EditorialActivityKind::ResearchChallenge,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'drafting',
        'input_version' => $attempt->input_version,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'research-'.Str::random(8),
        'idempotency_key' => 'research-'.Str::random(16),
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', Str::random(16)),
        'input' => ['approval_hashes' => [ApprovalKind::Angle->value => $approve->inputHashFor($attempt, ApprovalKind::Angle)]],
        'model_snapshot' => [],
        'response' => ['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []],
        'completed_at' => now(),
    ]);

    return $attempt;
}

/**
 * Runs a real Plan activity through a fake, so the attempt holds an agent plan with the given framework proposal.
 *
 * @param  array<string, mixed>  $framework
 */
function narrativeFrameworkAgentPlan(User $actor, string $postType, array $framework): PublishingAttempt
{
    $attempt = narrativeFrameworkDraftingAttempt($actor, ['goal' => 'Show owners what to check first', 'post_type' => $postType]);
    $plan = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Plan, [], 'plan-'.$attempt->review_cycle);
    $completed = narrativeFrameworkRun($plan, narrativeFrameworkPlan($framework));
    expect($completed->status)->toBe(EditorialActivityStatus::Completed);

    return $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');
}

test('a plan with an allowed primary and runner-up for the brief post type is valid', function () {
    $payload = narrativeFrameworkPlan(['primary' => 'the-false-start', 'runner_up' => 'the-montaigne']);

    $validated = app(EditorialOutput::class)->validate(EditorialActivityKind::Plan, $payload, [], [], narrativeFrameworkFrozenPlan('essay'));

    expect($validated['framework']['primary'])->toBe('the-false-start')
        ->and($validated['framework']['runner_up'])->toBe('the-montaigne');
});

test('a plan framework outside the post type list fails and names the allowed frameworks', function (string $postType, array $framework, string $reason) {
    $payload = narrativeFrameworkPlan($framework);

    expect(fn () => app(EditorialOutput::class)->validate(EditorialActivityKind::Plan, $payload, [], [], narrativeFrameworkFrozenPlan($postType)))
        ->toThrow(InvalidArgumentException::class, $reason);
})->with([
    'a framework allowed only for another post type' => ['essay', ['primary' => 'socratic-path', 'runner_up' => 'the-montaigne'], 'The primary framework "socratic-path" is not allowed for a essay post; allowed frameworks: the-false-start, the-sparkline, the-dialectic, the-jeremiad, the-montaigne.'],
    'the Jeremiad for a work story' => ['work_story', ['primary' => 'the-jeremiad', 'runner_up' => 'three-act'], 'allowed frameworks: story-circle, three-act.'],
    'an unknown slug' => ['playbook', ['primary' => 'kishotenketsu', 'runner_up' => 'three-act'], 'allowed frameworks: socratic-path, the-petal, three-act.'],
    'a runner-up equal to the primary' => ['playbook', ['primary' => 'three-act', 'runner_up' => 'three-act'], 'The runner-up framework must differ from the primary framework.'],
    'a missing reason' => ['playbook', ['primary' => 'three-act', 'runner_up' => 'the-petal', 'reasons' => ['primary' => 'It fits.', 'runner_up' => ' ']], 'The plan needs a one-sentence reason for its runner-up framework.'],
]);

test('a secondary framework on the guide avoid list fails in either order', function (string $primary, string $secondary) {
    $payload = narrativeFrameworkPlan([
        'primary' => $primary,
        'runner_up' => 'the-jeremiad',
        'secondary' => ['framework' => $secondary, 'section_heading' => 'What to check first'],
    ]);

    expect(fn () => app(EditorialOutput::class)->validate(EditorialActivityKind::Plan, $payload, [], [], narrativeFrameworkFrozenPlan('essay')))
        ->toThrow(InvalidArgumentException::class, 'The framework guide says to avoid combining "'.$primary.'" with "'.$secondary.'".');
})->with([
    'dialectic with a false start' => ['the-dialectic', 'the-false-start'],
    'false start with a dialectic' => ['the-false-start', 'the-dialectic'],
]);

test('a compatible secondary for one named section is valid', function () {
    $payload = narrativeFrameworkPlan([
        'primary' => 'the-false-start',
        'runner_up' => 'the-montaigne',
        'secondary' => ['framework' => 'the-sparkline', 'section_heading' => 'What to check first'],
    ]);

    expect(app(EditorialOutput::class)->validate(EditorialActivityKind::Plan, $payload, [], [], narrativeFrameworkFrozenPlan('essay'))['framework']['secondary'])
        ->toBe(['framework' => 'the-sparkline', 'section_heading' => 'What to check first']);
});

test('a plan without a post type in its brief fails with a clear reason', function () {
    $payload = narrativeFrameworkPlan(['primary' => 'three-act', 'runner_up' => 'the-petal']);

    expect(fn () => app(EditorialOutput::class)->validate(EditorialActivityKind::Plan, $payload, [], [], narrativeFrameworkFrozenPlan(null)))
        ->toThrow(InvalidArgumentException::class, 'Planning needs a post type in the brief; choose one of essay, playbook, teardown, work_story before planning.');
});

test('a re-plan must keep the framework the owner chose as its primary', function () {
    $payload = narrativeFrameworkPlan(['primary' => 'three-act', 'runner_up' => 'the-petal']);

    expect(fn () => app(EditorialOutput::class)->validate(EditorialActivityKind::Plan, $payload, [], [], narrativeFrameworkFrozenPlan('playbook', ['framework_choice' => 'the-petal'])))
        ->toThrow(InvalidArgumentException::class, 'The owner chose "the-petal", so it must be the primary framework of this plan.');
});

test('the length range excludes long or high-complexity frameworks for a short post type', function () {
    $catalog = FrameworkCatalog::fromArray([
        'post_types' => ['note' => ['label' => 'Short note', 'template' => 'post-types/work-story.md', 'words' => [600, 1200], 'frameworks' => ['three-act', 'the-jeremiad', 'reverse-chronology']]],
        'frameworks' => [
            'three-act' => ['name' => 'Three-Act Structure', 'file' => 'frameworks/three-act.md', 'complexity' => 'low', 'min_words' => 800],
            'the-jeremiad' => ['name' => 'The Jeremiad', 'file' => 'frameworks/the-jeremiad.md', 'complexity' => 'high', 'min_words' => 1500],
            'reverse-chronology' => ['name' => 'Reverse Chronology', 'file' => 'frameworks/reverse-chronology.md', 'complexity' => 'high', 'min_words' => 800],
        ],
        'avoid_combinations' => [],
    ]);

    expect($catalog->allowedFor('note'))->toBe(['three-act'])
        ->and($catalog->allowedFor('note', 2000))->toBe(['three-act', 'the-jeremiad', 'reverse-chronology'])
        ->and($catalog->validate(['primary' => 'the-jeremiad', 'runner_up' => 'three-act', 'reasons' => ['primary' => 'a', 'runner_up' => 'b']], 'note'))
        ->toBe(['The primary framework "the-jeremiad" is not allowed for a note post; allowed frameworks: three-act.']);
});

test('the canon catalog lists every framework file and each post type template', function () {
    $catalog = narrativeFrameworkCatalog();
    $snapshot = app(VoiceCanon::class)->snapshot();

    expect($catalog->postTypeSlugs())->toBe(['essay', 'playbook', 'teardown', 'work_story'])
        ->and($catalog->frameworks)->toHaveCount(13)
        ->and($catalog->templatePath('work_story'))->toBe('post-types/work-story.md');

    foreach ($catalog->frameworks as $slug => $framework) {
        expect($snapshot->files)->toHaveKey('resources/publishing/canon/'.$framework['file'])
            ->and($framework['file'])->toBe('frameworks/'.$slug.'.md');
    }
});

test('a planner that proposes a disallowed framework fails the paid activity with the allowed list', function () {
    $actor = narrativeFrameworkAuthor();
    $attempt = narrativeFrameworkDraftingAttempt($actor, ['goal' => 'Show owners a story', 'post_type' => 'work_story']);
    $plan = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Plan, [], 'plan-'.$attempt->review_cycle);

    $failed = narrativeFrameworkRun($plan, narrativeFrameworkPlan(['primary' => 'the-jeremiad', 'runner_up' => 'three-act']));

    expect($failed->status)->toBe(EditorialActivityStatus::Failed)
        ->and($failed->run_count)->toBe(1)
        ->and($failed->error_reason)->toContain('allowed frameworks: story-circle, three-act.')
        ->and($attempt->fresh()?->plan)->toBe([]);
});

test('the planner is told the frameworks the brief post type allows and the catalog is frozen into its input', function () {
    $actor = narrativeFrameworkAuthor();
    $attempt = narrativeFrameworkDraftingAttempt($actor, ['goal' => 'Show owners a story', 'post_type' => 'work_story']);
    $plan = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Plan, [], 'plan-'.$attempt->review_cycle);

    narrativeFrameworkRun($plan, narrativeFrameworkPlan(['primary' => 'story-circle', 'runner_up' => 'three-act']));

    $body = Http::recorded()->sole()[0]->data();
    $prompt = json_decode((string) data_get($body, 'messages.1.content'), true, flags: JSON_THROW_ON_ERROR);

    expect(data_get($body, 'response_format.json_schema.schema.properties.framework.properties.primary.description'))->toContain('Allowed frameworks for this work_story post: story-circle, three-act.')
        ->and(data_get($prompt, 'input.canon.catalog.post_types.work_story.frameworks'))->toBe(['story-circle', 'three-act'])
        ->and(data_get($plan->input, 'canon.catalog'))->toBe(narrativeFrameworkCatalog()->toArray());
});

test('keeping the primary records the choice inside the plan and starts nothing', function () {
    $actor = narrativeFrameworkAuthor();
    $attempt = narrativeFrameworkAgentPlan($actor, 'playbook', ['primary' => 'three-act', 'runner_up' => 'the-petal']);
    $approve = app(ApprovePublishingStage::class);
    $hashBefore = $approve->inputHashFor($attempt, ApprovalKind::Plan);

    $replan = app(AdvancePublishingAttempt::class)->chooseFramework($actor, $attempt, 'three-act');
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');

    expect($replan)->toBeNull()
        ->and($attempt->plan['framework']['chosen'] ?? null)->toBe('three-act')
        ->and($approve->inputHashFor($attempt, ApprovalKind::Plan))->not->toBe($hashBefore)
        ->and(EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Plan->value)->count())->toBe(1);

    $approval = $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));

    expect($approval->kind)->toBe(ApprovalKind::Plan);
});

test('an agent plan cannot be approved until the owner chooses a framework', function () {
    $actor = narrativeFrameworkAuthor();
    $attempt = narrativeFrameworkAgentPlan($actor, 'playbook', ['primary' => 'three-act', 'runner_up' => 'the-petal']);
    $approve = app(ApprovePublishingStage::class);

    expect(fn () => $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan)))
        ->toThrow(RuntimeException::class, 'Plan approval requires a chosen narrative framework');
});

test('picking the runner-up starts a re-plan with that framework fixed, once', function () {
    $actor = narrativeFrameworkAuthor();
    $attempt = narrativeFrameworkAgentPlan($actor, 'playbook', ['primary' => 'three-act', 'runner_up' => 'the-petal']);
    $advance = app(AdvancePublishingAttempt::class);

    $replan = $advance->chooseFramework($actor, $attempt, 'the-petal');
    $again = $advance->chooseFramework($actor, $attempt, 'the-petal');

    expect($replan)->toBeInstanceOf(EditorialActivity::class)
        ->and($replan?->kind)->toBe(EditorialActivityKind::Plan)
        ->and($replan?->status)->toBe(EditorialActivityStatus::Pending)
        ->and($replan?->input['framework_choice'] ?? null)->toBe('the-petal')
        ->and($replan?->batch_key)->toBe('plan-'.$attempt->review_cycle.'-the-petal')
        ->and($again?->id)->toBe($replan?->id)
        ->and(data_get($attempt->fresh()?->plan, 'framework.chosen', 'missing'))->toBeNull();

    expect(fn () => $advance->chooseFramework($actor, $attempt, 'socratic-path'))
        ->toThrow(RuntimeException::class, 'Wait for the current plan to finish before choosing another framework.');

    $completed = narrativeFrameworkRun($replan ?? throw new RuntimeException('No re-plan.'), narrativeFrameworkPlan(['primary' => 'the-petal', 'runner_up' => 'three-act']));
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');

    expect($completed->error_reason)->toBeNull()
        ->and($completed->status)->toBe(EditorialActivityStatus::Completed)
        ->and($attempt->plan['framework']['primary'] ?? null)->toBe('the-petal')
        ->and($attempt->plan['framework']['chosen'] ?? null)->toBe('the-petal');
});

test('a framework the post type does not allow is refused, as is a choice after plan approval', function () {
    $actor = narrativeFrameworkAuthor();
    $attempt = narrativeFrameworkAgentPlan($actor, 'playbook', ['primary' => 'three-act', 'runner_up' => 'the-petal']);
    $advance = app(AdvancePublishingAttempt::class);

    expect(fn () => $advance->chooseFramework($actor, $attempt, 'the-jeremiad'))
        ->toThrow(RuntimeException::class, 'Choose one of the frameworks allowed for this post type: socratic-path, the-petal, three-act.');

    $advance->chooseFramework($actor, $attempt, 'three-act');
    $approve = app(ApprovePublishingStage::class);
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));

    expect(fn () => $advance->chooseFramework($actor, $attempt, 'the-petal'))
        ->toThrow(RuntimeException::class, 'The framework can only change before the plan is approved.');
});

test('the interview stores the post type and target query in the brief', function () {
    $actor = narrativeFrameworkAuthor();
    setPublishingAgentsPaused(true);
    $write = app(WriteArticle::class);
    $article = $write->captureIdea($actor, 'Owners keep rebuilding the Friday report by hand.');
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article, null, ['idea' => 'Owners keep rebuilding the Friday report by hand.']);
    $interview = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'interview-1');

    narrativeFrameworkRun($interview, [
        'questions' => ['What happened last Friday?'],
        'brief' => ['summary' => 'A brief.', 'missingAnswers' => [], 'postType' => 'teardown', 'targetQuery' => '  why does the friday report take all afternoon  ', 'ideaTitle' => null],
        'angleOptions' => [['title' => 'Walk the Friday report', 'thesis' => 'The report sticks in five places.']],
    ]);

    $brief = ($attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.'))->brief;
    expect($brief['post_type'] ?? null)->toBe('teardown')
        ->and($brief['target_query'] ?? null)->toBe('why does the friday report take all afternoon')
        ->and($brief)->not->toHaveKeys(['postType', 'targetQuery', 'ideaTitle']);
});

test('an interview post type outside the catalog fails and names the allowed post types', function () {
    $payload = ['questions' => [], 'brief' => ['summary' => 'A brief.', 'missingAnswers' => [], 'postType' => 'listicle'], 'angleOptions' => []];

    expect(fn () => app(EditorialOutput::class)->validate(EditorialActivityKind::Interview, $payload))
        ->toThrow(InvalidArgumentException::class, 'Interview brief post type must be one of: essay, playbook, teardown, work_story.');
});

test('the owner picks a post type, which withdraws the angle approval like any brief change', function () {
    $actor = narrativeFrameworkAuthor();
    $attempt = narrativeFrameworkDraftingAttempt($actor, ['goal' => 'Show owners what to check first']);

    $updated = app(AdvancePublishingAttempt::class)->choosePostType($actor, $attempt, 'essay');

    expect($updated->brief['post_type'] ?? null)->toBe('essay')
        ->and($updated->stage->value)->toBe('developing')
        ->and($updated->approvals()->whereNull('invalidated_at')->exists())->toBeFalse();
    expect(fn () => app(AdvancePublishingAttempt::class)->choosePostType($actor, $attempt, 'listicle'))
        ->toThrow(RuntimeException::class, 'Choose one of these post types: essay, playbook, teardown, work_story.');
});

test('the draft and voice review carry the chosen framework and the post type template in full', function () {
    $actor = narrativeFrameworkAuthor();
    $attempt = narrativeFrameworkAgentPlan($actor, 'essay', [
        'primary' => 'the-false-start',
        'runner_up' => 'the-montaigne',
        'secondary' => ['framework' => 'the-sparkline', 'section_heading' => 'What to check first'],
    ]);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);
    $advance->chooseFramework($actor, $attempt, 'the-false-start');
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');
    $canonFile = fn (string $relative): string => trim((string) file_get_contents(resource_path('publishing/canon/'.$relative)));

    $draft = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Draft, [], 'draft-1');

    expect($draft->input['canon']['framework'] ?? null)->toBe([
        'slug' => 'the-false-start',
        'text' => $canonFile('frameworks/the-false-start.md'),
        'secondary' => ['slug' => 'the-sparkline', 'text' => $canonFile('frameworks/the-sparkline.md'), 'section_heading' => 'What to check first'],
    ])
        ->and($draft->input['canon']['post_type'] ?? null)->toBe(['slug' => 'essay', 'text' => $canonFile('post-types/essay.md')]);

    $draft->forceFill(['status' => EditorialActivityStatus::Completed, 'completed_at' => now(), 'proposal' => ['applied_revision_id' => $attempt->article()->firstOrFail()->working_revision_id]])->save();
    $voice = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::ReviewVoice, ['draft_activity_id' => $draft->id], 'review-1');
    $buyer = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::ReviewBuyer, ['draft_activity_id' => $draft->id], 'review-1');

    expect($voice->input['canon']['framework'] ?? null)->toBe($draft->input['canon']['framework'])
        ->and($voice->input['canon']['post_type'] ?? null)->toBe($draft->input['canon']['post_type'])
        ->and($buyer->input['canon'])->not->toHaveKeys(['framework', 'post_type']);
});

test('a plan approved before frameworks existed freezes no framework into the draft', function () {
    $actor = narrativeFrameworkAuthor();
    $attempt = narrativeFrameworkDraftingAttempt($actor, ['goal' => 'Show owners what to check first']);
    $attempt = app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, plan: ['outline' => ['intro'], 'visualPlan' => ['hero image']]);
    $approve = app(ApprovePublishingStage::class);
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));

    $draft = app(StartEditorialActivity::class)->start($actor, $attempt->fresh() ?? $attempt, EditorialActivityKind::Draft, [], 'draft-1');

    expect($draft->input['canon'])->not->toHaveKeys(['framework', 'post_type']);
});
