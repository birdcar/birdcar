<?php

namespace App\Actions\Publishing;

use App\Models\Article;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Models\Conversation;
use RuntimeException;

class ResumeEditorialActivity
{
    public function handle(User $actor, EditorialActivity $activity, string $expectedHash, ?string $answers): void
    {
        DB::transaction(function () use ($actor, $activity, $expectedHash, $answers): void {
            $locked = EditorialActivity::query()->whereKey($activity->id)->lockForUpdate()->firstOrFail();
            $article = Article::query()->whereKey($locked->article_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('develop', $article);
            abort_unless((int) $locked->initiating_user_id === (int) $actor->id, 403);

            $attempt = PublishingAttempt::query()->whereKey($locked->attempt_id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== EditorialActivityStatus::AwaitingApproval
                || $locked->kind !== EditorialActivityKind::Interview
                || ! hash_equals($locked->pendingApprovalHash(), $expectedHash)) {
                throw new RuntimeException('This agent request has changed or has already been answered. Refresh the workspace.');
            }
            if ((int) $article->current_attempt_id !== (int) $attempt->id
                || $attempt->paused_at !== null || $attempt->parked_at !== null || $attempt->abandoned_at !== null
                || $attempt->getAttribute('stage')->value !== $locked->stage
                || (int) $attempt->review_cycle !== (int) $locked->review_cycle
                || $attempt->input_version !== $locked->input_version
                || $article->working_revision_id !== $locked->revision_id
                || ($locked->revision_hash !== null && $article->workingRevision?->content_hash !== $locked->revision_hash)) {
                throw new RuntimeException('This agent request targets stale or blocked publishing work.');
            }
            if (! Conversation::query()->whereKey($locked->ai_conversation_id)
                ->where('participant_type', Conversation::participantType($actor))
                ->where('participant_id', Conversation::participantKey($actor))->exists()) {
                throw new RuntimeException('The agent conversation does not belong to this author.');
            }

            if ($answers !== null) {
                Validator::make(['answers' => trim($answers)], ['answers' => ['required', 'string', 'max:4000']])->validate();
            }

            $pending = $locked->pending_tool_approvals;
            if (! is_array($pending) || $pending === []) {
                throw new RuntimeException('There are no pending tool approvals.');
            }
            $decisions = [];
            foreach ($pending as $approval) {
                if ($approval['tool'] !== 'AskAuthor') {
                    throw new RuntimeException('Unsupported editorial tool approval.');
                }
                $decisions[$approval['id']] = $answers === null
                    ? ['action' => 'reject']
                    : ['action' => 'edit', 'arguments' => [
                        'questions' => $approval['arguments']['questions'] ?? [],
                        'answers' => trim($answers),
                    ]];
            }
            $locked->forceFill([
                'status' => EditorialActivityStatus::Pending,
                'tool_decisions' => $decisions,
                'available_at' => now(),
            ])->save();
            RunEditorialActivity::dispatch((int) $locked->id)->afterCommit();
        });
    }
}
