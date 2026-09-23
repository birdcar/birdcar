<?php

namespace App\Services\Publishing;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;

class ArticleDocument
{
    private const MAX_BYTES = 1_048_576;

    private const MAX_DEPTH = 32;

    private const MAX_NODES = 10_000;

    private const MAX_SVG_BYTES = 32_768;

    private const MAX_SVG_DEPTH = 16;

    private const MAX_SVG_ELEMENTS = 200;

    /** @var array<string, true> */
    private const BLOCK_NODES = [
        'paragraph' => true,
        'heading' => true,
        'blockquote' => true,
        'bulletList' => true,
        'orderedList' => true,
        'listItem' => true,
        'codeBlock' => true,
        'horizontalRule' => true,
        'note' => true,
        'callout' => true,
        'chart' => true,
        'diagram' => true,
    ];

    /** @var array<string, true> */
    private const SVG_ELEMENTS = [
        'svg' => true,
        'g' => true,
        'path' => true,
        'line' => true,
        'polyline' => true,
        'polygon' => true,
        'rect' => true,
        'circle' => true,
        'ellipse' => true,
        'text' => true,
        'tspan' => true,
        'title' => true,
        'desc' => true,
    ];

    /** @var array<string, true> */
    private const SVG_PRESENTATION_ATTRS = [
        'fill' => true,
        'stroke' => true,
        'stroke-width' => true,
        'stroke-linecap' => true,
        'stroke-linejoin' => true,
        'opacity' => true,
        'fill-opacity' => true,
        'stroke-opacity' => true,
        'class' => true,
        'aria-hidden' => true,
        'role' => true,
    ];

    /** @var array<string, true> */
    private const SVG_GEOMETRY_ATTRS = [
        'viewBox' => true,
        'width' => true,
        'height' => true,
        'x' => true,
        'y' => true,
        'x1' => true,
        'y1' => true,
        'x2' => true,
        'y2' => true,
        'cx' => true,
        'cy' => true,
        'r' => true,
        'rx' => true,
        'ry' => true,
        'd' => true,
        'points' => true,
        'transform' => true,
    ];

    public function __construct(private readonly PublishingFingerprint $fingerprint) {}

    /**
     * @param  array<string, mixed>  $document
     */
    public function validate(array $document): void
    {
        $this->canonicalize($document);
    }

