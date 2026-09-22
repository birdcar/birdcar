<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Services\Publishing\ArchiveMarkdownImporter;
use App\Services\Publishing\ArticleDocument;
use App\Services\Publishing\PublishingFingerprint;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportWritingArchive
{
    public function __construct(
        private readonly ArchiveMarkdownImporter $importer,
        private readonly ArticleDocument $documents,
        private readonly PublishingFingerprint $fingerprint,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dryRun(string $sourceDirectory, string $baselineCommit): array
    {
        $articles = $this->importer->parseDirectory($sourceDirectory);

        return $this->report($articles, $baselineCommit, false, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function write(string $sourceDirectory, string $baselineCommit, User $operator): array
    {
        if (! $operator->can(PublishingPermission::Write->value)) {
            throw new AuthorizationException('This user is not allowed to import publishing records.');
        }

        $articles = $this->importer->parseDirectory($sourceDirectory);
        $published = $this->publishedEntries($articles);
        $conflicts = $this->conflicts($published, $baselineCommit);

        if ($conflicts !== []) {
            throw new RuntimeException('Archive import would clobber existing articles: '.implode(', ', $conflicts));
        }

        $created = DB::transaction(function () use ($published, $baselineCommit, $operator): array {
            $created = [];

            foreach ($published as $entry) {
                $article = Article::query()->where('slug', $entry['slug'])->lockForUpdate()->first();

                if ($article !== null) {
                    if (! $this->importIdentityMatches($article, $entry, $baselineCommit)) {
                        throw new RuntimeException('Archive import would clobber existing articles: '.$entry['slug']);
                    }

                    $created[] = ['slug' => $entry['slug'], 'status' => 'already_imported'];

                    continue;
                }

                /** @var array<string, mixed> $metadata */
                $metadata = $entry['metadata'];
                /** @var array<string, mixed> $document */
                $document = $entry['document'];
                $date = CarbonImmutable::parse((string) $metadata['date']);

                $article = Article::create([
                    'author_id' => $operator->id,
                    'idea' => $metadata['title'],
                    'slug' => $entry['slug'],
                    'first_published_at' => $date,
                ]);
                $contentHash = $this->fingerprint->hash(['document' => $document, 'metadata' => $metadata]);
                $revision = ArticleRevision::create([
                    'article_id' => $article->id,
                    'number' => 1,
                    'parent_revision_id' => null,
                    'created_by' => $operator->id,
                    'origin' => 'import',
                    'document' => $document,
                    'metadata' => $metadata,
                    'content_hash' => $contentHash,
                    'client_mutation_id' => 'archive:'.$baselineCommit.':'.$entry['slug'],
                ]);
                $html = $this->documents->renderHtml($document);
                $payload = [
                    'schema_version' => 1,
                    'document' => $document,
                    'metadata' => $metadata,
                    'rendered_content_version' => 1,
                    'rendered_document' => [
                        'htmlVersion' => 1,
                        'html' => $html,
                        'hash' => $this->fingerprint->hash($html),
                    ],
                    'original_public_date' => $date->toISOString(),
                    'canonical_slug' => $entry['slug'],
                    'archive' => [
                        'baseline' => $baselineCommit,
                        'source_manifest' => $entry['source_manifest'],
                        'parity_manifest' => $entry['parity_manifest'],
                    ],
                    'supporting_evidence_manifest' => [],
                    'review_manifest' => [],
                    'delivery_intent' => ['channel' => 'archive-import'],
                    'scheduled_at' => null,
                ];
                $release = ArticleRelease::create([
                    'article_id' => $article->id,
                    'attempt_id' => null,
                    'revision_id' => $revision->id,
                    'origin' => 'import',
                    'payload' => $payload,
                    'release_hash' => $this->fingerprint->hash($payload),
                    'status' => 'published',
                    'scheduled_at' => null,
                    'published_by' => $operator->id,
                    'published_at' => $date,
                ]);
                $article->forceFill([
                    'working_revision_id' => $revision->id,
                    'published_release_id' => $release->id,
                    'first_published_at' => $date,
                ])->save();

                $created[] = ['slug' => $entry['slug'], 'status' => 'created', 'article_id' => $article->id, 'revision_id' => $revision->id, 'release_id' => $release->id];
            }

            return $created;
        });

        return $this->report($articles, $baselineCommit, true, $created);
    }

    /**
     * @param  list<array<string, mixed>>  $articles
     * @return list<string>
     */
    private function conflicts(array $articles, string $baselineCommit): array
    {
        $conflicts = [];

        foreach ($articles as $entry) {
            $article = Article::query()->where('slug', $entry['slug'])->first();

            if ($article === null) {
                continue;
            }

            if (! $this->importIdentityMatches($article, $entry, $baselineCommit)) {
                $conflicts[] = $entry['slug'];
            }
        }

        return $conflicts;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function importIdentityMatches(Article $article, array $entry, string $baselineCommit): bool
    {
        $metadata = $entry['metadata'] ?? null;
        $document = $entry['document'] ?? null;
        $sourceHash = $entry['source_manifest']['source']['sha256'] ?? null;

        if (! is_array($metadata) || ! is_array($document) || ! is_string($sourceHash) || $sourceHash === '') {
            return false;
        }

        $expectedMutationId = 'archive:'.$baselineCommit.':'.$entry['slug'];
        $expectedContentHash = $this->fingerprint->hash(['document' => $document, 'metadata' => $metadata]);
        $releases = ArticleRelease::query()
            ->with('revision')
            ->where('article_id', $article->id)
            ->where('origin', 'import')
            ->get();

        foreach ($releases as $release) {
            $payload = $release->getAttribute('payload');
            $archive = is_array($payload) ? ($payload['archive'] ?? null) : null;
            $sourceManifest = is_array($archive) ? ($archive['source_manifest'] ?? null) : null;
            $storedSource = is_array($sourceManifest) ? ($sourceManifest['source'] ?? null) : null;
            $revision = $release->revision;

            if (! $revision instanceof ArticleRevision || ! is_array($archive) || ! is_array($storedSource)) {
                continue;
            }

            if (($archive['baseline'] ?? null) === $baselineCommit
                && ($storedSource['sha256'] ?? null) === $sourceHash
                && $revision->client_mutation_id === $expectedMutationId
                && $revision->content_hash === $expectedContentHash) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $articles
     * @param  list<array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    private function report(array $articles, string $baselineCommit, bool $wrote, array $results): array
    {
        $published = $this->publishedEntries($articles);
        $excluded = array_values(array_filter($articles, static fn (array $entry): bool => ($entry['excluded'] ?? false) === true));

        return [
            'baseline' => $baselineCommit,
            'write' => $wrote,
            'counts' => [
                'published' => count($published),
                'excluded' => count($excluded),
                'created' => count(array_filter($results, static fn (array $result): bool => ($result['status'] ?? null) === 'created')),
                'already_imported' => count(array_filter($results, static fn (array $result): bool => ($result['status'] ?? null) === 'already_imported')),
            ],
            'sources' => array_map(static function (array $entry): array {
                $document = $entry['document'] ?? null;

                return [
                    'slug' => $entry['slug'],
                    'title' => $entry['metadata']['title'] ?? '',
                    'status' => ($entry['excluded'] ?? false) === true ? 'excluded' : 'published',
                    'exclusion_reason' => $entry['exclusion_reason'] ?? null,
                    'source_sha256' => $entry['source_manifest']['source']['sha256'] ?? '',
                    'source_manifest' => $entry['source_manifest'] ?? null,
                    'document_hash' => is_array($document) ? app(PublishingFingerprint::class)->hash($document) : null,
                    'parity' => $entry['parity_manifest'] ?? null,
                ];
            }, $articles),
            'excluded' => array_map(static fn (array $entry): array => [
                'slug' => $entry['slug'],
                'title' => $entry['metadata']['title'] ?? '',
                'reason' => $entry['exclusion_reason'] ?? 'excluded',
                'source_sha256' => $entry['source_manifest']['source']['sha256'] ?? '',
            ], $excluded),
            'results' => $results,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $articles
     * @return list<array<string, mixed>>
     */
    private function publishedEntries(array $articles): array
    {
        return array_values(array_filter($articles, static fn (array $entry): bool => ($entry['excluded'] ?? false) !== true));
    }
}
