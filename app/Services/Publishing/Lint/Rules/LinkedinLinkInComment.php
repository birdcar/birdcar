<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Publishing\PieceKind;
use App\Services\Publishing\Lint\LintTarget;

/** LinkedIn buries posts that link out, so the post text carries no link and the first comment carries the post's URL. */
class LinkedinLinkInComment extends KitRule
{
    public function id(): string
    {
        return 'linkedin-link-in-comment';
    }

    protected function kinds(): array
    {
        return [PieceKind::LinkedinPost];
    }

    public function check(LintTarget $target): array
    {
        $hits = [];
        $text = $target->roleBlock('text');
        foreach ($text === null ? [] : $this->urls($text) as $url) {
            $hits[] = $this->blockHit($text['id'], $url, 'Take the link "'.$url.'" out of the post text; on LinkedIn the link goes in the first comment.');
        }

        $comment = $target->roleBlock('first_comment');
        $canonical = $target->contextValue('canonical_url');
        if ($comment !== null && $canonical !== null) {
            $linked = array_filter($this->urls($comment), fn (string $url): bool => $this->samePage($url, $canonical));
            if ($linked === []) {
                $hits[] = $this->blockHit($comment['id'], '', 'Put the link to the post, '.$canonical.', in the first comment.');
            }
        }

        return $hits;
    }
}
