<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

/** Checks the first sentence of the post and the first sentence after every heading. */
class StockOpeners extends VoiceRule
{
    private const PATTERN = '/^\s*(?:In this post|In this article|In today[\'’]s|Let[\'’]s explore|Let[\'’]s dive in|Now that we[\'’]ve covered|In conclusion|Ultimately,)(?![\p{L}\p{N}])/iu';

    public function id(): string
    {
        return 'stock-openers';
    }

    public function check(LintTarget $target): array
    {
        $hits = [];
        $opening = true;

        foreach ($target->blocks as $block) {
            if ($block['type'] === 'heading') {
                $opening = true;

                continue;
            }

            if ($block['type'] !== 'paragraph') {
                continue;
            }

            if ($opening && preg_match(self::PATTERN, $block['text'], $match) === 1) {
                $opener = trim($match[0]);
                $hits[] = $this->blockHit($block['id'], $opener, 'Open with the point instead of the stock opener "'.$opener.'".');
            }

            $opening = false;
        }

        return $hits;
    }
}
