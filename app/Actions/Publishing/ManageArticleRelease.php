<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\ArticleDocument;
use App\Services\Publishing\PublishingFingerprint;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ManageArticleRelease
{
    public function __construct(
        private PublishingFingerprint $fingerprint,
        private ArticleDocument $articleDocument,
    ) {}

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
        $release = DB::transaction(function () use ($attempt, $revisionId, $slug, $scheduledAt, $deliveryIntent): ArticleRelease {
            $attemptId = $this->attemptId($attempt);
            $article = $this->lockedArticleForAttemptId($attemptId);
            $lockedAttempt = $this->lockedAttemptById($attemptId);
            $this->ensureAttemptIsCurrentForArticle($lockedAttempt, $article);

            $revision = ArticleRevision::query()->whereKey($revisionId)->lockForUpdate()->firstOrFail();

            if ((int) $revision->article_id !== (int) $article->id || (int) ($article->working_revision_id ?? 0) !== (int) $revision->id) {
                throw new RuntimeException('Release preparation requires the current revision for the same article.');
            }

            $canonicalSlug = $this->canonicalSlug($article, $slug);
            $revisionDocumentValue = $revision->getAttribute('document');
            $revisionMetadataValue = $revision->getAttribute('metadata');
            $revisionDocument = is_array($revisionDocumentValue) ? $revisionDocumentValue : [];
            $revisionMetadata = is_array($revisionMetadataValue) ? $revisionMetadataValue : [];
            $renderedHtml = $this->articleDocument->renderHtml($revisionDocument);
            $payload = [
                'document' => $this->documentSnapshotForPayload($revisionDocument),
                'metadata' => $revisionMetadata,
                'rendered_content_version' => 1,
                'rendered_document' => [
                    'htmlVersion' => 1,
                    'html' => $renderedHtml,
                    'hash' => $this->fingerprint->hash($renderedHtml),
                ],
                'original_public_date' => $this->timestampIsoString($article->first_published_at),
                'canonical_slug' => $canonicalSlug,
                'supporting_evidence_manifest' => $this->evidenceManifest((int) $lockedAttempt->id),
                'review_manifest' => $this->reviewManifest((int) $lockedAttempt->id, (int) $revision->id),
                'delivery_intent' => $deliveryIntent,
                'scheduled_at' => $scheduledAt?->toISOString(),
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

            EditorialApproval::query()
                ->where('attempt_id', $lockedAttempt->id)
                ->where('kind', ApprovalKind::Release->value)
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => now()]);

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

            if ((int) ($article->published_release_id ?? 0) !== (int) ($expectedPreviousLiveReleaseId ?? 0)) {
                throw new RuntimeException('The live article changed before delivery.');
            }

            $lockedAttempt = null;

            if ($lockedRelease->attempt_id !== null) {
                $lockedAttempt = $this->lockedAttemptById((int) $lockedRelease->attempt_id);
                $this->ensureAttemptIsCurrentForArticle($lockedAttempt, $article);
                $this->ensureAttemptIsDeliverable($lockedAttempt);
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
            $lockedRelease->forceFill([
                'status' => 'published',
                'published_by' => $actor->id,
                'published_at' => $publishedAt,
            ])->save();

            $article->forceFill([
                'published_release_id' => $lockedRelease->id,
                'first_published_at' => $article->first_published_at ?? $publishedAt,
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
        ArticleRelease::query()
            ->where('attempt_id', $attemptId)
            ->where('status', 'scheduled')
            ->whereNull('published_at')
            ->whereNull('withdrawn_at')
            ->update([
                'status' => 'withdrawn',
                'withdrawn_at' => now(),
            ]);
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
        $unresolved = EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', $attempt->review_cycle)
            ->whereIn('revision_id', $revisionIdsToInspect)
            ->whereNull('stale_at')
            ->where(function ($query): void {
                $query->where('severity', 'blocking')
                    ->orWhere('reconciliation_state', 'conflict');
            })
            ->where(function ($query): void {
                $query->whereNull('disposition')
                    ->orWhere('disposition', 'deferred')
                    ->orWhereIn('disposition', ['accepted', 'rejected'])
                    ->orWhere(function ($query): void {
                        $query->where('disposition', 'false_positive')
                            ->where(function ($query): void {
                                $query->whereNull('disposition_reason')->orWhere('disposition_reason', '');
                            });
                    });
            })
            ->exists();

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

    /** @return list<array<string, mixed>> */
    private function evidenceManifest(int $attemptId): array
    {
        $manifest = [];

        foreach (EvidenceSource::query()
            ->where('attempt_id', $attemptId)
            ->whereNull('unresolved_reason')
            ->get(['id', 'source_type', 'url', 'final_url', 'title', 'content_hash', 'retrieved_at']) as $source) {
            $manifest[] = [
                'id' => $source->id,
                'source_type' => $source->source_type,
                'url' => $source->url,
                'final_url' => $source->final_url,
                'title' => $source->title,
                'content_hash' => $source->content_hash,
                'retrieved_at' => $this->timestampIsoString($source->retrieved_at),
            ];
        }

        return $manifest;
    }

    /** @return array<string, mixed> */
    private function reviewManifest(int $attemptId, int $revisionId): array
    {
        $findings = EditorialFinding::query()
            ->where('attempt_id', $attemptId)
            ->where('revision_id', $revisionId)
            ->whereNull('stale_at')
            ->get(['id', 'review_cycle', 'lens', 'kind', 'severity', 'disposition', 'input_hash']);

        return [
            'findings' => $findings->map(fn (EditorialFinding $finding): array => [
                'id' => $finding->id,
                'review_cycle' => $finding->review_cycle,
                'lens' => $finding->lens,
                'kind' => $finding->kind,
                'severity' => $finding->severity,
                'disposition' => $finding->disposition,
                'input_hash' => $finding->input_hash,
            ])->values()->all(),
        ];
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
