<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FinishEditorialReview
{
    public function __construct(private readonly StartEditorialActivity $activities) {}

    /**
     * @param  array<int, array{disposition: string, reason?: string}>  $dispositions
     */
    public function finish(User $actor, PublishingAttempt|int $attempt, int $expectedRevisionId, array $dispositions = [], bool $startRecheck = true): ?EditorialActivity
    {
        if (! $actor->can(PublishingPermission::Approve->value)) {
            throw new AuthorizationException('This user is not allowed to finish editorial review.');
        }

        /** @var EditorialActivity|null $activity */
        $activity = DB::transaction(function () use ($actor, $attempt, $expectedRevisionId, $dispositions, $startRecheck): ?EditorialActivity {
            $lockedAttempt = $this->lockedAttempt($attempt);
            $article = Article::query()->whereKey($lockedAttempt->article_id)->lockForUpdate()->firstOrFail();
            if ((int) ($article->current_attempt_id ?? 0) !== (int) $lockedAttempt->id) {
                throw new RuntimeException('The publishing attempt changed before review was finished.');
            }
            $targetRevisionId = (int) ($article->working_revision_id ?? 0);
            if ($targetRevisionId === 0) {
                throw new RuntimeException('The article has no current revision for review.');
            }
            $reviewedRevision = ArticleRevision::query()->whereKey($expectedRevisionId)->firstOrFail();
            $targetRevision = ArticleRevision::query()->whereKey($targetRevisionId)->firstOrFail();
            if ((int) $reviewedRevision->article_id !== (int) $article->id || (int) $targetRevision->article_id !== (int) $article->id) {
                throw new RuntimeException('Review revisions cannot cross articles.');
            }

            foreach ($dispositions as $findingId => $disposition) {
                $finding = EditorialFinding::query()->whereKey((int) $findingId)->lockForUpdate()->firstOrFail();
                if ((int) $finding->attempt_id !== (int) $lockedAttempt->id || (int) $finding->revision_id !== $expectedRevisionId) {
                    throw new RuntimeException('Review dispositions cannot cross attempts or revisions.');
                }
                $value = $disposition['disposition'];
                if (! in_array($value, ['accepted', 'rejected', 'false_positive', 'deferred'], true)) {
                    throw new RuntimeException('Unknown review disposition.');
                }
                $finding->forceFill([
                    'disposition' => $value,
                    'disposition_reason' => $disposition['reason'] ?? null,
                    'disposition_actor_id' => $actor->id,
                    'disposed_at' => now(),
                ])->save();
            }

            $requiredLenses = ['review_facts', 'review_voice', 'review_buyer'];
            foreach ($requiredLenses as $lens) {
                $exists = $lockedAttempt->editorialActivities()
                    ->where('review_cycle', $lockedAttempt->review_cycle)
                    ->where('kind', $lens)
                    ->where('status', 'completed')
                    ->where('revision_id', $expectedRevisionId)
                    ->exists();
                if (! $exists) {
                    throw new RuntimeException('All three same-revision review lenses must complete before finishing review.');
                }
            }

            $reconciliationComplete = $lockedAttempt->editorialActivities()
                ->where('review_cycle', $lockedAttempt->review_cycle)
                ->where('kind', EditorialActivityKind::Reconciliation->value)
                ->where('status', 'completed')
                ->where('revision_id', $expectedRevisionId)
                ->exists();
            if (! $reconciliationComplete) {
                return $this->activities->start($actor, $lockedAttempt->refresh(), EditorialActivityKind::Reconciliation, ['expected_revision_id' => $expectedRevisionId], 'reconciliation-'.$lockedAttempt->review_cycle.'-'.$expectedRevisionId);
            }

            $blocking = EditorialFinding::query()
                ->where('attempt_id', $lockedAttempt->id)
                ->where('review_cycle', $lockedAttempt->review_cycle)
                ->where('revision_id', $expectedRevisionId)
                ->where('severity', 'blocking')
                ->whereNull('stale_at')
                ->whereNull('disposition')
                ->exists();
            if ($blocking) {
                throw new RuntimeException('Blocking review findings need a human disposition before finish review.');
            }

            $conflicting = EditorialFinding::query()
                ->where('attempt_id', $lockedAttempt->id)
                ->where('review_cycle', $lockedAttempt->review_cycle)
                ->where('revision_id', $expectedRevisionId)
                ->where('reconciliation_state', 'conflict')
                ->whereNull('stale_at')
                ->whereNull('disposition')
                ->exists();
            if ($conflicting) {
                throw new RuntimeException('Conflicting reconciled review findings need a human disposition before finish review.');
            }

            $shouldRecheck = $startRecheck && $this->needsAffectedAreaRecheck($lockedAttempt, $expectedRevisionId, $targetRevisionId, (string) $reviewedRevision->content_hash, (string) $targetRevision->content_hash);
            $activity = null;
            if ($shouldRecheck && ! (bool) $lockedAttempt->recheck_used) {
                $lockedAttempt->forceFill(['recheck_used' => true])->save();
                $activity = $this->activities->start($actor, $lockedAttempt->refresh(), EditorialActivityKind::Recheck, [
                    'reviewed_revision_id' => $expectedRevisionId,
                    'expected_revision_id' => $targetRevisionId,
                    'reviewed_revision_hash' => (string) $reviewedRevision->content_hash,
                    'target_revision_hash' => (string) $targetRevision->content_hash,
                ], 'recheck-'.$lockedAttempt->review_cycle.'-'.$targetRevisionId);
            }

            return $activity;
        });

        return $activity;
    }

    private function needsAffectedAreaRecheck(PublishingAttempt $attempt, int $revisionId, int $targetRevisionId, ?string $reviewedRevisionHash, ?string $targetRevisionHash): bool
    {
        if ($revisionId !== $targetRevisionId || ($reviewedRevisionHash !== null && $targetRevisionHash !== null && ! hash_equals($reviewedRevisionHash, $targetRevisionHash))) {
            return true;
        }

        return EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', $attempt->review_cycle)
            ->where('revision_id', $revisionId)
            ->whereNull('stale_at')
            ->whereIn('disposition', ['accepted', 'deferred'])
            ->exists();
    }

    public function restart(User $actor, PublishingAttempt|int $attempt): PublishingAttempt
    {
        if (! $actor->can(PublishingPermission::Approve->value)) {
            throw new AuthorizationException('This user is not allowed to restart editorial review.');
        }

        /** @var PublishingAttempt $updated */
        $updated = DB::transaction(function () use ($attempt): PublishingAttempt {
            $lockedAttempt = $this->lockedAttempt($attempt);
            $article = Article::query()->whereKey($lockedAttempt->article_id)->lockForUpdate()->firstOrFail();
            if ((int) ($article->current_attempt_id ?? 0) !== (int) $lockedAttempt->id) {
                throw new RuntimeException('Only the current publishing attempt can restart review.');
            }

            $lockedAttempt->forceFill([
                'review_cycle' => ((int) $lockedAttempt->review_cycle) + 1,
                'recheck_used' => false,
            ])->save();

            return $lockedAttempt->refresh();
        });

        return $updated;
    }

    private function lockedAttempt(PublishingAttempt|int $attempt): PublishingAttempt
    {
        $attemptId = $attempt instanceof PublishingAttempt ? $attempt->getKey() : $attempt;

        return PublishingAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
    }
}
