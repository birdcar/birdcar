<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApplyEditorialProposal;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\FinishEditorialReview;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\AgentBudget;
use App\Services\Publishing\EditorialPrompts;
use App\Services\Publishing\OpenRouterClient;
use App\Services\Publishing\PublishingFingerprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('initial draft writes only into an empty untouched manuscript', function (): void {
    config()->set('publishing_agents.enabled', true);
    config()->set('publishing_agents.openrouter.api_key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '0.000001');
    config()->set('publishing_agents.routes.default.pricing.completion', '0.000002');
    config()->set('publishing_agents.routes.default.context_tokens', 100);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 50);

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'gen-draft',
            'choices' => [['message' => ['content' => json_encode([
                'document' => ['version' => 1, 'type' => 'doc', 'content' => [['type' => 'paragraph', 'text' => 'AI draft']]],
                'metadataProposals' => ['title' => 'AI Draft'],
            ])]]],
            'usage' => ['cost' => '0.00005'],
        ]),
    ]);

    $actor = reviewAuthor();
    $attempt = reviewAttempt($actor, []);
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Draft, [], 'draft-test');
    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(OpenRouterClient::class), app(AgentBudget::class), app(EditorialPrompts::class), app(WriteArticle::class));

    $article = $attempt->article()->firstOrFail()->fresh();
    expect($article?->workingRevision?->origin)->toBe('agent-initial')
        ->and($article?->workingRevision?->document['content'][0]['content'][0]['text'])->toBe('AI draft')
        ->and($article?->workingRevision?->metadata['title'])->toBe('Review')
        ->and($activity->fresh()?->proposal['metadata']['title'])->toBe('AI Draft');
});

test('initial draft does not overwrite nested manuscript text', function (): void {
    config()->set('publishing_agents.enabled', true);
    config()->set('publishing_agents.openrouter.api_key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '0.000001');
    config()->set('publishing_agents.routes.default.pricing.completion', '0.000002');
    config()->set('publishing_agents.routes.default.context_tokens', 100);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 50);

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'gen-nested-draft',
            'choices' => [['message' => ['content' => json_encode([
                'document' => ['version' => 1, 'type' => 'doc', 'content' => [['type' => 'paragraph', 'text' => 'AI replacement']]],
                'metadataProposals' => [],
            ])]]],
            'usage' => ['cost' => '0.00005'],
        ]),
    ]);

    $actor = reviewAuthor();
    $attempt = reviewAttempt($actor, [[
        'type' => 'bulletList',
        'content' => [[
            'type' => 'listItem',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Human nested text']],
            ]],
        ]],
    ]]);
    $originalRevisionId = (int) $attempt->article()->firstOrFail()->fresh()->working_revision_id;
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Draft, [], 'draft-nested-text');

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(OpenRouterClient::class), app(AgentBudget::class), app(EditorialPrompts::class), app(WriteArticle::class));

    expect((int) $attempt->article()->firstOrFail()->fresh()->working_revision_id)->toBe($originalRevisionId)
        ->and($activity->fresh()?->proposal['document']['content'][0]['text'])->toBe('AI replacement')
        ->and($activity->fresh()?->proposal['reason'])->toContain('not empty');
});

