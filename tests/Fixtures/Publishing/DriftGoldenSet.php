<?php

namespace Tests\Fixtures\Publishing;

use App\Models\Publishing\PieceKind;
use App\Services\Publishing\Lint\LintHit;
use App\Services\Publishing\Lint\LintTarget;
use App\Services\Publishing\Lint\VoiceLinter;
use JsonException;
use RuntimeException;

/**
 * The golden set for the voice drift trial: real passages from the exemplar canon and the site copy, and one slopped
 * rewrite of each that records its injected tells by block. DriftFixtureTest checks the set for free and the paid
 * VoiceDriftTrialTest reviews it, and both build and lint each passage here, so they see the same manuscript.
 *
 * @phpstan-type RealPassage array{id: string, source: string, blocks: list<string>}
 * @phpstan-type InjectedTell array{block_index: int, tell: string, mechanical: bool}
 * @phpstan-type SloppedPassage array{id: string, real_id: string, blocks: list<string>, injected: list<InjectedTell>}
 */
final class DriftGoldenSet
{
    /** Tells no lint rule can see. A mechanical tell is named after the lint rule id it breaks. */
    public const NON_MECHANICAL_TELLS = ['flattened_rhythm', 'no_turn', 'synonym_cycling', 'generic_conclusion', 'neutral_reporting'];

    /**
     * A passage is an excerpt, and an excerpt cannot hold the closing invitation a whole post must end with, so the rule
     * that requires it is never checked on one. Adding the same invitation to every passage instead would put one shared
     * paragraph in front of the reviewer twenty times.
     */
    public const EXCERPT_EXEMPT_RULES = ['walkthrough-link'];

    /** @return array{real: list<RealPassage>, slopped: list<SloppedPassage>} */
    public static function load(): array
    {
        try {
            $decoded = json_decode((string) file_get_contents(__DIR__.'/drift/passages.json'), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The drift golden set is not valid JSON: '.$exception->getMessage(), previous: $exception);
        }

        if (! is_array($decoded) || ! is_array($decoded['real'] ?? null) || ! is_array($decoded['slopped'] ?? null)) {
            throw new RuntimeException('The drift golden set must hold a real list and a slopped list.');
        }

        $real = [];
        foreach ($decoded['real'] as $passage) {
            $real[] = [
                'id' => self::string($passage, 'id'),
                'source' => self::string($passage, 'source'),
                'blocks' => self::blocks($passage),
            ];
        }

        $slopped = [];
        foreach ($decoded['slopped'] as $passage) {
            $injected = [];
            foreach (is_array($passage) && is_array($passage['injected'] ?? null) ? $passage['injected'] : [] as $tell) {
                if (! is_array($tell) || ! is_int($tell['block_index'] ?? null) || ! is_bool($tell['mechanical'] ?? null)) {
                    throw new RuntimeException('Every injected tell needs an integer block_index and a boolean mechanical flag.');
                }
                $injected[] = ['block_index' => $tell['block_index'], 'tell' => self::string($tell, 'tell'), 'mechanical' => $tell['mechanical']];
            }

            $slopped[] = [
                'id' => self::string($passage, 'id'),
                'real_id' => self::string($passage, 'real_id'),
                'blocks' => self::blocks($passage),
                'injected' => $injected,
            ];
        }

        return ['real' => $real, 'slopped' => $slopped];
    }

    /** A valid, stable block id, so an injected block index always maps to the same block id. */
    public static function blockId(string $passageId, int $index): string
    {
        return 'blk_'.substr(hash('sha256', 'drift:'.$passageId.':'.$index), 0, 16);
    }

    /**
     * One paragraph per block, with no title or heading: the passage is all the reviewer sees.
     *
     * @param  list<string>  $blocks
     * @return array{version: int, type: string, content: list<array<string, mixed>>}
     */
    public static function document(string $passageId, array $blocks): array
    {
        $content = [];
        foreach ($blocks as $index => $text) {
            $content[] = ['type' => 'paragraph', 'attrs' => ['id' => self::blockId($passageId, $index), 'protected' => false], 'content' => [['type' => 'text', 'text' => $text]]];
        }

        return ['version' => 1, 'type' => 'doc', 'content' => $content];
    }

    /**
     * Lints a passage document as a blog post with no metadata, without the rules an excerpt is exempt from.
     *
     * @param  array<array-key, mixed>  $document
     * @return list<LintHit>
     */
    public static function lint(array $document): array
    {
        $hits = app(VoiceLinter::class)->lint(LintTarget::fromDocument($document, [], PieceKind::BlogPost));

        return array_values(array_filter($hits, fn (LintHit $hit): bool => ! in_array($hit->ruleId, self::EXCERPT_EXEMPT_RULES, true)));
    }

    private static function string(mixed $item, string $key): string
    {
        $value = is_array($item) ? ($item[$key] ?? null) : null;
        if (! is_string($value)) {
            throw new RuntimeException("Every drift golden set entry needs a string {$key}.");
        }

        return $value;
    }

    /** @return list<string> */
    private static function blocks(mixed $passage): array
    {
        $blocks = is_array($passage) ? ($passage['blocks'] ?? null) : null;
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            throw new RuntimeException('Every drift golden set passage needs a list of blocks.');
        }

        return array_map(function (mixed $block): string {
            if (! is_string($block)) {
                throw new RuntimeException('Every drift golden set block is plain text.');
            }

            return $block;
        }, $blocks);
    }
}
