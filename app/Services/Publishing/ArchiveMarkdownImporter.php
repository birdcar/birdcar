<?php

namespace App\Services\Publishing;

use App\Actions\ReadWriting;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class ArchiveMarkdownImporter
{
    private string $slug = 'article';

    private int $blockIndex = 0;

    /** @var list<string> */
    private array $dataFiles = [];

    public function __construct(
        private readonly ArticleDocument $documents,
        private readonly ArchiveSourceManifest $manifests,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function parseDirectory(string $directory): array
    {
        $root = $this->realDirectory($directory);
        $paths = glob($root.'/*.md') ?: [];
        sort($paths);
        $articles = [];

        foreach ($paths as $path) {
            $articles[] = $this->parseFile($path, $root);
        }

        return $articles;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFile(string $path, string $root): array
    {
        $parts = preg_split('/\A---\R|\R---\R/', File::get($path), 3);

        if ($parts === false || count($parts) !== 3) {
            throw new RuntimeException($path.': missing YAML front matter.');
        }

        $metadata = Yaml::parse($parts[1]);
        if (! is_array($metadata)) {
            throw new RuntimeException($path.': invalid YAML front matter.');
        }

        $dateValue = $metadata['date'] ?? null;
        $date = is_int($dateValue)
            ? CarbonImmutable::createFromTimestampUTC($dateValue)
            : CarbonImmutable::parse((string) $dateValue);

        $this->slug = pathinfo($path, PATHINFO_FILENAME);

        $normalizedMetadata = [
            'title' => (string) ($metadata['title'] ?? $this->slug),
            'description' => (string) ($metadata['description'] ?? ''),
            'date' => $date->toDateString(),
            'tags' => array_values(is_array($metadata['tags'] ?? null) ? $metadata['tags'] : []),
            'slug' => $this->slug,
            'readMinutes' => max(1, (int) ceil(str_word_count(strip_tags($parts[2])) / 220)),
        ];

        if (($metadata['draft'] ?? false) === true || $date->isFuture()) {
            return [
                'slug' => $this->slug,
                'metadata' => $normalizedMetadata,
                'document' => null,
                'source_manifest' => $this->manifests->buildExcluded($path, $normalizedMetadata),
                'parity_manifest' => null,
                'excluded' => true,
                'exclusion_reason' => ($metadata['draft'] ?? false) === true ? 'draft' : 'future_date',
            ];
        }

        $this->blockIndex = 0;
        $this->dataFiles = [];

        $document = [
            'version' => 1,
            'type' => 'doc',
            'content' => $this->parseMarkdownWithDirectives($parts[2], $root, $path),
        ];
        $document = $this->documents->canonicalize($document);
        $html = $this->documents->renderHtml($document);

        $sourceParity = $this->sourceParity($parts[2], $root, $path);
        $legacyHtml = $this->legacyHtml($root, $parts[2]);
        $legacyParity = $legacyHtml === null ? null : $this->htmlParity($legacyHtml);
        $documentParity = $this->documentParity($document);
        $renderedParity = $this->htmlParity($html);
        $parity = $this->parityManifest($path, $sourceParity, $legacyParity, $documentParity, $renderedParity);
        $sourceManifest = $this->manifests->build($path, $this->dataFiles, $normalizedMetadata, $document, $html, $sourceParity, $legacyParity);

        return [
            'slug' => $this->slug,
            'metadata' => $normalizedMetadata,
            'document' => $document,
            'source_manifest' => $sourceManifest,
            'parity_manifest' => $parity,
            'excluded' => false,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseMarkdownWithDirectives(string $body, string $root, string $sourcePath): array
    {
        $blocks = [];
        $markdown = preg_replace_callback(
            '/^@figure kind=diagram name=(walkthrough|reporting) caption="((?:[^"\\\\\r\n]|\\\\["\\\\])+)"[\t ]*\R@endfigure[\t ]*$/m',
            function (array $match) use (&$blocks): string {
                $token = 'BIRDCARIMPORT'.count($blocks).'END';
                $blocks[$token] = [[
                    'type' => 'diagram',
                    'attrs' => [
                        'id' => $this->nextId(),
                        'sourceType' => 'preset',
                        'name' => $match[1],
                        'caption' => strtr($match[2], ['\\"' => '"', '\\\\' => '\\']),
                    ],
                ]];

                return "\n\n{$token}\n\n";
            },
            $body,
        ) ?? $body;

        $markdown = preg_replace_callback(
            '/^@figure kind=chart type=(line|bar) src=\.\/data\/([a-z0-9-]+\.json) width=wide caption="([^"]+)"\R@endfigure\s*$/m',
            function (array $match) use (&$blocks, $root, $sourcePath): string {
                $token = 'BIRDCARIMPORT'.count($blocks).'END';
                $dataPath = $this->safeDataPath($root, $match[2], $sourcePath);
                /** @var array{x: string, series: list<array{key: string, label: string}>, data: list<array<string, int|float|string>>} $data */
                $data = json_decode(File::get($dataPath), true, flags: JSON_THROW_ON_ERROR);
                $this->dataFiles[] = $dataPath;
                $blocks[$token] = [[
                    'type' => 'chart',
                    'attrs' => [
                        'id' => $this->nextId(),
                        'chartType' => $match[1],
                        'x' => $data['x'],
                        'series' => $data['series'],
                        'data' => $data['data'],
                        'caption' => $match[3],
                        'width' => 'wide',
                    ],
                ]];

                return "\n\n{$token}\n\n";
            },
            $markdown,
        ) ?? $markdown;

        $markdown = preg_replace_callback(
            '/^@(aside|callout)(?: title="([^"\r\n]+)"| type=key)\R(.*?)\R@end\1[\t ]*$/ms',
            function (array $match) use (&$blocks, $root): string {
                $token = 'BIRDCARIMPORT'.count($blocks).'END';
                $noteMarkdown = preg_replace('/\(@([a-z][a-z0-9_-]*)\)/i', '([@$1](/authors/$1))', $match[3]) ?? $match[3];
                $blocks[$token] = [[
                    'type' => $match[1] === 'callout' ? 'callout' : 'note',
                    'attrs' => [
                        'id' => $this->nextId(),
                        'title' => $match[1] === 'callout' ? 'Key takeaway' : $match[2],
                        'style' => $match[1] === 'callout' ? 'key' : null,
                    ],
                    'content' => $this->markdownBlocks($noteMarkdown, $root),
                ]];

                return "\n\n{$token}\n\n";
            },
            $markdown,
        ) ?? $markdown;

        if (preg_match('/^@(figure|aside|callout)\b/m', $markdown) === 1) {
            throw new RuntimeException($sourcePath.': unknown or malformed archive directive.');
        }

        $content = [];
        foreach (preg_split('/^(BIRDCARIMPORT\d+END)$/m', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
            if ($part === '') {
                continue;
            }

            if (isset($blocks[$part])) {
                array_push($content, ...$blocks[$part]);

                continue;
            }

            array_push($content, ...$this->markdownBlocks($part, $root));
        }

        return $content;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function markdownBlocks(string $markdown, string $root): array
    {
        if (trim($markdown) === '') {
            return [];
        }

        if (preg_match('/<[a-zA-Z][^>]*>/', $markdown) === 1) {
            throw new RuntimeException($this->slug.': raw HTML is not supported in archive imports.');
        }

        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);
        if (! $body instanceof DOMElement) {
            throw new RuntimeException($this->slug.': Markdown could not be parsed.');
        }

        $nodes = [];
        foreach ($body->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $nodes[] = $this->elementToBlock($child, $root);
            }
        }

        return $nodes;
    }

    /** @return array<string, mixed> */
    private function elementToBlock(DOMElement $element, string $root): array
    {
        return match ($element->tagName) {
            'p' => ['type' => 'paragraph', 'attrs' => ['id' => $this->nextId()], 'content' => $this->inlineChildren($element)],
            'h1', 'h2', 'h3' => ['type' => 'heading', 'attrs' => ['id' => $this->nextId(), 'level' => $element->tagName === 'h3' ? 3 : 2], 'content' => $this->inlineChildren($element)],
            'blockquote' => ['type' => 'blockquote', 'attrs' => ['id' => $this->nextId()], 'content' => $this->blockChildren($element, $root)],
            'ul' => ['type' => 'bulletList', 'attrs' => ['id' => $this->nextId()], 'content' => $this->listItems($element, $root)],
            'ol' => ['type' => 'orderedList', 'attrs' => ['id' => $this->nextId(), 'start' => max(1, (int) ($element->getAttribute('start') ?: 1))], 'content' => $this->listItems($element, $root)],
            'pre' => ['type' => 'codeBlock', 'attrs' => ['id' => $this->nextId()], 'content' => [['type' => 'text', 'text' => $element->textContent]]],
            'hr' => ['type' => 'horizontalRule', 'attrs' => ['id' => $this->nextId()]],
            default => throw new RuntimeException($this->slug.': unsupported Markdown element '.$element->tagName.'.'),
        };
    }

    /** @return list<array<string, mixed>> */
    private function blockChildren(DOMElement $element, string $root): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $this->elementToBlock($child, $root);
            }
        }

        return $children;
    }

    /** @return list<array<string, mixed>> */
    private function listItems(DOMElement $element, string $root): array
    {
        $items = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === 'li') {
                $items[] = ['type' => 'listItem', 'attrs' => ['id' => $this->nextId()], 'content' => $this->liChildren($child, $root)];
            }
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function liChildren(DOMElement $element, string $root): array
    {
        $children = [];
        $inline = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && in_array($child->tagName, ['ul', 'ol'], true)) {
                if ($inline !== []) {
                    $children[] = ['type' => 'paragraph', 'attrs' => ['id' => $this->nextId()], 'content' => $inline];
                    $inline = [];
                }

                $children[] = $this->elementToBlock($child, $root);

                continue;
            }

            array_push($inline, ...$this->inlineNode($child, []));
        }

        if ($inline !== []) {
            $children[] = ['type' => 'paragraph', 'attrs' => ['id' => $this->nextId()], 'content' => $inline];
        }

        return $children;
    }

    /** @return list<array<string, mixed>> */
    private function inlineChildren(DOMElement $element): array
    {
        $nodes = [];
        foreach ($element->childNodes as $child) {
            array_push($nodes, ...$this->inlineNode($child, []));
        }

        return $nodes;
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, mixed>>
     */
    private function inlineNode(DOMNode $node, array $marks): array
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            $text = $node->textContent;

            return $text === '' ? [] : [array_filter(['type' => 'text', 'text' => $text, 'marks' => $marks], static fn (mixed $value): bool => $value !== [])];
        }

        if (! $node instanceof DOMElement) {
            return [];
        }

        if ($node->tagName === 'br') {
            return [['type' => 'hardBreak']];
        }

        $nextMarks = $marks;
        if (in_array($node->tagName, ['strong', 'b'], true)) {
            $nextMarks[] = ['type' => 'bold'];
        } elseif (in_array($node->tagName, ['em', 'i'], true)) {
            $nextMarks[] = ['type' => 'italic'];
        } elseif (in_array($node->tagName, ['s', 'del'], true)) {
            $nextMarks[] = ['type' => 'strike'];
        } elseif ($node->tagName === 'code') {
            $nextMarks[] = ['type' => 'code'];
        } elseif ($node->tagName === 'a') {
            $nextMarks[] = ['type' => 'link', 'attrs' => ['href' => $node->getAttribute('href')]];
        } else {
            throw new RuntimeException($this->slug.': unsupported inline element '.$node->tagName.'.');
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            array_push($children, ...$this->inlineNode($child, $nextMarks));
        }

        return $children;
    }

    private function nextId(): string
    {
        $id = 'imp_'.substr(hash('sha256', $this->slug.':'.$this->blockIndex), 0, 16);
        $this->blockIndex++;

        return $id;
    }

    private function safeDataPath(string $root, string $file, string $sourcePath): string
    {
        $dataRoot = realpath($root.'/data');
        $path = realpath($root.'/data/'.$file);

        if ($dataRoot === false || $path === false || ! str_starts_with($path, $dataRoot.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException($sourcePath.': chart data path escapes the archive data directory.');
        }

        return $path;
    }

    private function realDirectory(string $directory): string
    {
        $candidate = str_starts_with($directory, DIRECTORY_SEPARATOR) ? $directory : base_path($directory);
        $real = realpath($candidate);

        if ($real === false || ! is_dir($real)) {
            throw new RuntimeException('Archive source directory does not exist: '.$directory);
        }

        return $real;
    }

    /** @return array<string, mixed> */
    private function sourceParity(string $body, string $root, string $sourcePath): array
    {
        return [
            'directive_counts' => [
                'figures' => preg_match_all('/^@figure\b/m', $body),
                'notes' => preg_match_all('/^@(aside|callout)\b/m', $body),
            ],
            'links' => $this->sourceLinks($body),
            'note_titles' => $this->sourceNoteTitles($body),
            'charts' => $this->sourceCharts($body, $root, $sourcePath),
            'diagrams' => $this->sourceDiagrams($body),
        ];
    }

    /** @return list<string> */
    private function sourceLinks(string $body): array
    {
        $body = preg_replace('/\(@([a-z][a-z0-9_-]*)\)/i', '([@$1](/authors/$1))', $body) ?? $body;
        preg_match_all('/\[[^\]\r\n]+\]\(([^)\s]+)(?:\s+["\'][^)]+)?\)/', $body, $matches);

        return $matches[1];
    }

    /** @return list<string> */
    private function sourceNoteTitles(string $body): array
    {
        preg_match_all('/^@(aside|callout)(?: title="([^"\r\n]+)"| type=key)\R.*?\R@end\1[\t ]*$/ms', $body, $matches, PREG_SET_ORDER);

        $titles = [];

        foreach ($matches as $match) {
            $titles[] = $match[1] === 'callout' ? 'Key takeaway' : (string) ($match[2] ?? '');
        }

        return $titles;
    }

    /** @return list<array<string, mixed>> */
    private function sourceCharts(string $body, string $root, string $sourcePath): array
    {
        preg_match_all('/^@figure kind=chart type=(line|bar) src=\.\/data\/([a-z0-9-]+\.json) width=wide caption="([^"]+)"\R@endfigure\s*$/m', $body, $matches, PREG_SET_ORDER);
        $charts = [];

        foreach ($matches as $match) {
            /** @var array{x: string, series: list<array{key: string, label: string}>, data: list<array<string, int|float|string>>} $data */
            $data = json_decode(File::get($this->safeDataPath($root, $match[2], $sourcePath)), true, flags: JSON_THROW_ON_ERROR);
            $charts[] = [
                'chartType' => $match[1],
                'source' => 'data/'.$match[2],
                'x' => $data['x'],
                'series' => $data['series'],
                'data' => $data['data'],
                'caption' => $match[3],
                'width' => 'wide',
            ];
        }

        return $charts;
    }

    /** @return list<array{name: string, caption: string, sourceType: string}> */
    private function sourceDiagrams(string $body): array
    {
        preg_match_all('/^@figure kind=diagram name=(walkthrough|reporting) caption="((?:[^"\\\\\r\n]|\\\\["\\\\])+)"[\t ]*\R@endfigure[\t ]*$/m', $body, $matches, PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [
            'sourceType' => 'preset',
            'name' => $match[1],
            'caption' => strtr($match[2], ['\\"' => '"', '\\\\' => '\\']),
        ], $matches);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function documentParity(array $document): array
    {
        $parity = [
            'directive_counts' => ['figures' => 0, 'notes' => 0],
            'links' => [],
            'note_titles' => [],
            'charts' => [],
            'diagrams' => [],
        ];

        /** @var list<array<string, mixed>> $content */
        $content = is_array($document['content'] ?? null) ? $document['content'] : [];
        $this->collectDocumentParity($content, $parity);

        return $parity;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $parity
     */
    private function collectDocumentParity(array $nodes, array &$parity): void
    {
        foreach ($nodes as $node) {
            $type = $node['type'] ?? null;
            /** @var array<string, mixed> $attrs */
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

            if ($type === 'note' || $type === 'callout') {
                $parity['directive_counts']['notes']++;
                $parity['note_titles'][] = (string) ($attrs['title'] ?? '');
            }

            if ($type === 'chart') {
                $parity['directive_counts']['figures']++;
                $parity['charts'][] = [
                    'chartType' => $attrs['chartType'] ?? null,
                    'x' => $attrs['x'] ?? null,
                    'series' => $attrs['series'] ?? null,
                    'data' => $attrs['data'] ?? null,
                    'caption' => $attrs['caption'] ?? null,
                    'width' => $attrs['width'] ?? null,
                ];
            }

            if ($type === 'diagram') {
                $parity['directive_counts']['figures']++;
                $parity['diagrams'][] = [
                    'sourceType' => $attrs['sourceType'] ?? null,
                    'name' => $attrs['name'] ?? null,
                    'caption' => $attrs['caption'] ?? null,
                ];
            }

            if ($type === 'text') {
                foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
                    if (is_array($mark) && ($mark['type'] ?? null) === 'link' && is_array($mark['attrs'] ?? null)) {
                        $parity['links'][] = (string) ($mark['attrs']['href'] ?? '');
                    }
                }
            }

            if (is_array($node['content'] ?? null)) {
                /** @var list<array<string, mixed>> $children */
                $children = $node['content'];
                $this->collectDocumentParity($children, $parity);
            }
        }
    }

    /** @return array{text: string, links: list<string>, note_titles: list<string>, chart_rows: list<list<array<string, string>>>} */
    private function htmlParity(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $links = [];
        foreach ($document->getElementsByTagName('a') as $link) {
            $links[] = $link->getAttribute('href');
        }

        $noteTitles = [];
        foreach ($document->getElementsByTagName('h3') as $heading) {
            if (str_contains(' '.$heading->getAttribute('class').' ', ' article-note-title ')) {
                $noteTitles[] = trim($heading->textContent);
            }
        }

        return [
            'text' => $this->normalizedHtmlText($html),
            'links' => $links,
            'note_titles' => $noteTitles,
            'chart_rows' => $this->htmlChartRows($document),
        ];
    }

    private function normalizedHtmlText(string $html): string
    {
        $withTagSpacing = preg_replace('/<[^>]+>/', ' ', $html) ?? $html;

        $text = trim(preg_replace('/\s+/', ' ', $withTagSpacing) ?? '');

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @return list<list<array<string, string>>> */
    private function htmlChartRows(DOMDocument $document): array
    {
        $tables = [];

        foreach ($document->getElementsByTagName('table') as $table) {
            $rows = [];

            foreach ($table->getElementsByTagName('tr') as $row) {
                $cells = [];

                foreach (['th', 'td'] as $tag) {
                    foreach ($row->getElementsByTagName($tag) as $cell) {
                        $cells[] = trim($cell->textContent);
                    }
                }

                if (count($cells) >= 2 && $cells[0] !== 'Month' && $cells[0] !== 'Type') {
                    $rows[] = ['label' => $cells[0], 'value' => $cells[1]];
                }
            }

            if ($rows !== []) {
                $tables[] = $rows;
            }
        }

        return $tables;
    }

    /**
     * @param  array<string, mixed>  $sourceParity
     * @param  array<string, mixed>|null  $legacyParity
     * @param  array<string, mixed>  $documentParity
     * @param  array<string, mixed>  $renderedParity
     * @return array<string, mixed>
     */
    private function parityManifest(string $sourcePath, array $sourceParity, ?array $legacyParity, array $documentParity, array $renderedParity): array
    {
        $mismatches = [];
        $sourceCharts = array_map(static function (array $chart): array {
            unset($chart['source']);

            return $chart;
        }, $sourceParity['charts']);

        foreach ([
            'directive_counts' => [$sourceParity['directive_counts'], $documentParity['directive_counts']],
            'links' => [$sourceParity['links'], $documentParity['links']],
            'note_titles' => [$sourceParity['note_titles'], $documentParity['note_titles']],
            'charts' => [$sourceCharts, $documentParity['charts']],
            'diagrams' => [$sourceParity['diagrams'], $documentParity['diagrams']],
        ] as $key => [$expected, $actual]) {
            if ($expected !== $actual) {
                $mismatches[$key] = ['expected' => $expected, 'actual' => $actual];
            }
        }

        if ($legacyParity !== null) {
            foreach (['text', 'links', 'note_titles', 'chart_rows'] as $key) {
                if (($legacyParity[$key] ?? null) !== ($renderedParity[$key] ?? null)) {
                    $mismatches['legacy_'.$key] = ['expected' => $legacyParity[$key] ?? null, 'actual' => $renderedParity[$key] ?? null];
                }
            }
        }

        if ($mismatches !== []) {
            throw new RuntimeException($sourcePath.': archive parity mismatch for '.implode(', ', array_keys($mismatches)).'.');
        }

        return [
            'directive_counts' => $sourceParity['directive_counts'],
            'source' => $sourceParity,
            'legacy_reader' => $legacyParity,
            'imported_document' => $documentParity,
            'rendered' => $renderedParity,
            'matches' => true,
        ];
    }

    private function legacyHtml(string $root, string $body): ?string
    {
        if ($root === realpath(resource_path('writing'))) {
            return app(ReadWriting::class)->render($body);
        }

        return null;
    }
}
