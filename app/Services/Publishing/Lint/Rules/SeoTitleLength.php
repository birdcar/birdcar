<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

class SeoTitleLength extends VoiceRule
{
    use MeasuresMetadataLength;

    public function id(): string
    {
        return 'seo-title-length';
    }

    public function check(LintTarget $target): array
    {
        return $this->lengthHits($target, 'seo_title', 'SEO title', 50, 60);
    }
}
