<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Publishing\PieceKind;
use App\Services\Publishing\Lint\LintHit;
use App\Services\Publishing\Lint\LintRule;

abstract class VoiceRule implements LintRule
{
    /** The canon's mechanical rules govern blog posts; distribution pieces get their own applicability. */
    public function appliesTo(PieceKind $kind): bool
    {
        return $kind === PieceKind::BlogPost;
    }

    protected function blockHit(string $blockId, string $excerpt, string $message): LintHit
    {
        return new LintHit($this->id(), $blockId, $excerpt, $message);
    }

    protected function metadataHit(string $field, string $excerpt, string $message): LintHit
    {
        return new LintHit($this->id(), null, $excerpt, $message, $field);
    }

    /**
     * A few words either side of a match, so the owner can find it and two matches in one block hash differently.
     */
    protected function excerptAround(string $text, int $byteOffset, string $match): string
    {
        $radius = 16;
        $start = mb_strlen(substr($text, 0, $byteOffset));
        $length = mb_strlen($match);
        $from = max(0, $start - $radius);
        $before = mb_substr($text, $from, $start - $from);
        $after = mb_substr($text, $start + $length, $radius);

        if ($from > 0) {
            $before = preg_replace('/^\S*\s/u', '', $before) ?? $before;
        }

        if (mb_strlen($text) > $start + $length + $radius) {
            $after = preg_replace('/\s\S*$/u', '', $after) ?? $after;
        }

        return trim(preg_replace('/\s+/u', ' ', $before.$match.$after) ?? $before.$match.$after);
    }
}
