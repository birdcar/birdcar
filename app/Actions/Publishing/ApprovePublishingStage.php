<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\ArticleRelease;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\PublishingFingerprint;
use App\Settings\PublishingAgentSettings;
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
            $actualHash = $this->inputHashFor($lockedAttempt, $kind, $revisionId, $releaseId);

            if (! hash_equals($actualHash, $expectedInputHash)) {
                throw new RuntimeException('The approval input is stale.');
            }

            $this->ensureApprovalIsAllowed($lockedAttempt, $kind);

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
            $lockedAttempt->refresh();

            if (! app(PublishingAgentSettings::class)->paused) {
                if ($kind === ApprovalKind::Angle) {
                    app(StartEditorialActivity::class)->start($actor, $lockedAttempt, EditorialActivityKind::ResearchChallenge, [], 'research-'.$lockedAttempt->review_cycle);
                }
                if ($kind === ApprovalKind::Plan) {
                    app(StartEditorialActivity::class)->start($actor, $lockedAttempt, EditorialActivityKind::Draft, [], 'draft-'.$lockedAttempt->review_cycle);
                }
            }

            return $approval;
        });

        return $approval;
    }

    public function inputHashFor(PublishingAttempt $attempt, ApprovalKind $kind, ?int $revisionId = null, ?int $releaseId = null): string
    {
        return $this->snapshotInputHashFor($attempt->fresh() ?? $attempt, $kind, $revisionId, $releaseId);
    }

    public function snapshotInputHashFor(PublishingAttempt $attempt, ApprovalKind $kind, ?int $revisionId = null, ?int $releaseId = null): string
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
            ApprovalKind::Release => $this->releaseHash($attempt, $revisionId, $releaseId),
        };
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
            ApprovalKind::Angle => $this->ensureAngleApprovalRequirements($attempt),
            ApprovalKind::Plan => $this->ensurePlanApprovalRequirements($attempt),
            ApprovalKind::Release => $this->ensurePrerequisiteApproval($attempt, [EditorialStage::InReview, EditorialStage::Approved, EditorialStage::Scheduled], ApprovalKind::Plan, 'Release approval requires an active plan approval.'),
        };
    }

    private function ensureAngleApprovalRequirements(PublishingAttempt $attempt): void
    {
        $this->ensureStage($attempt, EditorialStage::Developing, 'Angle approval requires a developing attempt.');

        $context = $attempt->getAttribute('interview_context');
        $context = is_array($context) ? $context : [];
        $questions = is_array($context['questions'] ?? null) ? $context['questions'] : [];
        if ($questions !== []) {
            $answers = $context['answers'] ?? null;
            $answered = false;
            if (is_string($answers)) {
                $answered = trim($answers) !== '';
            } elseif (is_array($answers)) {
                $answered = collect($questions)->every(function (mixed $question, int|string $key) use ($answers): bool {
                    $answer = $answers[$key] ?? (is_array($question) ? ($answers[$question['id'] ?? ''] ?? null) : null);

                    return is_string($answer) && trim($answer) !== '';
                });
            }

            if (! $answered) {
                throw new RuntimeException('Angle approval requires answers to the latest interview questions.');
            }
        }

        $this->ensureCurrentAngleSelected($attempt, $context);
    }

    /** @param array<string, mixed> $context */
    private function ensureCurrentAngleSelected(PublishingAttempt $attempt, array $context): void
    {
        $angleValue = $attempt->getAttribute('angle');
        $angle = is_array($angleValue) ? $angleValue : [];
        $options = is_array($context['angle_options'] ?? null) ? $context['angle_options'] : [];
        if ($angle === [] && $options !== []) {
            throw new RuntimeException('Angle approval requires a selected angle option or explicit human angle.');
        }

        if ($options === []) {
            if (($angle['source'] ?? null) === 'human' || ($angle['source'] ?? null) === 'owner' || ($angle['explicit_human_angle'] ?? false) === true) {
                return;
            }

            $briefValue = $attempt->getAttribute('brief');
            $brief = is_array($briefValue) ? $briefValue : [];
            if (! array_key_exists('latest_interview_activity_id', $context) && ($context === [] || $brief !== [])) {
                return;
            }

            throw new RuntimeException('Angle approval requires a selected angle option or explicit human angle.');
        }

        $selectedKey = $context['selected_angle_option'] ?? null;
        if (! is_scalar($selectedKey) || ! array_key_exists((string) $selectedKey, $options)) {
            throw new RuntimeException('Angle approval requires a current selected angle option.');
        }

        $selected = $options[(string) $selectedKey];
        if (! is_array($selected) || $selected !== $angle) {
            throw new RuntimeException('Angle approval requires the current selected angle option.');
        }
    }

    private function ensurePlanApprovalRequirements(PublishingAttempt $attempt): void
    {
        $this->ensurePrerequisiteApproval($attempt, [EditorialStage::Drafting], ApprovalKind::Angle, 'Plan approval requires an active angle approval.');

        if (! $this->completedCurrentCycleActivityExists($attempt, EditorialActivityKind::ResearchChallenge)) {
            throw new RuntimeException('Plan approval requires completed research/challenge work for the current review cycle.');
        }

        if (! $this->hasReviewedPlanForCurrentInput($attempt)) {
            throw new RuntimeException('Plan approval requires a reviewed outline and visual plan for the current input.');
        }
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

    private function completedCurrentCycleActivityExists(PublishingAttempt $attempt, EditorialActivityKind $kind): bool
    {
        return EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', $kind->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->where('input_version', $attempt->input_version)
            ->get()
            ->contains(fn (EditorialActivity $activity): bool => $this->activityMatchesCurrentApproval($activity, $attempt, $kind));
    }

    private function hasReviewedPlanForCurrentInput(PublishingAttempt $attempt): bool
    {
        $planValue = $attempt->getAttribute('plan');
        $plan = is_array($planValue) ? $planValue : [];
        $outline = array_key_exists('outline', $plan) ? $plan['outline'] : null;
        $visualPlan = array_key_exists('visualPlan', $plan) ? $plan['visualPlan'] : null;

        if (! $this->isFilledListOrText($outline) || ! $this->isFilledListOrText($visualPlan)) {
            return false;
        }

        $source = array_key_exists('source', $plan) ? $plan['source'] : 'human';
        if ($source === 'agent') {
            return EditorialActivity::query()
                ->where('attempt_id', $attempt->id)
                ->where('review_cycle', (int) $attempt->review_cycle)
                ->where('kind', EditorialActivityKind::Plan->value)
                ->where('status', EditorialActivityStatus::Completed->value)
                ->where('input_version', $attempt->input_version)
                ->get()
                ->contains(fn (EditorialActivity $activity): bool => $this->activityMatchesCurrentApproval($activity, $attempt, EditorialActivityKind::Plan));
        }

        return true;
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

    private function activeApprovalHash(PublishingAttempt $attempt, ApprovalKind $kind): ?string
    {
        $inputHash = $this->inputHashFor($attempt, $kind);

        $approval = EditorialApproval::query()
            ->where('attempt_id', $attempt->id)
            ->where('kind', $kind->value)
            ->where('input_hash', $inputHash)
            ->whereNull('invalidated_at')
            ->first();

        return $approval instanceof EditorialApproval ? (string) $approval->input_hash : null;
    }

    private function isFilledListOrText(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return is_array($value) && $value !== [];
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