test('review lenses are queued only after draft completion on the drafted revision', function (): void {
    config()->set('publishing_agents.enabled', true);
    config()->set('publishing_agents.openrouter.api_key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '0.000001');
    config()->set('publishing_agents.routes.default.pricing.completion', '0.000002');
    config()->set('publishing_agents.routes.default.context_tokens', 100);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 50);
    Queue::fake();

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'gen-draft-review',
            'choices' => [['message' => ['content' => json_encode([
                'document' => ['version' => 1, 'type' => 'doc', 'content' => [['type' => 'paragraph', 'text' => 'Draft for review']]],
                'metadataProposals' => ['title' => 'Proposed'],
            ])]]],
            'usage' => ['cost' => '0.00005'],
        ]),
    ]);

    $actor = reviewAuthor();
    $attempt = reviewAttempt($actor, []);

    expect(EditorialActivity::query()->where('attempt_id', $attempt->id)->whereIn('kind', ['review_facts', 'review_voice', 'review_buyer'])->count())->toBe(0);

    $draft = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Draft, [], 'draft-review-queue');
    app(RunEditorialActivity::class, ['activityId' => $draft->id])->handle(app(OpenRouterClient::class), app(AgentBudget::class), app(EditorialPrompts::class), app(WriteArticle::class));
    $reviewRevisionId = (int) $attempt->article()->firstOrFail()->fresh()->working_revision_id;

    expect(EditorialActivity::query()->where('attempt_id', $attempt->id)->whereIn('kind', ['review_facts', 'review_voice', 'review_buyer'])->count())->toBe(3)
        ->and(EditorialActivity::query()->where('attempt_id', $attempt->id)->whereIn('kind', ['review_facts', 'review_voice', 'review_buyer'])->pluck('revision_id')->unique()->all())->toBe([$reviewRevisionId]);
});

test('proposal acceptance rejects protected prose and stale hashes', function (): void {
    $actor = reviewAuthor();
    $attempt = reviewAttempt($actor, [['type' => 'paragraph', 'attrs' => ['id' => 'blk_1111111111111111', 'protected' => true], 'content' => [['type' => 'text', 'text' => 'Protected']]]]);
    $article = $attempt->article()->firstOrFail()->fresh(['workingRevision']);
    $revision = $article?->workingRevision;
    $block = $revision?->document['content'][0];
    $hash = app(PublishingFingerprint::class)->hash($block);

    $finding = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => 1,
        'revision_id' => $revision?->id,
        'input_hash' => $revision?->content_hash,
        'lens' => 'review_voice',
        'kind' => 'style',
        'severity' => 'advisory',
        'block_id' => 'blk_1111111111111111',
        'expected_subtree_hash' => $hash,
        'statement' => 'Tighten prose.',
        'supporting_source_ids' => [],
        'proposed_patch' => ['block_id' => 'blk_1111111111111111', 'expected_hash' => $hash, 'replacement' => ['type' => 'paragraph', 'attrs' => ['id' => 'blk_1111111111111111'], 'content' => [['type' => 'text', 'text' => 'Changed']]]],
    ]);

    expect(fn () => app(ApplyEditorialProposal::class)->apply($actor, $attempt, [$finding->id], (int) $revision?->id))
        ->toThrow(RuntimeException::class, 'Protected passages');
});

test('proposal acceptance rejects full document replacement findings', function (): void {
    $actor = reviewAuthor();
    $attempt = reviewAttempt($actor, [['type' => 'paragraph', 'attrs' => ['id' => 'blk_1212121212121212'], 'content' => [['type' => 'text', 'text' => 'Base']]]]);
    $article = $attempt->article()->firstOrFail()->fresh(['workingRevision']);
    $revision = $article?->workingRevision;

    $finding = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => 1,
        'revision_id' => $revision?->id,
        'input_hash' => $revision?->content_hash,
        'lens' => 'review_voice',
        'kind' => 'style',
        'severity' => 'advisory',
        'statement' => 'Replace everything.',
        'supporting_source_ids' => [],
        'proposed_patch' => ['document' => ['version' => 1, 'type' => 'doc', 'content' => []]],
    ]);

    expect(fn () => app(ApplyEditorialProposal::class)->apply($actor, $attempt, [$finding->id], (int) $revision?->id))
        ->toThrow(RuntimeException::class, 'bounded block patches');
});

