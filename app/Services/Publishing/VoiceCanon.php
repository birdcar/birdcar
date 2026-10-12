<?php

namespace App\Services\Publishing;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Reads the repo-owned voice and goals canon. Bound as scoped, so each request or queued job reads the files once
 * and a long-running worker never serves a canon from before a deploy.
 */
final class VoiceCanon
{
    private ?CanonSnapshot $snapshot = null;

    public function __construct(private Filesystem $files) {}

    public function snapshot(): CanonSnapshot
    {
        return $this->snapshot ??= $this->read();
    }

    private function read(): CanonSnapshot
    {
        $canonPath = rtrim((string) config('publishing_agents.canon.path'), '/');
        $archivePath = rtrim((string) config('publishing_agents.canon.archive_path'), '/');
        if (! $this->files->isDirectory($canonPath)) {
            throw new RuntimeException("The voice canon directory {$canonPath} does not exist.");
        }

        $contents = [];
        foreach ($this->files->allFiles($canonPath) as $file) {
            if (in_array($file->getExtension(), ['md', 'json'], true)) {
                $contents[CanonSnapshot::CANON_PREFIX.$this->relativePath($file)] = $file->getContents();
            }
        }

        $roles = $this->roles($contents);
        $exemplars = $this->exemplarManifest($contents);

        $required = ['goals.md', ...$exemplars['files']];
        foreach ($roles as $role) {
            array_push($required, ...$role['files']);
        }
        foreach ($required as $relative) {
            if (! array_key_exists(CanonSnapshot::CANON_PREFIX.$relative, $contents)) {
                throw new RuntimeException("The voice canon file {$relative} is missing.");
            }
        }

        foreach ($exemplars['archive'] as $slug) {
            $source = $archivePath.'/'.$slug.'.md';
            if (! $this->files->isFile($source)) {
                throw new RuntimeException("The voice canon exemplar {$slug} has no archive source file.");
            }
            $contents[CanonSnapshot::ARCHIVE_PREFIX.$slug.'.md'] = $this->files->get($source);
        }

        $hashes = array_map(static fn (string $content): string => hash('sha256', $content), $contents);
        $lines = [];
        foreach ($hashes as $path => $hash) {
            $lines[] = $path."\t".$hash;
        }
        sort($lines, SORT_STRING);
        ksort($contents, SORT_STRING);
        ksort($hashes, SORT_STRING);

        return new CanonSnapshot(hash('sha256', implode("\n", $lines)), $contents, $hashes, $roles, $exemplars);
    }

    private function relativePath(SplFileInfo $file): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());
    }

    /**
     * @param  array<string, string>  $contents
     * @return array<string, array{files: list<string>, exemplars: bool}>
     */
    private function roles(array $contents): array
    {
        $decoded = $this->json($contents, 'roles.json');
        $roles = [];
        foreach ($decoded as $kind => $role) {
            if (! is_string($kind) || ! is_array($role) || ! is_array($role['files'] ?? null) || ! is_bool($role['exemplars'] ?? null)) {
                throw new RuntimeException('The voice canon roles.json entries must each list files and an exemplars flag.');
            }
            $files = [];
            foreach ($role['files'] as $file) {
                if (! is_string($file) || $file === '') {
                    throw new RuntimeException("The voice canon roles.json entry {$kind} lists an invalid file.");
                }
                $files[] = $file;
            }
            $roles[$kind] = ['files' => $files, 'exemplars' => $role['exemplars']];
        }

        return $roles;
    }

    /**
     * @param  array<string, string>  $contents
     * @return array{label: string, archive: list<string>, files: list<string>}
     */
    private function exemplarManifest(array $contents): array
    {
        $decoded = $this->json($contents, 'exemplars/manifest.json');
        $label = $decoded['label'] ?? null;
        $archive = $decoded['archive'] ?? null;
        $files = $decoded['files'] ?? null;
        if (! is_string($label) || trim($label) === '' || ! is_array($archive) || ! is_array($files)) {
            throw new RuntimeException('The voice canon exemplars/manifest.json must have a label, archive slugs and files.');
        }

        return [
            'label' => $label,
            'archive' => $this->strings($archive, 'archive slug'),
            'files' => $this->strings($files, 'exemplar file'),
        ];
    }

    /**
     * @param  array<string, string>  $contents
     * @return array<mixed>
     */
    private function json(array $contents, string $relative): array
    {
        $content = $contents[CanonSnapshot::CANON_PREFIX.$relative] ?? null;
        if ($content === null) {
            throw new RuntimeException("The voice canon file {$relative} is missing.");
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException("The voice canon file {$relative} is not valid JSON.");
        }

        return $decoded;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function strings(array $values, string $label): array
    {
        $strings = [];
        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw new RuntimeException("The voice canon exemplar manifest lists an invalid {$label}.");
            }
            $strings[] = $value;
        }

        return $strings;
    }
}
