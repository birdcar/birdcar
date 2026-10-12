<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Publishing\PieceKind;
use App\Services\Publishing\Lint\LintTarget;

class EmailSubjectLength extends KitRule
{
    public const LIMIT = 50;

    public function id(): string
    {
        return 'email-subject-length';
    }

    protected function kinds(): array
    {
        return [PieceKind::EmailBlurb];
    }

    public function check(LintTarget $target): array
    {
        $subject = $target->roleBlock('subject');
        $length = $subject === null ? 0 : mb_strlen($subject['text']);
        if ($subject === null || $length <= self::LIMIT) {
            return [];
        }

        return [$this->blockHit($subject['id'], $subject['text'], 'The subject line is '.$length.' characters; it must be at most '.self::LIMIT.'.')];
    }
}
