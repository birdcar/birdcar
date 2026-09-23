<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\AgentBudget;
use App\Services\Publishing\EditorialPrompts;
use App\Services\Publishing\OpenRouterClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    config(['publishing.public_reader' => 'database']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('captured ideas stay idle until deliberate develop creates one allowance backed attempt', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);

    $article = $write->capture($actor, 'Investigate agent publishing.', 'agent-publishing');
    $revision = $write->save($actor, $article, null, editorialDocument('First draft'), editorialMetadata('Agent Publishing'), 'draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Publish safely']);
    $duplicateAttempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Publish safely']);

    expect($article->current_attempt_id)->toBeNull()
        ->and($attempt->is($duplicateAttempt))->toBeTrue()
        ->and($attempt->stage)->toBe(EditorialStage::Developing)
        ->and($attempt->allowance_nano_usd)->toBe(5_000_000_000)
        ->and($article->fresh()?->current_attempt_id)->toBe($attempt->id);
});

test('develop after publication creates a fresh working attempt without replacing the live release', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);
    $releases = app(ManageArticleRelease::class);

    $article = $write->capture($actor, 'Published revisions keep going.', 'published-revisions');
    $revision = $write->save($actor, $article, null, editorialDocument('Published body'), editorialMetadata('Published'), 'draft-1');
    $publishedAttempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Ship it']);
    $approve->approve($actor, $publishedAttempt, ApprovalKind::Angle, $approve->inputHashFor($publishedAttempt, ApprovalKind::Angle));
    $publishedAttempt = publishingAttemptWithReviewedPlan($advance, $actor, $publishedAttempt->fresh());
    $approve->approve($actor, $publishedAttempt, ApprovalKind::Plan, $approve->inputHashFor($publishedAttempt, ApprovalKind::Plan));
    $publishedAttempt = $publishedAttempt->fresh();
    publishingAttemptWithCompletedReviews($actor, $publishedAttempt);
    $release = $releases->prepare($actor, $publishedAttempt, $revision->id, 'published-revisions');
    $releases->approve($actor, $release, $release->release_hash);
    $releases->deliver($actor, $release, null);

    $nextAttempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Develop a follow-up']);
    $duplicateNextAttempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Develop a follow-up']);

    expect($nextAttempt->id)->not->toBe($publishedAttempt->id)
        ->and($duplicateNextAttempt->is($nextAttempt))->toBeTrue()
        ->and($article->fresh()?->published_release_id)->toBe($release->id)
        ->and($article->fresh()?->working_revision_id)->toBe($revision->id);
});

test('human approvals advance gates and context changes invalidate downstream gates', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);

    $article = $write->capture($actor, 'Approval gates.', 'approval-gates');
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), editorialMetadata('Approval Gates'), 'draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Gate automatic work']);

    $angleApproval = $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = publishingAttemptWithReviewedPlan($advance, $actor, $attempt->fresh(), ['outline' => ['intro', 'body'], 'visualPlan' => ['hero']]);
    $planApproval = $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $advance->rethink($actor, $attempt, brief: ['goal' => 'Changed premise']);

    expect($angleApproval->fresh()?->invalidated_at)->not->toBeNull()
        ->and($planApproval->fresh()?->invalidated_at)->not->toBeNull()
        ->and($attempt->fresh()?->stage)->toBe(EditorialStage::Developing);
});

