<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ExemplarSection;
use App\Models\Publishing\PieceKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExemplarSection>
 */
class ExemplarSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $heading = fake()->sentence(4);
        $text = fake()->paragraph();

        return [
            'article_id' => Article::factory(),
            'release_id' => null,
            'kind' => PieceKind::BlogPost,
            'position' => 0,
            'heading' => $heading,
            'text' => $text,
            'content_hash' => hash('sha256', $heading."\n".$text),
        ];
    }

    /**
     * @param  list<float>  $vector
     */
    public function embedded(array $vector): static
    {
        return $this->afterMaking(function (ExemplarSection $section) use ($vector): void {
            $section->forceFill(['embedding' => $vector, 'embedded_at' => now()]);
        });
    }
}
