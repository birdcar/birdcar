<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Ai\Agents\EditorialAgent;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\EditorialOutput;
use App\Settings\PublishingAgentSettings;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
 * Paid acceptance fixture for the publishing agent recommendations. It sits outside the Unit and Feature
 * suites and skips unless explicitly enabled, after the owner confirms the OpenRouter workspace is ready:
 *
 *     PUBLISHING_LIVE_TRIAL=1 vendor/bin/pest tests/Manual/PublishingModelTrialTest.php --compact
 *
 * One run makes one initial request per role, one AskAuthor continuation and one Auto Router probe, using
 * synthetic data in an in-memory database. It records every attempt, including failures, and never retries.
 * PUBLISHING_LIVE_TRIAL=auto-probe or =recheck-replay repeats one request (the Auto Router probe, or the
 * RevisionRechecker) against the most recent recorded input for that role.
 */

pest()->extend(TestCase::class);

test('recommended publishing agents complete one synthetic editorial journey against OpenRouter', function (): void {
    $mode = getenv('PUBLISHING_LIVE_TRIAL');
    if (! in_array($mode, ['1', 'auto-probe', 'recheck-replay'], true)) {
        $this->markTestSkipped('Paid live trial: set PUBLISHING_LIVE_TRIAL=1 only after the owner confirms the OpenRouter workspace is ready.');
    }

    liveTrialRefuseUnlessIsolated();
    liveTrialPrepareApplication();

    $evidencePath = base_path('docs/ideation/2026-09-24-publishing-agent-simplification/model-validation.json');
    if (! is_file($evidencePath)) {
        throw new RuntimeException('Refusing the live trial: the evidence file with recommendations does not exist yet.');
    }

    Http::preventStrayRequests();
    Http::allowStrayRequests(['https://openrouter.ai/api/v1/*']);
    Http::record();

    if ($mode !== '1') {
        $trial = $mode === 'auto-probe'
            ? liveTrialStandalone(liveTrialRecordedActivity($evidencePath, 'VoiceReviewer', EditorialActivityKind::ReviewVoice), 'auto_probe', EditorialAgent::AUTO_ROUTER)
            : liveTrialStandalone(liveTrialRecordedActivity($evidencePath, 'RevisionRechecker', EditorialActivityKind::Recheck), 'recheck_replay');
        liveTrialRecord($evidencePath, 'live-'.now()->format('Ymd\THis\Z').'-'.$mode, $trial);

        expect($trial['status'])->toBe('completed', "The {$mode} request did not complete: ".$trial['error_category']);

        return;
    }

    $incomplete = liveTrialJourney($evidencePath, 'live-'.now()->format('Ymd\THis\Z'));

    expect($incomplete)->toBe([], 'The live trial is incomplete: '.implode(' ', $incomplete));
});

function liveTrialRefuseUnlessIsolated(): void
{
    $connection = (string) config('database.default');
    $unsafe = array_values(array_filter([
        app()->environment('testing') ? null : 'The application is not running in the testing environment.',
        app()->configurationIsCached() ? 'Cached configuration is present.' : null,
        $connection === 'sqlite' ? null : 'The default database connection is not SQLite.',
        config("database.connections.{$connection}.database") === ':memory:' ? null : 'The configured database is not in-memory.',
        filled(config("database.connections.{$connection}.url")) ? 'A database URL is configured.' : null,
        DB::connection()->getDriverName() === 'sqlite' && DB::connection()->getConfig('database') === ':memory:' ? null : 'The active connection is not an in-memory SQLite database.',
    ]));

    if ($unsafe !== []) {
        throw new RuntimeException('Refusing the live trial before migrating: '.implode(' ', $unsafe));
    }
}

