<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\EditorialActivity;
use App\Models\EvidenceSource;
use App\Models\PublishingAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvidenceSource> */
class EvidenceSourceFactory extends Factory
{
    public function definition(): array
    {
        $text = fake()->paragraph();

        return [
            'article_id' => Article::factory(),
            'attempt_id' => PublishingAttempt::factory(),
            'activity_id' => EditorialActivity::factory(),
            'source_type' => 'public',
            'url' => 'https://example.com/source',
            'final_url' => 'https://example.com/source',
            'title' => fake()->sentence(),
            'retrieved_at' => now(),
            'retrieval_method' => 'openrouter-web',
            'extracted_text' => $text,
            'content_hash' => hash('sha256', $text),
            'origin_metadata' => [],
            'unresolved_reason' => null,
            'restricted_processing_consent' => false,
            'publication_permission' => false,
            'consent_actor_id' => null,
            'consented_at' => null,
        ];
    }

    public function restricted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source_type' => 'restricted',
            'restricted_processing_consent' => true,
            'publication_permission' => false,
        ]);
    }

    public function unresolved(string $reason = 'missing text'): static
    {
        return $this->state(fn (array $attributes): array => [
            'extracted_text' => null,
            'content_hash' => null,
            'unresolved_reason' => $reason,
        ]);
    }
}
