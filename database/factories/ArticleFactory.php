<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ContentIdea;
use App\Models\Publishing\PieceKind;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'idea-'.Str::lower(Str::random(12)),
            'author_id' => User::factory(),
            'content_idea_id' => fn (array $attributes): ContentIdeaFactory => ContentIdea::factory()->state(['author_id' => $attributes['author_id']]),
            'kind' => PieceKind::BlogPost,
            'parent_article_id' => null,
            'working_revision_id' => null,
            'published_release_id' => null,
            'current_attempt_id' => null,
            'first_published_at' => null,
        ];
    }

    public function idea(string $body): static
    {
        return $this->state([
            'content_idea_id' => fn (array $attributes): ContentIdeaFactory => ContentIdea::factory()->body($body)->state(['author_id' => $attributes['author_id']]),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'first_published_at' => now(),
        ]);
    }
}