test('rethink resets invalidated gates so brief angle and plan changes can be re-approved in order', function (): void {
    $actor = editorialAuthor();
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);

    $briefAttempt = approvedEditorialAttempt($actor, 'Brief rethink.', 'brief-rethink');
    $briefAttempt = $advance->rethink($actor, $briefAttempt, brief: ['goal' => 'Changed premise']);

    expect($briefAttempt->stage)->toBe(EditorialStage::Developing);
    expect(fn () => $approve->approve($actor, $briefAttempt, ApprovalKind::Plan, $approve->inputHashFor($briefAttempt, ApprovalKind::Plan)))
        ->toThrow(RuntimeException::class, 'Plan approval requires an active angle approval');
    $approve->approve($actor, $briefAttempt, ApprovalKind::Angle, $approve->inputHashFor($briefAttempt, ApprovalKind::Angle));
    $briefAttempt = publishingAttemptWithReviewedPlan($advance, $actor, $briefAttempt->fresh());
    $approve->approve($actor, $briefAttempt, ApprovalKind::Plan, $approve->inputHashFor($briefAttempt, ApprovalKind::Plan));

    expect($briefAttempt->fresh()?->stage)->toBe(EditorialStage::InReview);

    $angleAttempt = approvedEditorialAttempt($actor, 'Angle rethink.', 'angle-rethink');
    $angleAttempt = $advance->rethink($actor, $angleAttempt, angle: ['thesis' => 'Sharper angle']);

    expect($angleAttempt->stage)->toBe(EditorialStage::Developing);
    expect(fn () => $approve->approve($actor, $angleAttempt, ApprovalKind::Plan, $approve->inputHashFor($angleAttempt, ApprovalKind::Plan)))
        ->toThrow(RuntimeException::class, 'Plan approval requires an active angle approval');
    $approve->approve($actor, $angleAttempt, ApprovalKind::Angle, $approve->inputHashFor($angleAttempt, ApprovalKind::Angle));
    $angleAttempt = publishingAttemptWithReviewedPlan($advance, $actor, $angleAttempt->fresh());
    $approve->approve($actor, $angleAttempt, ApprovalKind::Plan, $approve->inputHashFor($angleAttempt, ApprovalKind::Plan));

    expect($angleAttempt->fresh()?->stage)->toBe(EditorialStage::InReview);

    $planAttempt = approvedEditorialAttempt($actor, 'Plan rethink.', 'plan-rethink');
    $planAttempt = $advance->rethink($actor, $planAttempt, plan: ['outline' => ['new opening'], 'visualPlan' => ['diagram']]);

    expect($planAttempt->stage)->toBe(EditorialStage::Drafting);
    expect(fn () => $approve->approve($actor, $planAttempt, ApprovalKind::Release, 'not-an-approved-package'))
        ->toThrow(RuntimeException::class, 'managed through release packages');
    $approve->approve($actor, $planAttempt, ApprovalKind::Plan, $approve->inputHashFor($planAttempt, ApprovalKind::Plan));

    expect($planAttempt->fresh()?->stage)->toBe(EditorialStage::InReview);
});

test('stale saves and conflicting mutation keys do not change the current manuscript', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Concurrent edits.', 'concurrent-edits');
    $firstRevision = $write->save($actor, $article, null, editorialDocument('First'), editorialMetadata('First'), 'edit-1');
    $sameMutation = $write->save($actor, $article, $firstRevision->id, editorialDocument('Second'), editorialMetadata('Second'), 'edit-2');

    expect(fn () => $write->save($actor, $article, $firstRevision->id, editorialDocument('Stale'), editorialMetadata('Stale'), 'edit-3'))
        ->toThrow(RuntimeException::class, 'changed since this edit began');
    expect(fn () => $write->save($actor, $article, $sameMutation->id, editorialDocument('Different'), editorialMetadata('Different'), 'edit-2'))
        ->toThrow(RuntimeException::class, 'mutation key');

    expect($write->save($actor, $article, $firstRevision->id, editorialDocument('Second'), editorialMetadata('Second'), 'edit-2')->is($sameMutation))->toBeTrue()
        ->and($write->save($actor, $article, $sameMutation->id, editorialDocument('Second'), editorialMetadata('Second'), 'edit-2')->is($sameMutation))->toBeTrue()
        ->and(ArticleRevision::query()->where('article_id', $article->id)->count())->toBe(2)
        ->and($article->fresh()?->working_revision_id)->toBe($sameMutation->id);
});

