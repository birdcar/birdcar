<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\EditorialApproval;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The owner posts a kit piece by hand and records it here. The app never posts anywhere, and never fetches the URL the
 * owner gives. A piece may be marked posted only once its exact text is approved and its flagship post is live, because
 * a social post must never link to a post before it is published.
 */
class MarkKitPiecePosted
{
    public const URL_LIMIT = 2048;

    public function mark(User $actor, Article $kitPiece, ?string $postedUrl = null): void
    {
        if (! $actor->can(PublishingPermission::Publish->value)) {
            throw new AuthorizationException('This user is not allowed to mark distribution pieces posted.');
        }

        $postedUrl = $this->postedUrl($postedUrl);

        DB::transaction(function () use ($kitPiece, $postedUrl): void {
            $piece = Article::query()->whereKey($kitPiece->id)->lockForUpdate()->firstOrFail();
            $problem = $this->problem($piece);
            if ($problem !== null) {
                throw new RuntimeException($problem);
            }

            $attempt = PublishingAttempt::query()->whereKey($piece->current_attempt_id)->lockForUpdate()->firstOrFail();
            $piece->forceFill(['posted_at' => now(), 'posted_url' => $postedUrl])->save();
            $attempt->forceFill(['stage' => EditorialStage::Published])->save();
        });
    }

    /**
     * Why the piece cannot be marked posted yet, naming the unmet condition, or null when it can.
     */
    public function problem(Article $piece): ?string
    {
        if (! $piece->isKit()) {
            return 'Only a distribution kit piece can be marked posted.';
        }

        if ($piece->posted_at !== null) {
            return 'This piece is already marked posted.';
        }

        $attempt = $piece->current_attempt_id === null ? null : PublishingAttempt::query()->find($piece->current_attempt_id);
        if (! $attempt instanceof PublishingAttempt || $attempt->stage !== EditorialStage::Approved || ! $this->approvedAsWritten($attempt, $piece)) {
            return 'Approve this piece as written before marking it posted.';
        }

        $flagship = $piece->parent_article_id === null ? null : Article::query()->find($piece->parent_article_id);
        if (! $flagship instanceof Article || ! $this->isLive($flagship)) {
            return 'The flagship post is not published yet. Post this piece after the post is live, then mark it posted.';
        }

        return null;
    }

    /** The flagship is live: its published pointer names a published release whose own attempt reached publication. */
    public function isLive(Article $flagship): bool
    {
        $release = $flagship->published_release_id === null ? null : ArticleRelease::query()->find($flagship->published_release_id);
        if (! $release instanceof ArticleRelease || $release->status !== 'published' || $release->published_at === null) {
            return false;
        }

        // An imported release has no attempt; any other was published through its own attempt, even after a newer one began.
        return $release->attempt_id === null
            || PublishingAttempt::query()->whereKey($release->attempt_id)->where('stage', EditorialStage::Published->value)->exists();
    }

    private function approvedAsWritten(PublishingAttempt $attempt, Article $piece): bool
    {
        return EditorialApproval::query()
            ->where('attempt_id', $attempt->id)
            ->where('kind', ApprovalKind::Kit->value)
            ->whereNull('invalidated_at')
            ->where('revision_id', $piece->working_revision_id)
            ->exists();
    }

    private function postedUrl(?string $postedUrl): ?string
    {
        $postedUrl = trim((string) $postedUrl);
        if ($postedUrl === '') {
            return null;
        }

        $valid = mb_strlen($postedUrl) <= self::URL_LIMIT
            && filter_var($postedUrl, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($postedUrl, PHP_URL_SCHEME)) === 'https'
            && is_string(parse_url($postedUrl, PHP_URL_HOST));
        if (! $valid) {
            throw ValidationException::withMessages(['posted_url' => 'Use the https link to where you posted this piece, or leave it blank.']);
        }

        return $postedUrl;
    }
}
