<?php

namespace App\Services\Publishing;

use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\PublishingAttempt;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ReleaseFreshnessManifest
{
    public function __construct(private readonly PublishingFingerprint $fingerprint) {}

    /** @return list<array<string, mixed>> */
    public function evidenceManifest(int $attemptId): array
    {
        $manifest = EvidenceSource::query()
            ->where('attempt_id', $attemptId)
            ->orderBy('id')
            ->get([
                'id',
                'activity_id',
                'source_type',
                'url',
                'final_url',
                'title',
                'retrieval_method',
                'extracted_text',
                'content_hash',
                'origin_metadata',
                'restricted_processing_consent',
                'publication_permission',
                'unresolved_reason',
                'consent_actor_id',
                'retrieved_at',
                'consented_at',
            ])
            ->map(fn (EvidenceSource $source): array => [
                'id' => $source->id,
                'activity_id' => $source->activity_id,
                'source_type' => $source->source_type,
                'url' => $source->url,
                'final_url' => $source->final_url,
                'title' => $source->title,
                'retrieval_method' => $source->retrieval_method,
                'content_hash' => $source->content_hash,
                'extracted_text_hash' => is_string($source->extracted_text) ? $this->fingerprint->hash($source->extracted_text) : null,
                'origin_metadata' => $this->arrayValue($source->origin_metadata),
                'restricted_processing_consent' => (bool) $source->restricted_processing_consent,
                'publication_permission' => (bool) $source->publication_permission,
                'unresolved_reason' => $this->normalizedRestrictionReason($source->unresolved_reason),
                'consent_actor_id' => $source->consent_actor_id,
                'retrieved_at' => $this->timestampIsoString($source->retrieved_at),
                'consented_at' => $this->timestampIsoString($source->consented_at),
            ])
            ->values()
            ->all();

        return array_values($manifest);
    }

    /** @return array{lineage: array<string, mixed>, findings: list<array<string, mixed>>, activities: list<array<string, mixed>>} */
    public function reviewManifest(int $attemptId, int $revisionId): array
    {
        $lineage = $this->reviewLineage($attemptId, $revisionId) ?? [
            'mode' => 'incomplete',
            'release_revision_id' => $revisionId,
            'reviewed_revision_ids' => [],
            'revision_ids' => [$revisionId],
            'recheck_activity_ids' => [],
        ];
        $revisionIds = array_values(array_unique(array_map('intval', $lineage['revision_ids'])));

        return [
            'lineage' => $lineage,
            'findings' => $this->findingManifest($attemptId, $revisionIds),
            'activities' => $this->activityManifest($attemptId, $revisionIds),
        ];
    }

    /** @return array{mode: string, release_revision_id: int, reviewed_revision_ids: list<int>, revision_ids: list<int>, recheck_activity_ids: list<int>}|null */
    public function reviewLineage(int $attemptId, int $revisionId): ?array
    {
        $attempt = PublishingAttempt::query()->find($attemptId);
        if (! $attempt instanceof PublishingAttempt) {
            return null;
        }

        if ($this->hasCompletedReviewBatchForRevision($attempt, $revisionId)) {
            return [
                'mode' => 'same_revision',
                'release_revision_id' => $revisionId,
                'reviewed_revision_ids' => [],
                'revision_ids' => [$revisionId],
                'recheck_activity_ids' => [],
            ];
        }

        $releaseRevision = ArticleRevision::query()->whereKey($revisionId)->first();
        if (! $releaseRevision instanceof ArticleRevision) {
            return null;
        }

        $reviewedRevisionIds = [];
        $recheckActivityIds = [];
        $rechecks = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::Recheck->value)
            ->where('status', 'completed')
            ->where('revision_id', $releaseRevision->id)
            ->latest('id')
            ->get();

        foreach ($rechecks as $activity) {
            $reviewedRevisionId = $this->successfulTargetedRecheckReviewedRevisionId($attempt, $releaseRevision, $activity);
            if ($reviewedRevisionId === null) {
                continue;
            }

            $reviewedRevisionIds[] = $reviewedRevisionId;
            $recheckActivityIds[] = (int) $activity->id;
        }

        $reviewedRevisionIds = array_values(array_unique($reviewedRevisionIds));
        if ($reviewedRevisionIds === []) {
            return null;
        }

        return [
            'mode' => 'targeted_recheck',
            'release_revision_id' => $revisionId,
            'reviewed_revision_ids' => $reviewedRevisionIds,
            'revision_ids' => array_values(array_unique([$revisionId, ...$reviewedRevisionIds])),
            'recheck_activity_ids' => array_values(array_unique($recheckActivityIds)),
        ];
    }

    /** @param list<int> $revisionIds
     * @return list<array<string, mixed>>
     */
    private function findingManifest(int $attemptId, array $revisionIds): array
    {
        $manifest = EditorialFinding::query()
            ->where('attempt_id', $attemptId)
            ->whereIn('revision_id', $revisionIds)
            ->whereNull('stale_at')
            ->orderBy('review_cycle')
            ->orderBy('id')
            ->get([
                'id',
                'activity_id',
                'review_cycle',
                'revision_id',
                'input_hash',
                'lens',
                'kind',
                'severity',
                'block_id',
                'expected_subtree_hash',
                'statement',
                'rationale',
                'supporting_source_ids',
                'supporting_quotations',
                'proposed_patch',
                'reconciliation_state',
                'reconciliation_group',
                'reconciled_into_finding_id',
                'reconciliation_payload',
                'disposition',
                'disposition_reason',
                'disposition_actor_id',
                'disposed_at',
            ])
            ->map(fn (EditorialFinding $finding): array => [
                'id' => $finding->id,
                'activity_id' => $finding->activity_id,
                'review_cycle' => $finding->review_cycle,
                'revision_id' => $finding->revision_id,
                'input_hash' => $finding->input_hash,
                'lens' => $finding->lens,
                'kind' => $finding->kind,
                'severity' => $finding->severity,
                'block_id' => $finding->block_id,
                'expected_subtree_hash' => $finding->expected_subtree_hash,
                'statement' => $finding->statement,
                'rationale' => $finding->rationale,
                'supporting_source_ids' => $this->arrayValue($finding->supporting_source_ids),
                'supporting_quotations' => $this->arrayValue($finding->supporting_quotations),
                'proposed_patch' => $this->arrayValue($finding->proposed_patch),
                'reconciliation_state' => $finding->reconciliation_state,
                'reconciliation_group' => $finding->reconciliation_group,
                'reconciled_into_finding_id' => $finding->reconciled_into_finding_id,
                'reconciliation_payload' => $this->arrayValue($finding->reconciliation_payload),
                'disposition' => $finding->disposition,
                'disposition_reason' => $finding->disposition_reason,
                'disposition_actor_id' => $finding->disposition_actor_id,
                'disposed_at' => $this->timestampIsoString($finding->disposed_at),
            ])
            ->values()
            ->all();

        return array_values($manifest);
    }

    /** @param list<int> $revisionIds
     * @return list<array<string, mixed>>
     */
    private function activityManifest(int $attemptId, array $revisionIds): array
    {
        $kinds = [
            EditorialActivityKind::ReviewFacts->value,
            EditorialActivityKind::ReviewVoice->value,
            EditorialActivityKind::ReviewBuyer->value,
            EditorialActivityKind::Reconciliation->value,
            EditorialActivityKind::Recheck->value,
        ];

        $manifest = EditorialActivity::query()
            ->where('attempt_id', $attemptId)
            ->whereIn('kind', $kinds)
            ->whereIn('revision_id', $revisionIds)
            ->orderBy('review_cycle')
            ->orderBy('kind')
            ->orderBy('id')
            ->get([
                'id',
                'kind',
                'status',
                'stage',
                'input_version',
                'revision_id',
                'revision_hash',
                'review_cycle',
                'batch_key',
                'prompt_version',
                'prompt_hash',
                'input',
                'response',
                'proposal',
                'paused_at',
                'pause_reason',
                'error_reason',
                'completed_at',
            ])
            ->map(fn (EditorialActivity $activity): array => [
                'id' => $activity->id,
                'kind' => $this->enumValue($activity->kind),
                'status' => $this->enumValue($activity->status),
                'stage' => $activity->stage,
                'input_version' => $activity->input_version,
                'revision_id' => $activity->revision_id,
                'revision_hash' => $activity->revision_hash,
                'review_cycle' => $activity->review_cycle,
                'batch_key' => $activity->batch_key,
                'prompt_version' => $activity->prompt_version,
                'prompt_hash' => $activity->prompt_hash,
                'input_hash' => $this->nullableArrayHash($activity->input),
                'response_hash' => $this->nullableArrayHash($activity->response),
                'proposal_hash' => $this->nullableArrayHash($activity->proposal),
                'paused_at' => $this->timestampIsoString($activity->paused_at),
                'pause_reason' => $activity->pause_reason,
                'error_reason' => $activity->error_reason,
                'completed_at' => $this->timestampIsoString($activity->completed_at),
            ])
            ->values()
            ->all();

        return array_values($manifest);
    }

    private function successfulTargetedRecheckReviewedRevisionId(PublishingAttempt $attempt, ArticleRevision $releaseRevision, EditorialActivity $activity): ?int
    {
        $input = $activity->getAttribute('input');
        $response = $activity->getAttribute('response');
        if (! is_array($input) || ! is_array($response)) {
            return null;
        }

        $reviewedRevisionId = (int) ($input['reviewed_revision_id'] ?? 0);
        $expectedRevisionId = (int) ($input['expected_revision_id'] ?? 0);
        $reviewedHash = $input['reviewed_revision_hash'] ?? null;
        $targetHash = $input['target_revision_hash'] ?? null;
        if ($reviewedRevisionId === 0 || $expectedRevisionId !== (int) $releaseRevision->id || ! is_string($reviewedHash) || ! is_string($targetHash) || ! is_string($activity->batch_key) || $activity->batch_key === '') {
            return null;
        }

        if (! hash_equals((string) $releaseRevision->content_hash, $targetHash)) {
            return null;
        }

        $reviewedRevision = ArticleRevision::query()->whereKey($reviewedRevisionId)->first();
        if (! $reviewedRevision instanceof ArticleRevision || ! hash_equals((string) $reviewedRevision->content_hash, $reviewedHash)) {
            return null;
        }

        if (! $this->hasCompletedReviewBatchForRevision($attempt, $reviewedRevisionId)) {
            return null;
        }

        if (is_array($response['unresolved'] ?? null) && count($response['unresolved']) > 0) {
            return null;
        }

        $newBlocking = is_array($response['newBlockingFindings'] ?? null) ? count($response['newBlockingFindings']) : 0;
        if ($newBlocking > 0) {
            $persistedBlocking = EditorialFinding::query()
                ->where('activity_id', $activity->id)
                ->where('attempt_id', $attempt->id)
                ->where('revision_id', $releaseRevision->id)
                ->where('severity', 'blocking')
                ->count();
            if ($persistedBlocking < $newBlocking) {
                return null;
            }
        }

        return $reviewedRevisionId;
    }

    private function hasCompletedReviewBatchForRevision(PublishingAttempt $attempt, int $revisionId): bool
    {
        foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer, EditorialActivityKind::Reconciliation] as $kind) {
            $complete = EditorialActivity::query()
                ->where('attempt_id', $attempt->id)
                ->where('review_cycle', $attempt->review_cycle)
                ->where('kind', $kind->value)
                ->where('status', 'completed')
                ->where('revision_id', $revisionId)
                ->exists();

            if (! $complete) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function nullableArrayHash(mixed $value): ?string
    {
        return is_array($value) ? $this->fingerprint->hash($value) : null;
    }

    private function normalizedRestrictionReason(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $reason = trim($value);

        return $reason === '' ? null : $reason;
    }

    private function timestampIsoString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->utc()->startOfSecond()->toISOString();
        }

        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value)->utc()->startOfSecond()->toISOString();
        }

        return null;
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