test('approvals cannot skip angle plan or current stage gates', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);

    $article = $write->capture($actor, 'Denied gates.', 'denied-gates');
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), editorialMetadata('Denied Gates'), 'draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id);

    expect(fn () => $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan)))
        ->toThrow(RuntimeException::class, 'Plan approval requires an active angle approval');

    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = publishingAttemptWithReviewedPlan($advance, $actor, $attempt->fresh());

    expect(fn () => $approve->approve($actor, $attempt, ApprovalKind::Release, 'not-an-approved-package', $revision->id))
        ->toThrow(RuntimeException::class, 'managed through release packages');

    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $attempt = $attempt->fresh();

    expect(fn () => $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle)))
        ->toThrow(RuntimeException::class, 'Angle approval requires a developing attempt');
});

test('plan approval requires current cycle research and a non empty outline visual plan', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);

    $article = $write->capture($actor, 'Plan prerequisites.', 'plan-prerequisites');
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), editorialMetadata('Plan prerequisites'), 'plan-prerequisites-draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh();

    expect(fn () => $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan)))
        ->toThrow(RuntimeException::class, 'completed research/challenge');

    EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => EditorialActivityKind::ResearchChallenge,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'drafting',
        'input_version' => $attempt->input_version,
        'revision_id' => null,
        'revision_hash' => null,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'research-plan-prerequisites',
        'idempotency_key' => 'research-plan-prerequisites',
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', 'research-plan-prerequisites'),
        'input' => ['approval_hashes' => [ApprovalKind::Angle->value => $approve->inputHashFor($attempt, ApprovalKind::Angle)]],
        'model_snapshot' => [],
        'response' => ['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []],
        'completed_at' => now(),
    ]);

    expect(fn () => $approve->approve($actor, $attempt->fresh(), ApprovalKind::Plan, $approve->inputHashFor($attempt->fresh(), ApprovalKind::Plan)))
        ->toThrow(RuntimeException::class, 'reviewed outline and visual plan');
});

test('plan approval requires research bound to the exact approved brief and angle', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);

    $article = $write->capture($actor, 'Exact research binding.', 'exact-research-binding');
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), editorialMetadata('Exact research binding'), 'exact-research-draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Angle A']);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh();
    $angleAHash = $approve->inputHashFor($attempt, ApprovalKind::Angle);

    EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => EditorialActivityKind::ResearchChallenge,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'drafting',
        'input_version' => $attempt->input_version,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'research-angle-a',
        'idempotency_key' => 'research-angle-a',
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', 'research-angle-a'),
        'input' => ['approval_hashes' => [ApprovalKind::Angle->value => $angleAHash]],
        'model_snapshot' => [],
        'response' => ['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []],
        'completed_at' => now(),
    ]);

    $attempt = $advance->rethink($actor, $attempt, angle: ['thesis' => 'Angle B']);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $advance->rethink($actor, $attempt->fresh(), plan: ['outline' => ['new'], 'visualPlan' => ['new visual']]);

    expect(fn () => $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan)))
        ->toThrow(RuntimeException::class, 'completed research/challenge');

    EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => EditorialActivityKind::ResearchChallenge,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'drafting',
        'input_version' => $attempt->input_version,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'research-angle-b',
        'idempotency_key' => 'research-angle-b',
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', 'research-angle-b'),
        'input' => ['approval_hashes' => [ApprovalKind::Angle->value => $approve->inputHashFor($attempt, ApprovalKind::Angle)]],
        'model_snapshot' => [],
        'response' => ['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []],
        'completed_at' => now(),
    ]);

    $approve->approve($actor, $attempt->fresh(), ApprovalKind::Plan, $approve->inputHashFor($attempt->fresh(), ApprovalKind::Plan));

    expect($attempt->fresh()?->stage)->toBe(EditorialStage::InReview);
});

