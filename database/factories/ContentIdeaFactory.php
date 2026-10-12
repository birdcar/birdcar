<?php

namespace Database\Factories;

use App\Models\ContentIdea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentIdea>
 */
class ContentIdeaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = fake()->paragraph();

        return [
            'author_id' => User::factory(),
            'summary_title' => ContentIdea::deriveSummaryTitle($body),
            'summary_title_source' => ContentIdea::SOURCE_DERIVED,
            'body' => $body,
        ];
    }

    public function body(string $body): static
    {
        return $this->state(fn (array $attributes): array => [
            'body' => $body,
            'summary_title' => ContentIdea::deriveSummaryTitle($body),
            'summary_title_source' => ContentIdea::SOURCE_DERIVED,
        ]);
    }
}