function liveTrialPrepareApplication(): void
{
    if ((string) config('ai.providers.openrouter.key', '') === '') {
        throw new RuntimeException('Refusing the live trial: OpenRouter credentials are not configured.');
    }
    if (config('ai.providers.openrouter.url') !== 'https://openrouter.ai/api/v1') {
        throw new RuntimeException('Refusing the live trial: the OpenRouter base URL is not the intended public API.');
    }

    Artisan::call('migrate', ['--force' => true]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Artisan::call('authorization:sync');

    Queue::fake();
    Mail::fake();
    Notification::fake();
    config()->set('publishing_agents.limits.max_retries', 0);

    $settings = app(PublishingAgentSettings::class);
    $settings->paused = false;
    $settings->model_overrides = [];
    $settings->save();
}

/**
 * Runs the scripted journey through the workspace and application actions, recording each paid attempt as it happens.
 *
 * @return list<string> reasons the validation is incomplete; empty when every required record completed
 */
function liveTrialJourney(string $evidencePath, string $runId): array
{
    $record = fn (array $trial) => liveTrialRecord($evidencePath, $runId, $trial);
    $fixture = liveTrialFixture();
    $incomplete = [];

    $owner = User::factory()->create();
    $owner->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);
    $writer = app(WriteArticle::class);

    $voiceArticle = $writer->capture($owner, 'Synthetic published voice sample', 'live-trial-voice-sample');
    $voiceRevision = $writer->save($owner, $voiceArticle, null, liveTrialDocument(['blk_0000000000000a01' => $fixture['voice_sample']]), ['title' => 'Boring fixes for small service teams'], 'live-trial-voice-sample');
    $voiceRelease = ArticleRelease::factory()->imported()->create([
        'article_id' => $voiceArticle->id,
        'attempt_id' => null,
        'revision_id' => $voiceRevision->id,
    ]);
    $voiceArticle->forceFill(['published_release_id' => $voiceRelease->id, 'first_published_at' => $voiceRelease->published_at])->save();

    $article = $writer->capture($owner, 'How a small service team stopped dropping customer follow-ups', 'live-trial-follow-ups');
    $emptyRevision = $writer->save($owner, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'Dropped follow-ups'], 'live-trial-empty');
    $attempt = app(AdvancePublishingAttempt::class)->develop($owner, $article, $emptyRevision->id, ['goal' => 'Explain how a small service team stopped dropping customer follow-ups.']);
    $ownerEvidence = EvidenceSource::create([
        'article_id' => $article->id,
        'attempt_id' => $attempt->id,
        'source_type' => 'owner',
        'title' => 'Brightline intake notes (synthetic)',
        'extracted_text' => $fixture['owner_evidence'],
        'content_hash' => hash('sha256', $fixture['owner_evidence']),
    ]);

    liveTrialWorkspace($owner, $article)
        ->set('selectedVoiceSampleArticleIds', [$voiceArticle->id])
        ->set('selectedEvidenceSourceIds', [$ownerEvidence->id])
        ->call('startInterview')
        ->assertSet('saveError', null);

    [$interview, $durationMs] = liveTrialRun(liveTrialPendingActivity($attempt, EditorialActivityKind::Interview));
    $askedAuthor = $interview->status === EditorialActivityStatus::AwaitingApproval;
    $record(liveTrialTrial('initial', $interview, $durationMs, $askedAuthor || $interview->status === EditorialActivityStatus::Completed, $askedAuthor
        ? ['pending_tool_approvals' => $interview->pending_tool_approvals]
        : ['response' => $interview->response]));

    if ($askedAuthor) {
        $interviewInput = ['synthetic' => true, 'author_answer' => $fixture['author_answer'], 'answered_questions' => $interview->pending_tool_approvals];
        liveTrialWorkspace($owner, $article)
            ->set("agentAnswers.{$interview->id}", $fixture['author_answer'])
            ->call('answerAgent', $interview->id, $interview->pendingApprovalHash())
            ->assertSet('saveError', null);
        [$interview, $durationMs] = liveTrialRun($interview->fresh());
        $record(liveTrialTrial('ask_author_continuation', $interview, $durationMs, $interview->status === EditorialActivityStatus::Completed, ['response' => $interview->response], $interviewInput));
    } elseif ($interview->status === EditorialActivityStatus::Completed) {
        $incomplete[] = 'The Interviewer completed without calling AskAuthor, so the native continuation was not observed.';
        $record(liveTrialNotAttempted('ask_author_continuation', 'Interviewer', 'The initial interview did not call AskAuthor.'));
    }

    if ($interview->status !== EditorialActivityStatus::Completed) {
        return [...$incomplete, liveTrialStopReason($interview)];
    }

    $angleOptions = $attempt->fresh()->interview_context['angle_options'] ?? [];
    if (! is_array($angleOptions) || $angleOptions === []) {
        return [...$incomplete, 'The interview proposed no angle option to approve.'];
    }
    $angle = liveTrialWorkspace($owner, $article)->call('selectAngleOption', (string) array_key_first($angleOptions));
    if (filled($attempt->fresh()->interview_context['questions'] ?? [])) {
        $angle->set('interviewAnswers', $fixture['interview_answers'])->call('submitInterviewAnswers');
    }
    $angle->call('approveAngle')->assertSet('saveError', null);

    [$research, $durationMs] = liveTrialRun(liveTrialPendingActivity($attempt, EditorialActivityKind::ResearchChallenge));
    $record(liveTrialTrial('initial', $research, $durationMs, $research->status === EditorialActivityStatus::Completed, [
        'response' => $research->response,
        'evidence_sources' => EvidenceSource::query()->where('activity_id', $research->id)->get()
            ->map(fn (EvidenceSource $source): array => [
                'url' => $source->getAttribute('url'),
                'title' => $source->getAttribute('title'),
                'retrieval_method' => $source->getAttribute('retrieval_method'),
                'unresolved_reason' => $source->getAttribute('unresolved_reason'),
                'extracted_text' => $source->getAttribute('extracted_text'),
            ])->all(),
    ]));
    if ($research->status !== EditorialActivityStatus::Completed) {
        return [...$incomplete, liveTrialStopReason($research)];
    }

    [$plan, $durationMs] = liveTrialRun(liveTrialPendingActivity($attempt, EditorialActivityKind::Plan));
    $record(liveTrialTrial('initial', $plan, $durationMs, $plan->status === EditorialActivityStatus::Completed, ['response' => $plan->response]));
    if ($plan->status !== EditorialActivityStatus::Completed) {
        return [...$incomplete, liveTrialStopReason($plan)];
    }

    // The owner's working manuscript carries the seeded defects, so the drafter proposes against it and the lenses review it.
    $seeded = $writer->save($owner, $article, (int) $article->fresh()->working_revision_id, liveTrialDocument($fixture['seeded_manuscript']), ['title' => 'Dropped follow-ups'], 'live-trial-seeded-review');
    liveTrialWorkspace($owner, $article)->call('approvePlan')->assertSet('saveError', null);

    [$draft, $durationMs] = liveTrialRun(liveTrialPendingActivity($attempt, EditorialActivityKind::Draft));
    $record(liveTrialTrial('initial', $draft, $durationMs, $draft->status === EditorialActivityStatus::Completed, ['proposal' => $draft->proposal]));
    if ($draft->status !== EditorialActivityStatus::Completed) {
        return [...$incomplete, liveTrialStopReason($draft)];
    }

    liveTrialWorkspace($owner, $article)
        ->set('selectedVoiceSampleArticleIds', [$voiceArticle->id])
        ->call('startReviews')
        ->assertSet('saveError', null);

    $lenses = [];
    foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer] as $kind) {
        [$lens, $durationMs] = liveTrialRun(liveTrialPendingActivity($attempt, $kind, $seeded->id));
        $record(liveTrialTrial('initial', $lens, $durationMs, $lens->status === EditorialActivityStatus::Completed, [
            'response' => $lens->response,
            'seeded_blocks_flagged' => liveTrialFlaggedBlocks($lens, $fixture['seeded_blocks']),
        ]));
        if ($lens->status !== EditorialActivityStatus::Completed) {
            return [...$incomplete, liveTrialStopReason($lens)];
        }
        $lenses[$kind->value] = $lens;
    }

    [$reconciliation, $durationMs] = liveTrialRun(liveTrialPendingActivity($attempt, EditorialActivityKind::Reconciliation, $seeded->id));
    $record(liveTrialTrial('initial', $reconciliation, $durationMs, $reconciliation->status === EditorialActivityStatus::Completed, ['response' => $reconciliation->response]));
    if ($reconciliation->status !== EditorialActivityStatus::Completed) {
        return [...$incomplete, liveTrialStopReason($reconciliation)];
    }

    // Fix only the seeded factual defect; the voice and buyer defects stay so the recheck must tell them apart.
    $writer->save($owner, $article, $seeded->id, liveTrialDocument($fixture['fixed_manuscript']), ['title' => 'Dropped follow-ups'], 'live-trial-fixed-review');
    $finish = liveTrialWorkspace($owner, $article);
    EditorialFinding::query()
        ->where('attempt_id', $attempt->id)
        ->where('revision_id', $seeded->id)
        ->whereNull('stale_at')
        ->where(fn ($query) => $query->where('severity', 'blocking')->orWhere('reconciliation_state', 'conflict'))
        ->pluck('id')
        ->each(fn (int $findingId) => $finish->call('decideFinding', $findingId, 'accepted', 'Synthetic trial owner accepts this finding.'));
    $finish->call('finishReview')->assertSet('saveError', null);

    [$recheck, $durationMs] = liveTrialRun(liveTrialPendingActivity($attempt, EditorialActivityKind::Recheck));
    $record(liveTrialTrial('initial', $recheck, $durationMs, $recheck->status === EditorialActivityStatus::Completed, [
        'response' => $recheck->response,
        'fixed_block' => $fixture['seeded_blocks']['fact'],
        'unresolved_seeded_blocks' => [$fixture['seeded_blocks']['voice'], $fixture['seeded_blocks']['buyer']],
    ]));
    if ($recheck->status !== EditorialActivityStatus::Completed) {
        $incomplete[] = liveTrialStopReason($recheck);
    }

    $probe = liveTrialStandalone($lenses[EditorialActivityKind::ReviewVoice->value], 'auto_probe', EditorialAgent::AUTO_ROUTER);
    $record($probe);
    if ($probe['status'] !== 'completed') {
        $incomplete[] = 'The Auto Router probe did not complete: '.$probe['error_category'];
    }

    return $incomplete;
}

