<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Publishing\PieceKind;
use App\Services\Publishing\Lint\LintTarget;

/**
 * A kit piece sends the reader to the post, and the post carries the Walkthrough invitation. A link mark to /walkthrough
 * and a written-out /walkthrough path both count; the word "Walkthrough" alone does not.
 */
class KitNoWalkthroughCta extends KitRule
{
    private const HREF = '~^(?:https?://(?:www\.)?birdcar\.dev)?/walkthrough/?(?:[?#].*)?$~i';

    private const TEXT = '~(?:https?://)?(?:www\.)?birdcar\.dev/walkthrough\b\S*|(?<![\w./-])/walkthrough\b\S*~i';

    public function id(): string
    {
        return 'kit-no-walkthrough-cta';
    }

    protected function kinds(): array
    {
        return [PieceKind::LinkedinPost, PieceKind::BlueskyThread, PieceKind::EmailBlurb];
    }

    public function check(LintTarget $target): array
    {
        $hits = [];
        foreach ($target->blocks as $block) {
            $written = preg_match_all(self::TEXT, $block['text'], $matches) > 0 ? $matches[0] : [];
            foreach ($written as $path) {
                $hits[] = $this->blockHit($block['id'], $path, $this->message($path));
            }

            foreach ($block['links'] as $link) {
                if (preg_match(self::HREF, trim($link['href'])) === 1 && preg_match(self::TEXT, $link['text']) !== 1) {
                    $hits[] = $this->blockHit($block['id'], $link['text'], $this->message($link['text']));
                }
            }
        }

        return $hits;
    }

    private function message(string $excerpt): string
    {
        return 'Remove the link to /walkthrough ("'.trim($excerpt).'"); a kit piece links to the post, and the post carries the Walkthrough invitation.';
    }
}
