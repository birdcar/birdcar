<?php

namespace App\Services\Publishing\Lint;

use App\Models\Publishing\PieceKind;

/**
 * One deterministic mechanical voice rule. Its id matches exactly one [rule:<id>] tag in the voice canon.
 */
interface LintRule
{
    public function id(): string;

    public function appliesTo(PieceKind $kind): bool;

    /** @return list<LintHit> */
    public function check(LintTarget $target): array;
}
