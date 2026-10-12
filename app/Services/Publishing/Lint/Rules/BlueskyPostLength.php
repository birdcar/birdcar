<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Publishing\PieceKind;
use App\Services\Publishing\Lint\LintTarget;

/**
 * Bluesky caps a post at 300 characters, its i/n number included. Characters are counted as written; the canon bans emoji,
 * so this matches Bluesky's own count for the text a kit piece may hold.
 */
class BlueskyPostLength extends KitRule
{
    public const LIMIT = 300;

    public function id(): string
    {
        return 'bluesky-post-length';
    }

    protected function kinds(): array
    {
        return [PieceKind::BlueskyThread];
    }

    public function check(LintTarget $target): array
    {
        $hits = [];
        foreach ($target->postBlocks() as $index => $post) {
            $length = mb_strlen($post['text']);
            if ($length > self::LIMIT) {
                $hits[] = $this->blockHit($post['id'], $post['text'], 'Post '.($index + 1).' is '.$length.' characters with its number; it must be at most '.self::LIMIT.'. Shorten it.');
            }
        }

        return $hits;
    }
}