/**
 * One standalone request for a frozen task (the Auto Router probe, or a replayed recheck); its output is recorded,
 * never applied.
 *
 * @return array<string, mixed>
 */
function liveTrialStandalone(EditorialActivity $activity, string $scenario, ?string $model = null): array
{
    $agent = EditorialAgent::forActivity($activity, $model);
    $trial = [
        'scenario' => $scenario,
        'agent' => class_basename($agent),
        'activity_kind' => $activity->kind->value,
        'requested_model' => $agent->model(),
        'returned_model' => null,
        'reasoning_effort' => $agent->reasoningEffort(),
        'status' => 'failed',
        'observed_at' => now()->toIso8601String(),
        'input' => ['synthetic' => true, 'kind' => $activity->kind->value, 'activity_input' => $activity->input],
        'output' => null,
        'error_category' => null,
    ];

    $started = hrtime(true);
    try {
        $response = $agent->prompt($agent->promptText());
        $trial['duration_ms'] = intdiv(hrtime(true) - $started, 1_000_000);
        $trial['returned_model'] = $response->meta->model;
        if (! $response instanceof StructuredAgentResponse || $response->steps->last()?->finishReason === FinishReason::Length) {
            $trial['error_category'] = 'The response did not include a complete structured answer.';

            return $trial;
        }

        $evidence = collect($activity->input['evidence_sources'] ?? [])
            ->filter(fn (mixed $source): bool => is_array($source) && is_int($source['id'] ?? null))
            ->mapWithKeys(fn (array $source): array => [$source['id'] => is_string($source['extracted_text'] ?? null) ? $source['extracted_text'] : null])
            ->all();
        $validated = app(EditorialOutput::class)->validate($activity->kind, $response->toArray(), array_keys($evidence), $evidence);
        $trial['output'] = ['response' => $validated];
        $concrete = is_string($trial['returned_model']) && $trial['returned_model'] !== '' && $trial['returned_model'] !== EditorialAgent::AUTO_ROUTER;
        $unassessed = $activity->kind === EditorialActivityKind::Recheck ? liveTrialUnassessedFindings($activity, $validated) : [];
        $trial['status'] = $concrete && $unassessed === [] ? 'completed' : 'failed';
        $trial['error_category'] = match (true) {
            ! $concrete => 'The provider did not report a concrete model.',
            $unassessed !== [] => 'The recheck did not assess findings '.implode(', ', $unassessed).'.',
            default => null,
        };
    } catch (Throwable $throwable) {
        $trial['duration_ms'] = intdiv(hrtime(true) - $started, 1_000_000);
        // Validation messages are fixed application strings; provider errors are reduced to their HTTP status.
        $trial['error_category'] = match (true) {
            $throwable instanceof RequestException => class_basename($throwable).' (HTTP '.$throwable->response->status().')',
            $throwable instanceof InvalidArgumentException => class_basename($throwable).': '.$throwable->getMessage(),
            default => class_basename($throwable),
        };
    }

    $exchanges = liveTrialNewProviderExchanges();
    $trial['providers'] = array_column($exchanges, 'provider');
    $trial['usage'] = array_column($exchanges, 'usage');
    $trial['provider_response'] = $trial['status'] === 'completed' ? null : $exchanges;

    return $trial;
}

