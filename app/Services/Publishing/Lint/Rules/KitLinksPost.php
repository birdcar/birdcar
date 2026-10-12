<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Publishing\PieceKind;
use App\Services\Publishing\Lint\LintTarget;

/**
 * Each kit piece links to the flagship post exactly once, in one place: the LinkedIn first comment, the last Bluesky post or
 * the email body. A link anywhere else is a hit on that passage, and a missing link is a hit on the place it belongs.
 *
 * @phpstan-import-type LintBlock from LintTarget
 */
class KitLinksPost extends KitRule
{
    public function id(): string
    {
        return 'kit-links-post';
    }

    protected function kinds(): array
    {
        return [PieceKind::LinkedinPost, PieceKind::BlueskyThread, PieceKind::EmailBlurb];
    }

    public function check(LintTarget $target): array
    {
        $canonical = $target->contextValue('canonical_url');
        if ($canonical === null) {
            return [];
        }

        $home = $this->home($target);
        $label = match ($target->kind) {
            PieceKind::LinkedinPost => 'first comment',
            PieceKind::BlueskyThread => 'last post',
            default => 'body',
        };
        if ($home === null) {
            return [];
        }

        $hits = [];
        $atHome = 0;
        foreach ($target->blocks as $block) {
            foreach ($this->urls($block) as $url) {
                if (! $this->samePage($url, $canonical)) {
                    continue;
                }

                if ($block['id'] !== $home['id']) {
                    $hits[] = $this->blockHit($block['id'], $url, 'Remove the link to the post here; it belongs once, in the '.$label.'.');
                } elseif (++$atHome === 2) {
                    $hits[] = $this->blockHit($block['id'], $url, 'Link the post only once in the '.$label.'; remove the repeat.');
                }
            }
        }

        if ($atHome === 0) {
            $hits[] = $this->blockHit($home['id'], '', 'Link the post once in the '.$label.': '.$canonical);
        }

        return $hits;
    }

    /** @return LintBlock|null the passage the link belongs in */
    private function home(LintTarget $target): ?array
    {
        if ($target->kind === PieceKind::BlueskyThread) {
            $posts = $target->postBlocks();

            return $posts === [] ? null : $posts[array_key_last($posts)];
        }

        return $target->roleBlock($target->kind === PieceKind::LinkedinPost ? 'first_comment' : 'body');
    }
}
