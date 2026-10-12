<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

/**
 * A rule that matches one regular expression against block text and, for character rules, the title and description.
 *
 * @phpstan-import-type LintBlock from LintTarget
 */
abstract class PatternRule extends VoiceRule
{
    abstract protected function pattern(): string;

    abstract protected function message(string $excerpt): string;

    protected function checksMetadata(): bool
    {
        return false;
    }

    protected function excerpt(string $text, string $match, int $byteOffset): string
    {
        return $match;
    }

    /** @param LintBlock $block */
    protected function blockText(array $block): string
    {
        return $block['text'];
    }

    protected function accepts(string $match): bool
    {
        return true;
    }

    public function check(LintTarget $target): array
    {
        $hits = [];

        if ($this->checksMetadata()) {
            foreach ($target->metadataText() as $field => $text) {
                foreach ($this->matches($text) as [$match, $offset]) {
                    $excerpt = $this->excerpt($text, $match, $offset);
                    $hits[] = $this->metadataHit($field, $excerpt, $this->message($excerpt));
                }
            }
        }

        foreach ($target->blocks as $block) {
            $text = $this->blockText($block);
            foreach ($this->matches($text) as [$match, $offset]) {
                $excerpt = $this->excerpt($text, $match, $offset);
                $hits[] = $this->blockHit($block['id'], $excerpt, $this->message($excerpt));
            }
        }

        return $hits;
    }

    /** @return list<array{0: string, 1: int}> */
    private function matches(string $text): array
    {
        if (trim($text) === '' || ! preg_match_all($this->pattern(), $text, $found, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $matches = [];
        foreach ($found[0] as [$match, $offset]) {
            if ($this->accepts($match)) {
                $matches[] = [$match, $offset];
            }
        }

        return $matches;
    }
}
