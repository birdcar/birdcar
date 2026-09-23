<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\EditorialPrompts;
use App\Services\Publishing\PublishingFingerprint;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StartEditorialActivity
{
    public function __construct(private readonly PublishingFingerprint $fingerprint) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function start(User $actor, PublishingAttempt|int $attempt, EditorialActivityKind $kind, array $input = [], ?string $batchKey = null): EditorialActivity
    {
        if (! $actor->can(PublishingPermission::Develop->value)) {
            throw new AuthorizationException('This user is not allowed to start publishing agent work.');
        }

        /** @var EditorialActivity $activity */
        $activity = DB::transaction(function () use ($actor, $attempt, $kind, $input, $batchKey): EditorialActivity {
            $lockedAttempt = $this->lockedAttempt($attempt);
            $article = Article::query()->whereKey($lockedAttempt->article_id)->lockForUpdate()->firstOrFail();

            if ((int) ($article->current_attempt_id ?? 0) !== (int) $lockedAttempt->id) {
                throw new RuntimeException('Only the current publishing attempt can start agent work.');
            }

            if ($lockedAttempt->paused_at !== null || $lockedAttempt->parked_at !== null || $lockedAttempt->abandoned_at !== null) {
                throw new RuntimeException('Blocked publishing attempts cannot start agent work.');
            }

            $revision = $article->working_revision_id === null ? null : ArticleRevision::query()->find($article->working_revision_id);
            $stageValue = $this->stageValue($lockedAttempt);
            $this->ensureEligible($lockedAttempt, $article, $kind, $stageValue, $revision, $input);
            $this->persistSelectedEvidenceContext($lockedAttempt, $input);
            $this->persistVoiceContext($lockedAttempt, $input);
            $lockedAttempt->refresh();
            $approvalHashes = [
                ApprovalKind::Angle->value => $this->activeApprovalHash($lockedAttempt, ApprovalKind::Angle),
                ApprovalKind::Plan->value => $this->activeApprovalHash($lockedAttempt, ApprovalKind::Plan),
            ];
            $frozenInput = array_merge($input, [
                'article_id' => $article->id,
                'attempt_id' => $lockedAttempt->id,
                'stage' => $stageValue,
                'brief' => $lockedAttempt->brief ?? [],
                'angle' => $lockedAttempt->angle ?? [],
                'plan' => $lockedAttempt->plan ?? [],
                'owner_context' => $this->ownerContextForPrompt($lockedAttempt),
                'approval_hashes' => $approvalHashes,
                'revision_id' => $revision?->id,
                'revision_hash' => $revision?->content_hash,
                'manuscript' => $revision instanceof ArticleRevision ? [
                    'revision_id' => $revision->id,
                    'content_hash' => $revision->content_hash,
                    'document' => $revision->document ?? [],
                    'metadata' => $revision->metadata ?? [],
                ] : null,
                'completed_research' => $this->completedResearchSnapshots($lockedAttempt),
                'evidence_sources' => $this->eligibleEvidenceSources($lockedAttempt),
                'voice_context' => $this->voiceContext($lockedAttempt, $input),
            ]);
            $promptHash = $this->fingerprint->hash([
                'version' => EditorialPrompts::VERSION,
                'kind' => $kind->value,
                'input' => $frozenInput,
            ]);
            $idempotencyKey = $this->fingerprint->hash($this->idempotencyPayload($lockedAttempt, $kind, $revision, $batchKey, $promptHash));

            $existing = EditorialActivity::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof EditorialActivity) {
                return $existing;
            }

            return EditorialActivity::create([
                'article_id' => $article->id,
                'attempt_id' => $lockedAttempt->id,
                'initiating_user_id' => $actor->id,
                'kind' => $kind,
                'status' => EditorialActivityStatus::Pending,
                'stage' => $stageValue,
                'input_version' => $lockedAttempt->input_version,
                'revision_id' => $revision?->id,
                'revision_hash' => $revision?->content_hash,
                'review_cycle' => (int) $lockedAttempt->review_cycle,
                'batch_key' => $batchKey,
                'idempotency_key' => $idempotencyKey,
                'prompt_version' => EditorialPrompts::VERSION,
                'prompt_hash' => $promptHash,
                'input' => $frozenInput,
                'model_snapshot' => [],
                'run_count' => 0,
                'available_at' => now(),
            ]);
        });

        if ((bool) config('publishing_agents.enabled', false)) {
            RunEditorialActivity::dispatch((int) $activity->id)->afterCommit();
        }

        return $activity;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function ensureEligible(PublishingAttempt $attempt, Article $article, EditorialActivityKind $kind, string $stageValue, ?ArticleRevision $revision, array $input): void
    {
        match ($kind) {
            EditorialActivityKind::Interview => $this->ensureStage($stageValue, [EditorialStage::Developing], 'Interview work can only start while developing.'),
            EditorialActivityKind::ResearchChallenge => $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::Drafting], ApprovalKind::Angle, 'Research requires an approved angle and drafting stage.'),
            EditorialActivityKind::Plan => $this->ensurePlanEligible($attempt, $stageValue),
            EditorialActivityKind::Draft => $this->ensureDraftEligible($attempt, $stageValue),
            EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer => $this->ensureReviewEligible($attempt, $article, $stageValue, $revision),
            EditorialActivityKind::Reconciliation => $this->ensureReconciliationEligible($attempt, $article, $stageValue),
            EditorialActivityKind::Recheck => $this->ensureRecheckEligible($attempt, $article, $stageValue, $input),
        };
    }

    /** @param list<EditorialStage> $stages */
    private function ensureStage(string $stageValue, array $stages, string $message): void
    {
        $allowed = array_map(static fn (EditorialStage $stage): string => $stage->value, $stages);
        if (! in_array($stageValue, $allowed, true)) {
            throw new RuntimeException($message);
        }
    }

    /** @param list<EditorialStage> $stages */
    private function ensureStageAndApproval(PublishingAttempt $attempt, string $stageValue, array $stages, ApprovalKind $approval, string $message): void
    {
        $this->ensureStage($stageValue, $stages, $message);
        if (! $this->hasCurrentActiveApproval($attempt, $approval)) {
            throw new RuntimeException($message);
        }
    }

    private function ensurePlanEligible(PublishingAttempt $attempt, string $stageValue): void
    {
        $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::Drafting], ApprovalKind::Angle, 'Planning requires an approved angle and drafting stage.');
        if (! $this->completedActivityExists($attempt, EditorialActivityKind::ResearchChallenge)) {
            throw new RuntimeException('Planning requires completed research/challenge work.');
        }
    }

    private function ensureDraftEligible(PublishingAttempt $attempt, string $stageValue): void
    {
        $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::InReview], ApprovalKind::Plan, 'Drafting requires an approved plan and review stage.');
    }

    private function ensureReviewEligible(PublishingAttempt $attempt, Article $article, string $stageValue, ?ArticleRevision $revision): void
    {
        $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::InReview], ApprovalKind::Plan, 'Review lenses require an approved plan and review stage.');
        if (! $revision instanceof ArticleRevision || (int) ($article->working_revision_id ?? 0) !== (int) $revision->id) {
            throw new RuntimeException('Review lenses require the current drafted revision.');
        }
        if (! $this->completedDraftExistsForRevision($attempt, (int) $revision->id)) {
            throw new RuntimeException('Review lenses can only start after the draft has completed for the current revision.');
        }
    }

    private function ensureReconciliationEligible(PublishingAttempt $attempt, Article $article, string $stageValue): void
    {
        $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::InReview], ApprovalKind::Plan, 'Reconciliation requires an approved plan and review stage.');
        foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer] as $kind) {
            if (! $this->completedActivityExists($attempt, $kind, (int) ($article->working_revision_id ?? 0))) {
                throw new RuntimeException('Reconciliation requires all three review lenses on the current revision.');
            }
        }
    }

    /** @param array<string, mixed> $input */
    private function ensureRecheckEligible(PublishingAttempt $attempt, Article $article, string $stageValue, array $input): void
    {
        $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::InReview], ApprovalKind::Plan, 'Recheck requires an approved plan and review stage.');
        if (! (bool) $attempt->recheck_used) {
            throw new RuntimeException('Recheck requires an explicit finished review decision.');
        }
        $targetRevisionId = (int) ($input['expected_revision_id'] ?? 0);
        if ($targetRevisionId !== 0 && $targetRevisionId !== (int) ($article->working_revision_id ?? 0)) {
            throw new RuntimeException('Recheck must target the current review revision.');
        }

        $reviewedRevisionId = (int) ($input['reviewed_revision_id'] ?? $targetRevisionId);
        foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer, EditorialActivityKind::Reconciliation] as $kind) {
            if (! $this->completedActivityExists($attempt, $kind, $reviewedRevisionId)) {
                throw new RuntimeException('Recheck requires a complete reviewed base batch.');
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function completedResearchSnapshots(PublishingAttempt $attempt): array
    {
        $snapshots = [];
        $activities = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::ResearchChallenge->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->get();

        foreach ($activities as $activity) {
            if (! $this->activityMatchesCurrentApproval($activity, $attempt, EditorialActivityKind::ResearchChallenge)) {
                continue;
            }

            $response = $activity->getAttribute('response');
            $completedAt = $activity->getAttribute('completed_at');
            $snapshots[] = [
                'activity_id' => (int) $activity->id,
                'input_version' => (int) ($activity->input_version ?? 0),
                'response' => is_array($response) ? $this->sanitizeResearchResponse($response) : [],
                'completed_at' => $completedAt instanceof \DateTimeInterface ? $completedAt->format(DATE_ATOM) : null,
            ];
        }

        return $snapshots;
    }

    /**
     * @return list<array{id: int, source_type: string, title: string|null, url: string|null, final_url: string|null, extracted_text: string|null, content_hash: string|null, activity_id: int|null}>
     */
    private function eligibleEvidenceSources(PublishingAttempt $attempt): array
    {
        $sources = [];
        $records = EvidenceSource::query()
            ->where('attempt_id', $attempt->id)
            ->get();

        $selectedIds = $this->selectedEvidenceSourceIds($attempt);
        foreach ($records as $source) {
            $sourceType = (string) $source->source_type;
            $selected = in_array((int) $source->id, $selectedIds, true);
            $currentResearch = $source->activity_id !== null
                && $sourceType === 'public'
                && $this->sourceActivityMatchesCurrentApproval($source, $attempt);

            $selectedOwnerOrRestricted = $selected && in_array($sourceType, ['owner', 'restricted'], true);

            if (! $currentResearch && ! $selectedOwnerOrRestricted) {
                continue;
            }

            if ($sourceType === 'restricted' && (! $selectedOwnerOrRestricted || ! (bool) $source->restricted_processing_consent)) {
                continue;
            }

            $sources[] = [
                'id' => (int) $source->id,
                'source_type' => $sourceType,
                'title' => is_string($source->title) ? $source->title : null,
                'url' => is_string($source->url) ? $source->url : null,
                'final_url' => is_string($source->final_url) ? $source->final_url : null,
                'extracted_text' => is_string($source->extracted_text) ? $source->extracted_text : null,
                'content_hash' => is_string($source->content_hash) ? $source->content_hash : null,
                'activity_id' => $source->activity_id === null ? null : (int) $source->activity_id,
            ];
        }

        return $sources;
    }

    /** @return array<string, mixed> */
    private function ownerContextForPrompt(PublishingAttempt $attempt): array
    {
        $context = $attempt->getAttribute('interview_context');
        $context = is_array($context) ? $context : [];

        $angle = $attempt->getAttribute('angle');

        return array_filter([
            'latest_interview_activity_id' => is_numeric($context['latest_interview_activity_id'] ?? null) ? (int) $context['latest_interview_activity_id'] : null,
            'questions' => is_array($context['questions'] ?? null) ? $context['questions'] : [],
            'answers' => $this->boundedAnswerContext($context['answers'] ?? null),
            'answered_interview_activity_id' => is_numeric($context['answered_interview_activity_id'] ?? null) ? (int) $context['answered_interview_activity_id'] : null,
            'answered_at' => is_string($context['answered_at'] ?? null) ? $context['answered_at'] : null,
            'selected_angle_option' => is_scalar($context['selected_angle_option'] ?? null) ? (string) $context['selected_angle_option'] : null,
            'selected_angle' => is_array($angle) ? $angle : [],
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, mixed> */
    private function approvalOwnerContext(PublishingAttempt $attempt): array
    {
        $context = $this->ownerContextForPrompt($attempt);

        return [
            'latest_interview_activity_id' => $context['latest_interview_activity_id'] ?? null,
            'answers' => $context['answers'] ?? null,
            'answered_interview_activity_id' => $context['answered_interview_activity_id'] ?? null,
            'selected_angle_option' => $context['selected_angle_option'] ?? null,
        ];
    }

    private function boundedAnswerContext(mixed $answers): mixed
    {
        if (is_string($answers)) {
            return mb_substr($answers, 0, 4000);
        }

        if (! is_array($answers)) {
            return null;
        }

        $bounded = [];
        foreach ($answers as $key => $answer) {
            if (is_string($answer)) {
                $bounded[(string) $key] = mb_substr($answer, 0, 2000);
            }
        }

        return $bounded;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function sanitizeResearchResponse(array $response): array
    {
        if (is_array($response['sourceReferences'] ?? null)) {
            $response['sourceReferences'] = array_map(function (mixed $reference): mixed {
                if (! is_array($reference)) {
                    return $reference;
                }

                unset($reference['content'], $reference['retrieved_content'], $reference['extracted_text']);

                return $reference;
            }, $response['sourceReferences']);
        }

        return $response;
    }

    /** @return list<int> */
    private function selectedEvidenceSourceIds(PublishingAttempt $attempt): array
    {
        $context = $attempt->getAttribute('interview_context');
        $context = is_array($context) ? $context : [];
        $ids = $context['selected_evidence_source_ids'] ?? [];
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
    }

    private function sourceActivityMatchesCurrentApproval(EvidenceSource $source, PublishingAttempt $attempt): bool
    {
        $activityId = $source->getAttribute('activity_id');
        if (! is_numeric($activityId)) {
            return false;
        }

        $activity = EditorialActivity::query()->whereKey((int) $activityId)->first();
        if (! $activity instanceof EditorialActivity) {
            return false;
        }

        $kind = $activity->getAttribute('kind');
        $status = $activity->getAttribute('status');

        return (int) $activity->attempt_id === (int) $attempt->id
            && (int) $activity->review_cycle === (int) $attempt->review_cycle
            && (int) ($activity->input_version ?? 0) === (int) ($attempt->input_version ?? 0)
            && ($kind instanceof EditorialActivityKind ? $kind->value : (string) $kind) === EditorialActivityKind::ResearchChallenge->value
            && ($status instanceof EditorialActivityStatus ? $status->value : (string) $status) === EditorialActivityStatus::Completed->value
            && $this->activityMatchesCurrentApproval($activity, $attempt, EditorialActivityKind::ResearchChallenge);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function voiceContext(PublishingAttempt $attempt, array $input): array
    {
        $contextValue = $attempt->getAttribute('interview_context');
        $context = is_array($contextValue) ? $contextValue : [];
        $selected = array_key_exists('voice_sample_ids', $input) ? $input['voice_sample_ids'] : data_get($context, 'voice_sample_ids', []);
        $samples = array_key_exists('voice_samples', $input) ? $input['voice_samples'] : data_get($context, 'voice_samples', []);
        $notes = array_key_exists('voice_notes', $context) ? $context['voice_notes'] : null;

        return [
            'selected_voice_sample_ids' => is_array($selected) ? array_values(array_map('intval', $selected)) : [],
            'samples' => $this->boundedVoiceSamples($samples),
            'notes' => is_string($notes) ? $notes : null,
        ];
    }

    /** @param array<string, mixed> $input */
    private function persistSelectedEvidenceContext(PublishingAttempt $attempt, array $input): void
    {
        if (! array_key_exists('selected_evidence_source_ids', $input)) {
            return;
        }

        $ids = is_array($input['selected_evidence_source_ids']) ? $input['selected_evidence_source_ids'] : [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));

        $contextValue = $attempt->getAttribute('interview_context');
        $context = is_array($contextValue) ? $contextValue : [];
        $context['selected_evidence_source_ids'] = $ids;

        $attempt->forceFill(['interview_context' => $context])->save();
    }

    /** @param array<string, mixed> $input */
    private function persistVoiceContext(PublishingAttempt $attempt, array $input): void
    {
        if (! array_key_exists('voice_sample_ids', $input) && ! array_key_exists('voice_samples', $input)) {
            return;
        }

        $contextValue = $attempt->getAttribute('interview_context');
        $context = is_array($contextValue) ? $contextValue : [];
        if (array_key_exists('voice_sample_ids', $input)) {
            $ids = is_array($input['voice_sample_ids']) ? array_values(array_map('intval', $input['voice_sample_ids'])) : [];
            $context['voice_sample_ids'] = $ids;
        }
        if (array_key_exists('voice_samples', $input)) {
            $context['voice_samples'] = $this->boundedVoiceSamples($input['voice_samples']);
        }

        $attempt->forceFill(['interview_context' => $context])->save();
    }

    /**
     * @return list<array{id: int, excerpt: string}>
     */
    private function boundedVoiceSamples(mixed $samples): array
    {
        if (! is_array($samples)) {
            return [];
        }

        $bounded = [];
        foreach ($samples as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $id = $sample['id'] ?? null;
            $excerpt = $sample['excerpt'] ?? null;
            if (is_numeric($id) && is_string($excerpt) && trim($excerpt) !== '') {
                $bounded[] = ['id' => (int) $id, 'excerpt' => mb_substr($excerpt, 0, 1200)];
            }
        }

        return $bounded;
    }

    /**
     * @return array<string, mixed>
     */
    private function idempotencyPayload(PublishingAttempt $attempt, EditorialActivityKind $kind, ?ArticleRevision $revision, ?string $batchKey, string $promptHash): array
    {
        $payload = [
            'attempt_id' => $attempt->id,
            'kind' => $kind->value,
            'review_cycle' => (int) $attempt->review_cycle,
            'batch_key' => $batchKey,
        ];

        if (in_array($kind, [
            EditorialActivityKind::ReviewFacts,
            EditorialActivityKind::ReviewVoice,
            EditorialActivityKind::ReviewBuyer,
            EditorialActivityKind::Reconciliation,
            EditorialActivityKind::Recheck,
        ], true)) {
            $payload['revision_id'] = $revision?->id;
            $payload['revision_hash'] = $revision?->content_hash;

            return $payload;
        }

        $payload['prompt_hash'] = $promptHash;

        return $payload;
    }

    private function completedDraftExistsForRevision(PublishingAttempt $attempt, int $revisionId): bool
    {
        return EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('kind', EditorialActivityKind::Draft->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->get()
            ->contains(function (EditorialActivity $activity) use ($attempt, $revisionId): bool {
                $proposal = $activity->getAttribute('proposal');
                $targetsRevision = (int) ($activity->revision_id ?? 0) === $revisionId
                    || (is_array($proposal) && (int) ($proposal['applied_revision_id'] ?? 0) === $revisionId);

                if (! $targetsRevision || ! $this->activityMatchesCurrentApproval($activity, $attempt, EditorialActivityKind::Draft)) {
                    return false;
                }

                return (int) ($activity->review_cycle ?? 0) === (int) $attempt->review_cycle
                    || $this->isExplicitReviewRestartDraft($attempt, $activity, $revisionId);
            });
    }

    private function isExplicitReviewRestartDraft(PublishingAttempt $attempt, EditorialActivity $draft, int $revisionId): bool
    {
        if ((int) ($draft->review_cycle ?? 0) >= (int) $attempt->review_cycle) {
            return false;
        }

        return EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $draft->review_cycle)
            ->where('kind', EditorialActivityKind::Reconciliation->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->where('revision_id', $revisionId)
            ->exists();
    }

    private function completedActivityExists(PublishingAttempt $attempt, EditorialActivityKind $kind, ?int $revisionId = null): bool
    {
        $query = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', $kind->value)
            ->where('status', EditorialActivityStatus::Completed->value);

        if ($revisionId !== null) {
            $query->where('revision_id', $revisionId);
        }

        return $query->get()->contains(fn (EditorialActivity $activity): bool => $this->activityMatchesCurrentApproval($activity, $attempt, $kind));
    }

    private function hasCurrentActiveApproval(PublishingAttempt $attempt, ApprovalKind $kind): bool
    {
        return $this->activeApprovalHash($attempt, $kind) !== null;
    }

    private function activeApprovalHash(PublishingAttempt $attempt, ApprovalKind $kind): ?string
    {
        $inputHash = $this->approvalInputHashFor($attempt, $kind);

        $approval = EditorialApproval::query()
            ->where('attempt_id', $attempt->id)
            ->where('kind', $kind->value)
            ->where('input_hash', $inputHash)
            ->whereNull('invalidated_at')
            ->first();

        return $approval instanceof EditorialApproval ? (string) $approval->input_hash : null;
    }

    private function activityMatchesCurrentApproval(EditorialActivity $activity, PublishingAttempt $attempt, EditorialActivityKind $kind): bool
    {
        $approval = match ($kind) {
            EditorialActivityKind::ResearchChallenge, EditorialActivityKind::Plan => ApprovalKind::Angle,
            EditorialActivityKind::Draft, EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer, EditorialActivityKind::Reconciliation, EditorialActivityKind::Recheck => ApprovalKind::Plan,
            EditorialActivityKind::Interview => null,
        };

        if (! $approval instanceof ApprovalKind) {
            return true;
        }

        $input = $activity->getAttribute('input');
        $hashes = is_array($input) && is_array(data_get($input, 'approval_hashes')) ? data_get($input, 'approval_hashes') : [];
        $activityHash = $hashes[$approval->value] ?? null;
        $currentHash = $this->activeApprovalHash($attempt, $approval);

        return is_string($activityHash) && is_string($currentHash) && hash_equals($currentHash, $activityHash);
    }

    private function approvalInputHashFor(PublishingAttempt $attempt, ApprovalKind $kind): string
    {
        return match ($kind) {
            ApprovalKind::Angle => $this->fingerprint->hash([
                'kind' => $kind->value,
                'attempt_id' => $attempt->id,
                'brief' => $attempt->brief ?? [],
                'angle' => $attempt->angle ?? [],
                'owner_context' => $this->approvalOwnerContext($attempt),
                'input_version' => $attempt->input_version,
            ]),
            ApprovalKind::Plan => $this->fingerprint->hash([
                'kind' => $kind->value,
                'attempt_id' => $attempt->id,
                'brief' => $attempt->brief ?? [],
                'angle' => $attempt->angle ?? [],
                'owner_context' => $this->approvalOwnerContext($attempt),
                'plan' => $attempt->plan ?? [],
                'input_version' => $attempt->input_version,
            ]),
            ApprovalKind::Release => throw new RuntimeException('Release approval does not start agent activity.'),
        };
    }

    private function stageValue(PublishingAttempt $attempt): string
    {
        $stage = $attempt->getAttribute('stage');

        return $stage instanceof EditorialStage ? $stage->value : (string) $stage;
    }

    private function lockedAttempt(PublishingAttempt|int $attempt): PublishingAttempt
    {
        $attemptId = $attempt instanceof PublishingAttempt ? $attempt->getKey() : $attempt;

        return PublishingAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
    }
}
