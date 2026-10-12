<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Services\Publishing\Lint\LintTarget;

class SlugShape extends VoiceRule
{
    use AppliesToBlogPosts;

    public function id(): string
    {
        return 'slug-shape';
    }

    public function check(LintTarget $target): array
    {
        $slug = $target->metadataValue('slug');
        if ($slug === null || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+){3,5}$/', $slug) === 1) {
            return [];
        }

        return [$this->metadataHit('slug', $slug, 'The slug "'.$slug.'" must be four to six lowercase words joined by single hyphens.')];
    }
}
