<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\FinishEditorialReview;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\CanonSnapshot;
use App\Services\Publishing\EditorialOutput;
use App\Services\Publishing\VoiceCanon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

const REVIEW_RUBRIC_LENSES = [
    'review_facts' => ['file' => 'roles/fact-reviewer.md', 'markers' => ['claims', 'exclusions']],
    'review_voice' => ['file' => 'roles/voice-reviewer.md', 'markers' => ['voice', 'craft', 'framework-adherence']],
    'review_buyer' => ['file' => 'roles/buyer-reviewer.md', 'markers' => ['buyer', 'commercial-structure', 'discovery']],
];

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory(storage_path('framework/testing/review-rubric'));
});

function reviewRubricAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $user;
}

/**
 * Every lens marker a rubric file lacks, as "file: marker".
 *
 * @return list<string>
 */
function reviewRubricMissingMarkers(CanonSnapshot $snapshot): array
{
    $missing = [];
    foreach (REVIEW_RUBRIC_LENSES as $lens) {
        $content = $snapshot->files[CanonSnapshot::CANON_PREFIX.$lens['file']] ?? '';
        foreach ($lens['markers'] as $marker) {
            if (! str_contains($content, '<!-- lens-section: '.$marker.' -->')) {
                $missing[] = $lens['file'].': '.$marker;
            }
        }
    }

    return $missing;
}

function reviewRubricHashesAreDistinct(CanonSnapshot $snapshot): bool
{
    $hashes = array_map(fn (array $lens): string => $snapshot->hashes[CanonSnapshot::CANON_PREFIX.$lens['file']] ?? '', REVIEW_RUBRIC_LENSES);

    return count(array_unique($hashes)) === count($hashes);
}

/** @return array<string, mixed> */
function reviewRubricDocument(string $text): array
{
    return ['version' => 1, 'type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'attrs' => ['id' => 'blk_00000000000000d1'], 'content' => [['type' => 'text', 'text' => $text]]],
        ['type' => 'paragraph', 'attrs' => ['id' => 'blk_00000000000000d2'], 'content' => [
            ['type' => 'text', 'text' => 'If this sounds like your week, bring it to '],
            ['type' => 'text', 'text' => 'a Walkthrough', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/walkthrough']]]],
            ['type' => 'text', 'text' => '.'],
        ]],
    ]];
}

/**
 * An in-review attempt whose plan carries a chosen framework, with a completed Draft on the working revision. Built
 * while agents are paused, so nothing runs on its own.
 */
function reviewRubricDraftedAttempt(User $actor): PublishingAttempt
{
    setPublishingAgentsPaused(true);
    $write = app(WriteArticle::class);
    $approve = app(ApprovePublishingStage::class);
    $article = $write->captureIdea($actor, 'Why another admin hire will not fix the Friday report');
    $revision = $write->save($actor, $article, null, reviewRubricDocument('The Friday report takes an afternoon.'), ['title' => 'The Friday report'], 'review-rubric-'.Str::random(8));
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id, ['goal' => 'Show owners what to check first', 'post_type' => 'playbook']);
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
    $attempt = app(AdvancePublishingAttempt::class)->rethink($actor, $attempt, plan: [
        'outline' => ['What to check first'],
        'visualPlan' => ['The Friday report'],
        'framework' => ['primary' => 'three-act', 'runner_up' => 'the-petal', 'chosen' => 'three-act'],
    ]);
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');

    $draft = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Draft, [], 'draft-'.$attempt->review_cycle);
    $draft->forceFill(['status' => EditorialActivityStatus::Completed, 'completed_at' => now(), 'proposal' => ['applied_revision_id' => $revision->id]])->save();

    return $attempt;
}

/**
 * Runs one activity against a faked answer that ignores its input, and returns the decoded request it sent.
 *
 * @param  array<string, mixed>  $payload
 * @return array{system: string, input: array<string, mixed>}
 */
function reviewRubricRun(EditorialActivity $activity, array $payload): array
{
    setPublishingAgentsPaused(false);
    config()->set('ai.providers.openrouter.key', 'test-key');
    Queue::fake([RunEditorialActivity::class]);
    Http::swap(new HttpFactory(app(Dispatcher::class)));
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response([
        'id' => 'gen-review-rubric-'.Str::random(6),
        'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode($payload, JSON_THROW_ON_ERROR)]]],
    ])]);

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialOutput::class), app(WriteArticle::class));
    setPublishingAgentsPaused(true);

    $body = Http::recorded()->sole()[0]->data();
    $prompt = json_decode((string) data_get($body, 'messages.1.content'), true, flags: JSON_THROW_ON_ERROR);

    return ['system' => (string) data_get($body, 'messages.0.content'), 'input' => is_array($prompt['input'] ?? null) ? $prompt['input'] : []];
}

/** @return array<string, mixed> */
function reviewRubricFinding(string $statement): array
{
    return ['statement' => $statement, 'kind' => 'clarity', 'severity' => 'advisory', 'block_id' => 'blk_00000000000000d1', 'supporting_source_ids' => [], 'supporting_quotations' => []];
}

test('the three lens rubrics are distinct files with their own markers', function () {
    $snapshot = app(VoiceCanon::class)->snapshot();

    expect(reviewRubricHashesAreDistinct($snapshot))->toBeTrue()
        ->and(reviewRubricMissingMarkers($snapshot))->toBe([]);
});

