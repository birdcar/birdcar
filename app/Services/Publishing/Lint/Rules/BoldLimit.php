<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

/** At most one bold span per H2 section; the text before the first H2 is its own section. */
class BoldLimit extends VoiceRule
{
    public function id(): string
    {
        return 'bold-limit';
    }

    public function check(LintTarget $target): array
    {
        $hits = [];
        $sectionSpans = 0;

        foreach ($target->blocks as $block) {
            if ($block['type'] === 'heading' && ($block['level'] ?? 2) <= 2) {
                $sectionSpans = 0;
            }

            foreach ($block['bold_spans'] as $span) {
                $text = trim($span['text']);
                if ($text === '') {
                    continue;
                }

                $sectionSpans++;
                if ($sectionSpans > 1) {
                    $hits[] = $this->blockHit($block['id'], $text, 'Remove the bold from "'.$text.'"; use bold at most once per H2 section, and only for real emphasis.');
                }
            }
        }

        return $hits;
    }
}
