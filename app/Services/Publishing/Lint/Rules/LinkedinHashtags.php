<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Publishing\PieceKind;
use App\Services\Publishing\Lint\LintTarget;

/**
 * A hashtag is a # at the start of a word followed by a letter, so a URL fragment or "#1" never counts. Hashtags belong in
 * one run at the very end of the post, at most three of them.
 */
class LinkedinHashtags extends KitRule
{
    public const LIMIT = 3;

    private const TAG = '(?<!\S)#\p{L}[\p{L}\p{N}_]*';

    public function id(): string
    {
        return 'linkedin-hashtags';
    }

    protected function kinds(): array
    {
        return [PieceKind::LinkedinPost];
    }

    public function check(LintTarget $target): array
    {
        $block = $target->roleBlock('text');
        if ($block === null || preg_match_all('/'.self::TAG.'/u', $block['text'], $found, PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }

        $tailStart = preg_match('/(?:'.self::TAG.'\s*)+$/u', $block['text'], $tail, PREG_OFFSET_CAPTURE) === 1 ? $tail[0][1] : strlen($block['text']);
        $hits = [];
        foreach ($found[0] as [$tag, $offset]) {
            if ($offset < $tailStart) {
                $hits[] = $this->blockHit($block['id'], $tag, 'Move the hashtag "'.$tag.'" to the end of the post, after the last sentence.');
            }
        }

        $tags = array_column($found[0], 0);
        if (count($tags) > self::LIMIT) {
            $hits[] = $this->blockHit($block['id'], implode(' ', $tags), 'The post has '.count($tags).' hashtags; keep at most '.self::LIMIT.' plain topic hashtags, at the end.');
        }

        return $hits;
    }
}
