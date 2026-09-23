<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublishingAttempt>
 */
class PublishingAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'user_id' => User::factory(),
            'stage' => EditorialStage::Developing,
            'input_version' => null,
            'brief' => ['question' => fake()->sentence()],
            'angle' => [],
            'plan' => [],
            'interview_context' => [],
            'paused_at' => null,
            'pause_reason' => null,
            'parked_at' => null,
            'parked_reason' => null,
            'abandoned_at' => null,
            'abandoned_reason' => null,
            'allowance_nano_usd' => 5_000_000_000,
            'review_cycle' => 1,
            'recheck_used' => false,
            'allowance_changes' => [],
        ];
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'paused_at' => now(),
            'pause_reason' => 'Waiting on answers.',
        ]);
    }
}
