<?php

namespace App\Services\Publishing\Lint\Rules;

/**
 * Sentence case as the voice canon defines it: after the first word, only proper nouns on the allowlist, "I" and its
 * contractions, all-caps acronyms and words inside straight quotes may start with a capital.
 */
trait CountsCapitalizedWords
{
    /** @var list<string> */
    public const PROPER_NOUNS = [
        'Craft & Communicate', 'Walkthrough', 'Birdcar', 'GitHub', 'Heroku', 'Zapier', 'Twilio', 'Salesforce', 'LinkedIn',
        'Bluesky', 'Google', 'Nick',
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
        'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December',
    ];

    /** Two or more counted capitals after the first word make Title Case. */
    protected function isTitleCase(string $text): bool
    {
        return $this->capitalizedWords($text) >= 2;
    }

    private function capitalizedWords(string $heading): int
    {
        // Allowlisted names and quoted titles keep their position as a neutral token, so the first word stays the first word.
        $text = preg_replace('/"[^"]*"/u', '§', $heading) ?? $heading;
        foreach (self::PROPER_NOUNS as $name) {
            $text = preg_replace('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/u', '§', $text) ?? $text;
        }

        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        array_shift($words);
        $capitalized = 0;

        foreach ($words as $word) {
            $word = preg_replace('/^[\p{P}\p{S}]+|[\p{P}\p{S}]+$/u', '', $word) ?? $word;
            if ($word === ''
                || preg_match('/^I(?:[\'’](?:m|d|ve|ll))?$/u', $word) === 1
                || preg_match('/^\p{Lu}[\p{Lu}\p{N}]+s?$/u', $word) === 1) {
                continue;
            }

            if (preg_match('/^\p{Lu}/u', $word) === 1) {
                $capitalized++;
            }
        }

        return $capitalized;
    }
}