/**
 * Rebuilds the most recent completed activity for a role from the evidence file, without touching the database.
 */
function liveTrialRecordedActivity(string $evidencePath, string $agent, EditorialActivityKind $kind): EditorialActivity
{
    $evidence = json_decode((string) file_get_contents($evidencePath), true, flags: JSON_THROW_ON_ERROR);
    $recorded = collect($evidence['live_trials'] ?? [])->last(fn (array $trial): bool => ($trial['scenario'] ?? null) === 'initial'
        && ($trial['agent'] ?? null) === $agent
        && ($trial['status'] ?? null) === 'completed'
        && is_array($trial['input']['activity_input'] ?? null));

    if (! is_array($recorded)) {
        throw new RuntimeException("Refusing the replay: no completed {$agent} input has been recorded yet.");
    }

    $input = $recorded['input']['activity_input'];
    // Earlier recordings carried a misleading patch flag that the application no longer sends.
    if (is_array($input['review_findings'] ?? null)) {
        $input['review_findings'] = array_map(fn (array $finding): array => array_diff_key($finding, ['patch_applied' => true]), $input['review_findings']);
    }

    return new EditorialActivity(['kind' => $kind, 'input' => $input]);
}

/**
 * Mirrors the application's recheck coverage rule: every frozen finding is classified by ID or block.
 *
 * @param  array<string, mixed>  $payload
 * @return list<int>
 */
