<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

class MetaDescriptionLength extends VoiceRule
{
    use MeasuresMetadataLength;

    public function id(): string
    {
        return 'meta-description-length';
    }

    public function check(LintTarget $target): array
    {
        return $this->lengthHits($target, 'description', 'Meta description', 150, 160);
    }
}
