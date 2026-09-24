<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\EditorialFinding;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\ArticleDocument;
use App\Services\Publishing\PublishingFingerprint;
use App\Services\Publishing\ReleaseFreshnessManifest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ManageArticleRelease
{
    public function __construct(
        private PublishingFingerprint $fingerprint,
        private ArticleDocument $articleDocument,
        private CheckArticleRelease $releaseChecks,
        private ReleaseFreshnessManifest $releaseFreshness,
    ) {}

    /**
     * @return array{scheduled_at: CarbonImmutable, delivery_intent: array<string, mixed>}
     */
    public function resolveSchedule(string $wallTime, string $timezone): array
    {
        $parts = $this->localScheduleParts($wallTime);
        $timezone = trim($timezone);

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Scheduled releases require a valid IANA timezone.');
        }

        $normalizedWallTime = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $parts['year'], $parts['month'], $parts['day'], $parts['hour'], $parts['minute'], $parts['second']);
        $localEpoch = gmmktime($parts['hour'], $parts['minute'], $parts['second'], $parts['month'], $parts['day'], $parts['year']);

        if ($localEpoch === false) {
            throw new InvalidArgumentException('Scheduled releases require a valid local wall time.');
        }

        $zone = new DateTimeZone($timezone);
        $offsets = [$zone->getOffset(CarbonImmutable::createFromTimestampUTC($localEpoch)->toDateTimeImmutable())];
        $transitions = $zone->getTransitions($localEpoch - 172800, $localEpoch + 172800);

        foreach ($transitions as $transition) {
            $offsets[] = $transition['offset'];
        }

        $candidates = [];

        foreach (array_values(array_unique($offsets)) as $offset) {
            $candidate = CarbonImmutable::createFromTimestampUTC($localEpoch - $offset)->startOfSecond();
            if ($candidate->setTimezone($timezone)->format('Y-m-d H:i:s') === $normalizedWallTime) {
                $candidates[$candidate->getTimestamp()] = $candidate;
            }
        }

        if ($candidates === []) {
            throw new RuntimeException('Scheduled wall time does not exist in the selected timezone.');
        }

        if (count($candidates) > 1) {
            throw new RuntimeException('Scheduled wall time is ambiguous in the selected timezone.');
        }

        /** @var CarbonImmutable $scheduledAt */
        $scheduledAt = array_values($candidates)[0]->utc()->startOfSecond();
        $this->ensureScheduleIsFuture($scheduledAt);
        $local = $scheduledAt->setTimezone($timezone);

        return [
            'scheduled_at' => $scheduledAt,
            'delivery_intent' => [
                'channel' => 'scheduled',
                'scheduled_wall_time' => $normalizedWallTime,
                'selected_timezone' => $timezone,
                'scheduled_utc' => $scheduledAt->toISOString(),
                'utc_offset' => $local->format('P'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $deliveryIntent
     */
    public function prepare(
        User $actor,
        PublishingAttempt|int $attempt,
        int $revisionId,
        string $slug,
        ?CarbonInterface $scheduledAt = null,
        array $deliveryIntent = [],
    ): ArticleRelease {
        $this->authorize($actor, PublishingPermission::Publish->value);

        /** @var ArticleRelease $release */
        $release = DB::transaction(function () use ($actor, $attempt, $revisionId, $slug, $scheduledAt, $deliveryIntent): ArticleRelease {
            $attemptId = $this->attemptId($attempt);
            $article = $this->lockedArticleForAttemptId($attemptId);
            $lockedAttempt = $this->lockedAttemptById($attemptId);
            $this->ensureAttemptIsCurrentForArticle($lockedAttempt, $article);

            $revision = ArticleRevision::query()->whereKey($revisionId)->lockForUpdate()->firstOrFail();

            if ((int) $revision->article_id !== (int) $article->id || (int) ($article->working_revision_id ?? 0) !== (int) $revision->id) {
                throw new RuntimeException('Release preparation requires the current revision for the same article.');
            }

            $canonicalSlug = $this->canonicalSlug($article, $slug);
            [$scheduledAt, $deliveryIntent] = $this->normalizeScheduleIntent($scheduledAt, $deliveryIntent);
            $this->ensureScheduleIsFuture($scheduledAt);
            $readiness = $this->releaseChecks->check($actor, $lockedAttempt, (int) $revision->id, $canonicalSlug, $scheduledAt, $deliveryIntent);

            if ($readiness['blocking']) {
                throw new RuntimeException('Release readiness has blocking findings: '.$this->findingSummary($readiness['findings']));
            }

            $revisionDocumentValue = $revision->getAttribute('document');
            $revisionMetadataValue = $revision->getAttribute('metadata');
            $revisionDocument = is_array($revisionDocumentValue) ? $revisionDocumentValue : [];
            $revisionMetadata = is_array($revisionMetadataValue) ? $revisionMetadataValue : [];
            $renderedHtml = $this->articleDocument->renderHtml($revisionDocument);
            $originalPublicDate = $this->originalPublicDateForPayload($revisionMetadata, $article);
            $payload = [
                'document' => $this->documentSnapshotForPayload($revisionDocument),
                'metadata' => $revisionMetadata,
                'rendered_content_version' => 1,
                'rendered_document' => [
                    'htmlVersion' => 1,
                    'html' => $renderedHtml,
                    'hash' => $this->fingerprint->hash($renderedHtml),
                ],
                'original_public_date' => $this->timestampIsoString($originalPublicDate),
                'canonical_slug' => $canonicalSlug,
                'supporting_evidence_manifest' => $this->releaseFreshness->evidenceManifest((int) $lockedAttempt->id),
                'review_manifest' => $this->releaseFreshness->reviewManifest((int) $lockedAttempt->id, (int) $revision->id),
                'readiness_check' => $readiness,
                'delivery_intent' => $deliveryIntent,
                'scheduled_at' => $scheduledAt?->toISOString(),
                'expected_previous_live_release_id' => $article->published_release_id,
            ];
            $releaseHash = $this->fingerprint->hash($payload);

            $release = ArticleRelease::create([
                'article_id' => $article->id,
                'attempt_id' => $lockedAttempt->id,
                'revision_id' => $revision->id,
                'origin' => 'editorial',
                'payload' => $payload,
                'release_hash' => $releaseHash,
                'status' => 'prepared',
                'scheduled_at' => $scheduledAt,
            ]);

            if ($article->published_release_id === null && $article->slug !== $canonicalSlug) {
                $article->forceFill(['slug' => $canonicalSlug])->save();
            }

            return $release;
        });

        return $release;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function documentSnapshotForPayload(array $document): array
    {
        $snapshot = $document;
        $content = $snapshot['content'] ?? null;

        if (! is_array($content)) {
            return $snapshot;
        }

        foreach ($content as $index => $node) {
            if (! is_array($node) || array_key_exists('text', $node)) {
                continue;
            }

            $children = $node['content'] ?? null;
            if (is_array($children) && count($children) === 1 && ($children[0]['type'] ?? null) === 'text' && is_string($children[0]['text'] ?? null)) {
                $snapshot['content'][$index]['text'] = $children[0]['text'];
            }
        }

        return $snapshot;
    }

    public function approve(User $actor, ArticleRelease|int $release, string $expectedReleaseHash): EditorialApproval
    {
        $this->authorize($actor, PublishingPermission::Approve->value);

        /** @var EditorialApproval $approval */
        $approval = DB::transaction(function () use ($actor, $release, $expectedReleaseHash): EditorialApproval {
            $releaseId = $this->releaseId($release);
            $article = $this->lockedArticleForReleaseId($releaseId);
            $lockedRelease = $this->lockedReleaseById($releaseId);
            $this->ensureReleaseBelongsToArticle($lockedRelease, $article);

            if ($lockedRelease->attempt_id === null) {
                throw new RuntimeException('Imported releases do not receive fabricated approvals.');
            }

            $lockedAttempt = $this->lockedAttemptById((int) $lockedRelease->attempt_id);
            $this->ensureAttemptIsCurrentForArticle($lockedAttempt, $article);
            $this->ensureReleaseTargetsCurrentWorkingRevision($lockedRelease, $article);
            $this->ensureReleaseApprovalIsAllowed($lockedAttempt, $lockedRelease);

            if (! hash_equals((string) $lockedRelease->release_hash, $expectedReleaseHash)) {
                throw new RuntimeException('The release package changed before approval.');
            }

            $this->ensureReleaseReadinessIsCurrent($actor, $lockedAttempt, $lockedRelease);

            EditorialApproval::query()
                ->where('attempt_id', $lockedAttempt->id)
                ->where('kind', ApprovalKind::Release->value)
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => now()]);

            $this->withdrawScheduledReleasesForAttemptIdExcept((int) $lockedAttempt->id, (int) $lockedRelease->id);

            $approval = EditorialApproval::create([
                'attempt_id' => $lockedAttempt->id,
                'kind' => ApprovalKind::Release,
                'input_hash' => $lockedRelease->release_hash,
                'revision_id' => $lockedRelease->revision_id,
                'release_id' => $lockedRelease->id,
                'user_id' => $actor->id,
                'approved_at' => now(),
            ]);

            $lockedRelease->forceFill([
                'status' => $lockedRelease->scheduled_at === null ? 'approved' : 'scheduled',
            ])->save();

            $lockedAttempt->forceFill([
                'stage' => $lockedRelease->scheduled_at === null ? EditorialStage::Approved : EditorialStage::Scheduled,
            ])->save();

            return $approval;
        });

        return $approval;
    }

    public function deliver(User $actor, ArticleRelease|int $release, ?int $expectedPreviousLiveReleaseId): ArticleRelease
    {
        $this->authorize($actor, PublishingPermission::Publish->value);

        /** @var ArticleRelease $delivered */
        $delivered = DB::transaction(function () use ($actor, $release, $expectedPreviousLiveReleaseId): ArticleRelease {
            $releaseId = $this->releaseId($release);
            $article = $this->lockedArticleForReleaseId($releaseId);
            $lockedRelease = $this->lockedReleaseById($releaseId);
            $this->ensureReleaseBelongsToArticle($lockedRelease, $article);

            if ($lockedRelease->published_at !== null) {
                return $lockedRelease;
            }

            $this->ensureReleaseTargetsCurrentWorkingRevision($lockedRelease, $article);

            $payload = $this->arrayValue($lockedRelease->payload);
            $expectedPreviousFromPayload = $this->nullableInt($payload['expected_previous_live_release_id'] ?? null);
            $currentLiveReleaseId = $this->nullableInt($article->published_release_id);

            if ($currentLiveReleaseId !== $expectedPreviousFromPayload) {
                throw new RuntimeException('The live article changed before delivery.');
            }

            if ($expectedPreviousLiveReleaseId !== null && $currentLiveReleaseId !== $expectedPreviousLiveReleaseId) {
                throw new RuntimeException('The live article changed before delivery.');
            }

            $lockedAttempt = null;

            if ($lockedRelease->attempt_id !== null) {
                $lockedAttempt = $this->lockedAttemptById((int) $lockedRelease->attempt_id);
                $this->ensureAttemptIsCurrentForArticle($lockedAttempt, $article);
                $this->ensureAttemptIsDeliverable($lockedAttempt);
                $this->ensureReleaseReadinessIsCurrent($actor, $lockedAttempt, $lockedRelease);
            }

            if ($lockedRelease->withdrawn_at !== null || $lockedRelease->status === 'withdrawn') {
                throw new RuntimeException('Withdrawn releases cannot be delivered.');
            }

            if ($this->timestampIsFuture($lockedRelease->scheduled_at)) {
                throw new RuntimeException('Scheduled releases cannot be delivered before their scheduled time.');
            }

            if (! $this->hasExactReleaseApproval($lockedRelease)) {
                throw new RuntimeException('The release package has not been exactly approved.');
            }

            $publishedAt = now();
            $firstPublishedAt = $article->first_published_at ?? $this->carbonValue($this->arrayValue($lockedRelease->payload)['original_public_date'] ?? null) ?? $publishedAt;
            $lockedRelease->forceFill([
                'status' => 'published',
                'published_by' => $actor->id,
                'published_at' => $publishedAt,
            ])->save();

            $article->forceFill([
                'published_release_id' => $lockedRelease->id,
                'first_published_at' => $firstPublishedAt,
            ])->save();

            if ($lockedAttempt !== null) {
                $lockedAttempt->forceFill(['stage' => EditorialStage::Published])->save();
            }

            return $lockedRelease->refresh();
        });

        return $delivered;
    }

    public function withdrawScheduledReleasesForAttemptId(int $attemptId): void
    {
        $this->withdrawScheduledReleasesForAttemptIdExcept($attemptId);
    }

    private function withdrawScheduledReleasesForAttemptIdExcept(int $attemptId, ?int $exceptReleaseId = null): void
    {
        ArticleRelease::query()
            ->where('attempt_id', $attemptId)
            ->where('status', 'scheduled')
            ->when($exceptReleaseId !== null, fn ($query) => $query->whereKeyNot($exceptReleaseId))
            ->whereNull('published_at')
            ->whereNull('withdrawn_at')
            ->update([
                'status' => 'withdrawn',
                'withdrawn_at' => now(),
            ]);
    }

    /**
     * @param  list<array{severity: string, code: string, location: string, message: string, resolution: string}>  $findings
     */
    private function findingSummary(array $findings): string
    {
        return implode('; ', array_map(
            static fn (array $finding): string => $finding['code'].' at '.$finding['location'].': '.$finding['message'],
            array_slice($findings, 0, 5),
        ));
    }

    private function ensureReleaseReadinessIsCurrent(User $actor, PublishingAttempt $attempt, ArticleRelease $release): void
    {
        $payloadValue = $release->getAttribute('payload');
        $payload = is_array($payloadValue) ? $payloadValue : [];
        $storedValue = $payload['readiness_check'] ?? null;
        $stored = is_array($storedValue) ? $storedValue : [];
        $current = $this->releaseChecks->check(
            $actor,
            $attempt,
            (int) $release->revision_id,
            (string) ($payload['canonical_slug'] ?? ''),
            $this->carbonValue($release->getAttribute('scheduled_at')),
            $this->arrayValue($payload['delivery_intent'] ?? []),
        );

        if ($current['blocking']) {
            throw new RuntimeException('Release readiness has blocking findings: '.$this->findingSummary($current['findings']));
        }

        if (! is_string($stored['input_hash'] ?? null) || ! hash_equals((string) $stored['input_hash'], $current['input_hash'])) {
            throw new RuntimeException('The release readiness check is stale.');
        }
    }

    private function ensureAttemptIsDeliverable(PublishingAttempt $attempt): void
    {
        if ($attempt->paused_at !== null || $attempt->parked_at !== null || $attempt->abandoned_at !== null) {
            throw new RuntimeException('Blocked publishing attempts cannot be delivered.');
        }

        if (! in_array($this->stageValue($attempt), [EditorialStage::Approved->value, EditorialStage::Scheduled->value], true)) {
            throw new RuntimeException('Only approved or scheduled publishing attempts can be delivered.');
        }
    }

    private function stageValue(PublishingAttempt $attempt): string
    {
        $stage = $attempt->getAttribute('stage');

        return $stage instanceof EditorialStage ? $stage->value : (string) $stage;
    }

    private function ensureReleaseTargetsCurrentWorkingRevision(ArticleRelease $release, Article $article): void
    {
        if ((int) $release->article_id !== (int) $article->id || (int) ($article->working_revision_id ?? 0) !== (int) $release->revision_id) {
            throw new RuntimeException('Release packages must target the current working revision.');
        }
    }

    private function hasExactReleaseApproval(ArticleRelease $release): bool
    {
        if ($release->attempt_id === null) {
            return $release->origin === 'import';
        }

        return EditorialApproval::query()
            ->where('attempt_id', $release->attempt_id)
            ->where('release_id', $release->id)
            ->where('kind', ApprovalKind::Release->value)
            ->where('input_hash', $release->release_hash)
            ->whereNull('invalidated_at')
            ->exists();
    }

    private function canonicalSlug(Article $article, string $slug): string
    {
        $candidate = Str::slug($slug);

        if ($candidate === '') {
            throw new RuntimeException('A public slug is required before publication.');
        }

        $collision = Article::query()
            ->where('slug', $candidate)
            ->whereKeyNot($article->id)
            ->exists();

        if ($collision) {
            throw new RuntimeException('The public slug is already in use.');
        }

        if ($article->published_release_id !== null && $article->slug !== $candidate) {
            throw new RuntimeException('Published article slugs are immutable in this phase.');
        }

        return $candidate;
    }

    private function releaseId(ArticleRelease|int $release): int
    {
        return (int) ($release instanceof ArticleRelease ? $release->getKey() : $release);
    }

    private function attemptId(PublishingAttempt|int $attempt): int
    {
        return (int) ($attempt instanceof PublishingAttempt ? $attempt->getKey() : $attempt);
    }

    private function lockedArticleForAttemptId(int $attemptId): Article
    {
        $attempt = PublishingAttempt::query()
            ->select('article_id')
            ->whereKey($attemptId)
            ->firstOrFail();

        return Article::query()
            ->whereKey($attempt->article_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockedArticleForReleaseId(int $releaseId): Article
    {
        $release = ArticleRelease::query()
            ->select('article_id')
            ->whereKey($releaseId)
            ->firstOrFail();

        return Article::query()
            ->whereKey($release->article_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockedReleaseById(int $releaseId): ArticleRelease
    {
        return ArticleRelease::query()
            ->whereKey($releaseId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockedAttemptById(int $attemptId): PublishingAttempt
    {
        return PublishingAttempt::query()
            ->whereKey($attemptId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureReleaseBelongsToArticle(ArticleRelease $release, Article $article): void
    {
        if ((int) $release->article_id !== (int) $article->id) {
            throw new RuntimeException('Release packages cannot cross articles.');
        }
    }

    private function ensureAttemptIsCurrentForArticle(PublishingAttempt $attempt, Article $article): void
    {
        if ((int) $attempt->article_id !== (int) $article->id || (int) ($article->current_attempt_id ?? 0) !== (int) $attempt->id) {
            throw new RuntimeException('Release mutations require the current publishing attempt for the article.');
        }
    }

    private function ensureReleaseApprovalIsAllowed(PublishingAttempt $attempt, ArticleRelease $release): void
    {
        if ($attempt->paused_at !== null || $attempt->parked_at !== null || $attempt->abandoned_at !== null) {
            throw new RuntimeException('Blocked publishing attempts cannot receive approvals.');
        }

        if (! in_array($this->stageValue($attempt), [EditorialStage::InReview->value, EditorialStage::Approved->value, EditorialStage::Scheduled->value], true)) {
            throw new RuntimeException('Release approval requires an active plan approval.');
        }

        $approved = EditorialApproval::query()
            ->where('attempt_id', $attempt->id)
            ->where('kind', ApprovalKind::Plan->value)
            ->where('input_hash', $this->planInputHash($attempt))
            ->whereNull('invalidated_at')
            ->exists();

        if (! $approved) {
            throw new RuntimeException('Release approval requires an active plan approval.');
        }

        $readiness = $this->releaseReviewReadiness($attempt, $release);
        if ($readiness === null) {
            throw new RuntimeException('Release approval requires a complete same-revision review batch for the release revision or a completed targeted recheck tied to that reviewed batch.');
        }

        $revisionIdsToInspect = array_values(array_unique([(int) $release->revision_id, ...$readiness['reviewed_revision_ids']]));
        $targetedResolutions = $this->targetedRecheckResolvedReferences($attempt, $release);
        $unresolved = EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', $attempt->review_cycle)
            ->whereIn('revision_id', $revisionIdsToInspect)
            ->whereNull('stale_at')
            ->where(function ($query): void {
                $query->where('severity', 'blocking')
                    ->orWhere('reconciliation_state', 'conflict');
            })
            ->orderBy('id')
            ->get()
            ->contains(fn (EditorialFinding $finding): bool => ! $this->releaseFindingResolved($finding, $targetedResolutions));

        if ($unresolved) {
            throw new RuntimeException('Release approval requires actual resolution or a reasoned false-positive disposition for blocking or conflicting editorial findings.');
        }

        $pendingRecheck = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::Recheck->value)
            ->whereIn('status', ['pending', 'running', 'failed', 'paused', 'stale'])
            ->exists();

        if ($pendingRecheck) {
            throw new RuntimeException('Release approval requires the targeted editorial recheck to complete.');
        }
    }

    /**
     * @return array{reviewed_revision_ids: list<int>}|null
     */
    private function releaseReviewReadiness(PublishingAttempt $attempt, ArticleRelease $release): ?array
    {
        if ($this->hasCompletedReviewBatchForRevision($attempt, (int) $release->revision_id)) {
            return ['reviewed_revision_ids' => []];
        }

        $releaseRevision = ArticleRevision::query()->whereKey($release->revision_id)->first();
        if (! $releaseRevision instanceof ArticleRevision) {
            return null;
        }

        $reviewedRevisionIds = [];
        $rechecks = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::Recheck->value)
            ->where('status', 'completed')
            ->where('revision_id', $release->revision_id)
            ->latest('id')
            ->get();

        foreach ($rechecks as $activity) {
            $reviewedRevisionId = $this->successfulTargetedRecheckReviewedRevisionId($attempt, $releaseRevision, $activity);
            if ($reviewedRevisionId !== null) {
                $reviewedRevisionIds[] = $reviewedRevisionId;
            }
        }

        if ($reviewedRevisionIds === []) {
            return null;
        }

        return ['reviewed_revision_ids' => array_values(array_unique($reviewedRevisionIds))];
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

    /**
     * @return array{finding_ids: array<int, true>, block_ids: array<string, true>}
     */
    private function targetedRecheckResolvedReferences(PublishingAttempt $attempt, ArticleRelease $release): array
    {
        $resolved = ['finding_ids' => [], 'block_ids' => []];
        $releaseRevision = ArticleRevision::query()->whereKey($release->revision_id)->first();
        if (! $releaseRevision instanceof ArticleRevision) {
            return $resolved;
        }

        $rechecks = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::Recheck->value)
            ->where('status', 'completed')
            ->where('revision_id', $release->revision_id)
            ->orderBy('id')
            ->get();

        foreach ($rechecks as $activity) {
            if ($this->successfulTargetedRecheckReviewedRevisionId($attempt, $releaseRevision, $activity) === null) {
                continue;
            }

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

    /**
     * @param  array{finding_ids: array<int, true>, block_ids: array<string, true>}  $targetedResolutions
     */
    private function releaseFindingResolved(EditorialFinding $finding, array $targetedResolutions): bool
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

    private function planInputHash(PublishingAttempt $attempt): string
    {
        return $this->fingerprint->hash([
            'kind' => ApprovalKind::Plan->value,
            'attempt_id' => $attempt->id,
            'brief' => $attempt->brief ?? [],
            'angle' => $attempt->angle ?? [],
            'owner_context' => $this->approvalOwnerContext($attempt),
            'plan' => $attempt->plan ?? [],
            'input_version' => $attempt->input_version,
        ]);
    }

    /** @return array<string, mixed> */
    private function approvalOwnerContext(PublishingAttempt $attempt): array
    {
        $context = $attempt->getAttribute('interview_context');
        $context = is_array($context) ? $context : [];
        $answers = $context['answers'] ?? null;
        if (is_string($answers)) {
            $answers = mb_substr($answers, 0, 4000);
        } elseif (is_array($answers)) {
            $answers = array_map(static fn (mixed $answer): mixed => is_string($answer) ? mb_substr($answer, 0, 2000) : null, $answers);
        } else {
            $answers = null;
        }

        return [
            'latest_interview_activity_id' => is_numeric($context['latest_interview_activity_id'] ?? null) ? (int) $context['latest_interview_activity_id'] : null,
            'answers' => $answers,
            'answered_interview_activity_id' => is_numeric($context['answered_interview_activity_id'] ?? null) ? (int) $context['answered_interview_activity_id'] : null,
            'selected_angle_option' => is_scalar($context['selected_angle_option'] ?? null) ? (string) $context['selected_angle_option'] : null,
        ];
    }

    /**
     * @return array{year: int, month: int, day: int, hour: int, minute: int, second: int}
     */
    private function localScheduleParts(string $wallTime): array
    {
        if (! preg_match('/^\s*(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?\s*$/', $wallTime, $matches)) {
            throw new InvalidArgumentException('Scheduled releases require a valid local wall time in YYYY-MM-DD HH:MM format.');
        }

        $parts = [
            'year' => (int) $matches[1],
            'month' => (int) $matches[2],
            'day' => (int) $matches[3],
            'hour' => (int) $matches[4],
            'minute' => (int) $matches[5],
            'second' => array_key_exists(6, $matches) ? (int) $matches[6] : 0,
        ];

        if (! checkdate($parts['month'], $parts['day'], $parts['year']) || $parts['hour'] > 23 || $parts['minute'] > 59 || $parts['second'] > 59) {
            throw new InvalidArgumentException('Scheduled releases require a valid local wall time.');
        }

        return $parts;
    }

    /**
     * @param  array<string, mixed>  $deliveryIntent
     * @return array{0: CarbonImmutable|null, 1: array<string, mixed>}
     */
    private function normalizeScheduleIntent(?CarbonInterface $scheduledAt, array $deliveryIntent): array
    {
        if ($scheduledAt === null) {
            return [null, $deliveryIntent];
        }

        $scheduledAt = CarbonImmutable::instance($scheduledAt)->utc()->startOfSecond();
        $timezone = $deliveryIntent['selected_timezone'] ?? null;
        $wallTime = $deliveryIntent['scheduled_wall_time'] ?? null;

        if (! is_string($timezone) || trim($timezone) === '' || ! is_string($wallTime) || trim($wallTime) === '') {
            throw new InvalidArgumentException('Scheduled releases require explicit scheduling intent with a selected timezone and wall time.');
        }

        $resolved = $this->resolveSchedule($wallTime, $timezone);
        if ($resolved['scheduled_at']->getTimestamp() !== $scheduledAt->getTimestamp()) {
            throw new RuntimeException('Scheduled release UTC instant does not match the selected wall time and timezone.');
        }

        $deliveryIntent = array_merge($deliveryIntent, $resolved['delivery_intent']);
        $deliveryIntent['channel'] = 'scheduled';

        return [$scheduledAt, $deliveryIntent];
    }

    /** @return array<string, mixed> */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
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

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function ensureScheduleIsFuture(?CarbonInterface $scheduledAt): void
    {
        if ($scheduledAt !== null && ! CarbonImmutable::instance($scheduledAt)->isFuture()) {
            throw new RuntimeException('Scheduled releases must use a future scheduled_at instant.');
        }
    }

    /** @param array<string, mixed> $metadata */
    private function originalPublicDateForPayload(array $metadata, Article $article): CarbonImmutable
    {
        $firstPublishedAt = $this->carbonValue($article->getAttribute('first_published_at'));

        if ($firstPublishedAt instanceof CarbonImmutable) {
            return $firstPublishedAt;
        }

        $date = $metadata['date'] ?? $metadata['published_at'] ?? null;
        $publicDate = $this->carbonValue($date);

        if (! $publicDate instanceof CarbonImmutable) {
            throw new RuntimeException('A valid public date is required before preparing a first publication.');
        }

        return $publicDate;
    }

    private function timestampIsoString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value)->toISOString();
        }

        throw new RuntimeException('Publishing timestamps must be nullable strings or Carbon instances.');
    }

    private function timestampIsFuture(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if ($value instanceof CarbonInterface) {
            return $value->isFuture();
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value)->isFuture();
        }

        throw new RuntimeException('Publishing timestamps must be nullable strings or Carbon instances.');
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException('This user is not allowed to manage releases.');
        }
    }
}
