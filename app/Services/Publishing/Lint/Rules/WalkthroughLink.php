<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

/**
 * A blog post has exactly one link to /walkthrough, in the closing section: after the final H2, or within the last
 * three blocks when the post has no H2.
 */
class WalkthroughLink extends VoiceRule
{
    private const HREF = '~^(?:https?://(?:www\.)?birdcar\.dev)?/walkthrough/?(?:[?#].*)?$~i';

    public function id(): string
    {
        return 'walkthrough-link';
    }

    public function check(LintTarget $target): array
    {
        $blocks = $target->blocks;
        if ($blocks === []) {
            return [];
        }

        $lastH2 = null;
        $links = [];
        foreach ($blocks as $index => $block) {
            if ($block['type'] === 'heading' && ($block['level'] ?? 2) === 2) {
                $lastH2 = $index;
            }

            foreach ($block['links'] as $link) {
                if (preg_match(self::HREF, trim($link['href'])) === 1) {
                    $links[] = ['index' => $index, 'block_id' => $block['id'], 'text' => trim($link['text'])];
                }
            }
        }

        if ($links === []) {
            $last = $blocks[array_key_last($blocks)];

            return [$this->blockHit($last['id'], '', 'Add exactly one link to /walkthrough, in the closing invitation paragraph.')];
        }

        $closingStart = $lastH2 !== null ? $lastH2 + 1 : max(0, count($blocks) - 3);
        $closing = array_values(array_filter($links, static fn (array $link): bool => $link['index'] >= $closingStart));
        $misplaced = array_values(array_filter($links, static fn (array $link): bool => $link['index'] < $closingStart));

        $hits = [];
        foreach ($misplaced as $position => $link) {
            $moveThisOne = $closing === [] && $position === array_key_last($misplaced);
            $hits[] = $this->blockHit($link['block_id'], $link['text'], $moveThisOne
                ? 'Move the /walkthrough link "'.$link['text'].'" into the closing invitation paragraph.'
                : 'Keep exactly one link to /walkthrough, in the closing invitation; remove the link "'.$link['text'].'".');
        }

        foreach (array_slice($closing, 0, -1) as $link) {
            $hits[] = $this->blockHit($link['block_id'], $link['text'], 'Keep exactly one link to /walkthrough, in the closing invitation; remove the link "'.$link['text'].'".');
        }

        return $hits;
    }
}
