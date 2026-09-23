<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\EditorialApproval;
use App\Models\Publishing\ApprovalKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\ArticleDocument;
use App\Services\Publishing\PublishingFingerprint;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class WriteArticle
{
    public function __construct(
        private PublishingFingerprint $fingerprint,
        private ArticleDocument $articleDocument,
    ) {}

    public function capture(User $actor, string $idea, ?string $slug = null): Article
    {
        $this->authorize($actor, PublishingPermission::Write->value);

        return Article::create([
            'author_id' => $actor->id,
            'idea' => $idea,
            'slug' => $this->availableSlug($slug ?: 'idea-'.Str::lower(Str::random(12))),
        ]);
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $metadata
     */
    public function save(
        User $actor,
        Article|int $article,
        ?int $expectedRevisionId,
        array $document,
        array $metadata,
        ?string $mutationId,
        string $origin = 'human',
    ): ArticleRevision {
        $this->authorize($actor, PublishingPermission::Write->value);
        $document = $this->validateDocument($document, $metadata);
        $contentHash = $this->fingerprint->hash([
            'document' => $document,
            'metadata' => $metadata,
        ]);

        /** @var ArticleRevision $revision */
        $revision = DB::transaction(function () use ($actor, $article, $expectedRevisionId, $document, $metadata, $mutationId, $origin, $contentHash): ArticleRevision {
            $lockedArticle = $this->lockedArticle($article);

            if ($mutationId !== null && $mutationId !== '') {
                $existingRevision = ArticleRevision::query()
                    ->whereBelongsTo($lockedArticle)
                    ->where('client_mutation_id', $mutationId)
                    ->first();

                if ($existingRevision !== null) {
                    if ($existingRevision->content_hash !== $contentHash) {
                        throw new RuntimeException('The mutation key was already used for different content.');
                    }

                    return $existingRevision;
                }
            }

            if ((int) ($lockedArticle->working_revision_id ?? 0) !== (int) ($expectedRevisionId ?? 0)) {
                throw new RuntimeException('The article has changed since this edit began.');
            }

            $this->ensureProtectedBlocksAreUnchanged($origin, $lockedArticle, $document);

            $nextNumber = ((int) ArticleRevision::query()
                ->whereBelongsTo($lockedArticle)
                ->max('number')) + 1;

            $revision = ArticleRevision::create([
                'article_id' => $lockedArticle->id,
                'number' => $nextNumber,
                'parent_revision_id' => $lockedArticle->working_revision_id,
                'created_by' => $actor->id,
                'origin' => $origin,
                'document' => $document,
                'metadata' => $metadata,
                'content_hash' => $contentHash,
                'client_mutation_id' => $mutationId === '' ? null : $mutationId,
            ]);

            $lockedArticle->forceFill(['working_revision_id' => $revision->id])->save();

            if ($lockedArticle->current_attempt_id !== null) {
                $lockedAttempt = $this->lockedCurrentAttempt($lockedArticle);
                $this->invalidateApprovals((int) $lockedAttempt->id, [ApprovalKind::Release]);
                app(ManageArticleRelease::class)->withdrawScheduledReleasesForAttemptId((int) $lockedAttempt->id);
            }

            return $revision;
        });

        return $revision;
    }

    /**
     * @param  list<ApprovalKind>  $kinds
     */
    public function invalidateApprovals(int $attemptId, array $kinds): void
    {
        EditorialApproval::query()
            ->where('attempt_id', $attemptId)
            ->whereNull('invalidated_at')
            ->whereIn('kind', array_map(static fn (ApprovalKind $kind): string => $kind->value, $kinds))
            ->update(['invalidated_at' => now()]);
    }

    private function lockedArticle(Article|int $article): Article
    {
        $articleId = $article instanceof Article ? $article->getKey() : $article;

        return Article::query()
            ->whereKey($articleId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockedCurrentAttempt(Article $article): PublishingAttempt
    {
        $attempt = PublishingAttempt::query()
            ->whereKey($article->current_attempt_id)
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $attempt->article_id !== (int) $article->id || (int) $article->current_attempt_id !== (int) $attempt->id) {
            throw new RuntimeException('The current publishing attempt changed before this edit was saved.');
        }

        return $attempt;
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException('This user is not allowed to mutate publishing records.');
        }
    }

    private function availableSlug(string $slug): string
    {
        $base = Str::slug($slug) ?: 'article';
        $candidate = $base;
        $suffix = 2;

        while (Article::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function validateDocument(array $document, array $metadata): array
    {
        $canonical = $this->articleDocument->canonicalize($this->ensureBlockIds($document));
        $encoded = json_encode(['document' => $canonical, 'metadata' => $metadata], JSON_THROW_ON_ERROR);

        if (mb_strlen($encoded, '8bit') > 1_048_576) {
            throw new InvalidArgumentException('Article payloads must not exceed the document size limit.');
        }

        return $canonical;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function ensureBlockIds(array $document): array
    {
        $ids = [];
        $content = $document['content'] ?? [];

        if (is_array($content)) {
            $document['content'] = $this->ensureNodeIds($content, '$.content', $ids);
        }

        return $document;
    }

    /**
     * @param  array<mixed>  $nodes
     * @param  array<string, true>  $ids
     * @return array<mixed>
     */
    private function ensureNodeIds(array $nodes, string $path, array &$ids): array
    {
        $normalized = [];

        foreach ($nodes as $index => $node) {
            if (! is_array($node)) {
                $normalized[$index] = $node;

                continue;
            }

            /** @var array<string, mixed> $node */
            if (($node['type'] ?? null) !== 'text') {
                $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
                $id = $attrs['id'] ?? null;

                if (is_string($id) && preg_match('/^(blk|imp)_[0-9a-f]{16}$/', $id) && ! isset($ids[$id])) {
                    $ids[$id] = true;
                    $node['attrs'] = $attrs;
                } elseif (! array_key_exists('attrs', $node) && array_key_exists('text', $node)) {
                    $prefix = ($node['type'] ?? null) === 'importedBlock' ? 'imp' : 'blk';
                    $id = $this->stableBlockId($prefix, $path.'.'.$index, $node, $ids);
                    $attrs['id'] = $id;
                    $ids[$id] = true;
                    $node['attrs'] = $attrs;
                }
            }

            if (is_array($node['content'] ?? null)) {
                $node['content'] = $this->ensureNodeIds($node['content'], $path.'.'.$index.'.content', $ids);
            }

            $normalized[$index] = $node;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, true>  $ids
     */
    private function stableBlockId(string $prefix, string $path, array $node, array $ids): string
    {
        $counter = 0;

        do {
            $hash = substr(hash('sha256', $path.'|'.json_encode($node).'|'.$counter), 0, 16);
            $id = $prefix.'_'.$hash;
            $counter++;
        } while (isset($ids[$id]));

        return $id;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function ensureProtectedBlocksAreUnchanged(string $origin, Article $article, array $document): void
    {
        if ($origin !== 'agent' || $article->working_revision_id === null) {
            return;
        }

        $currentRevision = ArticleRevision::query()->whereKey($article->working_revision_id)->first();

        if ($currentRevision === null) {
            return;
        }

        $currentDocumentValue = $currentRevision->getAttribute('document');
        $currentDocument = is_array($currentDocumentValue) ? $currentDocumentValue : [];
        $existingProtected = $this->protectedBlocks($currentDocument);

        if ($existingProtected === []) {
            return;
        }

        $incomingBlocks = $this->blocksById($document);

        foreach ($existingProtected as $id => $block) {
            if (! array_key_exists($id, $incomingBlocks)) {
                throw new RuntimeException('Agent edits cannot remove protected blocks.');
            }

            if ($incomingBlocks[$id] !== $block) {
                throw new RuntimeException('Agent edits cannot modify protected blocks.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, array<string, mixed>>
     */
    private function protectedBlocks(array $document): array
    {
        return array_filter(
            $this->blocksById($document),
            static fn (array $block): bool => is_array($block['attrs'] ?? null) && ($block['attrs']['protected'] ?? false) === true,
        );
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, array<string, mixed>>
     */
    private function blocksById(array $document): array
    {
        $blocks = [];
        $this->collectBlocksById($document['content'] ?? [], $blocks);

        return $blocks;
    }

    /**
     * @param  array<string, array<string, mixed>>  $blocks
     */
    private function collectBlocksById(mixed $nodes, array &$blocks): void
    {
        if (! is_array($nodes)) {
            return;
        }

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            /** @var array<string, mixed> $node */
            $attrs = $node['attrs'] ?? null;
            $id = is_array($attrs) ? ($attrs['id'] ?? null) : null;

            if (is_string($id)) {
                $blocks[$id] = $node;
            }

            $this->collectBlocksById($node['content'] ?? [], $blocks);
        }
    }
}
