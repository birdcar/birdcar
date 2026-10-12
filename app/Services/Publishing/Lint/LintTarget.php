<?php

namespace App\Services\Publishing\Lint;

use App\Models\ArticleRevision;
use App\Models\Publishing\PieceKind;

/**
 * The text a voice rule reads: one block per paragraph, heading and titled or captioned node in document order, plus
 * the metadata a character rule checks. Code-marked text and code blocks are never part of a block's text.
 *
 * @phpstan-type LintBlock array{id: string, type: string, level: int|null, text: string, bold_spans: list<array{text: string, offset: int}>, links: list<array{href: string, text: string}>, in_blockquote: bool, protected: bool}
 */
final readonly class LintTarget
{
    /** Metadata a character rule reads. Dates, tags, slugs and the greatest-hits tag carry no public prose. */
    public const METADATA_FIELDS = ['title', 'seo_title', 'description'];

    /**
     * @param  array<array-key, mixed>  $metadata
     * @param  list<LintBlock>  $blocks
     * @param  int|null  $articleId  the piece being linted, so a slug rule can tell its own slug from another piece's
     */
    public function __construct(public PieceKind $kind, public array $metadata, public array $blocks, public ?int $articleId = null) {}

    public static function fromRevision(ArticleRevision $revision, PieceKind $kind): self
    {
        $document = $revision->getAttribute('document');
        $metadata = $revision->getAttribute('metadata');
        $articleId = $revision->getAttribute('article_id');

        return self::fromDocument(is_array($document) ? $document : [], is_array($metadata) ? $metadata : [], $kind, is_numeric($articleId) ? (int) $articleId : null);
    }

    /**
     * @param  array<array-key, mixed>  $document
     * @param  array<array-key, mixed>  $metadata
     */
    public static function fromDocument(array $document, array $metadata, PieceKind $kind, ?int $articleId = null): self
    {
        $blocks = [];
        self::collect(is_array($document['content'] ?? null) ? $document['content'] : [], '$.content', false, false, $blocks);

        return new self($kind, $metadata, $blocks, $articleId);
    }

    /** One metadata field as written, or null when it is missing or blank; presence is the release checklist's job. */
    public function metadataValue(string $field): ?string
    {
        $value = $this->metadata[$field] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /** @return array<string, string> non-empty metadata text keyed by field */
    public function metadataText(): array
    {
        $fields = [];
        foreach (self::METADATA_FIELDS as $field) {
            $value = $this->metadata[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $fields[$field] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param  array<array-key, mixed>  $nodes
     * @param  list<LintBlock>  $blocks
     */
    private static function collect(array $nodes, string $path, bool $inBlockquote, bool $protected, array &$blocks): void
    {
        foreach (array_values($nodes) as $index => $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = is_string($node['type'] ?? null) ? $node['type'] : '';
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $nodePath = $path.'['.$index.']';
            $id = is_string($attrs['id'] ?? null) ? $attrs['id'] : $nodePath;
            // A child of a protected blockquote, list, note or callout is protected too: patching it changes the ancestor.
            $nodeProtected = $protected || ($attrs['protected'] ?? false) === true;
            $nodeInBlockquote = $inBlockquote || $type === 'blockquote';

            if ($type === 'paragraph' || $type === 'heading') {
                $level = $type === 'heading' ? (is_int($attrs['level'] ?? null) ? $attrs['level'] : 2) : null;
                $blocks[] = self::textBlock($id, $type, $level, $node, $nodeInBlockquote, $nodeProtected);

                continue;
            }

            if ($type === 'codeBlock') {
                continue;
            }

            $attributeText = match ($type) {
                'note', 'callout' => $attrs['title'] ?? null,
                'chart', 'diagram' => $attrs['caption'] ?? null,
                default => null,
            };
            if (is_string($attributeText) && trim($attributeText) !== '') {
                $blocks[] = [
                    'id' => $id,
                    'type' => $type,
                    'level' => null,
                    'text' => $attributeText,
                    'bold_spans' => [],
                    'links' => [],
                    'in_blockquote' => $nodeInBlockquote,
                    'protected' => $nodeProtected,
                ];
            }

            if (is_array($node['content'] ?? null)) {
                self::collect($node['content'], $nodePath.'.content', $nodeInBlockquote, $nodeProtected, $blocks);
            }
        }
    }

    /**
     * Bold and link marks split across text nodes (bold plus italic, for one) are merged back into one span or link.
     *
     * @param  array<array-key, mixed>  $node
     * @return LintBlock
     */
    private static function textBlock(string $id, string $type, ?int $level, array $node, bool $inBlockquote, bool $protected): array
    {
        $children = is_array($node['content'] ?? null)
            ? $node['content']
            : (is_string($node['text'] ?? null) ? [['type' => 'text', 'text' => $node['text']]] : []);
        $text = '';
        $boldSpans = [];
        $links = [];
        $boldText = null;
        $boldOffset = 0;
        $openLink = null;

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            if (($child['type'] ?? null) !== 'text' || ! is_string($child['text'] ?? null)) {
                if ($boldText !== null) {
                    $boldSpans[] = ['text' => $boldText, 'offset' => $boldOffset];
                    $boldText = null;
                }
                $text .= ' ';
                $openLink = null;

                continue;
            }

            $raw = $child['text'];
            $markTypes = [];
            $href = null;
            foreach (is_array($child['marks'] ?? null) ? $child['marks'] : [] as $mark) {
                if (! is_array($mark) || ! is_string($mark['type'] ?? null)) {
                    continue;
                }
                $markTypes[] = $mark['type'];
                if ($mark['type'] === 'link' && is_array($mark['attrs'] ?? null) && is_string($mark['attrs']['href'] ?? null)) {
                    $href = $mark['attrs']['href'];
                }
            }

            $isCode = in_array('code', $markTypes, true);
            // A skipped code span becomes a space so the words on either side never fuse.
            $visible = $isCode ? ' ' : $raw;

            if (! $isCode && in_array('bold', $markTypes, true)) {
                if ($boldText === null) {
                    $boldText = '';
                    $boldOffset = mb_strlen($text);
                }
                $boldText .= $visible;
            } elseif ($boldText !== null) {
                $boldSpans[] = ['text' => $boldText, 'offset' => $boldOffset];
                $boldText = null;
            }

            if ($href !== null) {
                if ($openLink !== null && $links[$openLink]['href'] === $href) {
                    $links[$openLink]['text'] .= $raw;
                } else {
                    $links[] = ['href' => $href, 'text' => $raw];
                    $openLink = array_key_last($links);
                }
            } else {
                $openLink = null;
            }

            $text .= $visible;
        }

        if ($boldText !== null) {
            $boldSpans[] = ['text' => $boldText, 'offset' => $boldOffset];
        }

        return [
            'id' => $id,
            'type' => $type,
            'level' => $level,
            'text' => $text,
            'bold_spans' => $boldSpans,
            'links' => $links,
            'in_blockquote' => $inBlockquote,
            'protected' => $protected,
        ];
    }
}
