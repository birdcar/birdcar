<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\ImportWritingArchive;
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
use App\Models\User;
use App\Services\Publishing\Lint\LintHit;
use App\Services\Publishing\VoiceCanon;
use App\Settings\PublishingAgentSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\Publishing\DriftGoldenSet;
use Tests\TestCase;

/*
 * Paid golden-set drift trial for contract goal G3. It sits outside the Unit and Feature suites and skips unless
 * explicitly enabled, after the owner confirms the OpenRouter workspace is ready:
 *
 *     DRIFT_TRIAL=1 vendor/bin/pest tests/Manual/VoiceDriftTrialTest.php --compact
 *
 * One run migrates a fresh birdcar_test database, imports the writing archive and embeds the sections of the essays
 * outside the canon (so plan approval can retrieve exemplars), then for each of the 20 golden-set passages
 * (tests/Fixtures/Publishing/drift/passages.json) writes the passage as a blog piece's revision, lints it, and runs one
 * real VoiceReviewer request. That is about 20 review calls, 20 query embeddings and the archive section embeddings.
 *
 * Every passage gets the same neutral brief and plan, with no post type and no framework, so nothing but the
 * manuscript tells a real passage from a slopped one and the reviewer skips framework adherence on an excerpt.
 * Passages are linted without walkthrough-link, which no excerpt can satisfy (DriftGoldenSet::EXCERPT_EXEMPT_RULES).
 *
 * Every attempt is recorded, including failures, and nothing is retried: a failed passage counts as not caught
 * (slopped) or not flagged (real), and the owner reruns the whole trial deliberately. The evidence file is rewritten
 * after each passage, so a crash keeps what was recorded; a partial run fails the contract's jq check, which needs all
 * 20 passage ids. A slopped passage is caught when a lint hit or a reviewer finding targets an injected block (hits on
 * its clean blocks go to clean_block_hits and never count); a real passage is flagged when the reviewer returns a
 * voice_drift finding or lint hits it.
 *
 * canon_hash is VoiceCanon's snapshot version, the value `php artisan publishing:canon-hash` prints. This run reads it
 * from a freshly migrated birdcar_test database, which holds no Admin canon versions, so it is the repository canon's
 * hash; once an Admin canon version is active in development, the jq check compares against that version instead and
 * fails until the trial is rerun against the same canon.
 */

pest()->extend(TestCase::class);

const DRIFT_TRIAL_EVIDENCE = 'docs/ideation/2026-10-02-consistent-voice-publishing/drift-validation.json';

const DRIFT_TRIAL_IDEA = 'A short passage for owners about how work gets built and handed off';

const DRIFT_TRIAL_BRIEF = [
    'summary' => 'Owners lose time to work that keeps landing on their desk, and better tools change who handles it.',
    'target_query' => 'how do I stop being the bottleneck in my business',
];

const DRIFT_TRIAL_PLAN = [
    'outline' => ['Where the work goes today', 'What changes'],
    'visualPlan' => ['none'],
    'argument' => 'Owners get time back when the work stops routing through them.',
];

test('golden-set drift trial against OpenRouter', function (): void {
    if (getenv('DRIFT_TRIAL') !== '1') {
        $this->markTestSkipped('Paid trial: set DRIFT_TRIAL=1 after the owner confirms the OpenRouter workspace is ready.');
    }

    driftTrialRefuseUnlessIsolated();
    driftTrialPrepareApplication();

    Http::preventStrayRequests();
    Http::allowStrayRequests(['https://openrouter.ai/api/v1/*']);
    Http::record();

    $owner = User::factory()->create();
    $owner->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);
    driftTrialIndexArchive($owner);

    $set = DriftGoldenSet::load();
    $passages = [
        ...array_map(fn (array $passage): array => ['variant' => 'real', 'id' => $passage['id'], 'blocks' => $passage['blocks'], 'injected' => []], $set['real']),
        ...array_map(fn (array $passage): array => ['variant' => 'slopped', 'id' => $passage['id'], 'blocks' => $passage['blocks'], 'injected' => $passage['injected']], $set['slopped']),
    ];
    $run = [
        'canon_hash' => app(VoiceCanon::class)->snapshot()->version,
        'run_id' => 'drift-'.now()->format('Ymd\THis\Z'),
        'started_at' => now()->toIso8601String(),
        'attempts' => [],
    ];

    foreach ($passages as $passage) {
        $run['attempts'][] = driftTrialAttempt($owner, $passage);
        driftTrialRecord($run);
    }

    $failed = array_column(array_filter($run['attempts'], fn (array $attempt): bool => $attempt['status'] !== 'completed'), 'passage_id');
    expect($failed)->toBe([], 'These passages failed and count as not caught or not flagged; rerun the trial: '.implode(', ', $failed));
});

