<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\EditorialActivity;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdvancePublishingAttempt
{
    public function __construct(private WriteArticle $writeArticle) {}

    /**
     * @param  array<string, mixed>  $brief
     */
    public function develop(User $actor, Article|int $article, ?int $expectedRevisionId = null, array $brief = []): PublishingAttempt
    {
        $this->authorize($actor, PublishingPermission::Develop->value);

        /** @var PublishingAttempt $attempt */
        $attempt = DB::transaction(function () use ($actor, $article, $expectedRevisionId, $brief): PublishingAttempt {
            $lockedArticle = $this->lockedArticle($article);

            if ((int) ($lockedArticle->working_revision_id ?? 0) !== (int) ($expectedRevisionId ?? 0)) {
                throw new RuntimeException('The article has changed since develop was requested.');
            }

            if ($lockedArticle->current_attempt_id !== null) {
                $currentAttempt = PublishingAttempt::query()
                    ->whereKey($lockedArticle->current_attempt_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($currentAttempt->abandoned_at === null && ! in_array($this->stage($currentAttempt), [EditorialStage::Published, EditorialStage::Abandoned], true)) {
                    return $currentAttempt;
                }
            }

            $attempt = PublishingAttempt::create([
                'article_id' => $lockedArticle->id,
                'user_id' => $actor->id,
                'stage' => EditorialStage::Developing,
                'input_version' => $lockedArticle->working_revision_id,
                'brief' => $brief,
                'angle' => [],
                'plan' => [],
                'interview_context' => [],
                'review_cycle' => 1,
                'recheck_used' => false,
            ]);

            $lockedArticle->forceFill(['current_attempt_id' => $attempt->id])->save();

            return $attempt;
        });

        return $attempt;
    }

    public function pause(User $actor, PublishingAttempt|int $attempt, string $reason): PublishingAttempt
    {
        $this->authorize($actor, PublishingPermission::Develop->value);

        return $this->updateAttempt($attempt, [
            'paused_at' => now(),
            'pause_reason' => $reason,
        ]);
    }

    public function resume(User $actor, PublishingAttempt|int $attempt): PublishingAttempt
    {
        $this->authorize($actor, PublishingPermission::Develop->value);

        return $this->updateAttempt($attempt, [
            'paused_at' => null,
            'pause_reason' => null,
        ]);
    }

    public function park(User $actor, PublishingAttempt|int $attempt, string $reason): PublishingAttempt
    {
        $this->authorize($actor, PublishingPermission::Develop->value);

        return $this->updateAttempt($attempt, [
            'parked_at' => now(),
            'parked_reason' => $reason,
        ]);
    }

    public function abandon(User $actor, PublishingAttempt|int $attempt, string $reason): PublishingAttempt
    {
        $this->authorize($actor, PublishingPermission::Develop->value);

        return $this->updateAttempt($attempt, [
            'stage' => EditorialStage::Abandoned,
            'abandoned_at' => now(),
            'abandoned_reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $brief
     * @param  array<string, mixed>|null  $angle
     * @param  array<string, mixed>|null  $plan
     */
    public function rethink(User $actor, PublishingAttempt|int $attempt, ?array $brief = null, ?array $angle = null, ?array $plan = null): PublishingAttempt
    {
        $this->authorize($actor, PublishingPermission::Develop->value);

        /** @var PublishingAttempt $updated */
        $updated = DB::transaction(function () use ($attempt, $brief, $angle, $plan): PublishingAttempt {
            $attemptId = $this->attemptId($attempt);
            $lockedArticle = $this->lockedArticleForAttemptId($attemptId);
            $lockedAttempt = $this->lockedAttemptById($attemptId);
            $this->ensureAttemptCanBeMutated($lockedAttempt, $lockedArticle);
            $updates = [];
            $invalidatedKinds = [];

            if ($brief !== null) {
                $updates['brief'] = $brief;
                $invalidatedKinds = [ApprovalKind::Angle, ApprovalKind::Plan, ApprovalKind::Release];
            }

            if ($angle !== null) {
                $updates['angle'] = $angle;
                $invalidatedKinds = [ApprovalKind::Angle, ApprovalKind::Plan, ApprovalKind::Release];
            }

            if ($plan !== null) {
                $updates['plan'] = $plan;
                $invalidatedKinds = $invalidatedKinds === []
                    ? [ApprovalKind::Plan, ApprovalKind::Release]
                    : $invalidatedKinds;
            }

            if ($invalidatedKinds !== []) {
                $updates['stage'] = $this->earliestInvalidatedStage($lockedAttempt, $invalidatedKinds);
            }

            if ($updates !== []) {
                $lockedAttempt->forceFill($updates)->save();
            }

            if ($invalidatedKinds !== []) {
                $this->writeArticle->invalidateApprovals((int) $lockedAttempt->id, $invalidatedKinds);
                app(ManageArticleRelease::class)->withdrawScheduledReleasesForAttemptId((int) $lockedAttempt->id);
            }

            return $lockedAttempt->refresh();
        });

        return $updated;
    }

    public function selectAngleOption(User $actor, PublishingAttempt|int $attempt, string $optionKey): PublishingAttempt
    {
        $this->authorize($actor, PublishingPermission::Develop->value);

        /** @var PublishingAttempt $updated */
        $updated = DB::transaction(function () use ($attempt, $optionKey): PublishingAttempt {
            $attemptId = $this->attemptId($attempt);
            $lockedArticle = $this->lockedArticleForAttemptId($attemptId);
            $lockedAttempt = $this->lockedAttemptById($attemptId);
            $this->ensureAttemptCanBeMutated($lockedAttempt, $lockedArticle);

            $contextValue = $lockedAttempt->getAttribute('interview_context');
            $context = is_array($contextValue) ? $contextValue : [];
            $optionsValue = $context['angle_options'] ?? null;
            $options = is_array($optionsValue) ? $optionsValue : [];
            if (! array_key_exists($optionKey, $options)) {
                throw new RuntimeException('Selected angle option is no longer available.');
            }

            $selected = $options[$optionKey];
            if (! is_array($selected)) {
                throw new RuntimeException('Selected angle option is invalid.');
            }

            $context['selected_angle_option'] = $optionKey;
            $lockedAttempt->forceFill([
                'angle' => $selected,
                'interview_context' => $context,
                'stage' => $this->earliestInvalidatedStage($lockedAttempt, [ApprovalKind::Angle, ApprovalKind::Plan, ApprovalKind::Release]),
            ])->save();

            $this->writeArticle->invalidateApprovals((int) $lockedAttempt->id, [ApprovalKind::Angle, ApprovalKind::Plan, ApprovalKind::Release]);
            app(ManageArticleRelease::class)->withdrawScheduledReleasesForAttemptId((int) $lockedAttempt->id);

            return $lockedAttempt->refresh();
        });

        return $updated;
    }

    public function submitInterviewAnswers(User $actor, PublishingAttempt|int $attempt, string $answers): PublishingAttempt
    {
        $this->authorize($actor, PublishingPermission::Develop->value);

        /** @var PublishingAttempt $updated */
        $updated = DB::transaction(function () use ($actor, $attempt, $answers): PublishingAttempt {
            $attemptId = $this->attemptId($attempt);
            $lockedArticle = $this->lockedArticleForAttemptId($attemptId);
            $lockedAttempt = $this->lockedAttemptById($attemptId);
            $this->ensureAttemptCanBeMutated($lockedAttempt, $lockedArticle);

            $contextValue = $lockedAttempt->getAttribute('interview_context');
            $context = is_array($contextValue) ? $contextValue : [];
            $context['answers'] = $answers;
            $context['answered_interview_activity_id'] = $context['latest_interview_activity_id'] ?? null;
            $context['answered_at'] = now()->toISOString();
            $context['answered_by'] = $actor->id;

            $lockedAttempt->forceFill([
                'interview_context' => $context,
                'stage' => $this->earliestInvalidatedStage($lockedAttempt, [ApprovalKind::Angle, ApprovalKind::Plan, ApprovalKind::Release]),
            ])->save();

            $this->writeArticle->invalidateApprovals((int) $lockedAttempt->id, [ApprovalKind::Angle, ApprovalKind::Plan, ApprovalKind::Release]);
            app(ManageArticleRelease::class)->withdrawScheduledReleasesForAttemptId((int) $lockedAttempt->id);

            return $lockedAttempt->refresh();
        });

        return $updated;
    }

    /**
     * @param  list<ApprovalKind>  $invalidatedKinds
     */
    private function earliestInvalidatedStage(PublishingAttempt $attempt, array $invalidatedKinds): EditorialStage
    {
        $targetStage = in_array(ApprovalKind::Angle, $invalidatedKinds, true)
            ? EditorialStage::Developing
            : EditorialStage::Drafting;

        return $this->stagePrecedes($attempt, $targetStage) ? $this->stage($attempt) : $targetStage;
    }

    private function stagePrecedes(PublishingAttempt $attempt, EditorialStage $stage): bool
    {
        return $this->stageOrder($this->stage($attempt)) < $this->stageOrder($stage);
    }

    private function stage(PublishingAttempt $attempt): EditorialStage
    {
        $stage = $attempt->getAttribute('stage');

        if ($stage instanceof EditorialStage) {
            return $stage;
        }

        return EditorialStage::from((string) $stage);
    }

    private function stageOrder(EditorialStage $stage): int
    {
        return match ($stage) {
            EditorialStage::Developing => 10,
            EditorialStage::Drafting => 20,
            EditorialStage::InReview => 30,
            EditorialStage::Approved => 40,
            EditorialStage::Scheduled => 50,
            EditorialStage::Published => 60,
            EditorialStage::Abandoned => 70,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateAttempt(PublishingAttempt|int $attempt, array $attributes): PublishingAttempt
    {
        /** @var PublishingAttempt $updated */
        $updated = DB::transaction(function () use ($attempt, $attributes): PublishingAttempt {
            $attemptId = $this->attemptId($attempt);
            $lockedArticle = $this->lockedArticleForAttemptId($attemptId);
            $lockedAttempt = $this->lockedAttemptById($attemptId);
            $this->ensureAttemptCanBeMutated($lockedAttempt, $lockedArticle);
            $lockedAttempt->forceFill($attributes)->save();

            if (array_key_exists('paused_at', $attributes) && $attributes['paused_at'] !== null) {
                EditorialActivity::query()
                    ->where('attempt_id', $lockedAttempt->id)
                    ->whereIn('status', [EditorialActivityStatus::Pending->value, EditorialActivityStatus::Failed->value])
                    ->update([
                        'status' => EditorialActivityStatus::Paused->value,
                        'paused_at' => now(),
                        'pause_reason' => EditorialActivity::ATTEMPT_PAUSE_REASON,
                    ]);
            }

            if (array_key_exists('paused_at', $attributes) && $attributes['paused_at'] === null) {
                EditorialActivity::query()
                    ->where('attempt_id', $lockedAttempt->id)
                    ->where('status', EditorialActivityStatus::Paused->value)
                    ->whereIn('pause_reason', EditorialActivity::RESUMABLE_PAUSE_REASONS)
                    ->update([
                        'status' => EditorialActivityStatus::Pending->value,
                        'paused_at' => null,
                        'pause_reason' => null,
                        'available_at' => now(),
                    ]);
            }

            return $lockedAttempt->refresh();
        });

        return $updated;
    }

    private function lockedArticle(Article|int $article): Article
    {
        $articleId = $article instanceof Article ? $article->getKey() : $article;

        return Article::query()
            ->whereKey($articleId)
            ->lockForUpdate()
            ->firstOrFail();
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

    private function lockedAttemptById(int $attemptId): PublishingAttempt
    {
        return PublishingAttempt::query()
            ->whereKey($attemptId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureAttemptCanBeMutated(PublishingAttempt $attempt, Article $article): void
    {
        if ((int) $attempt->article_id !== (int) $article->id || (int) ($article->current_attempt_id ?? 0) !== (int) $attempt->id) {
            throw new RuntimeException('Only the current publishing attempt can be changed.');
        }

        if ($attempt->abandoned_at !== null || in_array($this->stage($attempt), [EditorialStage::Published, EditorialStage::Abandoned], true)) {
            throw new RuntimeException('Terminal publishing attempts cannot be changed.');
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException('This user is not allowed to advance publishing work.');
        }
    }
}
