<?php

namespace App\Services\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\AgentBudgetReservation;
use App\Models\EditorialActivity;
use App\Models\PublishingAttempt;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AgentBudget
{
    /**
     * @param  array<string, mixed>  $priceSnapshot
     * @param  array<string, mixed>  $requestBound
     */
    public function reserve(User $actor, PublishingAttempt|int $attempt, ?EditorialActivity $activity, int $reservedNanoUsd, array $priceSnapshot = [], array $requestBound = []): AgentBudgetReservation
    {
        if (! $actor->can(PublishingPermission::Develop->value)) {
            throw new AuthorizationException('This user is not allowed to spend publishing agent budget.');
        }

        if ($reservedNanoUsd <= 0) {
            throw new InvalidArgumentException('Reservations must be positive.');
        }

        /** @var AgentBudgetReservation $reservation */
        $reservation = DB::transaction(function () use ($attempt, $activity, $reservedNanoUsd, $priceSnapshot, $requestBound): AgentBudgetReservation {
            $lockedAttempt = $this->lockedAttempt($attempt);

            if ($lockedAttempt->paused_at !== null || $lockedAttempt->parked_at !== null || $lockedAttempt->abandoned_at !== null) {
                throw new RuntimeException('Blocked publishing attempts cannot spend agent budget.');
            }

            if ($activity !== null && (int) $activity->attempt_id !== (int) $lockedAttempt->id) {
                throw new RuntimeException('Budget reservations cannot cross attempts.');
            }

            $spentOrHeld = $this->spentOrHeldForLockedAttempt((int) $lockedAttempt->id);
            $allowance = (int) $lockedAttempt->allowance_nano_usd;

            if ($spentOrHeld + $reservedNanoUsd > $allowance) {
                throw new RuntimeException('The publishing agent budget allowance would be exceeded.');
            }

            $callNumber = 1;
            if ($activity !== null) {
                $callNumber = ((int) AgentBudgetReservation::query()
                    ->where('activity_id', $activity->id)
                    ->max('call_number')) + 1;
            }

            return AgentBudgetReservation::create([
                'attempt_id' => $lockedAttempt->id,
                'activity_id' => $activity?->id,
                'call_number' => $callNumber,
                'local_call_key' => $activity === null ? 'attempt-'.$lockedAttempt->id.'-'.bin2hex(random_bytes(8)) : 'activity-'.$activity->id.'-call-'.$callNumber,
                'reserved_nano_usd' => $reservedNanoUsd,
                'actual_nano_usd' => null,
                'state' => AgentBudgetReservation::STATE_RESERVED,
                'price_snapshot' => $priceSnapshot,
                'request_bound' => $requestBound,
            ]);
        });

        return $reservation;
    }

    public function settle(AgentBudgetReservation|int $reservation, int $actualNanoUsd, ?string $generationId = null): AgentBudgetReservation
    {
        if ($actualNanoUsd < 0) {
            throw new InvalidArgumentException('Actual cost cannot be negative.');
        }

        /** @var AgentBudgetReservation $settled */
        $settled = DB::transaction(function () use ($reservation, $actualNanoUsd, $generationId): AgentBudgetReservation {
            $locked = $this->lockedReservation($reservation);

            if ($locked->state === AgentBudgetReservation::STATE_SETTLED) {
                if ((int) $locked->actual_nano_usd !== $actualNanoUsd) {
                    throw new RuntimeException('Budget reservations can only be settled once.');
                }

                return $locked;
            }

            if ($locked->state !== AgentBudgetReservation::STATE_RESERVED && $locked->state !== AgentBudgetReservation::STATE_UNKNOWN) {
                throw new RuntimeException('Only reserved or unknown budget calls can be settled.');
            }

            $locked->forceFill([
                'state' => AgentBudgetReservation::STATE_SETTLED,
                'actual_nano_usd' => $actualNanoUsd,
                'provider_generation_id' => $generationId,
                'settled_at' => now(),
                'retained_unknown_reason' => null,
            ])->save();

            if ($actualNanoUsd > (int) $locked->reserved_nano_usd) {
                $attempt = PublishingAttempt::query()->whereKey($locked->attempt_id)->lockForUpdate()->first();
                if ($attempt !== null) {
                    $attempt->forceFill([
                        'paused_at' => now(),
                        'pause_reason' => 'Agent cost exceeded its budget reservation.',
                    ])->save();
                }
            }

            return $locked;
        });

        return $settled;
    }

    public function retainUnknown(AgentBudgetReservation|int $reservation, string $reason, ?string $generationId = null): AgentBudgetReservation
    {
        /** @var AgentBudgetReservation $unknown */
        $unknown = DB::transaction(function () use ($reservation, $reason, $generationId): AgentBudgetReservation {
            $locked = $this->lockedReservation($reservation);

            if ($locked->state === AgentBudgetReservation::STATE_SETTLED) {
                return $locked;
            }

            $locked->forceFill([
                'state' => AgentBudgetReservation::STATE_UNKNOWN,
                'provider_generation_id' => $generationId,
                'retained_unknown_reason' => $reason,
            ])->save();

            $attempt = PublishingAttempt::query()->whereKey($locked->attempt_id)->lockForUpdate()->first();
            if ($attempt !== null) {
                $attempt->forceFill([
                    'paused_at' => now(),
                    'pause_reason' => 'Agent billing outcome is unknown: '.$reason,
                ])->save();
            }

            return $locked;
        });

        return $unknown;
    }

    public function release(AgentBudgetReservation|int $reservation, string $reason): AgentBudgetReservation
    {
        /** @var AgentBudgetReservation $released */
        $released = DB::transaction(function () use ($reservation, $reason): AgentBudgetReservation {
            $locked = $this->lockedReservation($reservation);

            if ($locked->state === AgentBudgetReservation::STATE_SETTLED || $locked->state === AgentBudgetReservation::STATE_UNKNOWN) {
                throw new RuntimeException('Billable or unknown budget reservations cannot be released.');
            }

            if ($locked->state === AgentBudgetReservation::STATE_RELEASED) {
                return $locked;
            }

            $locked->forceFill([
                'state' => AgentBudgetReservation::STATE_RELEASED,
                'retained_unknown_reason' => $reason,
                'settled_at' => now(),
            ])->save();

            return $locked;
        });

        return $released;
    }

    /** @return array{allowance: int, spent: int, held: int, available: int} */
    public function available(PublishingAttempt|int $attempt): array
    {
        $attemptModel = $attempt instanceof PublishingAttempt ? $attempt : PublishingAttempt::query()->findOrFail($attempt);
        $settled = (int) AgentBudgetReservation::query()
            ->where('attempt_id', $attemptModel->id)
            ->where('state', AgentBudgetReservation::STATE_SETTLED)
            ->sum('actual_nano_usd');
        $held = (int) AgentBudgetReservation::query()
            ->where('attempt_id', $attemptModel->id)
            ->whereIn('state', [AgentBudgetReservation::STATE_RESERVED, AgentBudgetReservation::STATE_UNKNOWN])
            ->sum('reserved_nano_usd');
        $allowance = (int) $attemptModel->allowance_nano_usd;

        return [
            'allowance' => $allowance,
            'spent' => $settled,
            'held' => $held,
            'available' => max(0, $allowance - $settled - $held),
        ];
    }

    public function increaseAllowance(User $actor, PublishingAttempt|int $attempt, int $deltaNanoUsd, string $mutationKey): PublishingAttempt
    {
        if (! $actor->can(PublishingPermission::Budget->value)) {
            throw new AuthorizationException('This user is not allowed to change publishing agent budget.');
        }

        if ($deltaNanoUsd <= 0 || $deltaNanoUsd > 100_000_000_000) {
            throw new InvalidArgumentException('Budget top-up delta is out of range.');
        }

        /** @var PublishingAttempt $updated */
        $updated = DB::transaction(function () use ($actor, $attempt, $deltaNanoUsd, $mutationKey): PublishingAttempt {
            $locked = $this->lockedAttempt($attempt);
            $allowanceChanges = $locked->getAttribute('allowance_changes');
            $changes = is_array($allowanceChanges) ? $allowanceChanges : [];

            foreach ($changes as $change) {
                if (is_array($change) && ($change['mutation_key'] ?? null) === $mutationKey) {
                    if ((int) ($change['delta_nano_usd'] ?? 0) !== $deltaNanoUsd) {
                        throw new RuntimeException('The budget mutation key was already used for a different top-up.');
                    }

                    return $locked;
                }
            }

            $previous = (int) $locked->allowance_nano_usd;
            $next = $previous + $deltaNanoUsd;
            $changes[] = [
                'mutation_key' => $mutationKey,
                'actor_id' => $actor->id,
                'delta_nano_usd' => $deltaNanoUsd,
                'previous_nano_usd' => $previous,
                'new_nano_usd' => $next,
                'at' => now()->toISOString(),
            ];

            $locked->forceFill([
                'allowance_nano_usd' => $next,
                'allowance_changes' => $changes,
            ])->save();

            return $locked;
        });

        return $updated;
    }

    private function spentOrHeldForLockedAttempt(int $attemptId): int
    {
        $settled = (int) AgentBudgetReservation::query()
            ->where('attempt_id', $attemptId)
            ->where('state', AgentBudgetReservation::STATE_SETTLED)
            ->sum('actual_nano_usd');
        $held = (int) AgentBudgetReservation::query()
            ->where('attempt_id', $attemptId)
            ->whereIn('state', [AgentBudgetReservation::STATE_RESERVED, AgentBudgetReservation::STATE_UNKNOWN])
            ->sum('reserved_nano_usd');

        return $settled + $held;
    }

    private function lockedAttempt(PublishingAttempt|int $attempt): PublishingAttempt
    {
        $attemptId = $attempt instanceof PublishingAttempt ? $attempt->getKey() : $attempt;

        return PublishingAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
    }

    private function lockedReservation(AgentBudgetReservation|int $reservation): AgentBudgetReservation
    {
        $reservationId = $reservation instanceof AgentBudgetReservation ? $reservation->getKey() : $reservation;

        return AgentBudgetReservation::query()->whereKey($reservationId)->lockForUpdate()->firstOrFail();
    }
}
