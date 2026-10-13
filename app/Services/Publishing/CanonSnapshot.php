<?php

namespace App\Services\Publishing;

use App\Models\Publishing\EditorialActivityKind;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final readonly class CanonSnapshot
{
    public const CANON_PREFIX = 'resources/publishing/canon/';

    public const ARCHIVE_PREFIX = 'resources/writing/';

    /**
     * @param  array<string, string>  $files  repo-relative path => content (canon files and referenced archive sources)
     * @param  array<string, string>  $hashes  repo-relative path => sha256
     * @param  array<string, array{files: list<string>, exemplars: bool}>  $roles
     * @param  array{label: string, archive: list<string>, files: list<string>}  $exemplarManifest
     */
    public function __construct(
        public string $version,
        public array $files,
        public array $hashes,
        public array $roles,
        public array $exemplarManifest,
    ) {}

    /**
     * The canon directory's files, the part a canon version stores and the editor edits.
     *
     * @return array<string, string> canon-relative path => content
     */
    public function canonFiles(): array
    {
        $files = [];
        foreach ($this->files as $path => $content) {
            if (str_starts_with($path, self::CANON_PREFIX)) {
                $files[substr($path, strlen(self::CANON_PREFIX))] = $content;
            }
        }

        return $files;
    }

    /**
     * The archive sources the exemplars are read from, which a canon version references by hash.
     *
     * @return array<string, string> repo-relative archive path => sha256
     */
    public function externalHashes(): array
    {
        return array_filter($this->hashes, static fn (string $path): bool => str_starts_with($path, self::ARCHIVE_PREFIX), ARRAY_FILTER_USE_KEY);
    }

    /** @return array{files: array<string, string>, text: string} */
    public function rubricFor(EditorialActivityKind $kind): array
    {
        $role = $this->roles[$kind->value] ?? null;
        if ($role === null) {
            throw new RuntimeException("The voice canon has no rubric for the {$kind->value} activity kind.");
        }

        $files = [];
        $texts = [];
        foreach ($role['files'] as $relative) {
            $path = self::CANON_PREFIX.$relative;
            $files[$path] = $this->hashes[$path];
            $texts[] = trim($this->files[$path]);
        }

        return ['files' => $files, 'text' => implode("\n\n", $texts)];
    }

    public function goals(): string
    {
        return trim($this->files[self::CANON_PREFIX.'goals.md']);
    }

    public function exemplarLabel(): string
    {
        return $this->exemplarManifest['label'];
    }

    /** @return list<array{id: string, title: string, text: string}> */
    public function exemplars(): array
    {
        $exemplars = [];

        foreach ($this->exemplarManifest['archive'] as $slug) {
            [$title, $body] = self::archiveExemplar($this->files[self::ARCHIVE_PREFIX.$slug.'.md']);
            $exemplars[] = ['id' => $slug, 'title' => $title ?? $slug, 'text' => $body];
        }

        foreach ($this->exemplarManifest['files'] as $relative) {
            $content = trim($this->files[self::CANON_PREFIX.$relative]);
            $title = $relative;
            if (preg_match('/\A# (.+)\R+/', $content, $match) === 1) {
                $title = trim($match[1]);
                $content = trim(substr($content, strlen($match[0])));
            }
            $exemplars[] = ['id' => $relative, 'title' => $title, 'text' => $content];
        }

        return $exemplars;
    }

    /** @return list<string> sorted unique rule ids found in any canon file */
    public function ruleIds(): array
    {
        $ids = [];
        foreach ($this->files as $path => $content) {
            if (str_starts_with($path, self::CANON_PREFIX) && preg_match_all('/\[rule:([a-z0-9-]+)\]/', $content, $matches) > 0) {
                array_push($ids, ...$matches[1]);
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids, SORT_STRING);

        return $ids;
    }

    /**
     * The canon block every activity freezes into its input. Agents render goals and rubric from this copy, never
     * from the live files, so a continuation sees exactly the canon its activity started with.
     *
     * @return array{version: string, hash: string, role: string, rubric_files: array<string, string>, rubric: string, goals: string, exemplars: list<array{id: string, title: string, text: string}>, exemplar_label: string}
     */
    public function frozenFor(EditorialActivityKind $kind): array
    {
        $rubric = $this->rubricFor($kind);

        return [
            'version' => $this->version,
            'hash' => $this->version,
            'role' => $kind->value,
            'rubric_files' => $rubric['files'],
            'rubric' => $rubric['text'],
            'goals' => $this->goals(),
            'exemplars' => ($this->roles[$kind->value]['exemplars'] ?? false) ? $this->exemplars() : [],
            'exemplar_label' => $this->exemplarLabel(),
        ];
    }

    /**
     * Archive essays keep their Birdcar Markdown directives on disk; exemplars carry only the owner's headings and
     * prose, so frontmatter and whole figure, aside and callout blocks are dropped.
     *
     * @return array{0: string|null, 1: string}
     */
    private static function archiveExemplar(string $source): array
    {
        $title = null;
        $body = $source;
        $parts = preg_split('/\A---\R|\R---\R/', $source, 3);
        if (is_array($parts) && count($parts) === 3) {
            $body = $parts[2];
            $metadata = Yaml::parse($parts[1]);
            $title = is_array($metadata) && is_string($metadata['title'] ?? null) ? $metadata['title'] : null;
        }

        $body = preg_replace('/^@(figure|aside|callout)\b.*?^@end\1[\t ]*$\R?/ms', '', $body) ?? $body;
        $body = preg_replace('/\R{3,}/', "\n\n", $body) ?? $body;

        return [$title, trim($body)];
    }
}