test('agent generated plan is persisted displayed approved by human and gates draft reviews', function (): void {
    config()->set('publishing_agents.enabled', true);
    config()->set('publishing_agents.openrouter.api_key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '0.000001');
    config()->set('publishing_agents.routes.default.pricing.completion', '0.000002');
    config()->set('publishing_agents.routes.default.context_tokens', 100);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 50);

    $responses = [
        [
            'id' => 'gen-research-pipeline',
            'choices' => [['message' => ['content' => json_encode([
                'claims' => [['statement' => 'Research claim', 'supporting_source_ids' => [], 'supporting_source_refs' => ['research'], 'supporting_quotations' => [['source_ref' => 'research', 'quote' => 'Retained source passage supports the generated plan']]]],
                'sourceReferences' => [['local_id' => 'research', 'url' => 'https://example.com/research', 'title' => 'Research', 'content' => 'Model-authored source text is ignored.']],
                'contradictions' => [],
                'gaps' => [],
            ]), 'annotations' => [[
                'type' => 'url_citation',
                'url_citation' => ['url' => 'https://example.com/research', 'content' => 'Retained source passage supports the generated plan.'],
            ]]]]],
            'usage' => ['cost' => '0.00005'],
        ],
        [
            'id' => 'gen-plan-pipeline',
            'choices' => [['message' => ['content' => json_encode([
                'outline' => [['heading' => 'Lead with the owner problem']],
                'argument' => 'The article should explain the publishing control loop.',
                'visualPlan' => [['slot' => 'hero', 'description' => 'Workflow diagram']],
            ])]]],
            'usage' => ['cost' => '0.00005'],
        ],
        [
            'id' => 'gen-draft-pipeline',
            'choices' => [['message' => ['content' => json_encode([
                'document' => ['version' => 1, 'type' => 'doc', 'content' => [['type' => 'paragraph', 'text' => 'Agent drafted manuscript.']]],
                'metadataProposals' => [],
            ])]]],
            'usage' => ['cost' => '0.00005'],
        ],
        [
            'id' => 'gen-review-facts-pipeline',
            'choices' => [['message' => ['content' => json_encode(['findings' => []])]]],
            'usage' => ['cost' => '0.00005'],
        ],
        [
            'id' => 'gen-review-voice-pipeline',
            'choices' => [['message' => ['content' => json_encode(['findings' => []])]]],
            'usage' => ['cost' => '0.00005'],
        ],
        [
            'id' => 'gen-review-buyer-pipeline',
            'choices' => [['message' => ['content' => json_encode(['findings' => []])]]],
            'usage' => ['cost' => '0.00005'],
        ],
    ];

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => function () use (&$responses) {
            return Http::response(array_shift($responses));
        },
    ]);

    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);
    $article = $write->capture($actor, 'Agent plan pipeline.', 'agent-plan-pipeline');
    $revision = $write->save($actor, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], editorialMetadata('Agent Plan Pipeline'), 'agent-plan-empty-draft');
    $attempt = $advance->develop($actor, $article, $revision->id, ['goal' => 'Show the pipeline']);
    $attempt->forceFill(['interview_context' => [
        'voice_sample_ids' => [42],
        'voice_samples' => [['id' => 42, 'excerpt' => 'Distinct voice sample excerpt for bounded review prompts.']],
    ]])->save();

    expect(EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::ResearchChallenge->value)->exists())->toBeFalse();

    $approve->approve($actor, $attempt->fresh(), ApprovalKind::Angle, $approve->inputHashFor($attempt->fresh(), ApprovalKind::Angle));
    drainEditorialActivities(1);

    $source = EvidenceSource::query()->where('attempt_id', $attempt->id)->firstOrFail();
    $source->forceFill(['extracted_text' => 'Altered later source passage.'])->save();
    drainEditorialActivities(1);

    $attempt = $attempt->fresh();
    expect($attempt?->plan['source'] ?? null)->toBe('agent')
        ->and($attempt?->plan['argument'] ?? null)->toContain('publishing control loop')
        ->and(EditorialActivity::query()->where('attempt_id', $attempt?->id)->where('kind', EditorialActivityKind::Plan->value)->where('status', EditorialActivityStatus::Completed->value)->exists())->toBeTrue()
        ->and(EditorialActivity::query()->where('attempt_id', $attempt?->id)->where('kind', EditorialActivityKind::Draft->value)->exists())->toBeFalse();

    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    drainEditorialActivities(1);

    $draftedRevision = $article->fresh()?->workingRevision()->firstOrFail();
    if ($draftedRevision instanceof ArticleRevision) {
        DB::table('article_revisions')
            ->where('id', $draftedRevision->id)
            ->update(['document' => json_encode(editorialDocument('Altered later manuscript.'), JSON_THROW_ON_ERROR)]);
    }
    drainEditorialActivities(3);

    expect(EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Draft->value)->where('status', EditorialActivityStatus::Completed->value)->exists())->toBeTrue()
        ->and(EditorialActivity::query()->where('attempt_id', $attempt->id)->whereIn('kind', [EditorialActivityKind::ReviewFacts->value, EditorialActivityKind::ReviewVoice->value, EditorialActivityKind::ReviewBuyer->value])->where('status', EditorialActivityStatus::Completed->value)->count())->toBe(3);

    Http::assertSent(function ($request): bool {
        $payload = $request->data();
        $prompt = (string) data_get($payload, 'messages.1.content');

        return str_contains($prompt, 'Agent drafted manuscript.')
            && str_contains($prompt, 'Distinct voice sample excerpt for bounded review prompts.')
            && ! str_contains($prompt, 'Altered later manuscript')
            && data_get($payload, 'response_format.json_schema.schema.properties.findings.items.properties.supporting_quotations.items.properties.quote.type') === 'string';
    });

    $requests = collect(Http::recorded())->map(fn (array $record): array => $record[0]->data())->values();
    $decodedPrompt = fn (array $request): array => json_decode((string) data_get($request, 'messages.1.content'), true, flags: JSON_THROW_ON_ERROR);
    $decodedPrompts = $requests->map($decodedPrompt);
    $requestForKind = fn (EditorialActivityKind $kind): ?array => $requests->first(fn (array $request): bool => data_get($decodedPrompt($request), 'kind') === $kind->value);
    $researchPrompt = $decodedPrompts->firstWhere('kind', EditorialActivityKind::ResearchChallenge->value);
    $planPrompt = $decodedPrompts->firstWhere('kind', EditorialActivityKind::Plan->value);
    $reviewPrompt = $decodedPrompts->firstWhere('kind', EditorialActivityKind::ReviewFacts->value);
    $planRequest = $requestForKind(EditorialActivityKind::Plan);
    $reviewRequest = $requestForKind(EditorialActivityKind::ReviewFacts);

    expect(data_get($researchPrompt, 'input.brief.goal'))->toBe('Show the pipeline')
        ->and(data_get($planPrompt, 'input.completed_research.0.response.claims.0.statement'))->toBe('Research claim')
        ->and(data_get($planPrompt, 'input.evidence_sources.0.extracted_text'))->toBe('Retained source passage supports the generated plan.')
        ->and(str_contains(json_encode($planPrompt, JSON_THROW_ON_ERROR), 'Altered later source passage'))->toBeFalse()
        ->and(data_get($reviewPrompt, 'input.manuscript.document.content.0.content.0.text'))->toBe('Agent drafted manuscript.')
        ->and(data_get($reviewPrompt, 'input.voice_context.samples.0.excerpt'))->toBe('Distinct voice sample excerpt for bounded review prompts.')
        ->and(data_get($reviewPrompt, 'input.voice_context.samples.0.excerpt'))->not->toBeNull()
        ->and(str_contains(json_encode($reviewPrompt, JSON_THROW_ON_ERROR), 'Altered later manuscript'))->toBeFalse()
        ->and(data_get($planRequest, 'response_format.json_schema.schema.required'))->toBe(['outline', 'argument', 'visualPlan'])
        ->and(data_get($reviewRequest, 'response_format.json_schema.schema.properties.findings.items.properties.supporting_quotations.items.properties.quote.type'))->toBe('string');
});

