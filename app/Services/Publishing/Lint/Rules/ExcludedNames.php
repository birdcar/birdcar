<?php

namespace App\Services\Publishing\Lint\Rules;

class ExcludedNames extends BannedPhrasesRule
{
    public function id(): string
    {
        return 'excluded-names';
    }

    protected function phrases(): array
    {
        return ['WorkOS', 'GHX', 'DataDash'];
    }

    protected function checksMetadata(): bool
    {
        return true;
    }

    protected function message(string $excerpt): string
    {
        return 'Remove the mention of '.$excerpt.'.';
    }
}
