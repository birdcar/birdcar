<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\FinishEditorialReview;
use App\Actions\Publishing\LintRevision;
use App\Actions\Publishing\RetryEditorialActivity;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\EditorialOutput;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function lintWorkflowAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $user;
}

/** An attempt in review with an approved plan and an empty manuscript, so a draft applies as the first revision. */
function lintWorkflowAttempt(User $actor): PublishingAttempt
{
    $writer = app(WriteArticle::class);
    $approve = app(ApprovePublishingStage::class);
    $article = $writer->captureIdea($actor, 'Why another admin hire will not fix the Friday report.');
    $revision = $writer->save($actor, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'The Friday report'], 'lint-workflow-'.uniqid());
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id, ['goal' => 'Show owners what to check first']);
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
        'batch_key' => 'lint-workflow-research-'.$attempt->id,
        'idempotency_key' => 'lint-workflow-research-'.$attempt->id,
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', 'lint-workflow-research-'.$attempt->id),
        'input' => ['approval_hashes' => [ApprovalKind::Angle->value => $approve->inputHashFor($attempt, ApprovalKind::Angle)]],
        'model_snapshot' => [],
        'response' => ['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []],
        'completed_at' => now(),
    ]);
    $attempt = app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, plan: ['outline' => ['intro', 'body'], 'visualPlan' => ['hero image']]);
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));

    return $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');
}

/** @return array<string, mixed> */
function lintWorkflowParagraph(string $id, string $text, bool $protected = false): array
{
    return ['type' => 'paragraph', 'attrs' => ['id' => $id, 'protected' => $protected], 'content' => [['type' => 'text', 'text' => $text]]];
}

/** @return array<string, mixed> */
function lintWorkflowClosing(): array
{
    return ['type' => 'paragraph', 'attrs' => ['id' => 'blk_00000000000000ff', 'protected' => false], 'content' => [
        ['type' => 'text', 'text' => 'If this sounds like your week, bring it to '],
        ['type' => 'text', 'text' => 'a Walkthrough', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/walkthrough']]]],
        ['type' => 'text', 'text' => '.'],
    ]];
}

/**
 * @param  list<array<string, mixed>>  $content
 * @return array<string, mixed>
 */
function lintWorkflowDocument(array $content): array
{
    return ['version' => 1, 'type' => 'doc', 'content' => $content];
}

/**
 * Routes each agent request by its kind. The repair answer is built from the request's frozen input, the way the model
 * echoes the block hashes it was given.
 *
 * @param  list<array<string, mixed>>  $draft
 * @param  (Closure(string): string)|null  $repairText
 * @param  (Closure(array<string, mixed>): list<array<string, string>>)|null  $repairPatches
 */
function lintWorkflowFakeAgents(array $draft, ?Closure $repairText = null, ?Closure $repairPatches = null): void
{
    setPublishingAgentsPaused(false);
    config()->set('ai.providers.openrouter.key', 'test-key');
    Queue::fake([RunEditorialActivity::class]);
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => function (Request $request) use ($draft, $repairText, $repairPatches) {
        $prompt = json_decode((string) data_get($request->data(), 'messages.1.content'), true, flags: JSON_THROW_ON_ERROR);
        $input = is_array($prompt['input'] ?? null) ? $prompt['input'] : [];

        $payload = match ($prompt['kind'] ?? null) {
            EditorialActivityKind::Draft->value => ['document' => json_encode(lintWorkflowDocument($draft), JSON_THROW_ON_ERROR), 'metadataProposals' => []],
            EditorialActivityKind::LintRepair->value => ['patches' => $repairPatches !== null ? $repairPatches($input) : lintWorkflowPatches($input, $repairText ?? fn (string $text): string => $text)],
            EditorialActivityKind::ReviewFacts->value, EditorialActivityKind::ReviewVoice->value, EditorialActivityKind::ReviewBuyer->value => ['findings' => []],
            EditorialActivityKind::Reconciliation->value => ['groups' => [], 'conflicts' => []],
            default => throw new RuntimeException('Unexpected agent kind '.json_encode($prompt['kind'] ?? null)),
        };

        return Http::response(['id' => 'gen-lint-workflow', 'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode($payload, JSON_THROW_ON_ERROR)]]]]);
    }]);
}

/**
 * One patch per frozen block, with its text rewritten.
 *
 * @param  array<string, mixed>  $input
 * @param  Closure(string): string  $rewrite
 * @return list<array<string, string>>
 */
function lintWorkflowPatches(array $input, Closure $rewrite): array
{
    $patches = [];
    foreach (is_array($input['blocks'] ?? null) ? $input['blocks'] : [] as $block) {
        $node = is_array($block['node'] ?? null) ? $block['node'] : [];
        $node['content'] = array_map(fn (array $child): array => [...$child, 'text' => $rewrite((string) ($child['text'] ?? ''))], is_array($node['content'] ?? null) ? $node['content'] : []);
        $patches[] = [
            'hit_id' => (string) ($block['hit_ids'][0] ?? ''),
            'block_id' => (string) $block['block_id'],
            'expected_hash' => (string) $block['hash'],
            'replacement' => json_encode($node, JSON_THROW_ON_ERROR),
        ];
    }

    return $patches;
}

function lintWorkflowRun(EditorialActivity $activity): void
{
    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialOutput::class), app(WriteArticle::class));
}

