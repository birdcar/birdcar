<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

/**
 * Flags Title Case headings: two or more capitalized words after the first, not counting proper nouns on the allowlist,
 * "I" and its contractions, all-caps acronyms or words inside straight quotes.
 */
class SentenceCaseHeadings extends VoiceRule
{
    use AppliesToBlogPosts;
    use CountsCapitalizedWords;

    public function id(): string
    {
        return 'sentence-case-headings';
    }

    public function check(LintTarget $target): array
    {
        $hits = [];

        foreach ($target->blocks as $block) {
            if ($block['type'] !== 'heading' || ! $this->isTitleCase($block['text'])) {
                continue;
            }

            $heading = trim($block['text']);
            $hits[] = $this->blockHit($block['id'], $heading, 'Write the heading "'.$heading.'" in sentence case: capitalize the first word and proper nouns only.');
        }

        return $hits;
    }
}