function liveTrialUnassessedFindings(EditorialActivity $recheck, array $payload): array
{
    $assessed = collect([...($payload['resolved'] ?? []), ...($payload['unresolved'] ?? [])]);

    return collect($recheck->input['review_findings'] ?? [])
        ->reject(fn (array $finding): bool => $assessed->contains(fn (array $item): bool => ($item['finding_id'] ?? null) === $finding['id']
            || (($finding['block_id'] ?? null) !== null && ($item['block_id'] ?? null) === $finding['block_id'])))
        ->pluck('id')
        ->values()
        ->all();
}

/**
 * @return array{EditorialActivity, int}
 */
function liveTrialRun(?EditorialActivity $activity): array
{
    if (! $activity instanceof EditorialActivity) {
        throw new RuntimeException('The expected editorial activity was not queued by the application.');
    }

    $started = hrtime(true);
    app()->call([new RunEditorialActivity((int) $activity->id), 'handle']);

    return [$activity->fresh(), intdiv(hrtime(true) - $started, 1_000_000)];
}

function liveTrialPendingActivity(PublishingAttempt $attempt, EditorialActivityKind $kind, ?int $revisionId = null): ?EditorialActivity
{
    return EditorialActivity::query()
        ->where('attempt_id', $attempt->id)
        ->where('kind', $kind->value)
        ->where('status', EditorialActivityStatus::Pending->value)
        ->when($revisionId !== null, fn ($query) => $query->where('revision_id', $revisionId))
        ->latest('id')
        ->first();
}

function liveTrialWorkspace(User $owner, Article $article): Testable
{
    return Livewire::actingAs($owner)->test('admin.publishing.article-workspace', ['article' => $article->fresh()]);
}

