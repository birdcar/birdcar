<?php

namespace App\Services\Publishing\Lint\Rules;

class Emoji extends PatternRule
{
    public function id(): string
    {
        return 'emoji';
    }

    /**
     * Extended_Pictographic also covers typographic symbols that are not emoji (©, ®, ™, ‼, ℹ and the plain arrows), so
     * those are excluded; regional indicators make flags.
     */
    protected function pattern(): string
    {
        return '/(?![\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}\x{21AA}])\p{Extended_Pictographic}|[\x{1F1E6}-\x{1F1FF}]/u';
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
        return 'Remove the emoji in "'.$excerpt.'".';
    }
}
