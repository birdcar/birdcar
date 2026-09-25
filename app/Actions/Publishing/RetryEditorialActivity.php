<?php

namespace App\Actions\Publishing;

use App\Models\Article;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Settings\PublishingAgentSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Returns failed agent work to the queue on an explicit owner decision. Failed work already had a reply rejected,
 * so it is never retried automatically; the run rechecks ownership and frozen inputs before any new request.
 */
class RetryEditorialActivity
{
    public function handle(User $actor, EditorialActivity $activity): void
    {
        DB::transaction(function () use ($actor, $activity): void {
            $locked = EditorialActivity::query()->whereKey($activity->id)->lockForUpdate()->firstOrFail();
            $article = Article::query()->whereKey($locked->article_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('develop', $article);
            abort_unless((int) $locked->initiating_user_id === (int) $actor->id, 403);

            if ($locked->status !== EditorialActivityStatus::Failed) {
                throw new RuntimeException('Only failed agent work can be retried.');
            }

            $attempt = PublishingAttempt::query()->whereKey($locked->attempt_id)->lockForUpdate()->firstOrFail();
            if ((int) $article->current_attempt_id !== (int) $attempt->id
                || $attempt->paused_at !== null || $attempt->parked_at !== null || $attempt->abandoned_at !== null) {
                throw new RuntimeException('This agent work belongs to a blocked or replaced publishing attempt.');
            }

            $locked->forceFill([
                'status' => EditorialActivityStatus::Pending,
                'error_reason' => null,
                'available_at' => now(),
            ])->save();

            if (! app(PublishingAgentSettings::class)->paused) {
                RunEditorialActivity::dispatch((int) $locked->id)->afterCommit();
            }
        });
    }
}