    /**
     * Backwards-compatible validation for phase-one saves that predate block ids.
     *
     * @param  array<string, mixed>  $document
     */
    public function validateForSave(array $document): void
    {
        $this->canonicalizeDocument($document, false);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function canonicalize(array $document): array
    {
        return $this->canonicalizeDocument($document, true);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function renderHtml(array $document): string
    {
        $canonical = $this->canonicalizeDocument($document, false);
        /** @var list<array<string, mixed>> $content */
        $content = $canonical['content'];

        return implode('', array_map(fn (array $node): string => $this->renderBlock($node), $content));
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function fingerprint(array $document): string
    {
        return $this->fingerprint->hash($this->canonicalize($document));
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function canonicalizeDocument(array $document, bool $requireIds): array
    {
        $errors = [];
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (strlen($encoded) > self::MAX_BYTES) {
            $errors[] = ['path' => '$', 'message' => 'document exceeds 1 MiB'];
        }

        if (($document['version'] ?? null) !== 1) {
            $errors[] = ['path' => '$.version', 'message' => 'must be 1'];
        }

        if (($document['type'] ?? null) !== 'doc') {
            $errors[] = ['path' => '$.type', 'message' => 'must be doc'];
        }

        if (! is_array($document['content'] ?? null) || ! array_is_list($document['content'])) {
            $errors[] = ['path' => '$.content', 'message' => 'must be a list'];
            throw new ArticleDocumentValidationException($errors);
        }

        /** @var list<mixed> $content */
        $content = $document['content'];
        $ids = [];
        $nodeCount = 0;
        $canonicalContent = [];

        foreach ($content as $index => $node) {
            if (! is_array($node)) {
                $errors[] = ['path' => '$.content['.$index.']', 'message' => 'must be an object'];

                continue;
            }

            /** @var array<string, mixed> $node */
            $canonicalNode = $this->canonicalizeNode($node, '$.content['.$index.']', 1, $nodeCount, $ids, $errors, $requireIds);

            if (! in_array($canonicalNode['type'] ?? null, ['paragraph', 'heading', 'blockquote', 'bulletList', 'orderedList', 'codeBlock', 'horizontalRule', 'note', 'callout', 'chart', 'diagram'], true)) {
                $errors[] = ['path' => '$.content['.$index.']', 'message' => 'must be a top-level block node'];
            }

            $canonicalContent[] = $canonicalNode;
        }

        if ($nodeCount > self::MAX_NODES) {
            $errors[] = ['path' => '$', 'message' => 'document exceeds '.self::MAX_NODES.' nodes'];
        }

        if ($errors !== []) {
            throw new ArticleDocumentValidationException($errors);
        }

        return [
            'version' => 1,
            'type' => 'doc',
            'content' => $canonicalContent,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, true>  $ids
     * @param  list<array{path: string, message: string}>  $errors
     * @return array<string, mixed>
     */
    private function canonicalizeNode(array $node, string $path, int $depth, int &$nodeCount, array &$ids, array &$errors, bool $requireIds): array
    {
        $nodeCount++;

        if ($depth > self::MAX_DEPTH) {
            $errors[] = ['path' => $path, 'message' => 'is nested too deeply'];
        }

        $type = $node['type'] ?? null;

        if (! is_string($type)) {
            $errors[] = ['path' => $path.'.type', 'message' => 'must be a string'];

            return ['type' => 'invalid'];
        }

        if ($type === 'text') {
            return $this->canonicalizeText($node, $path, $errors);
        }

        if ($type === 'hardBreak') {
            return ['type' => 'hardBreak'];
        }

        if (! isset(self::BLOCK_NODES[$type])) {
            $errors[] = ['path' => $path.'.type', 'message' => 'unsupported node '.$type];

            return ['type' => $type];
        }

        $attrs = $this->canonicalizeAttrs($type, is_array($node['attrs'] ?? null) ? $node['attrs'] : [], $path.'.attrs', $ids, $errors, $requireIds);
        $canonical = ['type' => $type, 'attrs' => $attrs];

        if (isset($node['text'])) {
            if (! in_array($type, ['paragraph', 'heading', 'codeBlock'], true) || ! is_string($node['text'])) {
                $errors[] = ['path' => $path.'.text', 'message' => 'legacy text is only valid on paragraph, heading and codeBlock nodes'];
            } else {
                $canonical['content'] = [['type' => 'text', 'text' => (string) $node['text']]];
            }
        } elseif (isset($node['content'])) {
            if (! is_array($node['content']) || ! array_is_list($node['content'])) {
                $errors[] = ['path' => $path.'.content', 'message' => 'must be a list'];
            } else {
                /** @var list<mixed> $children */
                $children = $node['content'];
                $canonicalChildren = [];

                foreach ($children as $index => $child) {
                    if (! is_array($child)) {
                        $errors[] = ['path' => $path.'.content['.$index.']', 'message' => 'must be an object'];

                        continue;
                    }

                    /** @var array<string, mixed> $child */
                    $canonicalChildren[] = $this->canonicalizeNode($child, $path.'.content['.$index.']', $depth + 1, $nodeCount, $ids, $errors, $requireIds);
                }

                $canonical['content'] = $canonicalChildren;
            }
        } elseif (! in_array($type, ['horizontalRule', 'chart', 'diagram'], true)) {
            $canonical['content'] = [];
        }

        $this->validateChildShape($canonical, $path, $errors);

        return $canonical;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<array{path: string, message: string}>  $errors
     * @return array<string, mixed>
     */
    private function canonicalizeText(array $node, string $path, array &$errors): array
    {
        if (array_key_exists('content', $node)) {
            $errors[] = ['path' => $path.'.content', 'message' => 'text nodes cannot have children'];
        }

        if (! is_string($node['text'] ?? null)) {
            $errors[] = ['path' => $path.'.text', 'message' => 'must be a string'];

            return ['type' => 'text', 'text' => ''];
        }

        $canonical = ['type' => 'text', 'text' => $node['text']];

        if (isset($node['marks'])) {
            if (! is_array($node['marks']) || ! array_is_list($node['marks'])) {
                $errors[] = ['path' => $path.'.marks', 'message' => 'must be a list'];
            } else {
                /** @var list<mixed> $marks */
                $marks = $node['marks'];
                $canonicalMarks = [];

                foreach ($marks as $index => $mark) {
                    if (! is_array($mark)) {
                        $errors[] = ['path' => $path.'.marks['.$index.']', 'message' => 'must be an object'];

                        continue;
                    }

                    /** @var array<string, mixed> $mark */
                    $canonicalMarks[] = $this->canonicalizeMark($mark, $path.'.marks['.$index.']', $errors);
                }

                if ($canonicalMarks !== []) {
                    $canonical['marks'] = $canonicalMarks;
                }
            }
        }

        return $canonical;
    }

    /**
     * @param  array<string, mixed>  $mark
     * @param  list<array{path: string, message: string}>  $errors
     * @return array<string, mixed>
     */
    private function canonicalizeMark(array $mark, string $path, array &$errors): array
    {
        $type = $mark['type'] ?? null;

        if (! is_string($type) || ! in_array($type, ['bold', 'italic', 'strike', 'code', 'link'], true)) {
            $errors[] = ['path' => $path.'.type', 'message' => 'unsupported mark'];

            return ['type' => is_string($type) ? $type : 'invalid'];
        }

        if ($type !== 'link') {
            return ['type' => $type];
        }

        $attrs = is_array($mark['attrs'] ?? null) ? $mark['attrs'] : [];
        $href = $attrs['href'] ?? null;

        if (! is_string($href) || ! $this->isSafeHref($href)) {
            $errors[] = ['path' => $path.'.attrs.href', 'message' => 'is not an allowed URL'];
            $href = '#';
        }

        return ['type' => 'link', 'attrs' => ['href' => $href]];
    }

    /**
     * @param  array<array-key, mixed>  $attrs
     * @param  array<string, true>  $ids
     * @param  list<array{path: string, message: string}>  $errors
     * @return array<string, mixed>
     */
    private function canonicalizeAttrs(string $type, array $attrs, string $path, array &$ids, array &$errors, bool $requireIds): array
    {
        $canonical = [];
        $id = $attrs['id'] ?? null;

        if ($id === null && $requireIds) {
            $errors[] = ['path' => $path.'.id', 'message' => 'is required'];
        }

        if ($id !== null) {
            if (! is_string($id) || ! preg_match('/^(?:blk_[0-9a-f]{16}|imp_[0-9a-f]{16})$/', $id)) {
                $errors[] = ['path' => $path.'.id', 'message' => 'must be blk_ followed by exactly 16 lowercase hex characters or deterministic imp_ followed by 16 lowercase hex characters'];
            } else {
                if (isset($ids[$id])) {
                    $errors[] = ['path' => $path.'.id', 'message' => 'must be unique'];
                }

                $ids[$id] = true;
                $canonical['id'] = $id;
            }
        }

        if (array_key_exists('protected', $attrs) && $attrs['protected'] !== null) {
            if (! is_bool($attrs['protected'])) {
                $errors[] = ['path' => $path.'.protected', 'message' => 'must be boolean'];
            } else {
                $canonical['protected'] = $attrs['protected'];
            }
        }

        if ($type === 'heading') {
            $level = $attrs['level'] ?? 2;
            if (! in_array($level, [2, 3], true)) {
                $errors[] = ['path' => $path.'.level', 'message' => 'must be 2 or 3'];
                $level = 2;
            }
            $canonical['level'] = $level;
        }

        if ($type === 'orderedList') {
            $start = $attrs['start'] ?? 1;
            if (! is_int($start) || $start < 1) {
                $errors[] = ['path' => $path.'.start', 'message' => 'must be a positive integer'];
                $start = 1;
            }
            $canonical['start'] = $start;
        }

        if ($type === 'note' || $type === 'callout') {
            $title = $attrs['title'] ?? ($type === 'callout' ? 'Key takeaway' : 'Note');
            if (! is_string($title) || trim($title) === '') {
                $errors[] = ['path' => $path.'.title', 'message' => 'must be a nonempty string'];
                $title = $type === 'callout' ? 'Key takeaway' : 'Note';
            }
            $canonical['title'] = $title;

            if ($type === 'callout') {
                $style = $attrs['style'] ?? $attrs['type'] ?? 'key';
                if ($style !== 'key') {
                    $errors[] = ['path' => $path.'.style', 'message' => 'must be key'];
                }
                $canonical['style'] = 'key';
            }
        }

        if ($type === 'chart') {
            $canonical += $this->canonicalizeChartAttrs($attrs, $path, $errors);
        }

        if ($type === 'diagram') {
            $canonical += $this->canonicalizeDiagramAttrs($attrs, $path, $errors);
        }

        return $canonical;
    }

    /**
     * @param  array<array-key, mixed>  $attrs
     * @param  list<array{path: string, message: string}>  $errors
     * @return array<string, mixed>
     */
    private function canonicalizeChartAttrs(array $attrs, string $path, array &$errors): array
    {
        $chartType = $attrs['chartType'] ?? $attrs['type'] ?? null;
        if (! in_array($chartType, ['bar', 'line'], true)) {
            $errors[] = ['path' => $path.'.chartType', 'message' => 'must be bar or line'];
            $chartType = 'bar';
        }

        $x = $attrs['x'] ?? null;
        if (! is_string($x) || $x === '') {
            $errors[] = ['path' => $path.'.x', 'message' => 'must be a nonempty string'];
            $x = 'label';
        }

        $series = $attrs['series'] ?? null;
        if (! is_array($series) || ! array_is_list($series) || count($series) !== 1 || ! is_array($series[0] ?? null)) {
            $errors[] = ['path' => $path.'.series', 'message' => 'must contain exactly one series'];
            $series = [['key' => 'value', 'label' => 'Value']];
        }

        /** @var array<string, mixed> $firstSeries */
        $firstSeries = $series[0];
        $key = $firstSeries['key'] ?? null;
        $label = $firstSeries['label'] ?? null;
        if (! is_string($key) || $key === '' || ! is_string($label) || $label === '') {
            $errors[] = ['path' => $path.'.series[0]', 'message' => 'must define string key and label'];
            $key = 'value';
            $label = 'Value';
        }

        $rows = $attrs['data'] ?? [];
        $canonicalRows = [];
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) < 1 || count($rows) > 200) {
            $errors[] = ['path' => $path.'.data', 'message' => 'must contain 1 to 200 rows'];
            $rows = [];
        }

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors[] = ['path' => $path.'.data['.$index.']', 'message' => 'must be an object'];

                continue;
            }

            $labelValue = $row[$x] ?? null;
            $numericValue = $row[$key] ?? null;

            if (! is_string($labelValue) || $labelValue === '') {
                $errors[] = ['path' => $path.'.data['.$index.'].'.$x, 'message' => 'must be a nonempty string'];
                $labelValue = '';
            }

            if (! (is_int($numericValue) || is_float($numericValue)) || ! is_finite((float) $numericValue) || (float) $numericValue < 0) {
                $errors[] = ['path' => $path.'.data['.$index.'].'.$key, 'message' => 'must be a finite nonnegative number'];
                $numericValue = 0;
            }

            $canonicalRows[] = [$x => $labelValue, $key => $numericValue];
        }

        $caption = $attrs['caption'] ?? '';
        if (! is_string($caption) || trim($caption) === '') {
            $errors[] = ['path' => $path.'.caption', 'message' => 'must be a nonempty string'];
            $caption = 'Chart';
        }

        $width = $attrs['width'] ?? 'wide';
        if (! is_string($width) || ! in_array($width, ['wide', 'normal'], true)) {
            $width = 'wide';
        }

        return [
            'chartType' => $chartType,
            'x' => $x,
            'series' => [['key' => $key, 'label' => $label]],
            'data' => $canonicalRows,
            'caption' => $caption,
            'width' => $width,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $attrs
     * @param  list<array{path: string, message: string}>  $errors
     * @return array<string, mixed>
     */
    private function canonicalizeDiagramAttrs(array $attrs, string $path, array &$errors): array
    {
        $sourceType = $attrs['sourceType'] ?? 'preset';
        $caption = $attrs['caption'] ?? '';

        if (! is_string($caption) || trim($caption) === '') {
            $errors[] = ['path' => $path.'.caption', 'message' => 'must be a nonempty string'];
            $caption = 'Diagram';
        }

        if ($sourceType === 'preset') {
            $name = $attrs['name'] ?? null;
            if (! in_array($name, ['walkthrough', 'reporting'], true)) {
                $errors[] = ['path' => $path.'.name', 'message' => 'must be walkthrough or reporting'];
                $name = 'walkthrough';
            }

            return ['sourceType' => 'preset', 'name' => $name, 'caption' => $caption];
        }

        if ($sourceType !== 'svg') {
            $errors[] = ['path' => $path.'.sourceType', 'message' => 'must be preset or svg'];

            return ['sourceType' => 'preset', 'name' => 'walkthrough', 'caption' => $caption];
        }

        $source = $attrs['source'] ?? null;
        if (! is_string($source)) {
            $errors[] = ['path' => $path.'.source', 'message' => 'must be SVG XML'];
            $source = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><title>Diagram</title><desc>Diagram</desc></svg>';
        }

        try {
            $safeSvg = $this->sanitizeSvg($source, $caption);
        } catch (InvalidArgumentException $exception) {
            $errors[] = ['path' => $path.'.source', 'message' => $exception->getMessage()];
            $safeSvg = '<svg xmlns="http://www.w3.org/2000/svg" role="img" viewBox="0 0 1 1"><title>'.e($caption).'</title><desc>'.e($caption).'</desc></svg>';
        }

        return ['sourceType' => 'svg', 'source' => $source, 'safeSvg' => $safeSvg, 'caption' => $caption];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<array{path: string, message: string}>  $errors
     */
    private function validateChildShape(array $node, string $path, array &$errors): void
    {
        $type = $node['type'];
        $content = $node['content'] ?? [];

        if (! is_array($content)) {
            return;
        }

        $inlineNodes = ['text', 'hardBreak'];
        $blockNodes = ['paragraph', 'heading', 'blockquote', 'bulletList', 'orderedList', 'codeBlock', 'horizontalRule', 'note', 'callout', 'chart', 'diagram'];

        if (in_array($type, ['paragraph', 'heading'], true)) {
            $this->requireChildTypes($content, $inlineNodes, $path, $errors, 'must be inline content');

            return;
        }

        if ($type === 'blockquote' || $type === 'note' || $type === 'callout') {
            $this->requireChildTypes($content, $blockNodes, $path, $errors, 'must be block content');

            return;
        }

        if ($type === 'codeBlock') {
            foreach ($content as $index => $child) {
                if (! is_array($child) || ($child['type'] ?? null) !== 'text') {
                    $errors[] = ['path' => $path.'.content['.$index.']', 'message' => 'must be text content'];

                    continue;
                }

                if (array_key_exists('marks', $child)) {
                    $errors[] = ['path' => $path.'.content['.$index.'].marks', 'message' => 'code block text cannot have marks'];
                }
            }

            return;
        }

        if ($type === 'listItem') {
            $this->requireChildTypes($content, ['paragraph', 'bulletList', 'orderedList'], $path, $errors, 'must be paragraph or nested list');

            return;
        }

        if ($type === 'bulletList' || $type === 'orderedList') {
            $this->requireChildTypes($content, ['listItem'], $path, $errors, 'must be a listItem');

            return;
        }

        if (in_array($type, ['horizontalRule', 'chart', 'diagram'], true) && array_key_exists('content', $node) && $content !== []) {
            $errors[] = ['path' => $path.'.content', 'message' => 'must not have children'];
        }
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @param  list<string>  $allowedTypes
     * @param  list<array{path: string, message: string}>  $errors
     */
    private function requireChildTypes(array $content, array $allowedTypes, string $path, array &$errors, string $message): void
    {
        foreach ($content as $index => $child) {
            if (! is_array($child) || ! in_array($child['type'] ?? null, $allowedTypes, true)) {
                $errors[] = ['path' => $path.'.content['.$index.']', 'message' => $message];
            }
        }
    }

    /** @param array<string, mixed> $node */
    private function renderBlock(array $node): string
    {
        $type = $node['type'] ?? '';
        /** @var array<string, mixed> $attrs */
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

        return match ($type) {
            'paragraph' => '<p>'.$this->renderInlineChildren($node).'</p>',
            'heading' => $this->renderHeading($node, $attrs),
            'blockquote' => '<blockquote>'.$this->renderBlockChildren($node).'</blockquote>',
            'bulletList' => '<ul>'.$this->renderBlockChildren($node).'</ul>',
            'orderedList' => '<ol'.(((int) ($attrs['start'] ?? 1)) > 1 ? ' start="'.((int) $attrs['start']).'"' : '').'>'.$this->renderBlockChildren($node).'</ol>',
            'listItem' => '<li>'.$this->renderBlockChildren($node).'</li>',
            'codeBlock' => '<pre><code>'.e($this->plainText($node)).'</code></pre>',
            'horizontalRule' => '<hr />',
            'note', 'callout' => view('components.marketing.article-note', ['title' => (string) ($attrs['title'] ?? 'Note'), 'html' => $this->renderBlockChildren($node)])->render(),
            'chart' => view('components.marketing.article-chart', ['chart' => ['x' => $attrs['x'], 'series' => $attrs['series'], 'data' => $attrs['data']], 'type' => $attrs['chartType'], 'caption' => $attrs['caption']])->render(),
            'diagram' => $this->renderDiagram($attrs),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $attrs
     */
    private function renderHeading(array $node, array $attrs): string
    {
        $level = (int) ($attrs['level'] ?? 2);
        $level = in_array($level, [2, 3], true) ? $level : 2;

        return '<h'.$level.'>'.$this->renderInlineChildren($node).'</h'.$level.'>';
    }

    /** @param array<string, mixed> $node */
    private function renderBlockChildren(array $node): string
    {
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['content'] ?? null) ? $node['content'] : [];

        return implode('', array_map(fn (array $child): string => $this->renderBlock($child), $children));
    }

    /** @param array<string, mixed> $node */
    private function renderInlineChildren(array $node): string
    {
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['content'] ?? null) ? $node['content'] : [];

        return implode('', array_map(fn (array $child): string => $this->renderInline($child), $children));
    }

    /** @param array<string, mixed> $node */
    private function renderInline(array $node): string
    {
        $type = $node['type'] ?? '';

        if ($type === 'hardBreak') {
            return '<br />';
        }

        if ($type !== 'text') {
            return '';
        }

        $html = e((string) ($node['text'] ?? ''));
        /** @var list<array<string, mixed>> $marks */
        $marks = is_array($node['marks'] ?? null) ? $node['marks'] : [];

        foreach ($marks as $mark) {
            $markType = $mark['type'] ?? '';
            $html = match ($markType) {
                'bold' => '<strong>'.$html.'</strong>',
                'italic' => '<em>'.$html.'</em>',
                'strike' => '<s>'.$html.'</s>',
                'code' => '<code>'.$html.'</code>',
                'link' => $this->renderLinkMark($html, is_array($mark['attrs'] ?? null) ? $mark['attrs'] : []),
                default => $html,
            };
        }

        return $html;
    }

    /** @param array<string, mixed> $attrs */
    private function renderLinkMark(string $html, array $attrs): string
    {
        $href = (string) ($attrs['href'] ?? '#');
        $external = preg_match('/^https?:\/\//i', $href) === 1;

        return '<a href="'.e($href).'"'.($external ? ' rel="noopener noreferrer"' : '').'>'.$html.'</a>';
    }

    /** @param array<string, mixed> $attrs */
    private function renderDiagram(array $attrs): string
    {
        if (($attrs['sourceType'] ?? null) === 'svg') {
            return view('components.marketing.article-source-diagram', [
                'svg' => (string) ($attrs['safeSvg'] ?? ''),
                'caption' => (string) ($attrs['caption'] ?? 'Diagram'),
            ])->render();
        }

        return view('components.marketing.article-diagram', [
            'name' => (string) ($attrs['name'] ?? 'walkthrough'),
            'caption' => (string) ($attrs['caption'] ?? 'Diagram'),
        ])->render();
    }

    /** @param array<string, mixed> $node */
    private function plainText(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            return (string) ($node['text'] ?? '');
        }

        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['content'] ?? null) ? $node['content'] : [];

        return implode('', array_map(fn (array $child): string => $this->plainText($child), $children));
    }

    private function isSafeHref(string $href): bool
    {
        if ($href === '' || preg_match('/[\x00-\x1F\x7F]/', $href) === 1 || str_starts_with($href, '//')) {
            return false;
        }

        if (str_starts_with($href, '#') || str_starts_with($href, '/')) {
            return true;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);
        if (! is_string($scheme)) {
            return false;
        }

        if (! in_array(strtolower($scheme), ['http', 'https', 'mailto'], true)) {
            return false;
        }

        if (parse_url($href, PHP_URL_USER) !== null || parse_url($href, PHP_URL_PASS) !== null) {
            return false;
        }

        return filter_var($href, FILTER_VALIDATE_URL) !== false || strtolower($scheme) === 'mailto';
    }

    private function sanitizeSvg(string $source, string $caption): string
    {
        if (strlen($source) > self::MAX_SVG_BYTES) {
            throw new InvalidArgumentException('SVG source is too large');
        }

        if (preg_match('/<!DOCTYPE|<!ENTITY|<\?|xlink:|href\s*=|on[a-z]+\s*=|style\s*=|url\s*\(|data:/i', $source) === 1) {
            throw new InvalidArgumentException('SVG source contains active or external markup');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;

        try {
            if (! $document->loadXML($source, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                throw new InvalidArgumentException('SVG XML could not be parsed');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;
        if (! $root instanceof DOMElement || $root->tagName !== 'svg' || $root->namespaceURI !== 'http://www.w3.org/2000/svg') {
            throw new InvalidArgumentException('SVG root must use the SVG namespace');
        }

        $count = 0;
        $body = $this->rebuildSvgElement($root, 1, $count);

        $hasTitle = $this->hasNonBlankSvgTextElement($body, 'title');
        $hasDescription = $this->hasNonBlankSvgTextElement($body, 'desc');

        if (! $hasTitle || ! $hasDescription) {
            $fallback = (! $hasTitle ? '<title>'.e($caption).'</title>' : '')
                .(! $hasDescription ? '<desc>'.e($caption).'</desc>' : '');
            $body = preg_replace('/(<svg\b[^>]*>)/', '$1'.$fallback, $body, 1) ?? $body;
        }

        return $body;
    }

    private function hasNonBlankSvgTextElement(string $svg, string $element): bool
    {
        preg_match_all('/<'.preg_quote($element, '/').'(?:\s[^>]*)?>(.*?)<\/'.preg_quote($element, '/').'>/is', $svg, $matches);

        foreach ($matches[1] as $text) {
            if (trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function rebuildSvgElement(DOMElement $element, int $depth, int &$count): string
    {
        $count++;

        if ($count > self::MAX_SVG_ELEMENTS || $depth > self::MAX_SVG_DEPTH) {
            throw new InvalidArgumentException('SVG is too complex');
        }

        $name = $element->tagName;
        if (! isset(self::SVG_ELEMENTS[$name])) {
            throw new InvalidArgumentException('SVG element '.$name.' is not allowed');
        }

        $attributes = [];
        foreach ($element->attributes ?? [] as $attribute) {
            $attributeName = $attribute->nodeName;
            $value = $attribute->nodeValue ?? '';

            if ($attributeName === 'xmlns' && $name === 'svg') {
                $attributes[] = 'xmlns="http://www.w3.org/2000/svg"';

                continue;
            }

            if (! isset(self::SVG_GEOMETRY_ATTRS[$attributeName]) && ! isset(self::SVG_PRESENTATION_ATTRS[$attributeName])) {
                throw new InvalidArgumentException('SVG attribute '.$attributeName.' is not allowed');
            }

            if (! $this->isSafeSvgAttribute($attributeName, $value)) {
                throw new InvalidArgumentException('SVG attribute '.$attributeName.' has an unsafe value');
            }

            $attributes[] = $attributeName.'="'.e($value).'"';
        }

        if ($name === 'svg' && ! collect($attributes)->contains('xmlns="http://www.w3.org/2000/svg"')) {
            array_unshift($attributes, 'xmlns="http://www.w3.org/2000/svg"', 'role="img"');
        }

        $children = '';
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children .= $this->rebuildSvgElement($child, $depth + 1, $count);
            } elseif ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $children .= e($child->textContent);
            } elseif ($child->nodeType !== XML_COMMENT_NODE) {
                throw new InvalidArgumentException('SVG contains unsupported XML nodes');
            }
        }

        return '<'.$name.($attributes === [] ? '' : ' '.implode(' ', $attributes)).'>'.$children.'</'.$name.'>';
    }

    private function isSafeSvgAttribute(string $name, string $value): bool
    {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]|url\s*\(|javascript:|data:/i', $value) === 1) {
            return false;
        }

        if (in_array($name, ['fill', 'stroke'], true)) {
            return preg_match('/^(none|currentColor|#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6}|var\(--diagram-[a-z-]+\))$/', $value) === 1;
        }

        if ($name === 'class') {
            return preg_match('/^(diagram-(ink|muted|accent|fill|grid|label|surface|line|point)\s*)+$/', $value) === 1;
        }

        if (in_array($name, ['stroke-linecap', 'stroke-linejoin'], true)) {
            return in_array($value, ['butt', 'round', 'square', 'miter', 'bevel'], true);
        }

        if ($name === 'role') {
            return $value === 'img' || $value === 'presentation';
        }

        if ($name === 'aria-hidden') {
            return $value === 'true' || $value === 'false';
        }

        if ($name === 'd') {
            return preg_match('/^[MmZzLlHhVvCcSsQqTtAa0-9,\.\-+\s]+$/', $value) === 1;
        }

        if ($name === 'points' || $name === 'transform' || $name === 'viewBox') {
            return preg_match('/^[a-zA-Z0-9(),\.\-+\s]+$/', $value) === 1;
        }

        return preg_match('/^-?\d+(?:\.\d+)?(?:px|%)?$/', $value) === 1;
    }
}
