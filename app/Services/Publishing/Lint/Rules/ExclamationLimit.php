<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

/**
 * At most one exclamation point in a blog post's body, and none in a heading, the title, the description or a distribution
 * kit piece.
 */
class ExclamationLimit extends VoiceRule
{
    public function id(): string
    {
        return 'exclamation-limit';
    }

    public function check(LintTarget $target): array
    {
        $hits = [];

        foreach ($target->metadataText() as $field => $text) {
            foreach ($this->offsets($text) as $offset) {
                $excerpt = $this->excerptAround($text, $offset, '!');
                $hits[] = $this->metadataHit($field, $excerpt, 'Remove the exclamation point from "'.$excerpt.'"; titles and descriptions take none.');
            }
        }

        $allowed = $target->kind->isKit() ? 0 : 1;
        $bodyCount = 0;
        foreach ($target->blocks as $block) {
            foreach ($this->offsets($block['text']) as $offset) {
                $excerpt = $this->excerptAround($block['text'], $offset, '!');
                if ($block['type'] === 'heading') {
                    $hits[] = $this->blockHit($block['id'], $excerpt, 'Remove the exclamation point from the heading "'.$excerpt.'".');

                    continue;
                }

                $bodyCount++;
                if ($bodyCount > $allowed) {
                    $hits[] = $this->blockHit($block['id'], $excerpt, $allowed === 0
                        ? 'Remove the exclamation point from "'.$excerpt.'"; a distribution piece takes none.'
                        : 'Use at most one exclamation point in a post; rewrite "'.$excerpt.'".');
                }
            }
        }

        return $hits;
    }

    /** @return list<int> byte offsets of each exclamation point */
    private function offsets(string $text): array
    {
        if (! preg_match_all('/!/u', $text, $found, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        return array_map(static fn (array $match): int => $match[1], $found[0]);
    }
}