test('pause resume park and abandon retain the attempt identity without creating allowances', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $article = $write->capture($actor, 'Interruptions.', 'interruptions');
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), editorialMetadata('Interruptions'), 'draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id);

    $paused = $advance->pause($actor, $attempt, 'Waiting on source material.');
    $resumed = $advance->resume($actor, $paused);
    $parked = $advance->park($actor, $resumed, 'Not urgent.');
    $abandoned = $advance->abandon($actor, $parked, 'Killed by editor.');

    expect($paused->paused_at)->not->toBeNull()
        ->and($resumed->paused_at)->toBeNull()
        ->and($parked->parked_at)->not->toBeNull()
        ->and($abandoned->stage)->toBe(EditorialStage::Abandoned)
        ->and($abandoned->allowance_nano_usd)->toBe(5_000_000_000);
});

test('interruption transitions reject non current and terminal attempts', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $releases = app(ManageArticleRelease::class);

    $publishedAttempt = approvedEditorialAttempt($actor, 'Published terminal.', 'published-terminal');
    $article = $publishedAttempt->article()->firstOrFail();
    $revision = $article->workingRevision()->firstOrFail();
    $release = $releases->prepare($actor, $publishedAttempt, $revision->id, 'published-terminal');
    $releases->approve($actor, $release, $release->release_hash);
    $releases->deliver($actor, $release, null);

    expect(fn () => $advance->pause($actor, $publishedAttempt, 'Too late.'))
        ->toThrow(RuntimeException::class, 'Terminal publishing attempts cannot be changed');

    $abandonedArticle = $write->capture($actor, 'Abandoned terminal.', 'abandoned-terminal');
    $abandonedRevision = $write->save($actor, $abandonedArticle, null, editorialDocument('Draft'), editorialMetadata('Abandoned'), 'abandoned-draft-1');
    $abandonedAttempt = $advance->develop($actor, $abandonedArticle, $abandonedRevision->id);
    $advance->abandon($actor, $abandonedAttempt, 'Killed.');

    expect(fn () => $advance->resume($actor, $abandonedAttempt))
        ->toThrow(RuntimeException::class, 'Terminal publishing attempts cannot be changed');

    $currentArticle = $write->capture($actor, 'Current guard.', 'current-guard');
    $currentRevision = $write->save($actor, $currentArticle, null, editorialDocument('Draft'), editorialMetadata('Current Guard'), 'current-guard-draft-1');
    $oldAttempt = $advance->develop($actor, $currentArticle, $currentRevision->id);
    $replacementAttempt = PublishingAttempt::factory()->create([
        'article_id' => $currentArticle->id,
        'input_version' => $currentRevision->id,
    ]);
    $currentArticle->forceFill(['current_attempt_id' => $replacementAttempt->id])->save();

    expect(fn () => $advance->park($actor, $oldAttempt, 'Not current.'))
        ->toThrow(RuntimeException::class, 'Only the current publishing attempt can be changed');
});

