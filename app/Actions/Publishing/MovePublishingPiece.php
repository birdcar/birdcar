<?php

namespace App\Actions\Publishing;

use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\PieceKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\BoardColumn;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The board's move rules. A drag performs at most one human action: developing an idea, parking, killing or
 * unparking a piece. Scheduling hands over to the workspace's release checklist, and every other move is refused with
 * the gate the owner has to cross in the workspace. The piece's column is derived again under a lock, so a stale
 * board cannot move a piece that an agent advanced in the meantime.
 */
class MovePublishingPiece
{
    public const REASON_LIMIT = 1000;

    public const MOVED_ELSEWHERE = 'This piece moved; refresh the board to see where it is now.';

    public const NOT_ON_BOARD = 'That piece isn’t on your board.';

    public function __construct(
        private AdvancePublishingAttempt $attempts,
        private StartEditorialActivity $activities,
        private ManageArticleRelease $releases,
    ) {}

    /**
     * @throws ValidationException when a park or kill reason is blank or too long
     */
    public function move(User $actor, Article $piece, BoardColumn $from, BoardColumn $to, ?string $reason = null): MoveResult
    {
        try {
            /** @var MoveResult $result */
            $result = DB::transaction(fn (): MoveResult => $this->moveLocked($actor, $piece, $from, $to, $reason));

            return $result;
        } catch (AuthorizationException) {
            return MoveResult::refused('You don’t have permission to make that move.');
        } catch (QueryException $exception) {
            report($exception);

            return MoveResult::refused('The move couldn’t be saved. Try again.');
        } catch (RuntimeException $exception) {
            return MoveResult::refused($exception->getMessage());
        }
    }

    private function moveLocked(User $actor, Article $piece, BoardColumn $from, BoardColumn $to, ?string $reason): MoveResult
    {
        $locked = Article::query()
            ->whereKey($piece->getKey())
            ->where('author_id', $actor->id)
            ->where('kind', PieceKind::BlogPost->value)
            ->whereNull('parent_article_id')
            ->lockForUpdate()
            ->first();

        if (! $locked instanceof Article) {
            return MoveResult::refused(self::NOT_ON_BOARD);
        }

        $locked->load(BoardColumn::relations());

        if (BoardColumn::forPiece($locked) !== $from) {
            return MoveResult::refused(self::MOVED_ELSEWHERE);
        }

        if ($to === $from) {
            return MoveResult::moved();
        }

        if ($from === BoardColumn::Killed) {
            return MoveResult::refused('Killed pieces stay where they are. Open the workspace to develop the idea again.');
        }

        if ($from === BoardColumn::Published) {
            return MoveResult::refused(in_array($to, [BoardColumn::Parked, BoardColumn::Killed], true)
                ? 'Published work is already live, so it can’t be parked or killed.'
                : 'Published pieces stay published. Open the workspace to start a new revision.');
        }

        if (in_array($to, [BoardColumn::Parked, BoardColumn::Killed], true)) {
            return $this->setAside($actor, $locked, $from, $to, $reason);
        }

        if ($from === BoardColumn::Parked) {
            return $this->unpark($actor, $locked, $to);
        }

        if ($from === BoardColumn::Ideas && $to === BoardColumn::Shaping) {
            return $this->develop($actor, $locked);
        }

        if ($from === BoardColumn::Ready && $to === BoardColumn::Scheduled) {
            Gate::forUser($actor)->authorize('publish', $locked);

            return MoveResult::needsDialog('release', $locked);
        }

        return MoveResult::refused($this->gateMessage($from, $to));
    }

    private function develop(User $actor, Article $piece): MoveResult
    {
        Gate::forUser($actor)->authorize('develop', $piece);

        $body = $piece->contentIdea->body;
        $attempt = $this->attempts->develop($actor, $piece, $piece->working_revision_id, ['idea' => $body]);
        $this->activities->start($actor, $attempt, EditorialActivityKind::Interview, ['idea' => $body], 'initial-interview');

        return MoveResult::moved('Development started. The interview has been queued.');
    }

    /**
     * Parks or kills the current attempt. A scheduled release is withdrawn, as the workspace's withdraw action does, so
     * the scheduler never tries to publish a parked or killed piece.
     */
    private function setAside(User $actor, Article $piece, BoardColumn $from, BoardColumn $to, ?string $reason): MoveResult
    {
        if ($from === BoardColumn::Ideas) {
            return MoveResult::refused('Ideas are already off the clock, so there’s nothing to park or kill. Drag the idea to Shaping when you want to develop it.');
        }

        $attempt = $this->currentAttempt($piece);
        Gate::forUser($actor)->authorize('develop', $piece);

        if ($reason === null) {
            return MoveResult::needsDialog('reason', $piece);
        }

        $reason = trim($reason);
        $verb = $to === BoardColumn::Parked ? 'parking' : 'killing';
        if ($reason === '') {
            throw ValidationException::withMessages(['moveReason' => "Say why you’re {$verb} this piece."]);
        }

        if (mb_strlen($reason) > self::REASON_LIMIT) {
            throw ValidationException::withMessages(['moveReason' => 'Keep the reason to '.number_format(self::REASON_LIMIT).' characters or fewer.']);
        }

        $hadScheduledRelease = ArticleRelease::query()
            ->where('attempt_id', $attempt->id)
            ->where('status', 'scheduled')
            ->whereNull('published_at')
            ->whereNull('withdrawn_at')
            ->exists();

        if ($to === BoardColumn::Parked) {
            $this->attempts->park($actor, $attempt, $reason);
        } else {
            $this->attempts->abandon($actor, $attempt, $reason);
        }

        $this->releases->withdrawScheduledReleasesForAttemptId((int) $attempt->id);

        return MoveResult::moved(($to === BoardColumn::Parked ? 'Parked.' : 'Killed.').($hadScheduledRelease ? ' Its scheduled release was withdrawn.' : ''));
    }

    private function unpark(User $actor, Article $piece, BoardColumn $to): MoveResult
    {
        $attempt = $this->currentAttempt($piece);
        $home = BoardColumn::forStage($attempt);

        if ($to !== $home) {
            return MoveResult::refused('Drop this piece on '.$home->label().' to unpark it.');
        }

        Gate::forUser($actor)->authorize('develop', $piece);
        $this->attempts->unpark($actor, $attempt);

        return MoveResult::moved('Unparked.');
    }

    private function currentAttempt(Article $piece): PublishingAttempt
    {
        $attempt = $piece->currentAttempt;

        if (! $attempt instanceof PublishingAttempt) {
            throw new RuntimeException(self::MOVED_ELSEWHERE);
        }

        return $attempt;
    }

    private function gateMessage(BoardColumn $from, BoardColumn $to): string
    {
        if ($to->order() < $from->order()) {
            return 'Pieces don’t move backward on the board. Open the workspace to rethink the angle or the plan.';
        }

        return match ($from) {
            BoardColumn::Ideas => 'Drag the idea to Shaping to start developing it.',
            BoardColumn::Shaping => 'Open the workspace to approve the angle.',
            BoardColumn::Planning => 'Open the workspace to approve the plan.',
            BoardColumn::Writing => 'The piece moves to Review on its own when the draft finishes.',
            BoardColumn::Review => 'Open the workspace to approve the exact release.',
            BoardColumn::Ready => 'Publish from the workspace.',
            BoardColumn::Scheduled => 'Scheduled pieces publish at their time. Publish sooner from the workspace.',
            BoardColumn::Published, BoardColumn::Parked, BoardColumn::Killed => 'That move isn’t available on the board. Open the workspace.',
        };
    }
}
