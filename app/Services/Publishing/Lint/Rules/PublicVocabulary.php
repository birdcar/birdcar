<?php

namespace App\Services\Publishing\Lint\Rules;

class PublicVocabulary extends BannedPhrasesRule
{
    public function id(): string
    {
        return 'public-vocabulary';
    }

    /** Bare "stack" and "scale" as a hype verb need judgment, so the voice reviewer owns them. */
    protected function phrases(): array
    {
        return ['agentic', 'AI-powered', 'solution', 'solutions', 'streamline', 'unlock', 'supercharge', '10x', 'pain points', 'tech stack', 'tech debt', 'SaaS'];
    }

    protected function message(string $excerpt): string
    {
        return '"'.$excerpt.'" is banned in the public register. Use the words an owner would use.';
    }
}
