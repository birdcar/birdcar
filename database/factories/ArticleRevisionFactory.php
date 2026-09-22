<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Services\Publishing\PublishingFingerprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleRevision>
 */
class ArticleRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $document = [
            'version' => 1,
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'text' => fake()->sentence()],
            ],
        ];
        $metadata = [
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'tags' => [],
        ];

        return [
            'article_id' => Article::factory(),
            'number' => 1,
            'parent_revision_id' => null,
            'created_by' => User::factory(),
            'origin' => 'human',
            'document' => $document,
            'metadata' => $metadata,
            'content_hash' => (new PublishingFingerprint)->hash(['document' => $document, 'metadata' => $metadata]),
            'client_mutation_id' => fake()->uuid(),
        ];
    }
}
