<?php

namespace App\Services\Publishing\Lint\Rules;

class CorporateWe extends BannedPhrasesRule
{
    public function id(): string
    {
        return 'corporate-we';
    }

    protected function phrases(): array
    {
        return ['we', "we're", "we've", "we'll", 'us', 'our', 'ours', 'ourselves'];
    }

    /** Quoted speech and quoted sources may say "we": blockquotes and straight double-quoted spans are skipped. */
    protected function blockText(array $block): string
    {
        if ($block['in_blockquote']) {
            return '';
        }

        return preg_replace('/"[^"]*"/u', ' ', $block['text']) ?? $block['text'];
    }

    /** "US" in capitals is the country, not a pronoun. */
    protected function accepts(string $match): bool
    {
        return $match !== 'US';
    }

    protected function message(string $excerpt): string
    {
        return 'Write in the first-person singular instead of "'.$excerpt.'"; Birdcar is one person.';
    }
}
