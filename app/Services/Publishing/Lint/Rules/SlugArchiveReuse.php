<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Article;
use App\Services\Publishing\Lint\LintTarget;

/** Compares against every other piece, archive imports included; the piece's own slug is never a reuse. */
class SlugArchiveReuse extends VoiceRule
{
    use AppliesToBlogPosts;

    public function id(): string
    {
        return 'slug-archive-reuse';
    }

    public function check(LintTarget $target): array
    {
        $slug = $target->metadataValue('slug');
        if ($slug === null) {
            return [];
        }

        $reused = Article::query()
            ->where('slug', trim($slug))
            ->when($target->articleId !== null, fn ($query) => $query->whereKeyNot($target->articleId))
            ->exists();

        return $reused ? [$this->metadataHit('slug', $slug, 'The slug "'.$slug.'" is already used by another piece; never reuse an archive or published slug.')] : [];
    }
}