test('proposal acceptance rejects nested overlapping patches against one base', function (): void {
    $actor = reviewAuthor();
    $attempt = reviewAttempt($actor, [[
        'type' => 'bulletList',
        'attrs' => ['id' => 'blk_2222222222222222'],
        'content' => [[
            'type' => 'listItem',
            'attrs' => ['id' => 'blk_4444444444444444'],
            'content' => [[
                'type' => 'paragraph',
                'attrs' => ['id' => 'blk_3333333333333333'],
                'content' => [['type' => 'text', 'text' => 'Nested']],
            ]],
        ]],
    ]]);
    $article = $attempt->article()->firstOrFail()->fresh(['workingRevision']);
    $revision = $article?->workingRevision;
    $parent = $revision?->document['content'][0];
    $child = $revision?->document['content'][0]['content'][0]['content'][0];
    $fingerprint = app(PublishingFingerprint::class);

    $parentFinding = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => 1,
        'revision_id' => $revision?->id,
        'input_hash' => $revision?->content_hash,
        'lens' => 'review_voice',
        'kind' => 'style',
        'severity' => 'advisory',
        'block_id' => 'blk_2222222222222222',
        'expected_subtree_hash' => $fingerprint->hash($parent),
        'statement' => 'Change section.',
        'supporting_source_ids' => [],
        'proposed_patch' => ['block_id' => 'blk_2222222222222222', 'expected_hash' => $fingerprint->hash($parent), 'replacement' => ['type' => 'bulletList', 'attrs' => ['id' => 'blk_2222222222222222'], 'content' => []]],
    ]);
    $childFinding = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => 1,
        'revision_id' => $revision?->id,
        'input_hash' => $revision?->content_hash,
        'lens' => 'review_voice',
        'kind' => 'style',
        'severity' => 'advisory',
        'block_id' => 'blk_3333333333333333',
        'expected_subtree_hash' => $fingerprint->hash($child),
        'statement' => 'Change paragraph.',
        'supporting_source_ids' => [],
        'proposed_patch' => ['block_id' => 'blk_3333333333333333', 'expected_hash' => $fingerprint->hash($child), 'replacement' => ['type' => 'paragraph', 'attrs' => ['id' => 'blk_3333333333333333'], 'content' => [['type' => 'text', 'text' => 'Changed']]]],
    ]);

    expect(fn () => app(ApplyEditorialProposal::class)->apply($actor, $attempt, [$parentFinding->id, $childFinding->id], (int) $revision?->id))
        ->toThrow(RuntimeException::class, 'overlap');
});

