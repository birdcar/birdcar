<?php

namespace App\Services\Publishing\Lint\Rules;

class ExperienceClaim extends BannedPhrasesRule
{
    public function id(): string
    {
        return 'experience-claim';
    }

    protected function phrases(): array
    {
        return ['more than fifteen years', 'more than 15 years', 'over fifteen years', '15+ years'];
    }

    protected function message(string $excerpt): string
    {
        return 'Write the experience as "fifteen years" or "more than 12 years", not "'.$excerpt.'".';
    }
}
