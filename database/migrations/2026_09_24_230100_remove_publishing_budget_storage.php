<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the application agent budget. Budget-only history (reservations, allowances and top-ups) is
 * intentionally deleted; rolling back recreates empty schema and cannot restore that history.
 */
return new class extends Migration
{
    private const LEGACY_PRE_CALL_PAUSE_REASON = 'Paused before any provider request by the removed agent budget checks. Resume the publishing attempt to run it again.';

    /** Pause reasons written before any provider request by the removed budget code path. */
    private const PRE_CALL_BLOCKERS = [
        'Publishing agents are disabled.',
        'OpenRouter credentials are not configured.',
        'Publishing agent route is not configured.',
        'Publishing agent model and provider must be explicit.',
        'Publishing agent pricing is missing.',
        'Publishing agent context and output limits must be configured.',
        'A conservative token bound is required before spending.',
        'Endpoint pricing is missing.',
        'Endpoint pricing must be a non-negative decimal string.',
        'This user is not allowed to spend publishing agent budget.',
        'Reservations must be positive.',
        'Blocked publishing attempts cannot spend agent budget.',
        'Budget reservations cannot cross attempts.',
        'The publishing agent budget allowance would be exceeded.',
    ];

    private const FINANCIAL_SNAPSHOT_KEYS = ['provider', 'pricing', 'max_price', 'context_tokens', 'max_completion_tokens'];

    public function up(): void
    {
        if (Schema::hasTable('agent_budget_reservations')) {
            $this->preserveGenerationIds();
            $this->classifyBudgetPausedActivities();
            $this->explainBudgetPausedAttempts();
            Schema::drop('agent_budget_reservations');
        }

        $this->removeFinancialSnapshotKeys();

        $columns = array_values(array_filter(['allowance_nano_usd', 'allowance_changes'], fn (string $column): bool => Schema::hasColumn('publishing_attempts', $column)));
        if ($columns !== []) {
            Schema::table('publishing_attempts', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('publishing_attempts', function (Blueprint $table): void {
            $table->unsignedBigInteger('allowance_nano_usd')->default(5_000_000_000);
            $table->jsonb('allowance_changes')->nullable();
        });

        Schema::create('agent_budget_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('publishing_attempts')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('editorial_activities')->nullOnDelete();
            $table->unsignedInteger('call_number');
            $table->string('local_call_key')->unique();
            $table->unsignedBigInteger('reserved_nano_usd');
            $table->unsignedBigInteger('actual_nano_usd')->nullable();
            $table->string('state');
            $table->jsonb('price_snapshot')->nullable();
            $table->jsonb('request_bound')->nullable();
            $table->string('provider_generation_id')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->text('retained_unknown_reason')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'call_number']);
            $table->index(['attempt_id', 'state']);
        });
    }

    private function preserveGenerationIds(): void
    {
        DB::table('agent_budget_reservations')
            ->whereNotNull('activity_id')
            ->whereNotNull('provider_generation_id')
            ->orderBy('id')
            ->chunkById(200, function ($reservations): void {
                foreach ($reservations as $reservation) {
                    DB::table('editorial_activities')
                        ->where('id', $reservation->activity_id)
                        ->whereNull('generation_id')
                        ->update(['generation_id' => $reservation->provider_generation_id]);
                }
            });
    }

    private function classifyBudgetPausedActivities(): void
    {
        DB::table('editorial_activities')
            ->where('status', 'paused')
            ->whereNotNull('pause_reason')
            ->orderBy('id')
            ->chunkById(200, function ($activities): void {
                foreach ($activities as $activity) {
                    $reason = (string) $activity->pause_reason;
                    $openStates = DB::table('agent_budget_reservations')
                        ->where('activity_id', $activity->id)
                        ->whereIn('state', ['reserved', 'unknown'])
                        ->exists();

                    if (! $openStates && $this->isPreCallBlocker($reason)) {
                        $this->pauseActivity((int) $activity->id, self::LEGACY_PRE_CALL_PAUSE_REASON);

                        continue;
                    }

                    if ($reason === 'Billing outcome is unknown.' || $openStates) {
                        $this->pauseActivity((int) $activity->id, 'The removed agent budget could not confirm this provider call ('.$reason.'). Review the activity before starting new work; resuming the attempt will not rerun it.');
                    }
                }
            });
    }

    private function explainBudgetPausedAttempts(): void
    {
        DB::table('publishing_attempts')
            ->where(function ($query): void {
                $query->where('pause_reason', 'like', 'Agent billing outcome is unknown:%')
                    ->orWhere('pause_reason', 'Agent cost exceeded its budget reservation.');
            })
            ->orderBy('id')
            ->chunkById(200, function ($attempts): void {
                foreach ($attempts as $attempt) {
                    DB::table('publishing_attempts')->where('id', $attempt->id)->update([
                        'pause_reason' => 'Paused by the removed agent budget ('.$attempt->pause_reason.'). Review agent activity, then resume the attempt when ready.',
                    ]);
                }
            });
    }

    private function removeFinancialSnapshotKeys(): void
    {
        DB::table('editorial_activities')
            ->whereNotNull('model_snapshot')
            ->orderBy('id')
            ->chunkById(200, function ($activities): void {
                foreach ($activities as $activity) {
                    $snapshot = is_string($activity->model_snapshot) ? json_decode($activity->model_snapshot, true) : null;
                    if (! is_array($snapshot) || array_intersect_key($snapshot, array_flip(self::FINANCIAL_SNAPSHOT_KEYS)) === []) {
                        continue;
                    }

                    DB::table('editorial_activities')->where('id', $activity->id)->update([
                        'model_snapshot' => json_encode(array_diff_key($snapshot, array_flip(self::FINANCIAL_SNAPSHOT_KEYS)), JSON_THROW_ON_ERROR),
                    ]);
                }
            });
    }

    private function isPreCallBlocker(string $reason): bool
    {
        foreach (self::PRE_CALL_BLOCKERS as $blocker) {
            if (str_contains($reason, $blocker)) {
                return true;
            }
        }

        return false;
    }

    private function pauseActivity(int $activityId, string $reason): void
    {
        DB::table('editorial_activities')->where('id', $activityId)->update(['pause_reason' => $reason]);
    }
};
