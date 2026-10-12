<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

class LabelHeadings extends VoiceRule
{
    /** @var list<string> */
    private const LABELS = [
        'introduction', 'conclusion', 'faq', 'frequently asked questions', 'pros', 'cons', 'verdict', 'overview', 'summary',
        'background', 'final thoughts', 'wrapping up', 'bottom line', 'key takeaways',
    ];

    public function id(): string
    {
        return 'label-headings';
    }

    public function check(LintTarget $target): array
    {
        $hits = [];

        foreach ($target->blocks as $block) {
            if ($block['type'] !== 'heading') {
                continue;
            }

            $heading = trim($block['text']);
            $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', rtrim($heading, " \t.:?!")) ?? $heading);
            if (in_array($normalized, self::LABELS, true)) {
                $hits[] = $this->blockHit($block['id'], $heading, 'Replace the label heading "'.$heading.'" with a claim or a question.');
            }
        }

        return $hits;
    }
}
