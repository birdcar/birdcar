<?php

namespace App\Services\Publishing\Lint;

use Illuminate\Container\Attributes\Tag;

/**
 * Runs every registered mechanical voice rule that applies to a piece. Rules are registered with the
 * publishing.lint-rules container tag, and each id matches one [rule:<id>] tag in the voice canon.
 */
final class VoiceLinter
{
    public const TAG = 'publishing.lint-rules';

    /** Metadata fields in the order the owner reads them in the packaging panel. */
    private const METADATA_ORDER = ['title', 'seo_title', 'description', 'slug'];

    /** @param iterable<LintRule> $rules */
    public function __construct(#[Tag(self::TAG)] private readonly iterable $rules) {}

    /**
     * Hits in reading order: the packaging metadata first, then the body top to bottom. Overrides and findings key on
     * the hit hash, so a repeat of the same rule, location and excerpt is numbered as its own occurrence.
     *
     * @return list<LintHit>
     */
    public function lint(LintTarget $target): array
    {
        $positions = [];
        foreach (self::METADATA_ORDER as $index => $field) {
            $positions['$.metadata.'.$field] = $index;
        }
        foreach ($target->blocks as $index => $block) {
            $positions[$block['id']] ??= $index + count(self::METADATA_ORDER);
        }

        $hits = [];
        $occurrences = [];
        foreach ($this->rules as $rule) {
            if (! $rule->appliesTo($target->kind)) {
                continue;
            }

            foreach ($rule->check($target) as $hit) {
                $hash = $hit->inputHash();
                $occurrences[$hash] = ($occurrences[$hash] ?? 0) + 1;
                $hits[] = $occurrences[$hash] === 1 ? $hit : $hit->withOccurrence($occurrences[$hash]);
            }
        }

        usort($hits, static fn (LintHit $left, LintHit $right): int => ($positions[$left->location()] ?? PHP_INT_MAX) <=> ($positions[$right->location()] ?? PHP_INT_MAX));

        return $hits;
    }

    /** @return list<string> sorted unique ids of every registered rule */
    public function ruleIds(): array
    {
        $ids = [];
        foreach ($this->rules as $rule) {
            $ids[] = $rule->id();
        }

        $ids = array_values(array_unique($ids));
        sort($ids, SORT_STRING);

        return $ids;
    }
}
