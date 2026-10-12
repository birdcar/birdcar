<?php

namespace App\Services\Publishing\Lint\Rules;

/**
 * A list of banned words and phrases, matched on word boundaries and case-insensitively. Straight and curly
 * apostrophes both match, and a space matches any run of whitespace.
 */
abstract class BannedPhrasesRule extends PatternRule
{
    /** @return list<string> */
    abstract protected function phrases(): array;

    protected function pattern(): string
    {
        $phrases = $this->phrases();
        // Longest first, so "we're" wins over "we" and "pain points" is reported whole.
        usort($phrases, static fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));
        $alternatives = array_map(
            static fn (string $phrase): string => str_replace(["'", ' '], ["['’]", '\s+'], preg_quote($phrase, '/')),
            $phrases,
        );

        return '/(?<![\p{L}\p{N}])(?:'.implode('|', $alternatives).')(?![\p{L}\p{N}])/iu';
    }
}
