<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\ArticleDocument;
use App\Services\Publishing\ArticleDocumentValidationException;
use App\Services\Publishing\PublishingFingerprint;
use App\Services\Publishing\ReleaseFreshnessManifest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CheckArticleRelease
{
    public function __construct(
        private readonly ArticleDocument $articleDocument,
        private readonly PublishingFingerprint $fingerprint,
        private readonly ReleaseFreshnessManifest $releaseFreshness,
    ) {}

    /**
     * @param  array<string, mixed>  $deliveryIntent
     * @return array{input_hash: string, blocking: bool, findings: list<array{severity: string, code: string, location: string, message: string, resolution: string}>}
     */
    public function check(User $actor, PublishingAttempt|int $attempt, int $revisionId, string $slug, ?CarbonInterface $scheduledAt = null, array $deliveryIntent = []): array
    {
        if (! $actor->can(PublishingPermission::Approve->value) && ! $actor->can(PublishingPermission::Publish->value)) {
            throw new AuthorizationException('This user is not allowed to check article releases.');
        }

        $attempt = $attempt instanceof PublishingAttempt ? $attempt->fresh() : PublishingAttempt::query()->findOrFail($attempt);
        $article = Article::query()->findOrFail($attempt->article_id);
        $revision = ArticleRevision::query()->findOrFail($revisionId);

        if ((int) $revision->article_id !== (int) $article->id || (int) ($article->working_revision_id ?? 0) !== (int) $revision->id) {
            throw new RuntimeException('Release checks require the current revision for the same article.');
        }

        if ((int) ($article->current_attempt_id ?? 0) !== (int) $attempt->id) {
            throw new RuntimeException('Release checks require the current publishing attempt for the article.');
        }

        $document = $this->arrayValue($revision->getAttribute('document'));
        $metadata = $this->arrayValue($revision->getAttribute('metadata'));
        $canonicalSlug = Str::slug($slug);
        $findings = [];

        if (trim((string) ($metadata['title'] ?? '')) === '') {
            $findings[] = $this->finding('blocking', 'metadata.title.required', '$.metadata.title', 'A public title is required.', 'Add the final public title before preparing the release.');
        }

        if (trim((string) ($metadata['description'] ?? '')) === '') {
            $findings[] = $this->finding('blocking', 'metadata.description.required', '$.metadata.description', 'A public description is required.', 'Add the final public description before preparing the release.');
        }

        try {
            $publicDate = $this->publicDate($metadata, $article);
        } catch (Throwable) {
            $publicDate = null;
            $findings[] = $this->finding('blocking', 'metadata.date.invalid', '$.metadata.date', 'The public date could not be parsed.', 'Use a valid ISO-8601 public date or restore the original public date.');
        }

        if (! collect($findings)->contains(fn (array $finding): bool => $finding['code'] === 'metadata.date.invalid')) {
            if (! $publicDate instanceof CarbonImmutable) {
                $findings[] = $this->finding('blocking', 'metadata.date.required', '$.metadata.date', 'A public date is required.', 'Set the public date or publish an existing historical release.');
            } elseif ($publicDate->isFuture()) {
                $findings[] = $this->finding('blocking', 'metadata.date.future', '$.metadata.date', 'The public date cannot be in the future.', 'Use the original public date or schedule delivery separately.');
            }
        }

        if ($canonicalSlug === '') {
            $findings[] = $this->finding('blocking', 'slug.required', '$.slug', 'A public slug is required.', 'Choose a stable public slug.');
        } elseif ($this->slugCollides($article, $canonicalSlug)) {
            $findings[] = $this->finding('blocking', 'slug.unique', '$.slug', 'The public slug is already in use.', 'Choose a unique slug for this article.');
        } elseif ($article->published_release_id !== null && $article->slug !== $canonicalSlug) {
            $findings[] = $this->finding('blocking', 'slug.immutable', '$.slug', 'Published article slugs are immutable in this phase.', 'Keep the current public slug for revisions.');
        }

        try {
            $this->articleDocument->validate($document);
        } catch (ArticleDocumentValidationException $exception) {
            foreach ($exception->errors() as $error) {
                $findings[] = $this->finding('blocking', 'document.schema', $error['path'], $error['message'], 'Fix the canonical document before release.');
            }
        } catch (Throwable $exception) {
            $findings[] = $this->finding('blocking', 'document.renderable', '$.document', $exception->getMessage(), 'Fix the canonical document before release.');
        }

        if ($this->documentText($document) === '') {
            $findings[] = $this->finding('blocking', 'document.text.required', '$.document.content', 'The public article has no readable text.', 'Add public article content before release.');
        }

        foreach ($this->unsafeDocumentLinks($document) as $location => $href) {
            $findings[] = $this->finding('blocking', 'document.link.unsafe', $location, 'The link uses an unsafe URL: '.$href, 'Use http, https, mailto, tel, hash or relative links only.');
        }

        foreach ($this->reviewPrerequisiteFindings($attempt, $revision) as $finding) {
            $findings[] = $finding;
        }

        foreach ($this->evidenceRightsBlockers($attempt) as $finding) {
            $findings[] = $finding;
        }

        foreach ($this->materialEditorialBlockers($attempt, $revision) as $finding) {
            $findings[] = $finding;
        }

        $inputHash = $this->fingerprint->hash([
            'attempt_id' => $attempt->id,
            'article_id' => $article->id,
            'revision_id' => $revision->id,
            'revision_hash' => $revision->content_hash,
            'metadata' => $metadata,
            'document_hash' => $this->fingerprint->hash($document),
            'slug' => $canonicalSlug,
            'first_published_at' => $this->normalizedTimestamp($this->carbonValue($article->getAttribute('first_published_at'))),
            'scheduled_at' => $this->normalizedTimestamp($scheduledAt),
            'delivery_intent' => $deliveryIntent,
            'supporting_evidence_manifest' => $this->releaseFreshness->evidenceManifest((int) $attempt->id),
            'review_manifest' => $this->releaseFreshness->reviewManifest((int) $attempt->id, (int) $revision->id),
        ]);

        return [
            'input_hash' => $inputHash,
            'blocking' => collect($findings)->contains(fn (array $finding): bool => $finding['severity'] === 'blocking'),
            'findings' => $findings,
        ];
    }

    /**
     * @return list<array{severity: string, code: string, location: string, message: string, resolution: string}>
     */
    public function findingsForRelease(User $actor, ArticleRelease $release): array
    {
        $payload = $this->arrayValue($release->getAttribute('payload'));
        $attempt = $release->attempt_id === null ? null : PublishingAttempt::query()->find($release->attempt_id);

        if (! $attempt instanceof PublishingAttempt) {
            return [];
        }

        return $this->check(
            $actor,
            $attempt,
            (int) $release->revision_id,
            (string) ($payload['canonical_slug'] ?? ''),
            $this->carbonValue($release->getAttribute('scheduled_at')),
            $this->arrayValue($payload['delivery_intent'] ?? []),
        )['findings'];
    }

    /** @return array<string, mixed> */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return array{severity: string, code: string, location: string, message: string, resolution: string}
     */
    private function finding(string $severity, string $code, string $location, string $message, string $resolution): array
    {
        return compact('severity', 'code', 'location', 'message', 'resolution');
    }

    private function normalizedTimestamp(?CarbonInterface $timestamp): ?string
    {
        return $timestamp === null ? null : CarbonImmutable::instance($timestamp)->utc()->startOfSecond()->toISOString();
    }

    private function carbonValue(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value);
        }

        return null;
    }

    /** @param array<string, mixed> $metadata */
    private function publicDate(array $metadata, Article $article): ?CarbonImmutable
    {
        $date = $metadata['date'] ?? $metadata['published_at'] ?? null;

        if ($date instanceof CarbonInterface) {
            return CarbonImmutable::instance($date);
        }

        if (is_int($date)) {
            return CarbonImmutable::createFromTimestampUTC($date);
        }

        if (is_string($date) && trim($date) !== '') {
            return CarbonImmutable::parse($date);
        }

        return $this->carbonValue($article->getAttribute('first_published_at'));
    }

    private function slugCollides(Article $article, string $slug): bool
    {
        return Article::query()
            ->where('slug', $slug)
            ->whereKeyNot($article->id)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, string>
     */
    private function unsafeDocumentLinks(array $document): array
    {
        $links = [];
        $walk = function (mixed $node, string $path) use (&$walk, &$links): void {
            if (! is_array($node)) {
                return;
            }

            foreach (($node['marks'] ?? []) as $index => $mark) {
                if (! is_array($mark) || ($mark['type'] ?? null) !== 'link') {
                    continue;
                }

                $href = data_get($mark, 'attrs.href');
                if (is_string($href) && ! $this->isSafeHref($href)) {
                    $links[$path.'.marks['.$index.'].attrs.href'] = $href;
                }
            }

            foreach (($node['content'] ?? []) as $index => $child) {
                $walk($child, $path.'.content['.$index.']');
            }
        };

        foreach (($document['content'] ?? []) as $index => $node) {
            $walk($node, '$.document.content['.$index.']');
        }

        return $links;
    }

    private function isSafeHref(string $href): bool
    {
        if (str_starts_with($href, '#') || str_starts_with($href, '/') || str_starts_with($href, './') || str_starts_with($href, '../')) {
            return true;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true);
    }

    /** @param array<string, mixed> $document */
    private function documentText(array $document): string
    {
        $text = [];
        $walk = function (mixed $nodes) use (&$walk, &$text): void {
            if (! is_array($nodes)) {
                return;
            }

            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                if (is_string($node['text'] ?? null)) {
                    $text[] = $node['text'];
                }

                $walk($node['content'] ?? []);
            }
        };
        $walk($document['content'] ?? []);

        return trim(implode(' ', $text));
    }

    /**
     * @return list<array{severity: string, code: string, location: string, message: string, resolution: string}>
     */
    private function reviewPrerequisiteFindings(PublishingAttempt $attempt, ArticleRevision $revision): array
    {
        $findings = [];
        $lineage = $this->releaseFreshness->reviewLineage((int) $attempt->id, (int) $revision->id);

        foreach ($this->activeReviewWorkFindings($attempt, $revision) as $finding) {
            $findings[] = $finding;
        }

        if ($lineage !== null) {
            if ($this->hasPendingRecheck($attempt)) {
                $findings[] = $this->finding('blocking', 'review.prerequisite.recheck_pending', '$.editorial_activity.recheck', 'Targeted editorial recheck work is still unresolved.', 'Wait for the targeted recheck to complete successfully before release.');
            }

            return $findings;
        }

        foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer, EditorialActivityKind::Reconciliation] as $kind) {
            $query = $this->reviewActivityQuery($attempt, $revision, $kind);

            if ((clone $query)->whereIn('status', ['pending', 'running', 'paused', 'failed', 'stale'])->exists()) {
                continue;
            }

            if (! (clone $query)->where('status', 'completed')->exists()) {
                $findings[] = $this->finding('blocking', 'review.prerequisite.incomplete', '$.editorial_activity.'.$kind->value, 'Required '.$kind->value.' work is incomplete for this revision.', 'Complete the same-revision review batch or a targeted recheck tied to the reviewed base batch before release.');
            }
        }

        return $findings;
    }

    /**
     * @return list<array{severity: string, code: string, location: string, message: string, resolution: string}>
     */
    private function activeReviewWorkFindings(PublishingAttempt $attempt, ArticleRevision $revision): array
    {
        $findings = [];

        foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer, EditorialActivityKind::Reconciliation] as $kind) {
            $query = $this->reviewActivityQuery($attempt, $revision, $kind);

            if ((clone $query)->whereIn('status', ['pending', 'running', 'paused'])->exists()) {
                $findings[] = $this->finding('blocking', 'review.prerequisite.running', '$.editorial_activity.'.$kind->value, 'Required '.$kind->value.' work is still running or paused.', 'Wait for the same-revision review and reconciliation work to complete before release.');

                continue;
            }

            if ((clone $query)->whereIn('status', ['failed', 'stale'])->exists()) {
                $findings[] = $this->finding('blocking', 'review.prerequisite.stale', '$.editorial_activity.'.$kind->value, 'Required '.$kind->value.' work is failed or stale.', 'Restart and complete current same-revision review and reconciliation work before release.');
            }
        }

        return $findings;
    }

    /** @return Builder<EditorialActivity> */
    private function reviewActivityQuery(PublishingAttempt $attempt, ArticleRevision $revision, EditorialActivityKind $kind): Builder
    {
        return EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', $attempt->review_cycle)
            ->where('revision_id', $revision->id)
            ->where('kind', $kind->value);
    }

    private function hasPendingRecheck(PublishingAttempt $attempt): bool
    {
        return EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::Recheck->value)
            ->whereIn('status', ['pending', 'running', 'failed', 'paused', 'stale'])
            ->exists();
    }

    /**
     * @return list<array{severity: string, code: string, location: string, message: string, resolution: string}>
     */
    private function evidenceRightsBlockers(PublishingAttempt $attempt): array
    {
        $findings = [];
        $sources = EvidenceSource::query()
            ->where('attempt_id', $attempt->id)
            ->orderBy('id')
            ->get(['id', 'source_type', 'title', 'url', 'unresolved_reason', 'restricted_processing_consent', 'publication_permission']);

        foreach ($sources as $source) {
            $label = trim((string) ($source->title ?: $source->url ?: 'Evidence source '.$source->id));
            if (! (bool) $source->publication_permission) {
                $findings[] = $this->finding('blocking', 'evidence.publication_permission.required', '$.evidence_sources.'.$source->id.'.publication_permission', 'Evidence source "'.$label.'" does not have publication permission.', 'Remove the source from release-affecting support or record explicit publication permission before release.');
            }

            if (is_string($source->unresolved_reason) && trim($source->unresolved_reason) !== '') {
                $findings[] = $this->finding('blocking', 'evidence.rights.unresolved', '$.evidence_sources.'.$source->id.'.unresolved_reason', 'Evidence source "'.$label.'" has an unresolved rights or retrieval restriction: '.$source->unresolved_reason, 'Resolve the evidence restriction, replace the source, or remove the unsupported claim before release.');
            }

            if ((string) $source->source_type === 'restricted' && ! (bool) $source->restricted_processing_consent) {
                $findings[] = $this->finding('blocking', 'evidence.restricted_processing_consent.required', '$.evidence_sources.'.$source->id.'.restricted_processing_consent', 'Restricted evidence source "'.$label.'" lacks processing consent.', 'Record restricted-source processing consent or remove the restricted evidence before release.');
            }
        }

        return $findings;
    }

    /**
     * @return list<array{severity: string, code: string, location: string, message: string, resolution: string}>
     */
    private function materialEditorialBlockers(PublishingAttempt $attempt, ArticleRevision $revision): array
    {
        $findings = [];
        $lineage = $this->releaseFreshness->reviewLineage((int) $attempt->id, (int) $revision->id);
        $revisionIds = array_values(array_unique(array_map('intval', $lineage['revision_ids'] ?? [$revision->id])));
        $targetedResolutions = $this->targetedRecheckResolvedReferences($lineage['recheck_activity_ids'] ?? []);
        $editorialFindings = EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', $attempt->review_cycle)
            ->whereIn('revision_id', $revisionIds)
            ->whereNull('stale_at')
            ->where(function ($query): void {
                $query->where('severity', 'blocking')->orWhere('reconciliation_state', 'conflict');
            })
            ->get();

        foreach ($editorialFindings as $finding) {
            if ($this->materialFindingResolved($finding, $targetedResolutions)) {
                continue;
            }

            $findings[] = $this->finding('blocking', 'editorial.material_unresolved', '$.editorial_findings.'.$finding->id, (string) $finding->statement, 'Revise the article, add supporting evidence, or record a reasoned false-positive disposition.');
        }

        return $findings;
    }

    /**
     * @param  array{finding_ids: array<int, true>, block_ids: array<string, true>}  $targetedResolutions
     */
    private function materialFindingResolved(EditorialFinding $finding, array $targetedResolutions): bool
    {
        if ($finding->disposition === 'false_positive') {
            return is_string($finding->disposition_reason) && trim($finding->disposition_reason) !== '';
        }

        if ($finding->disposition === 'accepted') {
            $blockId = $finding->block_id;

            return isset($targetedResolutions['finding_ids'][(int) $finding->id])
                || (is_string($blockId) && isset($targetedResolutions['block_ids'][$blockId]));
        }

        if ($finding->disposition !== 'resolved') {
            return false;
        }

        $sourceIds = $finding->getAttribute('supporting_source_ids');
        $quotations = $finding->getAttribute('supporting_quotations');

        return (is_array($sourceIds) && $sourceIds !== []) || (is_array($quotations) && $quotations !== []);
    }

    /**
     * @param  list<int>  $activityIds
     * @return array{finding_ids: array<int, true>, block_ids: array<string, true>}
     */
    private function targetedRecheckResolvedReferences(array $activityIds): array
    {
        $resolved = ['finding_ids' => [], 'block_ids' => []];
        if ($activityIds === []) {
            return $resolved;
        }

        $activities = EditorialActivity::query()
            ->whereIn('id', $activityIds)
            ->orderBy('id')
            ->get(['id', 'response']);

        foreach ($activities as $activity) {
            $response = $activity->getAttribute('response');
            $items = is_array($response) && is_array($response['resolved'] ?? null) ? $response['resolved'] : [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $findingId = $item['finding_id'] ?? $item['findingId'] ?? null;
                if (is_int($findingId)) {
                    $resolved['finding_ids'][$findingId] = true;
                }

                $blockId = $item['block_id'] ?? $item['blockId'] ?? null;
                if (is_string($blockId) && trim($blockId) !== '') {
                    $resolved['block_ids'][$blockId] = true;
                }
            }
        }

        return $resolved;
    }
}
