<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\PublishingAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EditorialFinding> */
class EditorialFindingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'attempt_id' => PublishingAttempt::factory(),
            'activity_id' => EditorialActivity::factory(),
            'review_cycle' => 1,
            'revision_id' => ArticleRevision::factory(),
            'input_hash' => hash('sha256', 'input'),
            'lens' => 'review_facts',
            'kind' => 'material_fact',
            'severity' => 'blocking',
            'block_id' => null,
            'expected_subtree_hash' => null,
            'statement' => fake()->sentence(),
            'rationale' => fake()->sentence(),
            'supporting_source_ids' => [],
            'supporting_quotations' => [],
            'proposed_patch' => null,
            'disposition' => null,
            'disposition_reason' => null,
            'disposition_actor_id' => null,
            'disposed_at' => null,
            'stale_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'disposition' => 'accepted',
            'disposed_at' => now(),
        ]);
    }

    public function advisory(): static
    {
        return $this->state(fn (array $attributes): array => [
            'severity' => 'advisory',
        ]);
    }
}
