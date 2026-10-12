<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Publishing\PieceKind;

/**
 * Headings, the Walkthrough link and the packaging metadata exist only on a blog post; a distribution piece has none.
 */
trait AppliesToBlogPosts
{
    public function appliesTo(PieceKind $kind): bool
    {
        return $kind === PieceKind::BlogPost;
    }
}
