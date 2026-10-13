<?php

namespace App\Services\Publishing;

use App\Models\Article;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\Publishing\PieceKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * The publishing board's columns. Each column is derived from a piece's existing stage, flags and agent activity; the
 * board stores no column of its own.
 */
enum BoardColumn: string
{
    case Ideas = 'ideas';
    case Shaping = 'shaping';
    case Planning = 'planning';
    case Writing = 'writing';
    case Review = 'review';
    case Ready = 'ready';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Parked = 'parked';
    case Killed = 'killed';

    /** The Published column shows only the most recent pieces; the published library lists the rest. */
    public const PUBLISHED_LIMIT = 12;

    private const WRITING_KINDS = [EditorialActivityKind::Draft, EditorialActivityKind::LintRepair, EditorialActivityKind::DraftKit];

    /**
     * Reads only the piece's eager-loaded current attempt (see relations()). Killed wins over parked, because an attempt
     * can be parked and then abandoned.
     */
    public static function forPiece(Article $piece): self
    {
        $attempt = $piece->currentAttempt;

        if (! $attempt instanceof PublishingAttempt) {
            return $piece->published_release_id !== null ? self::Published : self::Ideas;
        }

        if ($attempt->abandoned_at !== null || $attempt->stage === EditorialStage::Abandoned) {
            return self::Killed;
        }

        if ($attempt->parked_at !== null) {
            return self::Parked;
        }

        return self::forStage($attempt);
    }

    /**
     * The column the attempt's stage puts it in, ignoring parking. A parked piece unparks by being dropped here.
     */
    public static function forStage(PublishingAttempt $attempt): self
    {
        return match ($attempt->stage) {
            EditorialStage::Developing => self::Shaping,
            EditorialStage::Drafting => self::Planning,
            EditorialStage::InReview => self::isWriting($attempt) ? self::Writing : self::Review,
            EditorialStage::Approved => self::Ready,
            EditorialStage::Scheduled => self::Scheduled,
            EditorialStage::Published => self::Published,
            EditorialStage::Abandoned => self::Killed,
        };
    }

    /**
     * A piece in review is still being written while a draft, voice-rule repair or kit draft is queued or running, or
     * until a draft has completed at all. Owner edits after the draft keep it in Review.
     */
    private static function isWriting(PublishingAttempt $attempt): bool
    {
        $writing = $attempt->editorialActivities->contains(fn (EditorialActivity $activity): bool => in_array($activity->kind, self::WRITING_KINDS, true)
            && in_array($activity->status, [EditorialActivityStatus::Pending, EditorialActivityStatus::Running], true));

        return $writing || ! (bool) $attempt->getAttribute('has_completed_draft');
    }

    /**
     * The column heading, or a kit piece's stage label: kits are "Ready to post" once approved and "Posted" once the
     * owner has posted them.
     */
    public function label(PieceKind $kind = PieceKind::BlogPost): string
    {
        if ($kind->isKit()) {
            return match ($this) {
                self::Ready => 'Ready to post',
                self::Published => 'Posted',
                self::Parked => 'Parked',
                self::Killed => 'Killed',
                default => 'In review',
            };
        }

        return match ($this) {
            self::Ideas => 'Ideas',
            self::Shaping => 'Shaping',
            self::Planning => 'Planning',
            self::Writing => 'Writing',
            self::Review => 'Review',
            self::Ready => 'Ready',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Parked => 'Parked',
            self::Killed => 'Killed',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Ideas => 'Worth keeping. Not on the clock.',
            self::Shaping => 'Interview and angle.',
            self::Planning => 'Research and plan.',
            self::Writing => 'Drafting the manuscript.',
            self::Review => 'Reviews, decisions and packaging.',
            self::Ready => 'Release approved.',
            self::Scheduled => 'Waiting for its time.',
            self::Published => 'The newest live work.',
            self::Parked => 'Paused on purpose.',
            self::Killed => 'Set aside for good.',
        };
    }

    /** The left-to-right position of the columns a piece moves through on its way to publication. */
    public function order(): int
    {
        return match ($this) {
            self::Ideas => 0,
            self::Shaping => 1,
            self::Planning => 2,
            self::Writing => 3,
            self::Review => 4,
            self::Ready => 5,
            self::Scheduled => 6,
            self::Published => 7,
            self::Parked => 8,
            self::Killed => 9,
        };
    }

    /**
     * The moves a card's menu offers from this column, so a touch screen gets the same single actions as a drag. The
     * server checks each one again.
     *
     * @return list<self>
     */
    public function menuTargets(?PublishingAttempt $attempt): array
    {
        return match ($this) {
            self::Ideas => [self::Shaping],
            self::Shaping, self::Planning, self::Writing, self::Review, self::Scheduled => [self::Parked, self::Killed],
            self::Ready => [self::Scheduled, self::Parked, self::Killed],
            self::Parked => $attempt instanceof PublishingAttempt ? [self::forStage($attempt), self::Killed] : [self::Killed],
            self::Published, self::Killed => [],
        };
    }

    /**
     * The owner's top-level pieces: blog posts without a parent. Siblings are their own pieces; kit pieces nest in the
     * post that spawned them.
     *
     * @return Builder<Article>
     */
    public static function pieces(User $owner): Builder
    {
        return Article::query()
            ->where('author_id', $owner->id)
            ->where('kind', PieceKind::BlogPost->value)
            ->whereNull('parent_article_id')
            ->with(self::relations());
    }

