<?php

namespace App\Services\Publishing\Lint\Rules;

class AiVocabulary extends BannedPhrasesRule
{
    public function id(): string
    {
        return 'ai-vocabulary';
    }

    protected function phrases(): array
    {
        return [
            'additionally', 'furthermore', 'moreover', 'testament', 'landscape', 'showcasing', 'delve', 'tapestry', 'realm',
            'pivotal', 'crucial', 'innovative', 'cutting-edge', 'game-changing', 'transformative', 'garner', 'underscore',
            'interplay', 'intricate', 'intricacies', 'vibrant', 'enhance', 'fostering', 'enduring', 'align with',
        ];
    }

    protected function message(string $excerpt): string
    {
        return '"'.$excerpt.'" is AI vocabulary. Use the plain word you mean, or cut it.';
    }
}
