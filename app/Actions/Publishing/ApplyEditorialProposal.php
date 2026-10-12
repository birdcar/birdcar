<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\EditorialFinding;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\DocumentBlocks;
use App\Services\Publishing\PublishingFingerprint;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApplyEditorialProposal
{
    public function __construct(
        private readonly WriteArticle $writer,
        private readonly PublishingFingerprint $fingerprint,
        private readonly DocumentBlocks $blocks,
    ) {}

    /**
     * @param  list<int>  $findingIds
     */
    public function apply(User $actor, PublishingAttempt|int $attempt, array $findingIds, int $expectedRevisionId): ArticleRevision
    {
        if (! $actor->can(PublishingPermission::Write->value)) {
            throw new AuthorizationException('This user is not allowed to apply editorial proposals.');
        }

        if ($findingIds === []) {
            throw new RuntimeException('Select at least one editorial proposal to apply.');
        }

        /** @var ArticleRevision $revision */
        $revision = DB::transaction(function () use ($actor, $attempt, $findingIds, $expectedRevisionId): ArticleRevision {
            $lockedAttempt = $this->lockedAttempt($attempt);
            $article = Article::query()->whereKey($lockedAttempt->article_id)->lockForUpdate()->firstOrFail();
            if ((int) ($article->current_attempt_id ?? 0) !== (int) $lockedAttempt->id) {
                throw new RuntimeException('Only the current publishing attempt can accept editorial proposals.');
            }
            if ((int) ($article->working_revision_id ?? 0) !== $expectedRevisionId) {
                throw new RuntimeException('The article revision changed before proposals were accepted.');
            }

            $baseRevision = ArticleRevision::query()->whereKey($expectedRevisionId)->lockForUpdate()->firstOrFail();
            if ((int) $baseRevision->article_id !== (int) $article->id) {
                throw new RuntimeException('Editorial proposals cannot cross articles.');
            }

            $findings = EditorialFinding::query()
                ->whereIn('id', $findingIds)
                ->lockForUpdate()
                ->get();

            if ($findings->count() !== count(array_unique($findingIds))) {
                throw new RuntimeException('One or more selected editorial proposals no longer exists.');
            }

            $baseDocument = $baseRevision->getAttribute('document');
            $baseMetadata = $baseRevision->getAttribute('metadata');
            $document = is_array($baseDocument) ? $baseDocument : [];
            $metadata = is_array($baseMetadata) ? $baseMetadata : [];
            $patches = [];

            foreach ($findings as $finding) {
                if ((int) $finding->attempt_id !== (int) $lockedAttempt->id || (int) $finding->article_id !== (int) $article->id) {
                    throw new RuntimeException('Editorial proposals cannot cross attempts.');
                }
                // A lint finding's input hash identifies its hit, not the revision; the patch's expected hash guards drift.
                $reviewedOtherContent = $finding->lens !== LintRevision::LENS && $finding->input_hash !== $baseRevision->content_hash;
                if ((int) ($finding->revision_id ?? 0) !== $expectedRevisionId || $reviewedOtherContent || $finding->stale_at !== null) {
                    throw new RuntimeException('Editorial proposal output is stale.');
                }
                $patchValue = $finding->getAttribute('proposed_patch');
                $patch = is_array($patchValue) ? $patchValue : null;
                if ($patch === null) {
                    throw new RuntimeException('Selected findings must include a bounded patch.');
                }

                if (array_key_exists('document', $patch)) {
                    throw new RuntimeException('Editorial findings must use bounded block patches, not full-document replacements.');
                }

                $blockId = $patch['block_id'] ?? $finding->block_id;
                if (! is_string($blockId) || $blockId === '') {
                    throw new RuntimeException('Patch block anchors are required.');
                }

                $expectedHash = is_string($patch['expected_hash'] ?? null) ? (string) $patch['expected_hash'] : $finding->expected_subtree_hash;
                $replacement = $patch['replacement'] ?? null;
                if (! is_array($replacement)) {
                    throw new RuntimeException('Patch replacement block is missing.');
                }

                $patches[] = $this->validatedPatchForBase($document, $blockId, $expectedHash, $replacement);
            }

            $this->rejectOverlappingPatches($patches);
            foreach ($patches as $patch) {
                $document = $this->blocks->replace($document, $patch['path'], $patch['replacement']);
            }

            $revision = $this->writer->save($actor, $article, $expectedRevisionId, $document, $metadata, 'agent-accepted-'.implode('-', $findingIds), 'agent-accepted');

            foreach ($findings as $finding) {
                $finding->forceFill([
                    'disposition' => 'accepted',
                    'disposition_actor_id' => $actor->id,
                    'disposed_at' => now(),
                ])->save();
            }

            $this->markOnlyInvalidatedFindingsStale($lockedAttempt, $expectedRevisionId, $findingIds, $revision);

            return $revision;
        });

        return $revision;
    }

    /**
     * @param  list<int>  $acceptedFindingIds
     */
    private function markOnlyInvalidatedFindingsStale(PublishingAttempt $attempt, int $baseRevisionId, array $acceptedFindingIds, ArticleRevision $newRevision): void
    {
        $documentValue = $newRevision->getAttribute('document');
        $document = is_array($documentValue) ? $documentValue : [];
        $content = is_array($document['content'] ?? null) ? $document['content'] : [];

        EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->whereNull('stale_at')
            ->where('revision_id', $baseRevisionId)
            ->whereNotIn('id', $acceptedFindingIds)
            ->get()
            ->each(function (EditorialFinding $finding) use ($content): void {
                if ($this->findingStillAnchors($finding, $content)) {
                    return;
                }

                $finding->forceFill(['stale_at' => now()])->save();
            });
    }

    /** @param array<int, mixed> $content */
    private function findingStillAnchors(EditorialFinding $finding, array $content): bool
    {
        $blockId = $finding->block_id;
        if (! is_string($blockId) || $blockId === '') {
            return true;
        }

        $match = $this->blocks->find(['content' => $content], $blockId);
        if ($match === null) {
            return false;
        }

        $expectedHash = $finding->expected_subtree_hash;
        if (! is_string($expectedHash) || $expectedHash === '') {
            return true;
        }

        return hash_equals($expectedHash, $this->fingerprint->hash($match['node']));
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $replacement
     * @return array{path: list<int>, replacement: array<string, mixed>}
     */
    private function validatedPatchForBase(array $document, string $blockId, ?string $expectedHash, array $replacement): array
    {
        $match = $this->blocks->find($document, $blockId);
        if ($match === null) {
            throw new RuntimeException('Patch target block was not found.');
        }

        if ($match['protected']) {
            throw new RuntimeException('Protected passages must be unlocked before accepting this proposal.');
        }
        if ($expectedHash !== null && ! hash_equals($expectedHash, $this->fingerprint->hash($match['node']))) {
            throw new RuntimeException('Patch target content changed.');
        }

        return ['path' => $match['path'], 'replacement' => $replacement];
    }

    /**
     * @param  list<array{path: list<int>, replacement: array<string, mixed>}>  $patches
     */
    private function rejectOverlappingPatches(array $patches): void
    {
        foreach ($patches as $leftIndex => $left) {
            foreach ($patches as $rightIndex => $right) {
                if ($leftIndex >= $rightIndex) {
                    continue;
                }
                if ($this->blocks->overlaps($left['path'], $right['path'])) {
                    throw new RuntimeException('Selected patches overlap.');
                }
            }
        }
    }

    private function lockedAttempt(PublishingAttempt|int $attempt): PublishingAttempt
    {
        $attemptId = $attempt instanceof PublishingAttempt ? $attempt->getKey() : $attempt;

        return PublishingAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
    }
}
