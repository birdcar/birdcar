<?php

namespace App\Services\Publishing\Lint\Rules;

class NegativeParallelism extends PatternRule
{
    public function id(): string
    {
        return 'negative-parallelism';
    }

    protected function pattern(): string
    {
        return '/\bnot\s+(?:just|only|merely)\b[^.]{0,80}?\b(?:it[\'’]?s|but(?:\s+also)?)\b|\bit[\'’]?s\s+not\s+(?:just\s+)?[^.,]{1,60},\s*it[\'’]?s\b/iu';
    }

    protected function message(string $excerpt): string
    {
        return 'Rewrite the negative parallelism "'.$excerpt.'" as one direct statement.';
    }
}