function lintWorkflowDraft(User $actor, PublishingAttempt $attempt): EditorialActivity
{
    $draft = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Draft, [], 'draft-lint-workflow');
    lintWorkflowRun($draft);

    return $draft->fresh() ?? throw new RuntimeException('The draft disappeared.');
}

function lintWorkflowPendingRepair(PublishingAttempt $attempt): EditorialActivity
{
    return EditorialActivity::query()
        ->where('attempt_id', $attempt->id)
        ->where('kind', EditorialActivityKind::LintRepair->value)
        ->where('status', EditorialActivityStatus::Pending->value)
        ->sole();
}

function lintWorkflowWorkingRevision(PublishingAttempt $attempt): ArticleRevision
{
    return Article::query()->findOrFail($attempt->article_id)->workingRevision()->firstOrFail();
}

/** @return list<string> */
function lintWorkflowTexts(ArticleRevision $revision): array
{
    $content = is_array($revision->document['content'] ?? null) ? $revision->document['content'] : [];

    return array_values(array_map(fn (array $node): string => implode('', array_column(is_array($node['content'] ?? null) ? $node['content'] : [], 'text')), $content));
}

/** @return array<int, string> lens kinds queued on the revision with the post-draft batch key */
function lintWorkflowQueuedLenses(PublishingAttempt $attempt, ArticleRevision $revision): array
{
    return EditorialActivity::query()
        ->where('attempt_id', $attempt->id)
        ->where('revision_id', $revision->id)
        ->where('batch_key', 'review-'.$attempt->review_cycle.'-'.$revision->id)
        ->whereIn('kind', ['review_facts', 'review_voice', 'review_buyer'])
        ->orderBy('kind')
        ->pluck('kind')
        ->map(fn (EditorialActivityKind $kind): string => $kind->value)
        ->values()
        ->all();
}

/** @return array<int, string> */
function lintWorkflowOpenFindingKinds(PublishingAttempt $attempt): array
{
    return EditorialFinding::query()
        ->where('attempt_id', $attempt->id)
        ->where('lens', LintRevision::LENS)
        ->whereNull('stale_at')
        ->orderBy('id')
        ->get()
        ->map(fn (EditorialFinding $finding): string => $finding->kind)
        ->values()
        ->all();
}

test('a repair that clears the hits rewrites only agent text and then queues the three review lenses', function () {
    $actor = lintWorkflowAuthor();
    $attempt = lintWorkflowAttempt($actor);
    lintWorkflowFakeAgents(
        [lintWorkflowParagraph('blk_00000000000000a1', 'The report takes an afternoon — every week — and nobody knows why.'), lintWorkflowClosing()],
        fn (string $text): string => str_replace(' — ', ', ', $text),
    );

    lintWorkflowDraft($actor, $attempt);
    $repair = lintWorkflowPendingRepair($attempt);

    expect(array_column($repair->input['hits'] ?? [], 'rule_id'))->toBe(['em-dash', 'em-dash'])
        ->and(array_column($repair->input['blocks'] ?? [], 'block_id'))->toBe(['blk_00000000000000a1'])
        ->and(lintWorkflowQueuedLenses($attempt, lintWorkflowWorkingRevision($attempt)))->toBe([]);

    lintWorkflowRun($repair);

    $revision = lintWorkflowWorkingRevision($attempt);
    expect($revision->origin)->toBe('agent-repair')
        ->and(lintWorkflowTexts($revision)[0])->toBe('The report takes an afternoon, every week, and nobody knows why.')
        ->and($repair->fresh()?->status)->toBe(EditorialActivityStatus::Completed)
        ->and($repair->fresh()?->proposal['applied_revision_id'] ?? null)->toBe($revision->id)
        ->and(lintWorkflowQueuedLenses($attempt, $revision))->toBe(['review_buyer', 'review_facts', 'review_voice'])
        ->and(lintWorkflowOpenFindingKinds($attempt))->toBe([])
        ->and(EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::LintRepair->value)->count())->toBe(1);
});

