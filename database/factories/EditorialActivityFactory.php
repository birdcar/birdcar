<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EditorialActivity> */
class EditorialActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'attempt_id' => PublishingAttempt::factory(),
            'initiating_user_id' => User::factory(),
            'kind' => EditorialActivityKind::Interview,
            'status' => EditorialActivityStatus::Pending,
            'stage' => EditorialStage::Developing->value,
            'input_version' => null,
            'revision_id' => null,
            'revision_hash' => null,
            'review_cycle' => 1,
            'batch_key' => null,
            'idempotency_key' => fake()->uuid(),
            'prompt_version' => 1,
            'prompt_hash' => hash('sha256', 'prompt'),
            'input' => [],
            'model_snapshot' => [],
            'response' => null,
            'proposal' => null,
            'run_count' => 0,
            'available_at' => now(),
            'started_at' => null,
            'completed_at' => null,
            'paused_at' => null,
            'pause_reason' => null,
            'error_reason' => null,
            'generation_id' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EditorialActivityStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EditorialActivityStatus::Paused,
            'paused_at' => now(),
            'pause_reason' => 'Waiting on owner input.',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EditorialActivityStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
