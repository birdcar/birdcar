<?php

namespace App\Services\Publishing\Lint\Rules;

class UnresolvedMarkers extends PatternRule
{
    public function id(): string
    {
        return 'unresolved-markers';
    }

    protected function pattern(): string
    {
        return '/\[(?:NEED|TODO):[^\]]{0,120}\]?/iu';
    }

    protected function checksMetadata(): bool
    {
        return true;
    }

    protected function message(string $excerpt): string
    {
        return 'Resolve the marker "'.$excerpt.'" before release.';
    }
}
