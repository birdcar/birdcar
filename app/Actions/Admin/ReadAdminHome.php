<?php

namespace App\Actions\Admin;

use App\Actions\Publishing\ApprovePublishingStage;
use App\Authorization\Admin\Permission as AdminPermission;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** @phpstan-type HomeItem array{article: Article, reason: string, action: string, updated_at: CarbonInterface} */
class ReadAdminHome
{
    public function __construct(private ApprovePublishingStage $approvals) {}

    /** @return array{blocked: list<HomeItem>, decisions: list<HomeItem>, continuing: list<HomeItem>} */
    public function forUser(User $actor): array
    {
        Gate::forUser($actor)->authorize(AdminPermission::View->value);
        Gate::forUser($actor)->authorize(PublishingPermission::View->value);

        $items = ['blocked' => [], 'decisions' => [], 'continuing' => []];
        $canDevelop = $actor->can(PublishingPermission::Develop->value);
        $canApprove = $actor->can(PublishingPermission::Approve->value);
        $canPublish = $actor->can(PublishingPermission::Publish->value);

        $articles = Article::query()
            ->where('author_id', $actor->id)
            ->whereHas('currentAttempt', fn ($query) => $query
                ->whereColumn('publishing_attempts.article_id', 'articles.id')
                ->whereNull('abandoned_at')
                ->whereNotIn('stage', [EditorialStage::Published, EditorialStage::Abandoned]))
            ->with([
                'workingRevision:id,article_id,content_hash,metadata,updated_at',
                'currentAttempt' => fn ($query) => $query->withExists([
                    'editorialFindings as has_open_findings' => fn ($findings) => $findings
                        ->whereColumn('editorial_findings.review_cycle', 'publishing_attempts.review_cycle')
                        ->whereNull('stale_at')
                        ->whereNull('disposition')
                        ->whereNotIn('reconciliation_state', ['duplicate', 'superseded'])
                        ->whereHas('article', fn ($article) => $article
                            ->whereColumn('articles.working_revision_id', 'editorial_findings.revision_id')
                            ->whereHas('workingRevision', fn ($revision) => $revision->whereColumn('article_revisions.content_hash', 'editorial_findings.input_hash'))),
                    'releases as has_approved_release' => fn ($releases) => $releases
                        ->where('status', 'approved')
                        ->whereNull('withdrawn_at')
                        ->whereHas('article', fn ($article) => $article->whereColumn('articles.working_revision_id', 'article_releases.revision_id')),
                    'releases as has_prepared_release' => fn ($releases) => $releases
                        ->where('status', 'prepared')
                        ->whereNull('withdrawn_at')
                        ->whereHas('article', fn ($article) => $article->whereColumn('articles.working_revision_id', 'article_releases.revision_id')),
                ]),
                'currentAttempt.approvals' => fn ($query) => $query->whereNull('invalidated_at'),
                'currentAttempt.editorialActivities' => fn ($query) => $query
                    ->select(['id', 'article_id', 'attempt_id', 'initiating_user_id', 'kind', 'status', 'stage', 'input_version', 'revision_id', 'revision_hash', 'review_cycle', 'input', 'updated_at'])
                    ->whereNotExists(fn ($newer) => $newer->select(DB::raw(1))
                        ->from('editorial_activities as newer')
                        ->whereColumn('newer.attempt_id', 'editorial_activities.attempt_id')
                        ->whereColumn('newer.kind', 'editorial_activities.kind')
                        ->whereColumn('newer.id', '>', 'editorial_activities.id'))
                    ->orderByDesc('id'),
            ])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->lazy(50);

        foreach ($articles as $article) {
            $attempt = $article->currentAttempt;
            if (! $attempt instanceof PublishingAttempt) {
                continue;
            }

            $activities = $attempt->editorialActivities->filter(fn (EditorialActivity $activity): bool => $this->isCurrentActivity($activity, $attempt, $article));
            $stopped = $activities->first(fn (EditorialActivity $activity): bool => in_array($activity->getAttribute('status'), [EditorialActivityStatus::Paused, EditorialActivityStatus::Failed], true));
            $running = $activities->contains(fn (EditorialActivity $activity): bool => in_array($activity->getAttribute('status'), [EditorialActivityStatus::Pending, EditorialActivityStatus::Running], true));
            $stage = $attempt->getAttribute('stage');
            $bucket = 'continuing';
            $reason = $running ? 'Agent work is in progress.' : $this->stageLabel($stage);
            $action = 'Open workspace';
            $updatedAt = $article->updated_at->max($attempt->updated_at);

            if ($attempt->paused_at !== null || $attempt->parked_at !== null || $stopped !== null) {
                $bucket = 'blocked';
                $reason = match (true) {
                    $attempt->paused_at !== null => 'Publishing is paused. Review the pause reason in the workspace.',
                    $attempt->parked_at !== null => 'This piece is parked. Review it when you are ready to continue.',
                    $stopped?->getAttribute('status') === EditorialActivityStatus::Failed => 'An agent activity failed. Inspect the failure before retrying.',
                    default => 'An agent activity is paused. Review what it needs to continue.',
                };
                $action = 'Inspect workspace';
                $updatedAt = $stopped !== null ? $updatedAt->max($stopped->updated_at) : $updatedAt;
            } elseif (! $running) {
                $contextValue = $attempt->getAttribute('interview_context');
                $context = is_array($contextValue) ? $contextValue : [];
                $interview = $activities->firstWhere('kind', EditorialActivityKind::Interview);
                $plan = $activities->firstWhere('kind', EditorialActivityKind::Plan);
                $hasInterview = $interview?->getAttribute('status') === EditorialActivityStatus::Completed
                    && (int) ($context['latest_interview_activity_id'] ?? 0) === (int) $interview->id;
                $hasPlan = filled(data_get($attempt->plan, 'outline')) && filled(data_get($attempt->plan, 'visualPlan'))
                    && (data_get($attempt->plan, 'source') !== 'agent'
                        || ($plan?->getAttribute('status') === EditorialActivityStatus::Completed && (int) data_get($attempt->plan, 'activity_id') === (int) $plan->id));

                $awaitingAuthor = $activities->contains(fn (EditorialActivity $activity): bool => $activity->status === EditorialActivityStatus::AwaitingApproval
                    && (int) $activity->initiating_user_id === (int) $actor->id);
                $decision = match (true) {
                    $canDevelop && $awaitingAuthor => ['The interview agent is waiting for your answers.', 'Answer interview'],
                    ($canDevelop || $canApprove) && $stage === EditorialStage::Developing && ($hasInterview || filled($attempt->angle)) => ['Review the interview and angle before development continues.', 'Review brief'],
                    $canApprove && $stage === EditorialStage::Drafting && $hasPlan => ['The outline and visual plan need your review.', 'Review plan'],
                    $canApprove && $stage === EditorialStage::InReview && (bool) $attempt->getAttribute('has_open_findings') => ['Editorial findings are waiting for your decision.', 'Review findings'],
                    $canApprove && $stage === EditorialStage::InReview && (bool) $attempt->getAttribute('has_prepared_release') => ['A prepared release package needs your review.', 'Review release'],
                    $canPublish && $stage === EditorialStage::Approved && (bool) $attempt->getAttribute('has_approved_release') => ['An approved release is waiting for delivery.', 'Review delivery'],
                    default => null,
                };

                if ($decision !== null) {
                    $bucket = 'decisions';
                    [$reason, $action] = $decision;
                }
            }

            if (count($items[$bucket]) < 5) {
                $items[$bucket][] = ['article' => $article, 'reason' => $reason, 'action' => $action, 'updated_at' => $updatedAt];
            }

            if (count($items['blocked']) === 5 && count($items['decisions']) === 5 && count($items['continuing']) === 5) {
                break;
            }
        }

        return $items;
    }

