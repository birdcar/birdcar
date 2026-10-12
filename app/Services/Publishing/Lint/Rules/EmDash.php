<?php

namespace App\Services\Publishing\Lint\Rules;

class EmDash extends PatternRule
{
    public function id(): string
    {
        return 'em-dash';
    }

    protected function pattern(): string
    {
        return '/\x{2014}/u';
    }

    protected function checksMetadata(): bool
    {
        return true;
    }

    protected function excerpt(string $text, string $match, int $byteOffset): string
    {
        return $this->excerptAround($text, $byteOffset, $match);
    }

    protected function message(string $excerpt): string
    {
        return 'Replace the em dash in "'.$excerpt.'" with a comma, parentheses, a colon or a new sentence.';
    }
}