    /**
     * Limits the query to pieces forPiece() puts in the Published column, or to every other piece. Both halves must
     * agree with forPiece() exactly, or a card appears twice or not at all.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public static function wherePublished(Builder $query, bool $published = true): Builder
    {
        $constraint = function (Builder $pieces): void {
            $pieces->where(fn (Builder $imports) => $imports->whereNull('current_attempt_id')->whereNotNull('published_release_id'))
                ->orWhereHas('currentAttempt', fn (Builder $attempt) => $attempt
                    ->where('stage', EditorialStage::Published->value)
                    ->whereNull('abandoned_at')
                    ->whereNull('parked_at'));
        };

        return $published ? $query->where($constraint) : $query->whereNot($constraint);
    }

    /**
     * Everything forPiece() and PieceStatus read, column-constrained so a whole board loads in a fixed number of
     * queries. The board and MovePublishingPiece share it, so both stay safe under strict lazy loading.
     *
     * @return array<string, Closure>
     */
    public static function relations(): array
    {
        $attempt = fn (BelongsTo $query) => self::withAttemptFlags($query);
        $approvals = fn (HasMany $query) => $query->select(['id', 'attempt_id', 'kind', 'invalidated_at'])->whereNull('invalidated_at');
        $activities = fn (HasMany $query) => self::latestActivityPerKind($query);

        return [
            'contentIdea' => fn (BelongsTo $query) => $query->select(['id', 'summary_title', 'body']),
            'workingRevision' => fn (BelongsTo $query) => $query->select(['id', 'article_id', 'metadata']),
            'currentAttempt' => $attempt,
            'currentAttempt.approvals' => $approvals,
            'currentAttempt.editorialActivities' => $activities,
            'children' => fn (HasMany $query) => $query
                ->select(['id', 'slug', 'author_id', 'kind', 'parent_article_id', 'current_attempt_id', 'published_release_id'])
                ->whereIn('kind', [PieceKind::LinkedinPost->value, PieceKind::BlueskyThread->value, PieceKind::EmailBlurb->value])
                ->orderBy('id'),
            'children.currentAttempt' => $attempt,
            'children.currentAttempt.approvals' => $approvals,
            'children.currentAttempt.editorialActivities' => $activities,
        ];
    }

    /**
     * Owner-owed work that would otherwise need a query per card, computed as subqueries on the attempt row.
     *
     * @param  BelongsTo<PublishingAttempt, Article>  $query
     * @return BelongsTo<PublishingAttempt, Article>
     */
    private static function withAttemptFlags(BelongsTo $query): BelongsTo
    {
        return $query->withExists([
            'editorialActivities as has_completed_draft' => fn (Builder $activities) => $activities
                ->whereIn('kind', array_map(fn (EditorialActivityKind $kind): string => $kind->value, self::WRITING_KINDS))
                ->where('status', EditorialActivityStatus::Completed->value),
            'editorialFindings as has_open_findings' => fn ($findings) => $findings->awaitingOwnerDecision(),
            'releases as has_prepared_release' => fn (Builder $releases) => $releases
                ->where('status', 'prepared')
                ->whereNull('withdrawn_at')
                ->whereHas('article', fn (Builder $article) => $article->whereColumn('articles.working_revision_id', 'article_releases.revision_id')),
            'editorialActivities as has_unaccepted_package' => fn (Builder $packages) => $packages
                ->where('kind', EditorialActivityKind::Package->value)
                ->where('status', EditorialActivityStatus::Completed->value)
                ->whereColumn('editorial_activities.review_cycle', 'publishing_attempts.review_cycle')
                ->whereNotExists(fn ($newer) => $newer->select(DB::raw(1))
                    ->from('editorial_activities as newer_package')
                    ->whereColumn('newer_package.attempt_id', 'editorial_activities.attempt_id')
                    ->whereColumn('newer_package.review_cycle', 'editorial_activities.review_cycle')
                    ->where('newer_package.kind', EditorialActivityKind::Package->value)
                    ->where('newer_package.status', EditorialActivityStatus::Completed->value)
                    ->whereColumn('newer_package.id', '>', 'editorial_activities.id'))
                ->whereNotExists(fn ($accepted) => $accepted->select(DB::raw(1))
                    ->from('article_revisions')
                    ->whereColumn('article_revisions.article_id', 'editorial_activities.article_id')
                    ->whereRaw("article_revisions.client_mutation_id like concat('package-', editorial_activities.id, '-%')")),
        ]);
    }

    /**
     * @param  HasMany<EditorialActivity, PublishingAttempt>  $query
     * @return HasMany<EditorialActivity, PublishingAttempt>
     */
    private static function latestActivityPerKind(HasMany $query): HasMany
    {
        return $query
            ->select(['id', 'article_id', 'attempt_id', 'initiating_user_id', 'kind', 'status', 'review_cycle', 'pause_reason', 'error_reason', 'updated_at'])
            ->whereNotExists(fn ($newer) => $newer->select(DB::raw(1))
                ->from('editorial_activities as newer')
                ->whereColumn('newer.attempt_id', 'editorial_activities.attempt_id')
                ->whereColumn('newer.kind', 'editorial_activities.kind')
                ->whereColumn('newer.id', '>', 'editorial_activities.id'))
            ->orderByDesc('id');
    }
}
