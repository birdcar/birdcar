<?php

namespace App\Services\Publishing;

use App\Models\Article;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use Illuminate\Support\Collection;

/**
 * A piece's live agent status for its board card, built only from what BoardColumn::relations() eager-loads. When
 * several states apply, failed wins over running, running over waiting, and waiting over idle.
 */
final readonly class PieceStatus
{
    public const RUNNING = 'running';

    public const WAITING = 'waiting';

    public const FAILED = 'failed';

    public const IDLE = 'idle';

    public function __construct(public string $state, public string $label, public ?string $reason = null) {}

    public static function for(Article $piece, int $viewerId, bool $agentsPaused): self
    {
        $attempt = $piece->currentAttempt;

        if (! $attempt instanceof PublishingAttempt
            || $attempt->abandoned_at !== null
            || $attempt->parked_at !== null
            || in_array($attempt->stage, [EditorialStage::Published, EditorialStage::Abandoned], true)) {
            return self::idle();
        }

        if ($attempt->paused_at !== null) {
            return new self(self::WAITING, 'Paused', $attempt->pause_reason ?: 'Resume the piece from its workspace.');
        }

        /** @var Collection<int, EditorialActivity> $activities */
        $activities = $attempt->editorialActivities->sortByDesc('id')->values();

        $latest = $activities->first(fn (EditorialActivity $activity): bool => $activity->status !== EditorialActivityStatus::Stale);
        if ($latest instanceof EditorialActivity && in_array($latest->status, [EditorialActivityStatus::Failed, EditorialActivityStatus::Paused], true)) {
            return new self(
                self::FAILED,
                self::activityLabel($latest->kind).($latest->status === EditorialActivityStatus::Failed ? ' failed' : ' paused'),
                $latest->pause_reason ?? $latest->error_reason ?? 'Open the workspace for details.',
            );
        }

        $working = $activities->first(fn (EditorialActivity $activity): bool => $activity->status === EditorialActivityStatus::Running)
            ?? $activities->first(fn (EditorialActivity $activity): bool => $activity->status === EditorialActivityStatus::Pending);
        if ($working instanceof EditorialActivity) {
            $label = match (true) {
                $working->status === EditorialActivityStatus::Running => 'Running',
                $agentsPaused => 'Queued; agents paused',
                default => 'Queued',
            };

            return new self(self::RUNNING, $label, self::activityLabel($working->kind));
        }

        $owed = self::owedDecision($piece, $attempt, $activities, $viewerId);

        return $owed === null ? self::idle() : new self(self::WAITING, 'Waiting on you', $owed);
    }

    public static function idle(): self
    {
        return new self(self::IDLE, '');
    }

    public function isIdle(): bool
    {
        return $this->state === self::IDLE;
    }

    public static function activityLabel(EditorialActivityKind $kind): string
    {
        return match ($kind) {
            EditorialActivityKind::Interview => 'Interview',
            EditorialActivityKind::ResearchChallenge => 'Research challenge',
            EditorialActivityKind::Plan => 'Plan',
            EditorialActivityKind::Draft => 'Draft',
            EditorialActivityKind::LintRepair => 'Voice-rule repair',
            EditorialActivityKind::ReviewFacts => 'Fact review',
            EditorialActivityKind::ReviewVoice => 'Voice review',
            EditorialActivityKind::ReviewBuyer => 'Buyer review',
            EditorialActivityKind::Reconciliation => 'Reconciliation',
            EditorialActivityKind::Recheck => 'Recheck',
            EditorialActivityKind::Package => 'Packaging',
            EditorialActivityKind::DraftKit => 'Kit draft',
            EditorialActivityKind::ReviewKit => 'Kit review',
        };
    }

    /**
     * The decision the owner owes, mirroring the Admin home's "Your decision" rules plus the package and kit
     * gates the board shows. Open findings come from EditorialFinding::awaitingOwnerDecision() on both surfaces.
     *
     * @param  Collection<int, EditorialActivity>  $activities  the latest activity of each kind, newest first
     */
    private static function owedDecision(Article $piece, PublishingAttempt $attempt, Collection $activities, int $viewerId): ?string
    {
        $awaitingAnswers = $activities->contains(fn (EditorialActivity $activity): bool => $activity->kind === EditorialActivityKind::Interview
            && $activity->status === EditorialActivityStatus::AwaitingApproval
            && (int) $activity->initiating_user_id === $viewerId);
        if ($awaitingAnswers) {
            return 'The interview is waiting for your answers.';
        }

        return match ($attempt->stage) {
            EditorialStage::Developing => self::angleIsReady($attempt) && ! self::hasActiveApproval($attempt, ApprovalKind::Angle)
                ? 'Choose and approve the angle.'
                : null,
            EditorialStage::Drafting => self::planIsReady($attempt, $activities) && ! self::hasActiveApproval($attempt, ApprovalKind::Plan)
                ? 'Review and approve the plan.'
                : null,
            EditorialStage::InReview => match (true) {
                (bool) $attempt->getAttribute('has_open_findings') => 'Decide the open findings.',
                $piece->isKit() && self::kitIsReady($activities) && ! self::hasActiveApproval($attempt, ApprovalKind::Kit) => 'Approve this kit piece.',
                (bool) $attempt->getAttribute('has_unaccepted_package') => 'Accept or edit the package.',
                (bool) $attempt->getAttribute('has_prepared_release') => 'Approve the prepared release.',
                default => null,
            },
            default => null,
        };
    }

    private static function angleIsReady(PublishingAttempt $attempt): bool
    {
        return filled(data_get($attempt->interview_context, 'angle_options')) || filled($attempt->angle);
    }

    /**
     * @param  Collection<int, EditorialActivity>  $activities
     */
    private static function planIsReady(PublishingAttempt $attempt, Collection $activities): bool
    {
        if (blank(data_get($attempt->plan, 'outline')) || blank(data_get($attempt->plan, 'visualPlan'))) {
            return false;
        }

        if (data_get($attempt->plan, 'source') !== 'agent') {
            return true;
        }

        $plan = $activities->first(fn (EditorialActivity $activity): bool => $activity->kind === EditorialActivityKind::Plan);

        return $plan?->status === EditorialActivityStatus::Completed && (int) data_get($attempt->plan, 'activity_id') === (int) $plan->id;
    }

    /**
     * @param  Collection<int, EditorialActivity>  $activities
     */
    private static function kitIsReady(Collection $activities): bool
    {
        $completed = fn (EditorialActivityKind $kind): bool => $activities->first(fn (EditorialActivity $activity): bool => $activity->kind === $kind)?->status === EditorialActivityStatus::Completed;

        return $completed(EditorialActivityKind::DraftKit) && $completed(EditorialActivityKind::ReviewKit);
    }

    private static function hasActiveApproval(PublishingAttempt $attempt, ApprovalKind $kind): bool
    {
        return $attempt->approvals->contains(fn (EditorialApproval $approval): bool => $approval->kind === $kind && $approval->invalidated_at === null);
    }
}