test('repairs that never clear a hit stop after two passes and leave one blocking finding before review', function () {
    $actor = lintWorkflowAuthor();
    $attempt = lintWorkflowAttempt($actor);
    lintWorkflowFakeAgents(
        [lintWorkflowParagraph('blk_00000000000000a1', 'I would leverage the CRM.'), lintWorkflowClosing()],
        fn (string $text): string => str_replace('.', ' again.', $text),
    );

    lintWorkflowDraft($actor, $attempt);
    lintWorkflowRun(lintWorkflowPendingRepair($attempt));
    lintWorkflowRun(lintWorkflowPendingRepair($attempt));

    $revision = lintWorkflowWorkingRevision($attempt);
    $finding = EditorialFinding::query()->where('attempt_id', $attempt->id)->where('lens', LintRevision::LENS)->whereNull('stale_at')->sole();
    expect(EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::LintRepair->value)->pluck('status')->all())
        ->toBe([EditorialActivityStatus::Completed, EditorialActivityStatus::Completed])
        ->and($finding->kind)->toBe('lint:corporate-vocabulary')
        ->and($finding->severity)->toBe('blocking')
        ->and($finding->revision_id)->toBe($revision->id)
        ->and($finding->block_id)->toBe('blk_00000000000000a1')
        ->and(lintWorkflowQueuedLenses($attempt, $revision))->toBe(['review_buyer', 'review_facts', 'review_voice']);
});

test('a protected block is never rewritten and its finding offers the fix only after the owner unlocks it', function () {
    $actor = lintWorkflowAuthor();
    $attempt = lintWorkflowAttempt($actor);
    lintWorkflowFakeAgents(
        [lintWorkflowParagraph('blk_00000000000000a1', 'I would leverage the CRM for the follow-up.', protected: true), lintWorkflowClosing()],
        fn (string $text): string => str_replace('leverage', 'use', $text),
    );
    lintWorkflowDraft($actor, $attempt);
    $draftedRevision = lintWorkflowWorkingRevision($attempt);

    lintWorkflowRun(lintWorkflowPendingRepair($attempt));

    $finding = EditorialFinding::query()->where('attempt_id', $attempt->id)->where('lens', LintRevision::LENS)->whereNull('stale_at')->sole();
    expect(lintWorkflowWorkingRevision($attempt)->id)->toBe($draftedRevision->id)
        ->and(lintWorkflowTexts($draftedRevision)[0])->toBe('I would leverage the CRM for the follow-up.')
        ->and($finding->kind)->toBe('lint:corporate-vocabulary')
        ->and($finding->proposed_patch['replacement']['content'][0]['text'] ?? null)->toBe('I would use the CRM for the follow-up.')
        ->and(lintWorkflowQueuedLenses($attempt, $draftedRevision))->toBe(['review_buyer', 'review_facts', 'review_voice']);

    $workspace = Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => Article::query()->findOrFail($attempt->article_id)])
        ->call('applyProposal', $finding->id)
        ->assertSet('saveError', 'Protected passages must be unlocked before accepting this proposal.');

    expect(lintWorkflowWorkingRevision($attempt)->id)->toBe($draftedRevision->id);

    $workspace->call('unprotectBlock', 'blk_00000000000000a1')->assertSet('saveError', null);

    $unlocked = lintWorkflowWorkingRevision($attempt);
    $carried = EditorialFinding::query()->where('attempt_id', $attempt->id)->where('lens', LintRevision::LENS)->whereNull('stale_at')->sole();
    expect($unlocked->id)->not->toBe($draftedRevision->id)
        ->and($carried->revision_id)->toBe($unlocked->id)
        ->and(data_get($carried->proposed_patch, 'replacement.attrs.protected'))->toBeFalse();

    $workspace->call('applyProposal', $carried->id)->assertSet('saveError', null);

    $accepted = lintWorkflowWorkingRevision($attempt);
    expect($accepted->origin)->toBe('agent-accepted')
        ->and(lintWorkflowTexts($accepted)[0])->toBe('I would use the CRM for the follow-up.')
        ->and(data_get($accepted->document, 'content.0.attrs.protected'))->toBeFalse()
        ->and($carried->fresh()?->disposition)->toBe('accepted')
        ->and(lintWorkflowOpenFindingKinds($attempt))->toBe([]);
});

