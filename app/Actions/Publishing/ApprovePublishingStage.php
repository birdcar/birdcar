<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\ArticleRelease;
use App\Models\EditorialApproval;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\PublishingFingerprint;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovePublishingStage
{
    public function __construct(private PublishingFingerprint $fingerprint) {}

    public function approve(
        User $actor,
        PublishingAttempt|int $attempt,
        ApprovalKind $kind,
        string $expectedInputHash,
        ?int $revisionId = null,
        ?int $releaseId = null,
    ): EditorialApproval {
        $this->authorize($actor, PublishingPermission::Approve->value);

        if ($kind === ApprovalKind::Release) {
            throw new RuntimeException('Release approvals must be managed through release packages.');
        }

        /** @var EditorialApproval $approval */
        $approval = DB::transaction(function () use ($actor, $attempt, $kind, $expectedInputHash, $revisionId, $releaseId): EditorialApproval {
            $lockedAttempt = $this->lockedAttempt($attempt);
            $this->ensureApprovalIsAllowed($lockedAttempt, $kind);
            $actualHash = $this->inputHashFor($lockedAttempt, $kind, $revisionId, $releaseId);

            if (! hash_equals($actualHash, $expectedInputHash)) {
                throw new RuntimeException('The approval input is stale.');
            }

            EditorialApproval::query()
                ->where('attempt_id', $lockedAttempt->id)
                ->where('kind', $kind->value)
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => now()]);

            $approval = EditorialApproval::create([
                'attempt_id' => $lockedAttempt->id,
                'kind' => $kind,
                'input_hash' => $actualHash,
                'revision_id' => $revisionId,
                'release_id' => $releaseId,
                'user_id' => $actor->id,
                'approved_at' => now(),
            ]);

            $lockedAttempt->forceFill(['stage' => $this->nextStage($kind)])->save();

            return $approval;
        });

        return $approval;
    }

    public function inputHashFor(PublishingAttempt $attempt, ApprovalKind $kind, ?int $revisionId = null, ?int $releaseId = null): string
    {
        $attempt = $attempt->fresh() ?? $attempt;

        return match ($kind) {
            ApprovalKind::Angle => $this->fingerprint->hash([
                'kind' => $kind->value,
                'attempt_id' => $attempt->id,
                'brief' => $attempt->brief ?? [],
                'angle' => $attempt->angle ?? [],
                'input_version' => $attempt->input_version,
            ]),
            ApprovalKind::Plan => $this->fingerprint->hash([
                'kind' => $kind->value,
                'attempt_id' => $attempt->id,
                'brief' => $attempt->brief ?? [],
                'angle' => $attempt->angle ?? [],
                'plan' => $attempt->plan ?? [],
                'input_version' => $attempt->input_version,
            ]),
            ApprovalKind::Release => $this->releaseHash($attempt, $revisionId, $releaseId),
        };
    }

    private function releaseHash(PublishingAttempt $attempt, ?int $revisionId, ?int $releaseId): string
    {
        if ($releaseId === null) {
            throw new RuntimeException('Release approvals require a prepared release package.');
        }

        $release = ArticleRelease::query()->findOrFail($releaseId);

        if ((int) $release->attempt_id !== (int) $attempt->id) {
            throw new RuntimeException('Release approval cannot cross publishing attempts.');
        }

        if ((int) $release->article_id !== (int) $attempt->article_id) {
            throw new RuntimeException('Release approval cannot cross articles.');
        }

        if ($revisionId !== null && (int) $release->revision_id !== (int) $revisionId) {
            throw new RuntimeException('Release approval must match the prepared package revision.');
        }

        return (string) $release->release_hash;
    }

    private function nextStage(ApprovalKind $kind): EditorialStage
    {
        return match ($kind) {
            ApprovalKind::Angle => EditorialStage::Drafting,
            ApprovalKind::Plan => EditorialStage::InReview,
            ApprovalKind::Release => EditorialStage::Approved,
        };
    }

    private function ensureApprovalIsAllowed(PublishingAttempt $attempt, ApprovalKind $kind): void
    {
        if ($attempt->paused_at !== null || $attempt->parked_at !== null || $attempt->abandoned_at !== null) {
            throw new RuntimeException('Blocked publishing attempts cannot receive approvals.');
        }

        match ($kind) {
            ApprovalKind::Angle => $this->ensureStage($attempt, EditorialStage::Developing, 'Angle approval requires a developing attempt.'),
            ApprovalKind::Plan => $this->ensurePrerequisiteApproval($attempt, [EditorialStage::Drafting], ApprovalKind::Angle, 'Plan approval requires an active angle approval.'),
            ApprovalKind::Release => $this->ensurePrerequisiteApproval($attempt, [EditorialStage::InReview, EditorialStage::Approved, EditorialStage::Scheduled], ApprovalKind::Plan, 'Release approval requires an active plan approval.'),
        };
    }

    private function ensureStage(PublishingAttempt $attempt, EditorialStage $stage, string $message): void
    {
        if ($this->stageValue($attempt) !== $stage->value) {
            throw new RuntimeException($message);
        }
    }

    /**
     * @param  list<EditorialStage>  $stages
     */
    private function ensurePrerequisiteApproval(PublishingAttempt $attempt, array $stages, ApprovalKind $prerequisite, string $message): void
    {
        $allowedStageValues = array_map(static fn (EditorialStage $stage): string => $stage->value, $stages);

        if (! in_array($this->stageValue($attempt), $allowedStageValues, true)) {
            throw new RuntimeException($message);
        }

        $inputHash = $this->inputHashFor($attempt, $prerequisite);

        $approved = EditorialApproval::query()
            ->where('attempt_id', $attempt->id)
            ->where('kind', $prerequisite->value)
            ->where('input_hash', $inputHash)
            ->whereNull('invalidated_at')
            ->exists();

        if (! $approved) {
            throw new RuntimeException($message);
        }
    }

    private function stageValue(PublishingAttempt $attempt): string
    {
        $stage = $attempt->getAttribute('stage');

        return $stage instanceof EditorialStage ? $stage->value : (string) $stage;
    }

    private function lockedAttempt(PublishingAttempt|int $attempt): PublishingAttempt
    {
        $attemptId = $attempt instanceof PublishingAttempt ? $attempt->getKey() : $attempt;

        return PublishingAttempt::query()
            ->whereKey($attemptId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException('This user is not allowed to approve publishing work.');
        }
    }
}