function driftTrialRefuseUnlessIsolated(): void
{
    $connection = (string) config('database.default');
    $unsafe = array_values(array_filter([
        app()->environment('testing') ? null : 'The application is not running in the testing environment.',
        app()->configurationIsCached() ? 'Cached configuration is present.' : null,
        $connection === 'pgsql' ? null : 'The default database connection is not Postgres.',
        config("database.connections.{$connection}.database") === 'birdcar_test' ? null : 'The configured database is not birdcar_test.',
        filled(config("database.connections.{$connection}.url")) ? 'A database URL is configured.' : null,
        DB::connection()->getDriverName() === 'pgsql' && DB::connection()->getConfig('database') === 'birdcar_test' ? null : 'The active connection is not the birdcar_test Postgres database.',
    ]));

    if ($unsafe !== []) {
        throw new RuntimeException('Refusing the drift trial before migrating: '.implode(' ', $unsafe));
    }
}

function driftTrialPrepareApplication(): void
{
    foreach (['openrouter' => 'OpenRouter', 'openrouter-embeddings' => 'OpenRouter embeddings'] as $provider => $label) {
        if ((string) config("ai.providers.{$provider}.key", '') === '') {
            throw new RuntimeException("Refusing the drift trial: {$label} credentials are not configured.");
        }
        if (config("ai.providers.{$provider}.url") !== 'https://openrouter.ai/api/v1') {
            throw new RuntimeException("Refusing the drift trial: the {$label} base URL is not the intended public API.");
        }
    }

    Artisan::call('migrate:fresh', ['--force' => true]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Artisan::call('authorization:sync');

    Queue::fake();
    Mail::fake();
    Notification::fake();
    config()->set('publishing_agents.limits.max_retries', 0);

    $settings = app(PublishingAgentSettings::class);
    $settings->paused = true;
    $settings->model_overrides = [];
    $settings->save();
}

/** Publishes the writing archive and embeds the sections retrieval may return: the essays outside the canon. */
function driftTrialIndexArchive(User $owner): void
{
    app(ImportWritingArchive::class)->write('resources/writing', '72f7d8ad8521573cb224022c902447f9ca4c4351', $owner);

    if (Artisan::call('publishing:index-exemplars') !== 0) {
        throw new RuntimeException('Refusing the drift trial: indexing the archive failed. '.Artisan::output());
    }
}

/**
 * Builds a blog piece whose working revision is the passage and whose completed Draft targets it, then runs one voice
 * review of it. Everything up to the angle is built while agents are paused, so nothing else starts; the plan is
 * approved unpaused, so it retrieves exemplars for the Draft the review copies them from.
 *
 * @param  array{variant: string, id: string, blocks: list<string>, injected: list<array{block_index: int, tell: string, mechanical: bool}>}  $passage
 * @return array{passage_id: string, variant: string, caught_at_injected_span: bool, drift_flagged: bool, caught_by_lint: bool, caught_by_reviewer: bool, clean_block_hits: list<array{source: string, block_id: string}>, retrieval_status: mixed, lint_hits: list<array{rule_id: string, block_id: string|null}>, findings: list<array{kind: string, block_id: string|null, severity: string, statement: string}>, model: mixed, status: string, error: string|null}
 */
function driftTrialAttempt(User $owner, array $passage): array
{
    $record = [
        'passage_id' => $passage['id'],
        'variant' => $passage['variant'],
        'caught_at_injected_span' => false,
        'drift_flagged' => false,
        'caught_by_lint' => false,
        'caught_by_reviewer' => false,
        'clean_block_hits' => [],
        'retrieval_status' => null,
        'lint_hits' => [],
        'findings' => [],
        'model' => null,
        'status' => 'failed',
        'error' => null,
    ];

    try {
        [$review, $revision] = driftTrialReview($owner, $passage);
        $record['lint_hits'] = array_map(
            fn (LintHit $hit): array => ['rule_id' => $hit->ruleId, 'block_id' => $hit->blockId],
            DriftGoldenSet::lint($revision->document),
        );
        $record['findings'] = array_values(EditorialFinding::query()
            ->select(['id', 'kind', 'block_id', 'severity', 'statement'])
            ->where('activity_id', $review->id)
            ->orderBy('id')
            ->get()
            ->map(fn (EditorialFinding $finding): array => [
                'kind' => (string) $finding->kind,
                'block_id' => is_string($finding->block_id) ? $finding->block_id : null,
                'severity' => (string) $finding->severity,
                'statement' => (string) $finding->statement,
            ])
            ->all());
        $snapshot = is_array($review->model_snapshot) ? $review->model_snapshot : [];
        $record['model'] = $snapshot['returned_model'] ?? $snapshot['requested_model'] ?? null;
        $record['retrieval_status'] = data_get($review->input, 'canon.retrieval_status');
        $record['status'] = $review->status === EditorialActivityStatus::Completed ? 'completed' : 'failed';
        $record['error'] = $review->status === EditorialActivityStatus::Completed ? null : 'VoiceReviewer ended '.$review->status->value.': '.($review->pause_reason ?? $review->error_reason ?? 'no reason recorded').'.';
    } catch (Throwable $exception) {
        $record['error'] = class_basename($exception).': '.$exception->getMessage();
    }

    if ($record['status'] !== 'completed') {
        return $record;
    }

    $record = [...$record, ...DriftGoldenSet::assess($passage['variant'], $passage['id'], $passage['injected'], $record['lint_hits'], $record['findings'])];

    return $record;
}

/**
 * @param  array{variant: string, id: string, blocks: list<string>, injected: list<array{block_index: int, tell: string, mechanical: bool}>}  $passage
 * @return array{EditorialActivity, ArticleRevision}
 */
function driftTrialReview(User $owner, array $passage): array
{
    $write = app(WriteArticle::class);
    $advance = app(AdvancePublishingAttempt::class);
    $approve = app(ApprovePublishingStage::class);

    setPublishingAgentsPaused(true);
    $piece = $write->captureIdea($owner, DRIFT_TRIAL_IDEA);
    $revision = $write->save($owner, $piece, null, DriftGoldenSet::document($passage['id'], $passage['blocks']), [], 'drift-'.$passage['id']);
    $attempt = $advance->develop($owner, $piece, $revision->id, DRIFT_TRIAL_BRIEF);
    $approve->approve($owner, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    $attempt = $attempt->fresh() ?? throw new RuntimeException('The attempt disappeared.');
    // Research is not what this trial measures, so the plan gate gets a completed research row instead of a paid one.
    EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $owner->id,
        'kind' => EditorialActivityKind::ResearchChallenge,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'drafting',
        'input_version' => $attempt->input_version,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'drift-research-'.Str::random(8),
        'idempotency_key' => 'drift-research-'.Str::random(16),
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', Str::random(16)),
        'input' => ['approval_hashes' => [ApprovalKind::Angle->value => $approve->inputHashFor($attempt, ApprovalKind::Angle)]],
        'model_snapshot' => [],
        'response' => ['claims' => [], 'sourceReferences' => [], 'contradictions' => [], 'gaps' => []],
        'completed_at' => now(),
    ]);
    $attempt = $advance->rethink($owner, $attempt, plan: DRIFT_TRIAL_PLAN);

    setPublishingAgentsPaused(false);
    $approve->approve($owner, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    // The passage is the manuscript, so the Draft the plan approval queued is completed without a paid request.
    $draft = EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Draft->value)->sole();
    $draft->forceFill(['status' => EditorialActivityStatus::Completed, 'completed_at' => now()])->save();

    $review = app(StartEditorialActivity::class)->start($owner, $attempt->fresh() ?? $attempt, EditorialActivityKind::ReviewVoice, ['draft_activity_id' => $draft->id], 'drift-review');
    app()->call([new RunEditorialActivity((int) $review->id), 'handle']);

    return [$review->fresh() ?? $review, $revision];
}

/** @param array{canon_hash: string, run_id: string, started_at: string, attempts: list<array<string, mixed>>} $run */
function driftTrialRecord(array $run): void
{
    $written = file_put_contents(base_path(DRIFT_TRIAL_EVIDENCE), json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");

    if ($written === false) {
        throw new RuntimeException('The drift trial could not write its evidence file.');
    }
}