test('a repair rewrites agent-written blocks but leaves an owner-edited block for the owner to accept', function () {
    $actor = lintWorkflowAuthor();
    $attempt = lintWorkflowAttempt($actor);
    lintWorkflowFakeAgents(
        [lintWorkflowParagraph('blk_00000000000000a1', 'I would leverage the CRM.'), lintWorkflowParagraph('blk_00000000000000a2', 'The robust report is late.'), lintWorkflowClosing()],
        fn (string $text): string => str_replace(['leverage', 'robust'], ['use', 'weekly'], $text),
    );
    lintWorkflowDraft($actor, $attempt);
    $drafted = lintWorkflowWorkingRevision($attempt);
    $ownerEdit = $drafted->document;
    $ownerEdit['content'][1]['content'][0]['text'] = 'The robust report is very late.';
    $ownerRevision = app(WriteArticle::class)->save($actor, $attempt->article_id, $drafted->id, $ownerEdit, $drafted->metadata ?? [], 'lint-workflow-owner-edit');
    $lint = app(LintRevision::class);
    $repair = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::LintRepair, $lint->repairInput($ownerRevision, $lint->repairable($lint->hits($ownerRevision->article()->firstOrFail(), $ownerRevision))), 'lint-repair-owner-edit');

    lintWorkflowRun($repair);
    $repaired = lintWorkflowWorkingRevision($attempt);
    $followUp = EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::LintRepair->value)->where('revision_id', $repaired->id)->sole();
    lintWorkflowRun($followUp);

    $finding = EditorialFinding::query()->where('attempt_id', $attempt->id)->where('lens', LintRevision::LENS)->whereNull('stale_at')->sole();
    expect($repaired->origin)->toBe('agent-repair')
        ->and(lintWorkflowTexts($repaired))->toBe(['I would use the CRM.', 'The robust report is very late.', 'If this sounds like your week, bring it to a Walkthrough.'])
        ->and(lintWorkflowWorkingRevision($attempt)->id)->toBe($repaired->id)
        ->and($finding->block_id)->toBe('blk_00000000000000a2')
        ->and($finding->proposed_patch['replacement']['content'][0]['text'] ?? null)->toBe('The weekly report is very late.');

    Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => Article::query()->findOrFail($attempt->article_id)])
        ->call('applyProposal', $finding->id)
        ->assertSet('saveError', null);

    $accepted = lintWorkflowWorkingRevision($attempt);
    expect($accepted->origin)->toBe('agent-accepted')
        ->and(lintWorkflowTexts($accepted)[1])->toBe('The weekly report is very late.')
        ->and($finding->fresh()?->disposition)->toBe('accepted')
        ->and(lintWorkflowOpenFindingKinds($attempt))->toBe([]);
});

test('a block the owner restores to text a repair already replaced counts as owner-written', function () {
    $actor = lintWorkflowAuthor();
    $attempt = lintWorkflowAttempt($actor);
    $passes = 0;
    lintWorkflowFakeAgents(
        [lintWorkflowParagraph('blk_00000000000000a1', 'I would leverage the CRM.'), lintWorkflowParagraph('blk_00000000000000a2', 'The robust report is late.'), lintWorkflowClosing()],
        repairPatches: function (array $input) use (&$passes): array {
            $passes++;
            $patches = lintWorkflowPatches($input, fn (string $text): string => str_replace(['leverage', 'robust'], ['use', 'weekly'], $text));

            return $passes === 1 ? array_slice($patches, 0, 1) : $patches;
        },
    );
    lintWorkflowDraft($actor, $attempt);
    lintWorkflowRun(lintWorkflowPendingRepair($attempt));
    $repaired = lintWorkflowWorkingRevision($attempt);
    $restore = $repaired->document;
    $restore['content'][0]['content'][0]['text'] = 'I would leverage the CRM.';
    $restored = app(WriteArticle::class)->save($actor, $attempt->article_id, $repaired->id, $restore, $repaired->metadata ?? [], 'lint-workflow-owner-restore');
    $lint = app(LintRevision::class);
    $repair = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::LintRepair, $lint->repairInput($restored, $lint->repairable($lint->hits($restored->article()->firstOrFail(), $restored))), 'lint-repair-owner-restore');

    lintWorkflowRun($repair);

    $final = lintWorkflowWorkingRevision($attempt);
    $finding = EditorialFinding::query()->where('attempt_id', $attempt->id)->where('lens', LintRevision::LENS)->whereNull('stale_at')->sole();
    expect(array_slice(lintWorkflowTexts($repaired), 0, 2))->toBe(['I would use the CRM.', 'The robust report is late.'])
        ->and($repair->fresh()?->proposal['applied_block_ids'] ?? null)->toBe(['blk_00000000000000a2'])
        ->and($final->origin)->toBe('agent-repair')
        ->and(array_slice(lintWorkflowTexts($final), 0, 2))->toBe(['I would leverage the CRM.', 'The weekly report is late.'])
        ->and($finding->block_id)->toBe('blk_00000000000000a1')
        ->and($finding->proposed_patch['replacement']['content'][0]['text'] ?? null)->toBe('I would use the CRM.');
});

