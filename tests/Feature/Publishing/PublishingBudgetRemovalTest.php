<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\EditorialActivity;
use App\Models\EvidenceSource;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function budgetRemovalMigration(): Migration
{
    return require database_path('migrations/2026_09_24_230100_remove_publishing_budget_storage.php');
}

/** @return array{User, PublishingAttempt} */
function legacyBudgetAttempt(): array
{
    $author = User::factory()->create();
    $author->assignRole(PublishingRole::Author->value);
    $article = Article::factory()->create(['author_id' => $author->id]);
    $attempt = PublishingAttempt::factory()->create(['article_id' => $article->id, 'user_id' => $author->id]);
    $article->forceFill(['current_attempt_id' => $attempt->id])->save();

    return [$author, $attempt];
}

function legacyActivity(PublishingAttempt $attempt, User $author, array $attributes): EditorialActivity
{
    return EditorialActivity::factory()->create(array_merge([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $author->id,
    ], $attributes));
}

function legacyReservation(PublishingAttempt $attempt, ?EditorialActivity $activity, string $state, ?string $generationId = null): void
{
    static $call = 0;
    $call++;
    DB::table('agent_budget_reservations')->insert([
        'attempt_id' => $attempt->id,
        'activity_id' => $activity?->id,
        'call_number' => $call,
        'local_call_key' => 'legacy-call-'.$call,
        'reserved_nano_usd' => 10_000,
        'actual_nano_usd' => $state === 'settled' ? 5_000 : null,
        'state' => $state,
        'price_snapshot' => json_encode(['pricing' => ['prompt' => '0.000001']]),
        'request_bound' => json_encode(['prompt_tokens' => 100]),
        'provider_generation_id' => $generationId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('a fresh install has settings storage and no financial schema', function (): void {
    expect(Schema::hasTable('settings'))->toBeTrue()
        ->and(Schema::hasTable('agent_budget_reservations'))->toBeFalse()
        ->and(Schema::hasColumn('publishing_attempts', 'allowance_nano_usd'))->toBeFalse()
        ->and(Schema::hasColumn('publishing_attempts', 'allowance_changes'))->toBeFalse()
        ->and(Schema::hasColumns('editorial_activities', ['model_snapshot', 'generation_id', 'ai_conversation_id']))->toBeTrue();
});

test('rollback recreates empty financial schema and the forward migration removes it again', function (): void {
    $migration = budgetRemovalMigration();

    $migration->down();
    expect(Schema::hasTable('agent_budget_reservations'))->toBeTrue()
        ->and(Schema::hasColumns('publishing_attempts', ['allowance_nano_usd', 'allowance_changes']))->toBeTrue()
        ->and(DB::table('agent_budget_reservations')->count())->toBe(0);

    $migration->up();
    expect(Schema::hasTable('agent_budget_reservations'))->toBeFalse();
});

test('upgrading populated legacy records preserves editorial work and diagnostic identity', function (): void {
    $migration = budgetRemovalMigration();
    $migration->down();
    [$author, $attempt] = legacyBudgetAttempt();
    DB::table('publishing_attempts')->where('id', $attempt->id)->update(['allowance_nano_usd' => 6_000_000_000, 'allowance_changes' => json_encode([['delta_nano_usd' => 1_000_000_000]])]);
    $completed = legacyActivity($attempt, $author, [
        'status' => EditorialActivityStatus::Completed,
        'response' => ['brief' => ['summary' => 'Kept']],
        'ai_conversation_id' => 'conversation-kept',
        'model_snapshot' => ['model' => 'deepseek/deepseek-v4-pro-0813', 'provider' => 'DeepSeek', 'pricing' => ['prompt' => '1'], 'max_price' => ['prompt' => '1'], 'context_tokens' => 100, 'max_completion_tokens' => 50],
    ]);
    $withoutGeneration = legacyActivity($attempt, $author, ['status' => EditorialActivityStatus::Completed, 'generation_id' => null]);
    legacyReservation($attempt, $completed, 'settled', 'gen-original');
    legacyReservation($attempt, $withoutGeneration, 'settled', 'gen-from-reservation');
    legacyReservation($attempt, null, 'settled');
    $completed->forceFill(['generation_id' => 'gen-original'])->save();
    $evidence = EvidenceSource::create(['article_id' => $attempt->article_id, 'attempt_id' => $attempt->id, 'activity_id' => $completed->id, 'source_type' => 'public', 'url' => 'https://example.com', 'extracted_text' => 'Evidence kept.']);

    $migration->up();

    expect(Schema::hasTable('agent_budget_reservations'))->toBeFalse()
        ->and(Schema::hasColumn('publishing_attempts', 'allowance_nano_usd'))->toBeFalse()
        ->and(PublishingAttempt::findOrFail($attempt->id)->brief)->toBe($attempt->brief)
        ->and($completed->fresh()->response)->toBe(['brief' => ['summary' => 'Kept']])
        ->and($completed->fresh()->ai_conversation_id)->toBe('conversation-kept')
        ->and($completed->fresh()->generation_id)->toBe('gen-original')
        ->and($completed->fresh()->model_snapshot)->toBe(['model' => 'deepseek/deepseek-v4-pro-0813'])
        ->and($withoutGeneration->fresh()->generation_id)->toBe('gen-from-reservation')
        ->and($evidence->fresh()?->extracted_text)->toBe('Evidence kept.');
});

test('legacy budget pauses stay protected unless they were positively pre-call blockers', function (): void {
    $migration = budgetRemovalMigration();
    $migration->down();
    [$author, $attempt] = legacyBudgetAttempt();
    $preCall = legacyActivity($attempt, $author, ['status' => EditorialActivityStatus::Paused, 'paused_at' => now(), 'pause_reason' => 'The publishing agent budget allowance would be exceeded.']);
    $unknownBilling = legacyActivity($attempt, $author, ['status' => EditorialActivityStatus::Paused, 'paused_at' => now(), 'pause_reason' => 'Billing outcome is unknown.', 'generation_id' => 'gen-discarded']);
    $afterReservation = legacyActivity($attempt, $author, ['status' => EditorialActivityStatus::Paused, 'paused_at' => now(), 'pause_reason' => 'cURL error 28: timeout']);
    $unrelated = legacyActivity($attempt, $author, ['status' => EditorialActivityStatus::Paused, 'paused_at' => now(), 'pause_reason' => 'The initiating actor can no longer run this publishing agent work.']);
    legacyReservation($attempt, $unknownBilling, 'unknown', 'gen-discarded');
    legacyReservation($attempt, $afterReservation, 'unknown');
    DB::table('publishing_attempts')->where('id', $attempt->id)->update(['paused_at' => now(), 'pause_reason' => 'Agent billing outcome is unknown: OpenRouter did not return complete cost metadata.']);

    $migration->up();

    expect($preCall->fresh()->pause_reason)->toBe(EditorialActivity::LEGACY_PRE_CALL_PAUSE_REASON)
        ->and($unknownBilling->fresh()->pause_reason)->toContain('could not confirm this provider call')
        ->and($unknownBilling->fresh()->generation_id)->toBe('gen-discarded')
        ->and($afterReservation->fresh()->pause_reason)->toContain('cURL error 28')
        ->and($unrelated->fresh()->pause_reason)->toBe('The initiating actor can no longer run this publishing agent work.')
        ->and($attempt->fresh()->pause_reason)->toContain('resume the attempt when ready');

    app(AdvancePublishingAttempt::class)->resume($author, $attempt->fresh());

    expect($preCall->fresh()->status)->toBe(EditorialActivityStatus::Pending)
        ->and($unknownBilling->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($afterReservation->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($unrelated->fresh()->status)->toBe(EditorialActivityStatus::Paused)
        ->and($attempt->fresh()->paused_at)->toBeNull();
});

test('upgrading with no budget records leaves activities untouched', function (): void {
    $migration = budgetRemovalMigration();
    $migration->down();
    [$author, $attempt] = legacyBudgetAttempt();
    $activity = legacyActivity($attempt, $author, ['status' => EditorialActivityStatus::Paused, 'paused_at' => now(), 'pause_reason' => 'Publishing attempt paused.', 'model_snapshot' => []]);

    $migration->up();

    expect($activity->fresh()->pause_reason)->toBe('Publishing attempt paused.')
        ->and($activity->fresh()->model_snapshot)->toBe([]);
});
