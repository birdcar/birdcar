<?php

namespace App\Services\Publishing;

use Illuminate\Support\Facades\File;

class ArchiveSourceManifest
{
    /**
     * @param  list<string>  $dataFiles
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $sourceParity
     * @param  array<string, mixed>|null  $legacyParity
     * @return array<string, mixed>
     */
    public function build(string $sourcePath, array $dataFiles, array $metadata, array $document, string $html, array $sourceParity = [], ?array $legacyParity = null): array
    {
        $data = [];

        foreach ($dataFiles as $file) {
            $data[] = [
                'path' => $file,
                'sha256' => hash_file('sha256', $file),
            ];
        }

        return [
            'source' => [
                'path' => $sourcePath,
                'sha256' => hash_file('sha256', $sourcePath),
                'bytes' => File::size($sourcePath),
                'data' => $data,
            ],
            'metadata' => $metadata,
            'parity' => [
                'source' => $sourceParity,
                'legacy_reader' => $legacyParity,
                'rendered_text' => $this->textRuns($html),
                'rendered_links' => $this->links($html),
                'rendered_note_titles' => $this->noteTitles($html),
                'document_hash' => app(PublishingFingerprint::class)->hash($document),
                'html_hash' => hash('sha256', $html),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function buildExcluded(string $sourcePath, array $metadata): array
    {
        return [
            'source' => [
                'path' => $sourcePath,
                'sha256' => hash_file('sha256', $sourcePath),
                'bytes' => File::size($sourcePath),
                'data' => [],
            ],
            'metadata' => $metadata,
            'excluded' => true,
        ];
    }

    /** @return list<string> */
    private function textRuns(string $html): array
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

        return $text === '' ? [] : [$text];
    }

    /** @return list<string> */
    private function links(string $html): array
    {
        preg_match_all('/<a\s+[^>]*href="([^"]+)"/i', $html, $matches);

        return $matches[1];
    }

    /** @return list<string> */
    private function noteTitles(string $html): array
    {
        preg_match_all('/<h3 class="article-note-title">([^<]+)<\/h3>/i', $html, $matches);

        return array_map(static fn (string $title): string => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches[1]);
    }
}