/**
 * @param  array<string, mixed>  $output
 * @param  array<string, mixed>|null  $input
 * @return array<string, mixed>
 */
function liveTrialTrial(string $scenario, EditorialActivity $activity, int $durationMs, bool $succeeded, array $output, ?array $input = null): array
{
    $snapshot = is_array($activity->model_snapshot) ? $activity->model_snapshot : [];
    $agent = EditorialAgent::forActivity($activity);
    $exchanges = liveTrialNewProviderExchanges();

    return [
        'scenario' => $scenario,
        'agent' => class_basename($agent),
        'activity_kind' => $activity->kind->value,
        'requested_model' => $snapshot['requested_model'] ?? $agent->model(),
        'returned_model' => $snapshot['returned_model'] ?? (end($exchanges) ?: [])['model'] ?? null,
        'reasoning_effort' => $snapshot['reasoning_effort'] ?? null,
        'status' => $succeeded ? 'completed' : 'failed',
        'activity_status' => $activity->status->value,
        'observed_at' => now()->toIso8601String(),
        'duration_ms' => $durationMs,
        'generation_id' => $activity->generation_id,
        'usage' => array_column($exchanges, 'usage'),
        'providers' => array_column($exchanges, 'provider'),
        'finish_reasons' => array_column($exchanges, 'finish_reason'),
        'error_category' => $succeeded ? null : liveTrialStopReason($activity),
        'input' => $input ?? ['synthetic' => true, 'kind' => $activity->kind->value, 'activity_input' => $activity->input],
        'output' => $output,
        // The application discards a paid response that fails validation, so keep the provider's own reply for diagnosis.
        'provider_response' => $succeeded ? null : $exchanges,
    ];
}

/**
 * Chat completion replies received since the previous call, without request or response headers.
 *
 * @return list<array<string, mixed>>
 */
function liveTrialNewProviderExchanges(): array
{
    static $consumed = 0;

    $responses = Http::recorded(fn ($request): bool => str_ends_with($request->url(), '/chat/completions'))
        ->map(fn (array $pair): mixed => $pair[1])
        ->values();
    $new = $responses->slice($consumed)->values();
    $consumed = $responses->count();

    return $new->map(function (mixed $response): array {
        $json = $response instanceof Response ? $response->json() : null;
        $content = data_get($json, 'choices.0.message.content');
        $decoded = is_string($content) ? json_decode($content, true) : null;

        return [
            'http_status' => $response instanceof Response ? $response->status() : null,
            'model' => data_get($json, 'model'),
            'provider' => data_get($json, 'provider'),
            'finish_reason' => data_get($json, 'choices.0.finish_reason'),
            'usage' => data_get($json, 'usage'),
            'content' => $decoded ?? $content,
            'annotations' => data_get($json, 'choices.0.message.annotations'),
            'tool_calls' => data_get($json, 'choices.0.message.tool_calls'),
        ];
    })->all();
}

/** @return array<string, mixed> */
function liveTrialNotAttempted(string $scenario, string $agent, string $reason): array
{
    return [
        'scenario' => $scenario,
        'agent' => $agent,
        'status' => 'not_attempted',
        'observed_at' => now()->toIso8601String(),
        'error_category' => $reason,
    ];
}

function liveTrialStopReason(EditorialActivity $activity): string
{
    return class_basename(EditorialAgent::forActivity($activity)).' ended '.$activity->status->value.': '.($activity->pause_reason ?? $activity->error_reason ?? 'no reason recorded').'.';
}

