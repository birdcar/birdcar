<?php

namespace App\Services\Publishing\Lint\Rules;

class CurlyQuotes extends PatternRule
{
    public function id(): string
    {
        return 'curly-quotes';
    }

    protected function pattern(): string
    {
        return '/[\x{2018}\x{2019}\x{201C}\x{201D}]/u';
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
        return 'Use straight quotes and apostrophes in "'.$excerpt.'".';
    }
}