test('reconciliation deduplicates compatible findings and retains conflicts for human decision', function (): void {
    config()->set('publishing_agents.enabled', true);
    config()->set('publishing_agents.openrouter.api_key', 'test-key');
    config()->set('publishing_agents.routes.default.pricing.prompt', '0.000001');
    config()->set('publishing_agents.routes.default.pricing.completion', '0.000002');
    config()->set('publishing_agents.routes.default.context_tokens', 100);
    config()->set('publishing_agents.routes.default.max_completion_tokens', 50);

    $actor = reviewAuthor();
    $attempt = reviewAttempt($actor, [['type' => 'paragraph', 'text' => 'Review me']]);
    $revision = $attempt->article()->firstOrFail()->workingRevision()->firstOrFail();

    $reviewActivityId = null;
    foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer] as $kind) {
        $activity = EditorialActivity::create([
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
            'batch_key' => 'reconcile-review-'.$kind->value,
            'idempotency_key' => 'reconcile-review-'.$kind->value.'-'.uniqid(),
            'prompt_version' => 1,
            'prompt_hash' => hash('sha256', 'reconcile-review-'.$kind->value),
            'input' => ['approval_hashes' => [ApprovalKind::Plan->value => app(ApprovePublishingStage::class)->inputHashFor($attempt, ApprovalKind::Plan)]],
            'model_snapshot' => [],
            'response' => ['findings' => []],
            'completed_at' => now(),
        ]);
        $reviewActivityId ??= $activity->id;
    }

    $duplicateA = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'activity_id' => $reviewActivityId,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $revision->id,
        'input_hash' => $revision->content_hash,
        'lens' => 'review_facts',
        'kind' => 'clarity',
        'severity' => 'advisory',
        'statement' => 'Duplicate clarity issue A.',
        'supporting_source_ids' => [],
    ]);
    $duplicateB = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'activity_id' => $reviewActivityId,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $revision->id,
        'input_hash' => $revision->content_hash,
        'lens' => 'review_voice',
        'kind' => 'clarity',
        'severity' => 'advisory',
        'statement' => 'Duplicate clarity issue B.',
        'supporting_source_ids' => [],
    ]);
    $conflictA = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'activity_id' => $reviewActivityId,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $revision->id,
        'input_hash' => $revision->content_hash,
        'lens' => 'review_facts',
        'kind' => 'positioning',
        'severity' => 'advisory',
        'statement' => 'Lead with price.',
        'supporting_source_ids' => [],
    ]);
    $conflictB = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'activity_id' => $reviewActivityId,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $revision->id,
        'input_hash' => $revision->content_hash,
        'lens' => 'review_buyer',
        'kind' => 'positioning',
        'severity' => 'advisory',
        'statement' => 'Do not lead with price.',
        'supporting_source_ids' => [],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'gen-reconciliation',
            'choices' => [['message' => ['content' => json_encode([
                'groups' => [[
                    'finding_ids' => [$duplicateA->id, $duplicateB->id],
                    'canonical_finding_id' => $duplicateA->id,
                    'summary' => 'Same clarity issue.',
                ]],
                'conflicts' => [[
                    'finding_ids' => [$conflictA->id, $conflictB->id],
                    'reason' => 'Opposing lead recommendations.',
                ]],
            ])]]],
            'usage' => ['cost' => '0.00005'],
        ]),
    ]);

    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Reconciliation, ['expected_revision_id' => $revision->id], 'reconciliation-regression');
    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(OpenRouterClient::class), app(AgentBudget::class), app(EditorialPrompts::class), app(WriteArticle::class));

    expect($duplicateA->fresh()?->reconciliation_state)->toBe('representative')
        ->and($duplicateA->fresh()?->stale_at)->toBeNull()
        ->and($duplicateB->fresh()?->reconciliation_state)->toBe('duplicate')
        ->and($duplicateB->fresh()?->reconciled_into_finding_id)->toBe($duplicateA->id)
        ->and($duplicateB->fresh()?->stale_at)->not->toBeNull()
        ->and($conflictA->fresh()?->reconciliation_state)->toBe('conflict')
        ->and($conflictB->fresh()?->reconciliation_payload['reason'])->toContain('Opposing');

    expect(fn () => app(FinishEditorialReview::class)->finish($actor, $attempt->fresh(), (int) $revision->id, [], false))
        ->toThrow(RuntimeException::class, 'Conflicting reconciled review findings');

    $finished = app(FinishEditorialReview::class)->finish($actor, $attempt->fresh(), (int) $revision->id, [
        $conflictA->id => ['disposition' => 'rejected'],
        $conflictB->id => ['disposition' => 'accepted'],
    ], false);

    expect($finished)->toBeNull();
});

