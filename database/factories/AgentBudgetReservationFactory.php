<?php

namespace Database\Factories;

use App\Models\AgentBudgetReservation;
use App\Models\EditorialActivity;
use App\Models\PublishingAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgentBudgetReservation> */
class AgentBudgetReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attempt_id' => PublishingAttempt::factory(),
            'activity_id' => EditorialActivity::factory(),
            'call_number' => 1,
            'local_call_key' => fake()->uuid(),
            'reserved_nano_usd' => 100_000_000,
            'actual_nano_usd' => null,
            'state' => AgentBudgetReservation::STATE_RESERVED,
            'price_snapshot' => [],
            'request_bound' => [],
            'provider_generation_id' => null,
            'settled_at' => null,
            'retained_unknown_reason' => null,
        ];
    }

    public function settled(int $actualNanoUsd = 50_000_000): static
    {
        return $this->state(fn (array $attributes): array => [
            'actual_nano_usd' => $actualNanoUsd,
            'state' => AgentBudgetReservation::STATE_SETTLED,
            'settled_at' => now(),
        ]);
    }

    public function unknown(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => AgentBudgetReservation::STATE_UNKNOWN,
            'retained_unknown_reason' => 'Timeout after possible receipt.',
        ]);
    }
}