test('an owner save that adds a curly quote records one voice-rule finding and a fix clears it', function () {
    $actor = lintWorkflowAuthor();
    $attempt = lintWorkflowAttempt($actor);
    lintWorkflowFakeAgents([lintWorkflowParagraph('blk_00000000000000a1', "It's a memory problem."), lintWorkflowClosing()]);
    lintWorkflowDraft($actor, $attempt);
    $drafted = lintWorkflowWorkingRevision($attempt);
    $curly = lintWorkflowDocument([lintWorkflowParagraph('blk_00000000000000a1', 'It’s a memory problem.'), lintWorkflowClosing()]);
    $workspace = Livewire::actingAs($actor)->test('admin.publishing.article-workspace', ['article' => Article::query()->findOrFail($attempt->article_id)]);

    $workspace->call('saveDocument', $drafted->id, 'lint-workflow-curly', $curly, $drafted->metadata ?? [])->assertSet('saveState', 'saved');

    $finding = EditorialFinding::query()->where('attempt_id', $attempt->id)->where('lens', LintRevision::LENS)->whereNull('stale_at')->sole();
    expect($finding->kind)->toBe('lint:curly-quotes')
        ->and($finding->severity)->toBe('blocking')
        ->and($finding->revision_id)->toBe(lintWorkflowWorkingRevision($attempt)->id);
    $workspace->assertSee('Voice rules')->assertSee('Use straight quotes and apostrophes');

    $workspace->call('saveDocument', lintWorkflowWorkingRevision($attempt)->id, 'lint-workflow-straight', lintWorkflowDocument([lintWorkflowParagraph('blk_00000000000000a1', "It's a memory problem."), lintWorkflowClosing()]), $drafted->metadata ?? [])->assertSet('saveState', 'saved');

    expect($finding->fresh()?->stale_at)->not->toBeNull()
        ->and(lintWorkflowOpenFindingKinds($attempt))->toBe([]);
});

test('unresolved markers skip repair, and voice-rule findings stay out of reconciliation and never hold up finishing review', function () {
    $actor = lintWorkflowAuthor();
    $attempt = lintWorkflowAttempt($actor);
    lintWorkflowFakeAgents([lintWorkflowParagraph('blk_00000000000000a1', 'The report saves [NEED: proof] hours.'), lintWorkflowClosing()]);
    lintWorkflowDraft($actor, $attempt);
    $revision = lintWorkflowWorkingRevision($attempt);
    foreach (EditorialActivity::query()->where('attempt_id', $attempt->id)->whereIn('kind', ['review_facts', 'review_voice', 'review_buyer'])->orderBy('id')->get() as $lens) {
        lintWorkflowRun($lens);
    }
    $reconciliation = EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Reconciliation->value)->sole();
    lintWorkflowRun($reconciliation);

    $next = app(FinishEditorialReview::class)->finish($actor, $attempt->fresh() ?? $attempt, $revision->id);

    expect(EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::LintRepair->value)->exists())->toBeFalse()
        ->and(lintWorkflowOpenFindingKinds($attempt))->toBe(['lint:unresolved-markers'])
        ->and($reconciliation->input['review_findings'] ?? null)->toBe([])
        ->and($reconciliation->fresh()?->status)->toBe(EditorialActivityStatus::Completed)
        ->and($next)->toBeNull();
});