/** @param array<string, mixed> $trial */
function liveTrialRecord(string $path, string $runId, array $trial): void
{
    $evidence = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $evidence['recorded_at'] = now()->toIso8601String();
    $evidence['live_trials'][] = ['run_id' => $runId, ...$trial];

    file_put_contents($path, json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
}

/**
 * @param  array<string, string>  $seededBlocks
 * @return array<string, bool>
 */
function liveTrialFlaggedBlocks(EditorialActivity $lens, array $seededBlocks): array
{
    $flagged = EditorialFinding::query()->where('activity_id', $lens->id)->pluck('block_id')->filter()->all();

    return array_map(fn (string $blockId): bool => in_array($blockId, $flagged, true), $seededBlocks);
}

/**
 * @param  array<string, string>  $paragraphs  block id => text
 * @return array<string, mixed>
 */
function liveTrialDocument(array $paragraphs): array
{
    return ['version' => 1, 'type' => 'doc', 'content' => array_map(
        fn (string $id, string $text): array => ['type' => 'paragraph', 'attrs' => ['id' => $id], 'content' => [['type' => 'text', 'text' => $text]]],
        array_keys($paragraphs),
        $paragraphs,
    )];
}

/**
 * Fictional scenario, business facts and voice guidance; nothing here comes from real manuscripts or customers.
 *
 * @return array{voice_sample: string, owner_evidence: string, author_answer: string, interview_answers: string, seeded_blocks: array{fact: string, voice: string, buyer: string}, seeded_manuscript: array<string, string>, fixed_manuscript: array<string, string>}
 */
function liveTrialFixture(): array
{
    $blocks = ['fact' => 'blk_00000000000000fa', 'voice' => 'blk_00000000000000ce', 'buyer' => 'blk_00000000000000bb'];
    $seeded = [
        'blk_0000000000000b01' => 'Brightline Plumbing is a six-person residential plumbing company. In March, 31 of its 212 inbound requests went more than 48 hours without anyone calling the customer back.',
        $blocks['fact'] => 'After we moved everything into one shared intake sheet, Brightline answered every request within an hour and doubled its revenue in April.',
        'blk_0000000000000b02' => 'The change was small. Every request, whether it came by phone, web form or email, went into one spreadsheet within an hour. At 8:00 each weekday the office manager ran a fifteen-minute triage and gave every open request an owner.',
        $blocks['voice'] => 'Honestly, this game-changing, revolutionary system will supercharge any business that tries it!!!',
        $blocks['buyer'] => 'Before you try this, get sign-off from your procurement committee and your enterprise architecture board, and budget for a six-month CRM implementation.',
        'blk_0000000000000b03' => 'The cost is real: fifteen minutes every morning, and someone has to own the sheet. For Brightline that trade was worth it.',
    ];
    $fixed = $seeded;
    $fixed[$blocks['fact']] = 'In April, 7 of 198 requests went more than 48 hours without a follow-up, down from 31 of 212 in March. We did not measure revenue.';

    return [
        'voice_sample' => 'I run operations reviews for small service businesses. Most of the fixes I recommend are boring on purpose: one list instead of three, a named owner for each step, and a check that happens at the same time every day. I write the way I would talk to a client across a kitchen table. Short sentences. Real numbers when I have them, and a plain "we did not measure that" when I do not. I do not use words like game-changing or revolutionary, and I do not promise results a reader cannot check. Every change has a cost, so I name it: a daily triage takes fifteen minutes that someone has to give up.',
        'owner_evidence' => 'Synthetic engagement notes for Brightline Plumbing, a fictional six-person residential plumbing company. March: 212 inbound requests by phone, web form and email; 31 went more than 48 hours without a follow-up. Requests were tracked in three places: a paper phone log, the owner\'s personal inbox and a web-form spreadsheet. Change made on April 1: one shared intake spreadsheet, every request entered within an hour, and a fixed fifteen-minute triage at 8:00 each weekday led by the office manager. April: 198 inbound requests; 7 went more than 48 hours without a follow-up. No new software was purchased. Revenue and customer satisfaction were not measured.',
        'author_answer' => 'I am writing for owner-operators of service businesses with 5 to 20 people who still handle intake themselves. I worked with Brightline as an outside operations adviser; I did not build or sell them software. My one takeaway: a shared intake list with a fixed daily triage beats buying a new CRM. Do not invent revenue or satisfaction results; we only measured follow-ups that took longer than 48 hours.',
        'interview_answers' => 'The reader is an owner-operator of a 5 to 20 person service business. I advised Brightline as an outside operations adviser. The only measured result is follow-ups taking longer than 48 hours: 31 of 212 in March and 7 of 198 in April.',
        'seeded_blocks' => $blocks,
        'seeded_manuscript' => $seeded,
        'fixed_manuscript' => $fixed,
    ];
}
