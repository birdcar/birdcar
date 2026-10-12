<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

/**
 * Flags Title Case headings: two or more capitalized words after the first, not counting proper nouns on the allowlist,
 * "I" and its contractions, all-caps acronyms or words inside straight quotes.
 */
class SentenceCaseHeadings extends VoiceRule
{
    /** @var list<string> */
    public const PROPER_NOUNS = [
        'Craft & Communicate', 'Walkthrough', 'Birdcar', 'GitHub', 'Heroku', 'Zapier', 'Twilio', 'Salesforce', 'LinkedIn',
        'Bluesky', 'Google', 'Nick',
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
        'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December',
    ];

    public function id(): string
    {
        return 'sentence-case-headings';
    }

    public function check(LintTarget $target): array
    {
        $hits = [];

        foreach ($target->blocks as $block) {
            if ($block['type'] !== 'heading' || $this->capitalizedWords($block['text']) < 2) {
                continue;
            }

            $heading = trim($block['text']);
            $hits[] = $this->blockHit($block['id'], $heading, 'Write the heading "'.$heading.'" in sentence case: capitalize the first word and proper nouns only.');
        }

        return $hits;
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