test('each lens is sent its own rubric file and markers, and the voice lens the chosen framework', function () {
    $actor = reviewRubricAuthor();
    $attempt = reviewRubricDraftedAttempt($actor);
    $snapshot = app(VoiceCanon::class)->snapshot();
    $draftId = (int) EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Draft->value)->value('id');

    foreach (REVIEW_RUBRIC_LENSES as $kind => $lens) {
        $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::from($kind), ['draft_activity_id' => $draftId], 'review-'.$attempt->review_cycle);
        $sent = reviewRubricRun($activity, ['findings' => [reviewRubricFinding('A finding from '.$kind.'.')]]);
        $path = CanonSnapshot::CANON_PREFIX.$lens['file'];

        expect($sent['input']['canon']['rubric_files'] ?? [])->toHaveKey($path)
            ->and($sent['input']['canon']['rubric_files'][$path] ?? null)->toBe($snapshot->hashes[$path]);
        foreach ($lens['markers'] as $marker) {
            expect($sent['system'])->toContain('<!-- lens-section: '.$marker.' -->');
        }

        if ($kind === EditorialActivityKind::ReviewVoice->value) {
            expect($sent['input']['canon']['framework']['slug'] ?? null)->toBe('three-act')
                ->and($sent['input']['canon']['framework']['text'] ?? null)->toBe(trim((string) file_get_contents(resource_path('publishing/canon/frameworks/three-act.md'))))
                ->and($sent['input']['canon']['post_type']['slug'] ?? null)->toBe('playbook');
        } else {
            expect($sent['input']['canon'])->not->toHaveKeys(['framework', 'post_type']);
        }
    }
});

test('reconciliation receives every lens finding and the reviewed text, and the recheck the findings and the revised text', function () {
    $actor = reviewRubricAuthor();
    $attempt = reviewRubricDraftedAttempt($actor);
    $article = $attempt->article()->firstOrFail();
    $reviewed = ArticleRevision::query()->findOrFail($article->working_revision_id);
    $draftId = (int) EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Draft->value)->value('id');

    foreach (array_keys(REVIEW_RUBRIC_LENSES) as $kind) {
        $lens = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::from($kind), ['draft_activity_id' => $draftId], 'review-'.$attempt->review_cycle);
        reviewRubricRun($lens, ['findings' => [reviewRubricFinding('A finding from '.$kind.'.')]]);
    }
    $findingIds = EditorialFinding::query()->where('attempt_id', $attempt->id)->where('lens', '!=', 'lint')->orderBy('id')->pluck('id')->all();
    $reconciliation = EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Reconciliation->value)->sole();

    $reconciliationInput = reviewRubricRun($reconciliation, ['groups' => [], 'conflicts' => []])['input'];

    expect($findingIds)->toHaveCount(3)
        ->and(array_column($reconciliationInput['review_findings'] ?? [], 'id'))->toBe($findingIds)
        ->and($reconciliationInput['manuscript']['document'] ?? null)->toEqual($reviewed->document);

    $revised = app(WriteArticle::class)->save($actor, $article->fresh() ?? $article, $reviewed->id, reviewRubricDocument('The Friday report takes twenty minutes now.'), $reviewed->metadata ?? [], 'review-rubric-revision', 'human');
    $recheck = app(FinishEditorialReview::class)->finish($actor, $attempt->fresh() ?? $attempt, $reviewed->id, [$findingIds[0] => ['disposition' => 'accepted']]);
    if (! $recheck instanceof EditorialActivity) {
        throw new RuntimeException('Finishing review queued no recheck.');
    }

    $recheckInput = reviewRubricRun($recheck, [
        'resolved' => array_map(fn (int $id): array => ['finding_id' => $id, 'status' => 'resolved', 'reason' => 'The revised paragraph fixes it.'], $findingIds),
        'unresolved' => [],
        'newBlockingFindings' => [],
    ])['input'];

    expect($recheck->kind)->toBe(EditorialActivityKind::Recheck)
        ->and(array_column($recheckInput['review_findings'] ?? [], 'id'))->toBe($findingIds)
        ->and($recheckInput['reviewed_manuscript']['document'] ?? null)->toEqual($reviewed->document)
        ->and($recheckInput['manuscript']['document'] ?? null)->toEqual($revised->document)
        ->and($recheckInput['manuscript']['revision_id'] ?? null)->toBe($revised->id);
});

test('the distinctness and marker checks catch a copied rubric and a missing marker', function () {
    $files = new Filesystem;
    $copy = storage_path('framework/testing/review-rubric/'.Str::random(12));
    $files->copyDirectory(resource_path('publishing/canon'), $copy);
    config()->set('publishing_agents.canon.path', $copy);
    $voiceRubric = (string) file_get_contents($copy.'/roles/voice-reviewer.md');
    $buyerRubric = (string) file_get_contents($copy.'/roles/buyer-reviewer.md');

    file_put_contents($copy.'/roles/buyer-reviewer.md', $voiceRubric);
    app()->forgetScopedInstances();
    $copied = app(VoiceCanon::class)->snapshot();

    expect(reviewRubricHashesAreDistinct($copied))->toBeFalse()
        ->and(reviewRubricMissingMarkers($copied))->toBe(['roles/buyer-reviewer.md: buyer', 'roles/buyer-reviewer.md: commercial-structure', 'roles/buyer-reviewer.md: discovery']);

    file_put_contents($copy.'/roles/buyer-reviewer.md', $buyerRubric);
    file_put_contents($copy.'/roles/fact-reviewer.md', str_replace("<!-- lens-section: exclusions -->\n", '', (string) file_get_contents($copy.'/roles/fact-reviewer.md')));
    app()->forgetScopedInstances();
    $restored = app(VoiceCanon::class)->snapshot();

    expect(reviewRubricHashesAreDistinct($restored))->toBeTrue()
        ->and(reviewRubricMissingMarkers($restored))->toBe(['roles/fact-reviewer.md: exclusions']);
});