test('finish review requires three same revision lenses and only rechecks affected areas once', function (): void {
    config()->set('publishing_agents.enabled', false);
    $actor = reviewAuthor();
    $attempt = reviewAttempt($actor, [['type' => 'paragraph', 'text' => 'Review me']]);
    $revisionId = (int) $attempt->article()->firstOrFail()->working_revision_id;
    $revisionHash = (string) $attempt->article()->firstOrFail()->workingRevision()->firstOrFail()->content_hash;

    expect(fn () => app(FinishEditorialReview::class)->finish($actor, $attempt, $revisionId, [], true))
        ->toThrow(RuntimeException::class, 'All three same-revision');

    $reviewActivityId = null;
    foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer] as $kind) {
        $activity = EditorialActivity::create([
            'article_id' => $attempt->article_id,
            'attempt_id' => $attempt->id,
            'initiating_user_id' => $actor->id,
            'kind' => $kind,
            'status' => EditorialActivityStatus::Completed,
            'stage' => 'in_review',
            'input_version' => $attempt->input_version,
            'revision_id' => $revisionId,
            'revision_hash' => $revisionHash,
            'review_cycle' => 1,
            'batch_key' => 'review-1',
            'idempotency_key' => 'review-'.$kind->value,
            'prompt_version' => 1,
            'prompt_hash' => hash('sha256', $kind->value),
            'input' => ['approval_hashes' => [ApprovalKind::Plan->value => app(ApprovePublishingStage::class)->inputHashFor($attempt, ApprovalKind::Plan)]],
            'model_snapshot' => [],
            'response' => ['findings' => []],
            'completed_at' => now(),
        ]);
        $reviewActivityId ??= $activity->id;
    }

    $reconciliation = app(FinishEditorialReview::class)->finish($actor, $attempt, $revisionId, [], true);

    expect($reconciliation?->kind)->toBe(EditorialActivityKind::Reconciliation)
        ->and($attempt->fresh()?->recheck_used)->toBeFalse();

    $reconciliation->forceFill([
        'status' => EditorialActivityStatus::Completed,
        'response' => ['groups' => [], 'conflicts' => []],
        'completed_at' => now(),
    ])->save();

    $cleanRecheck = app(FinishEditorialReview::class)->finish($actor, $attempt->fresh(), $revisionId, [], true);

    expect($cleanRecheck)->toBeNull()
        ->and($attempt->fresh()?->recheck_used)->toBeFalse();

    $finding = EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'activity_id' => $reviewActivityId,
        'review_cycle' => 1,
        'revision_id' => $revisionId,
        'input_hash' => $revisionHash,
        'lens' => 'review_facts',
        'kind' => 'clarity',
        'severity' => 'advisory',
        'statement' => 'Tighten this passage.',
        'proposed_patch' => ['path' => [0], 'replacement' => ['type' => 'paragraph', 'text' => 'Tighter']],
    ]);

    $recheck = app(FinishEditorialReview::class)->finish($actor, $attempt->fresh(), $revisionId, [
        $finding->id => ['disposition' => 'accepted'],
    ], true);
    $duplicate = app(FinishEditorialReview::class)->finish($actor, $attempt->fresh(), $revisionId, [
        $finding->id => ['disposition' => 'accepted'],
    ], true);

    expect($recheck?->kind)->toBe(EditorialActivityKind::Recheck)
        ->and($duplicate)->toBeNull()
        ->and($attempt->fresh()?->recheck_used)->toBeTrue()
        ->and(EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Recheck->value)->count())->toBe(1);
});

function reviewAttemptWithReviewedPlan(User $actor, PublishingAttempt $attempt): PublishingAttempt
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

    return app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, plan: ['outline' => ['intro', 'body'], 'visualPlan' => ['hero image']]);
}

function reviewAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

/** @param list<array<string, mixed>> $content */
function reviewAttempt(User $actor, array $content): PublishingAttempt
{
    $writer = app(WriteArticle::class);
    $article = $writer->capture($actor, 'Review agent work.', 'review-agent-work-'.uniqid());
    $revision = $writer->save($actor, $article, null, ['version' => 1, 'type' => 'doc', 'content' => $content], ['title' => 'Review'], 'review-draft-'.uniqid());

    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id, ['goal' => 'Review']);
    $enabled = (bool) config('publishing_agents.enabled', false);
    config()->set('publishing_agents.enabled', false);
    $approve = app(ApprovePublishingStage::class);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = reviewAttemptWithReviewedPlan($actor, $attempt->fresh());
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    config()->set('publishing_agents.enabled', $enabled);

    return $attempt->fresh();
}