test('a repair whose output names a block outside its hits goes stale, stores findings and queues review', function () {
    $actor = lintWorkflowAuthor();
    $attempt = lintWorkflowAttempt($actor);
    lintWorkflowFakeAgents(
        [lintWorkflowParagraph('blk_00000000000000a1', 'I would leverage the CRM.'), lintWorkflowClosing()],
        repairPatches: fn (array $input): array => [[
            'hit_id' => (string) ($input['hits'][0]['hit_id'] ?? ''),
            'block_id' => 'blk_00000000000000ff',
            'expected_hash' => 'not-a-hash',
            'replacement' => json_encode(lintWorkflowClosing(), JSON_THROW_ON_ERROR),
        ]],
    );
    lintWorkflowDraft($actor, $attempt);
    $repair = lintWorkflowPendingRepair($attempt);

    lintWorkflowRun($repair);

    $revision = lintWorkflowWorkingRevision($attempt);
    expect($repair->fresh()?->status)->toBe(EditorialActivityStatus::Stale)
        ->and($repair->fresh()?->error_reason)->toBe('Lint repair patch targeted a block that is not in its hit list. Review started without this repair.')
        ->and($revision->origin)->toBe('agent-initial')
        ->and(lintWorkflowOpenFindingKinds($attempt))->toBe(['lint:corporate-vocabulary'])
        ->and(lintWorkflowQueuedLenses($attempt, $revision))->toBe(['review_buyer', 'review_facts', 'review_voice'])
        ->and(fn () => app(RetryEditorialActivity::class)->handle($actor, $repair))->toThrow(RuntimeException::class, 'Only failed agent work can be retried.');

    Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => Article::query()->findOrFail($attempt->article_id)])
        ->assertDontSee('Skip repair and review');
});

test('the owner can skip a waiting repair, which stores the findings and starts review', function () {
    $actor = lintWorkflowAuthor();
    $attempt = lintWorkflowAttempt($actor);
    lintWorkflowFakeAgents([lintWorkflowParagraph('blk_00000000000000a1', 'I would leverage the CRM.'), lintWorkflowClosing()]);
    lintWorkflowDraft($actor, $attempt);
    $repair = lintWorkflowPendingRepair($attempt);

    Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => Article::query()->findOrFail($attempt->article_id)])
        ->assertSee('Skip repair and review')
        ->call('skipVoiceRuleRepair')
        ->assertSet('saveError', null);

    $revision = lintWorkflowWorkingRevision($attempt);
    expect($repair->fresh()?->status)->toBe(EditorialActivityStatus::Stale)
        ->and(lintWorkflowOpenFindingKinds($attempt))->toBe(['lint:corporate-vocabulary'])
        ->and(lintWorkflowQueuedLenses($attempt, $revision))->toBe(['review_buyer', 'review_facts', 'review_voice']);
});

test('overriding a voice rule requires a reason and records it', function () {
    $actor = lintWorkflowAuthor();
    $attempt = lintWorkflowAttempt($actor);
    lintWorkflowFakeAgents([lintWorkflowParagraph('blk_00000000000000a1', 'The client said the plan was robust.'), lintWorkflowClosing()]);
    lintWorkflowDraft($actor, $attempt);
    app(LintRevision::class)->skipRepair($actor, $attempt);
    $finding = EditorialFinding::query()->where('attempt_id', $attempt->id)->where('lens', LintRevision::LENS)->whereNull('stale_at')->sole();
    $workspace = Livewire::actingAs($actor)->test('admin.publishing.article-workspace', ['article' => Article::query()->findOrFail($attempt->article_id)]);

    $workspace->call('overrideVoiceRule', $finding->id)->assertHasErrors(['findingReasons.'.$finding->id]);

    expect($finding->fresh()?->disposition)->toBeNull();

    $workspace->set('findingReasons.'.$finding->id, 'The word is the client\'s, quoted from the brief.')
        ->call('overrideVoiceRule', $finding->id)
        ->assertHasNoErrors()
        ->assertSee('Overridden');

    expect($finding->fresh()?->disposition)->toBe('false_positive')
        ->and($finding->fresh()?->disposition_reason)->toBe('The word is the client\'s, quoted from the brief.')
        ->and($finding->fresh()?->disposition_actor_id)->toBe($actor->id);
});
