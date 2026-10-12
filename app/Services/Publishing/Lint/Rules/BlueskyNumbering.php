<?php

namespace App\Services\Publishing\Lint\Rules;

use App\Models\Publishing\PieceKind;
use App\Services\Publishing\Lint\LintTarget;

/** Every post carries i/n at its start or end: its place in the thread over the number of posts in the thread. */
class BlueskyNumbering extends KitRule
{
    public function id(): string
    {
        return 'bluesky-numbering';
    }

    protected function kinds(): array
    {
        return [PieceKind::BlueskyThread];
    }

    public function check(LintTarget $target): array
    {
        $posts = $target->postBlocks();
        $count = count($posts);
        $hits = [];

        foreach ($posts as $index => $post) {
            $expected = ($index + 1).'/'.$count;
            $found = $this->numbering($post['text']);
            if ($found === $expected) {
                continue;
            }

            $hits[] = $this->blockHit($post['id'], $found ?? '', $found === null
                ? 'Number this post '.$expected.' at its start.'
                : 'Number this post '.$expected.' instead of '.$found.'.');
        }

        return $hits;
    }

    private function numbering(string $text): ?string
    {
        $text = $this->withoutUrls($text);
        if (preg_match('~^\s*\(?(\d+)\s*/\s*(\d+)\)?~', $text, $match) === 1 || preg_match('~\(?(\d+)\s*/\s*(\d+)\)?[\s.]*$~', $text, $match) === 1) {
            return $match[1].'/'.$match[2];
        }

        return null;
    }
}
