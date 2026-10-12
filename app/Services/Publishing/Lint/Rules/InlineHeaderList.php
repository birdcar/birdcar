<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

/** A paragraph or list item that opens with a bold label and a colon, with the colon inside the bold or right after it. */
class InlineHeaderList extends VoiceRule
{
    public function id(): string
    {
        return 'inline-header-list';
    }

    public function check(LintTarget $target): array
    {
        $hits = [];

        foreach ($target->blocks as $block) {
            if ($block['type'] !== 'paragraph' || $block['bold_spans'] === []) {
                continue;
            }

            $leading = ltrim($block['text']);
            if (preg_match('/^([^:]{1,40}):/u', $leading, $match) !== 1) {
                continue;
            }

            $span = $block['bold_spans'][0];
            $spanText = ltrim($span['text']);
            $spanStart = $span['offset'] + mb_strlen($span['text']) - mb_strlen($spanText);
            $label = trim($match[1]);
            $bold = trim($spanText);

            if ($spanStart !== mb_strlen($block['text']) - mb_strlen($leading) || $label === '') {
                continue;
            }

            if (rtrim($bold, ': ') === $label || str_starts_with($bold, $label.':')) {
                $hits[] = $this->blockHit($block['id'], $label.':', 'Rewrite "'.$label.':" so the passage does not open with a bold label and a colon.');
            }
        }

        return $hits;
    }
}
