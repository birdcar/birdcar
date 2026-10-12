<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintHit;
use App\Services\Publishing\Lint\LintTarget;

/**
 * Counts characters as written (Unicode code points, the way the packaging panel counts them), and reports the measured
 * length so the owner or a retried agent knows how far off it is.
 */
trait MeasuresMetadataLength
{
    /** @return list<LintHit> */
    protected function lengthHits(LintTarget $target, string $field, string $label, int $min, int $max): array
    {
        $value = $target->metadataValue($field);
        if ($value === null) {
            return [];
        }

        $length = mb_strlen($value);
        if ($length >= $min && $length <= $max) {
            return [];
        }

        return [$this->metadataHit($field, $value, $label.' is '.$length.' characters; it must be '.$min.'-'.$max.'.')];
    }
}