test('develop never reuses attempts marked with the abandoned stage', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $article = $write->capture($actor, 'Stage abandoned.', 'stage-abandoned');
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), editorialMetadata('Stage Abandoned'), 'stage-abandoned-draft-1');
    $abandonedAttempt = $advance->develop($actor, $article, $revision->id);
    $abandonedAttempt->forceFill([
        'stage' => EditorialStage::Abandoned,
        'abandoned_at' => null,
    ])->save();

    $newAttempt = $advance->develop($actor, $article, $revision->id);

    expect($newAttempt->id)->not->toBe($abandonedAttempt->id)
        ->and($article->fresh()?->current_attempt_id)->toBe($newAttempt->id);
});

function drainEditorialActivities(int $limit = 10): void
{
    for ($i = 0; $i < $limit; $i++) {
        $activity = EditorialActivity::query()
            ->where('status', EditorialActivityStatus::Pending->value)
            ->oldest('id')
            ->first();

        if (! $activity instanceof EditorialActivity) {
            return;
        }

        app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(OpenRouterClient::class), app(AgentBudget::class), app(EditorialPrompts::class), app(WriteArticle::class));
    }
}

function approvedEditorialAttempt(User $actor, string $idea, string $slug): PublishingAttempt
{
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);

    $article = $write->capture($actor, $idea, $slug);
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), editorialMetadata($idea), $slug.'-draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id, ['goal' => $idea]);

    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = publishingAttemptWithReviewedPlan(app(AdvancePublishingAttempt::class), $actor, $attempt->fresh());
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $attempt = $attempt->fresh();
    publishingAttemptWithCompletedReviews($actor, $attempt);

    return $attempt->fresh();
}

