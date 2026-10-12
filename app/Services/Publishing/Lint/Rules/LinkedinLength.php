<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Publishing\PieceKind;
use App\Services\Publishing\Lint\LintTarget;

/**
 * LinkedIn shows about 210 characters before "more" and caps a post at 3,000, so the post stays under the cap and the
 * flagship's target phrase sits in the part a reader sees first. The phrase matches ignoring case and punctuation.
 */
class LinkedinLength extends KitRule
{
    public const LIMIT = 3000;

    public const PREVIEW = 210;

    public function id(): string
    {
        return 'linkedin-length';
    }

    protected function kinds(): array
    {
        return [PieceKind::LinkedinPost];
    }

    public function check(LintTarget $target): array
    {
        $block = $target->roleBlock('text');
        if ($block === null) {
            return [];
        }

        $hits = [];
        $length = mb_strlen($block['text']);
        if ($length > self::LIMIT) {
            $hits[] = $this->blockHit($block['id'], $block['text'], 'The LinkedIn text is '.number_format($length).' characters; it must be at most '.number_format(self::LIMIT).'. Cut it to one idea from the post.');
        }

        $query = $target->contextValue('target_query');
        if ($query !== null && ! str_contains($this->words(mb_substr($block['text'], 0, self::PREVIEW)), $this->words($query))) {
            $hits[] = $this->blockHit($block['id'], $query, 'Put the target phrase "'.$query.'" in the first '.self::PREVIEW.' characters, the part LinkedIn shows before "more".');
        }

        return $hits;
    }

    private function words(string $text): string
    {
        $letters = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text)) ?? $text;

        return ' '.trim($letters).' ';
    }
}
