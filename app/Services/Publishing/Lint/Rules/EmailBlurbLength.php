<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Publishing\PieceKind;
use App\Services\Publishing\Lint\LintTarget;

/**
 * A sentence ends at ., ? or ! followed by a space or the end of the text. URLs are set aside first, so their dots never
 * end a sentence, and a decimal such as 3.5 never does either. A closing link with no full stop still counts as a sentence.
 */
class EmailBlurbLength extends KitRule
{
    public const MIN = 2;

    public const MAX = 4;

    public function id(): string
    {
        return 'email-blurb-length';
    }

    protected function kinds(): array
    {
        return [PieceKind::EmailBlurb];
    }

    public function check(LintTarget $target): array
    {
        $body = $target->roleBlock('body');
        if ($body === null) {
            return [];
        }

        $sentences = $this->sentences($body['text']);
        if ($sentences >= self::MIN && $sentences <= self::MAX) {
            return [];
        }

        return [$this->blockHit($body['id'], $body['text'], 'The email body has '.$sentences.' '.($sentences === 1 ? 'sentence' : 'sentences').'; it must have two to four.')];
    }

    private function sentences(string $text): int
    {
        $pieces = preg_split('/(?<=[.?!])\s+/u', trim($this->withoutUrls($text))) ?: [];

        return count(array_filter($pieces, static fn (string $piece): bool => trim($piece) !== ''));
    }
}
