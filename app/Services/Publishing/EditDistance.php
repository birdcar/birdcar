<?php

namespace App\Services\Publishing;

/**
 * Word-level Levenshtein distance between two Tiptap documents: how many words were inserted, deleted or replaced.
 * Words are lowercase runs of letters and digits (an inner straight or curly apostrophe keeps "it's" one word), read
 * from every text node plus note and callout titles and chart and diagram captions, with block boundaries and hard
 * breaks as spaces. Punctuation, marks and formatting are not words, so changing only those measures 0. A malformed
 * document reads as no words.
 */
final class EditDistance
{
    /**
     * @param  array<array-key, mixed>  $baselineDocument
     * @param  array<array-key, mixed>  $revisedDocument
     */
    public function between(array $baselineDocument, array $revisedDocument): int
    {
        return $this->distance($this->words($baselineDocument), $this->words($revisedDocument));
    }

    /**
     * @param  array<array-key, mixed>  $document
     * @return list<string>
     */
    private function words(array $document): array
    {
        $text = mb_strtolower(str_replace('’', "'", $this->text($document)));

        return preg_match_all("/[\p{L}\p{N}]+(?:'[\p{L}\p{N}]+)*/u", $text, $matches) === false ? [] : $matches[0];
    }

    /**
     * Inline text joins without a separator, so a word split across marks stays one word; each block is padded with
     * spaces so words in neighbouring blocks never fuse.
     *
     * @param  array<array-key, mixed>  $node
     */
    private function text(array $node): string
    {
        $type = $node['type'] ?? null;
        if ($type === 'text') {
            return is_string($node['text'] ?? null) ? $node['text'] : '';
        }
        if ($type === 'hardBreak') {
            return ' ';
        }

        $text = ' ';
        foreach (['title', 'caption'] as $attribute) {
            $value = is_array($node['attrs'] ?? null) ? ($node['attrs'][$attribute] ?? null) : null;
            if (is_string($value)) {
                $text .= $value.' ';
            }
        }

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $text .= $this->text($child);
            }
        }

        return $text.' ';
    }

    /**
     * The shared opening and closing words cost nothing, so they are trimmed first; the rest runs a two-row dynamic
     * program over the shorter side, which keeps memory to two rows of word ids even for a full rewrite.
     *
     * @param  list<string>  $from
     * @param  list<string>  $to
     */
    private function distance(array $from, array $to): int
    {
        $start = 0;
        $fromEnd = count($from);
        $toEnd = count($to);
        while ($start < $fromEnd && $start < $toEnd && $from[$start] === $to[$start]) {
            $start++;
        }
        while ($fromEnd > $start && $toEnd > $start && $from[$fromEnd - 1] === $to[$toEnd - 1]) {
            $fromEnd--;
            $toEnd--;
        }

        $rows = array_slice($from, $start, $fromEnd - $start);
        $columns = array_slice($to, $start, $toEnd - $start);
        if (count($rows) < count($columns)) {
            [$rows, $columns] = [$columns, $rows];
        }
        if ($columns === []) {
            return count($rows);
        }

        // Integer ids compare faster than strings in the inner loop.
        $ids = [];
        $rowIds = array_map(function (string $word) use (&$ids): int {
            return $ids[$word] ??= count($ids);
        }, $rows);
        $columnIds = array_map(function (string $word) use (&$ids): int {
            return $ids[$word] ??= count($ids);
        }, $columns);
        $width = count($columnIds);

        $previous = range(0, $width);
        foreach ($rowIds as $row => $rowId) {
            $current = [$row + 1];
            $left = $row + 1;
            $diagonal = $previous[0];
            for ($column = 0; $column < $width; $column++) {
                $up = $previous[$column + 1];
                $best = $diagonal + ($rowId === $columnIds[$column] ? 0 : 1);
                if ($up + 1 < $best) {
                    $best = $up + 1;
                }
                if ($left + 1 < $best) {
                    $best = $left + 1;
                }
                $current[] = $best;
                $diagonal = $up;
                $left = $best;
            }
            $previous = $current;
        }

        return $previous[$width];
    }
}