    private function isCurrentActivity(EditorialActivity $activity, PublishingAttempt $attempt, Article $article): bool
    {
        if ((int) $activity->article_id !== (int) $article->id
            || $activity->review_cycle !== $attempt->review_cycle
            || (int) $activity->input_version !== (int) $attempt->input_version
            || $activity->stage !== $attempt->getAttribute('stage')->value
            || (int) $activity->revision_id !== (int) $article->working_revision_id
            || $activity->revision_hash !== $article->workingRevision?->content_hash) {
            return false;
        }

        if ($activity->getAttribute('kind') === EditorialActivityKind::Plan
            && $activity->getAttribute('status') !== EditorialActivityStatus::Completed
            && data_get($activity->input, 'plan', []) !== ($attempt->getAttribute('plan') ?? [])) {
            return false;
        }

        $prerequisite = match ($activity->getAttribute('kind')) {
            EditorialActivityKind::Interview => null,
            EditorialActivityKind::ResearchChallenge, EditorialActivityKind::Plan => ApprovalKind::Angle,
            default => ApprovalKind::Plan,
        };

        if ($prerequisite === null) {
            return true;
        }

        $hash = $this->approvals->snapshotInputHashFor($attempt, $prerequisite);

        return data_get($activity->input, 'approval_hashes.'.$prerequisite->value) === $hash
            && $attempt->approvals->contains(fn (EditorialApproval $approval): bool => $approval->getAttribute('kind') === $prerequisite && $approval->input_hash === $hash);
    }

    private function stageLabel(EditorialStage $stage): string
    {
        return match ($stage) {
            EditorialStage::Developing => 'Developing the brief and angle.',
            EditorialStage::Drafting => 'Preparing the plan and manuscript.',
            EditorialStage::InReview => 'Writing and editorial review.',
            EditorialStage::Approved => 'Release approved. Open the workspace to deliver it.',
            EditorialStage::Scheduled => 'Publication is scheduled.',
            default => 'Open the workspace to continue.',
        };
    }
}
