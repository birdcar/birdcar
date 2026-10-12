<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

/** The title is level 1, so body headings start at H2 and never skip a level. */
class HeadingOrder extends VoiceRule
{
    public function id(): string
    {
        return 'heading-order';
    }

    public function check(LintTarget $target): array
    {
        $hits = [];
        $previous = 1;

        foreach ($target->blocks as $block) {
            if ($block['type'] !== 'heading') {
                continue;
            }

            $level = $block['level'] ?? 2;
            $heading = trim($block['text']);

            if ($level <= 1) {
                $hits[] = $this->blockHit($block['id'], $heading, 'Make "'.$heading.'" an H2: the title is metadata, so body headings start at H2.');
            } elseif ($level > $previous + 1) {
                $hits[] = $this->blockHit($block['id'], $heading, 'The H'.$level.' "'.$heading.'" skips a level; put it beneath an H'.($level - 1).'.');
            }

            $previous = $level;
        }

        return $hits;
    }
}
