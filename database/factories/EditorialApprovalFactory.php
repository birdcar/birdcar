<?php

namespace Database\Factories;

use App\Models\EditorialApproval;
use App\Models\Publishing\ApprovalKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditorialApproval>
 */
class EditorialApprovalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => PublishingAttempt::factory(),
            'kind' => ApprovalKind::Angle,
            'input_hash' => hash('sha256', fake()->uuid()),
            'revision_id' => null,
            'release_id' => null,
            'user_id' => User::factory(),
            'approved_at' => now(),
            'invalidated_at' => null,
        ];
    }

    public function invalidated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'invalidated_at' => now(),
        ]);
    }
}