function publishingAttemptWithReviewedPlan(AdvancePublishingAttempt $advance, User $actor, PublishingAttempt $attempt, array $plan = ['outline' => ['intro', 'body'], 'visualPlan' => ['hero image']]): PublishingAttempt
{
    EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => EditorialActivityKind::ResearchChallenge,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'drafting',
        'input_version' => $attempt->input_version,
        'revision_id' => null,
        'revision_hash' => null,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'research-'.$attempt->id.'-'.uniqid(),
        'idempotency_key' => 'research-'.$attempt->id.'-'.uniqid(),
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', 'research-'.$attempt->id.uniqid()),
        'input' => ['approval_hashes' => [ApprovalKind::Angle->value => app(ApprovePublishingStage::class)->inputHashFor($attempt, ApprovalKind::Angle)]],
        'model_snapshot' => [],
        'response' => ['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []],
        'completed_at' => now(),
    ]);

    return $advance->rethink($actor, $attempt, plan: $plan);
}

function publishingAttemptWithCompletedReviews(User $actor, PublishingAttempt $attempt): void
{
    $revision = $attempt->article()->firstOrFail()->workingRevision()->firstOrFail();

    foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer, EditorialActivityKind::Reconciliation] as $kind) {
        EditorialActivity::create([
            'article_id' => $attempt->article_id,
            'attempt_id' => $attempt->id,
            'initiating_user_id' => $actor->id,
            'kind' => $kind,
            'status' => EditorialActivityStatus::Completed,
            'stage' => 'in_review',
            'input_version' => $attempt->input_version,
            'revision_id' => $revision->id,
            'revision_hash' => $revision->content_hash,
            'review_cycle' => $attempt->review_cycle,
            'batch_key' => 'review-'.$attempt->id.'-'.uniqid(),
            'idempotency_key' => 'review-'.$attempt->id.'-'.$kind->value.'-'.uniqid(),
            'prompt_version' => 1,
            'prompt_hash' => hash('sha256', 'review-'.$attempt->id.$kind->value.uniqid()),
            'input' => [],
            'model_snapshot' => [],
            'response' => $kind === EditorialActivityKind::Reconciliation ? ['groups' => [], 'conflicts' => []] : ['findings' => []],
            'completed_at' => now(),
        ]);
    }
}

function editorialAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function editorialMetadata(string $title): array
{
    return [
        'title' => $title,
        'description' => $title.' description.',
        'date' => '2024-01-01',
        'tags' => [],
    ];
}

function editorialDocument(string $text): array
{
    return [
        'version' => 1,
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'text' => $text],
        ],
    ];
}

test('pause and resume move pending agent activities without resetting allowance', function (): void {
    $actor = editorialAuthor();
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $article = $write->capture($actor, 'Agent pause resume.', 'agent-pause-resume');
    $revision = $write->save($actor, $article, null, editorialDocument('Draft'), editorialMetadata('Agent Pause'), 'agent-pause-draft-1');
    $attempt = $advance->develop($actor, $article, $revision->id);
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Interview, [], 'pause-resume');

    $advance->pause($actor, $attempt, 'Waiting.');
    expect($activity->fresh()?->status->value)->toBe('paused')
        ->and($attempt->fresh()?->allowance_nano_usd)->toBe(5_000_000_000);

    $advance->resume($actor, $attempt);
    expect($activity->fresh()?->status->value)->toBe('pending')
        ->and($attempt->fresh()?->allowance_nano_usd)->toBe(5_000_000_000);
});
