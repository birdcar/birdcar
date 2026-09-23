<?php

namespace App\Console\Commands;

use App\Actions\Publishing\RunEditorialActivity;
use App\Models\AgentBudgetReservation;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityStatus;
use App\Services\Publishing\AgentBudget;
use App\Services\Publishing\OpenRouterClient;
use Illuminate\Console\Command;
use Throwable;

class RecoverEditorialActivities extends Command
{
    protected $signature = 'publishing:recover-activities {--limit=50}';

    protected $description = 'Re-enqueue durable pending editorial agent activities and pause ambiguous old runs.';

    public function handle(OpenRouterClient $client, AgentBudget $budget): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $count = 0;
        $reconciled = 0;
        $paused = 0;

        EditorialActivity::query()
            ->where('status', EditorialActivityStatus::Pending->value)
            ->where(function ($query): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->each(function (EditorialActivity $activity) use (&$count): void {
                RunEditorialActivity::dispatch((int) $activity->id)->afterCommit();
                $count++;
            });

        EditorialActivity::query()
            ->where('status', EditorialActivityStatus::Running->value)
            ->where('started_at', '<', now()->subMinutes(30))
            ->orderBy('id')
            ->limit($limit)
            ->each(function (EditorialActivity $activity) use ($client, $budget, &$reconciled, &$paused): void {
                if ($this->reconcileRecordedGeneration($activity, $client, $budget)) {
                    $reconciled++;
                } else {
                    $paused++;
                }
            });

        $this->info('Recovered '.$count.' pending editorial activities; reconciled '.$reconciled.' recorded generations; paused '.$paused.' ambiguous runs.');

        return self::SUCCESS;
    }

    private function reconcileRecordedGeneration(EditorialActivity $activity, OpenRouterClient $client, AgentBudget $budget): bool
    {
        $generationId = is_string($activity->generation_id) && $activity->generation_id !== '' ? $activity->generation_id : null;
        $reservation = $this->recoverableReservation($activity, $generationId);

        if ($generationId === null && $reservation instanceof AgentBudgetReservation && is_string($reservation->provider_generation_id) && $reservation->provider_generation_id !== '') {
            $generationId = $reservation->provider_generation_id;
        }

        if ($generationId === null) {
            $this->pauseForOperator($activity, 'Recovery found a running activity without a recorded generation id.');

            return false;
        }

        if (! $reservation instanceof AgentBudgetReservation) {
            $this->pauseForOperator($activity, 'Recovery found a recorded generation id without a matching budget reservation; operator review is required.', $generationId);

            return false;
        }

        if ($reservation->state === AgentBudgetReservation::STATE_SETTLED) {
            $this->pauseForOperator($activity, 'Recovery found an already settled generation; operator review is required before applying or rerunning output.', $generationId);

            return true;
        }

        try {
            $generation = $client->generation($generationId);
            $actualNanoUsd = $this->actualCostNanoUsd($generation);
            if ($actualNanoUsd === null) {
                $budget->retainUnknown($reservation, 'Recovery generation lookup did not return complete cost metadata.', $generationId);
                $this->pauseForOperator($activity, 'Recovery could not settle the recorded generation cost; operator review is required.', $generationId);

                return false;
            }

            $budget->settle($reservation, $actualNanoUsd, $generationId);
            $this->pauseForOperator($activity, 'Recovery settled the recorded generation; operator review is required before applying or rerunning output.', $generationId);

            return true;
        } catch (Throwable $throwable) {
            $budget->retainUnknown($reservation, 'Recovery generation lookup failed: '.$throwable->getMessage(), $generationId);
            $this->pauseForOperator($activity, 'Recovery could not reconcile the recorded generation id; operator review is required.', $generationId);

            return false;
        }
    }

    private function recoverableReservation(EditorialActivity $activity, ?string $generationId): ?AgentBudgetReservation
    {
        $query = AgentBudgetReservation::query()
            ->where('activity_id', $activity->id)
            ->whereIn('state', [AgentBudgetReservation::STATE_RESERVED, AgentBudgetReservation::STATE_UNKNOWN, AgentBudgetReservation::STATE_SETTLED]);

        if ($generationId !== null && $generationId !== '') {
            $matching = (clone $query)
                ->where(function ($reservationQuery) use ($generationId): void {
                    $reservationQuery->where('provider_generation_id', $generationId)->orWhereNull('provider_generation_id');
                })
                ->orderByRaw('provider_generation_id is null')
                ->orderByDesc('call_number')
                ->first();

            if ($matching instanceof AgentBudgetReservation) {
                return $matching;
            }
        }

        return $query
            ->whereNotNull('provider_generation_id')
            ->orderByDesc('call_number')
            ->first();
    }

    private function pauseForOperator(EditorialActivity $activity, string $reason, ?string $generationId = null): void
    {
        $updates = [
            'status' => EditorialActivityStatus::Paused->value,
            'paused_at' => now(),
            'pause_reason' => $reason,
        ];

        if ($generationId !== null && $generationId !== '') {
            $updates['generation_id'] = $generationId;
        }

        EditorialActivity::query()
            ->whereKey($activity->id)
            ->where('status', EditorialActivityStatus::Running->value)
            ->update($updates);
    }

    /** @param array<string, mixed> $response */
    private function actualCostNanoUsd(array $response): ?int
    {
        $value = data_get($response, 'data.total_cost') ?? data_get($response, 'total_cost') ?? data_get($response, 'usage.cost') ?? data_get($response, 'usage.total_cost');
        if (is_int($value)) {
            return $value * 1_000_000_000;
        }
        if (is_float($value)) {
            return (int) ceil($value * 1_000_000_000);
        }
        if (is_string($value) && preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            return $this->decimalUsdToNanoUsd($value);
        }

        return null;
    }

    private function decimalUsdToNanoUsd(string $decimalUsd): int
    {
        [$whole, $fraction] = array_pad(explode('.', $decimalUsd, 2), 2, '');
        $fraction = str_pad($fraction, 10, '0');
        $nano = ((int) $whole * 1_000_000_000) + (int) substr($fraction, 0, 9);
        if (preg_match('/[1-9]/', substr($fraction, 9)) === 1) {
            $nano++;
        }

        return $nano;
    }
}
