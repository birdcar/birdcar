<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ContentIdea;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\PieceKind;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Starts another blog post on an idea that already has one. The sibling has no parent and runs the full post path,
 * angle gate included; its interview is frozen with the idea's other posts, so the interviewer proposes a distinct angle.
 */
class CreateSiblingPiece
{
    /** The owner's note travels with the interview under this key. */
    public const NOTE_KEY = 'sibling_note';

    public const NOTE_LIMIT = 2000;

    public function __construct(
        private WriteArticle $writer,
        private AdvancePublishingAttempt $attempts,
        private StartEditorialActivity $activities,
    ) {}

    public function create(User $actor, ContentIdea $idea, ?string $note = null): Article
    {
        if (! $actor->can(PublishingPermission::Develop->value)) {
            throw new AuthorizationException('This user is not allowed to start a new post.');
        }

        $note = trim((string) $note);
        $note = $note === '' ? null : mb_substr($note, 0, self::NOTE_LIMIT);

        /** @var Article $piece */
        $piece = DB::transaction(function () use ($actor, $idea, $note): Article {
            $piece = $this->writer->addPiece($actor, $idea, PieceKind::BlogPost);
            $attempt = $this->attempts->develop($actor, $piece, null, ['idea' => $idea->body]);
            $input = ['idea' => $idea->body];
            if ($note !== null) {
                $input[self::NOTE_KEY] = $note;
            }
            $this->activities->start($actor, $attempt, EditorialActivityKind::Interview, $input, 'interview-'.$attempt->review_cycle);

            return $piece;
        });

        return $piece->fresh() ?? $piece;
    }
}
