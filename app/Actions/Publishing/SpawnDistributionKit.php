<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\ContentIdea;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\Publishing\PieceKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\MarketingSite;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns an approved blog post release into its distribution kit: a LinkedIn post, a Bluesky thread and an email blurb,
 * each a child piece of the post with its own attempt in review. Spawning is idempotent per post and kind, so a retried
 * or repeated release approval never duplicates a piece. A piece not yet approved restarts on the new release; an
 * approved or posted piece is left alone and the kit panel shows that its flagship changed.
 */
class SpawnDistributionKit
{
    /** The kit kinds in the order the kit panel and the board list them. */
    public const KINDS = [PieceKind::LinkedinPost, PieceKind::BlueskyThread, PieceKind::EmailBlurb];

    public function __construct(private WriteArticle $writer, private StartEditorialActivity $activities) {}

    /**
     * Archive essays keep their original form and voice, so a new release of one spawns no kit. Every other blog post
     * with no parent (the flagship and any sibling post on the same idea) gets its own kit.
     */
    public function spawnsFor(Article $article): bool
    {
        return $article->kind === PieceKind::BlogPost
            && $article->parent_article_id === null
            && ! app(LintRevision::class)->isArchiveImport($article);
    }

    /**
     * @return list<Article> the three kit pieces in LinkedIn, Bluesky and email order; existing pieces are returned unchanged
     */
    public function spawn(User $actor, Article $flagship, ArticleRelease $release): array
    {
        if (! $actor->can(PublishingPermission::Write->value)) {
            throw new AuthorizationException('This user is not allowed to create distribution kit pieces.');
        }

        if ((int) $release->article_id !== (int) $flagship->id || ! $this->spawnsFor($flagship)) {
            throw new RuntimeException('Only an approved release of a blog post spawns a distribution kit.');
        }

        if (! $release->standsApproved()) {
            throw new RuntimeException('A distribution kit needs the post\'s approved release.');
        }

        /** @var array{pieces: list<Article>, drafts: list<Article>} $result */
        $result = DB::transaction(function () use ($actor, $flagship, $release): array {
            $locked = Article::query()->whereKey($flagship->id)->lockForUpdate()->firstOrFail();
            $idea = ContentIdea::query()->findOrFail($locked->content_idea_id);
            $brief = $this->brief($locked, $release);
            $pieces = [];
            $drafts = [];

            foreach (self::KINDS as $kind) {
                $piece = Article::query()->where('parent_article_id', $locked->id)->where('kind', $kind->value)->orderBy('id')->first();
                if (! $piece instanceof Article) {
                    $piece = $this->createPiece($actor, $idea, $kind, $locked, $brief);
                    $drafts[] = $piece;
                } elseif ($this->restart($piece, $brief)) {
                    $drafts[] = $piece;
                }

                $pieces[] = $piece;
            }

            return ['pieces' => $pieces, 'drafts' => $drafts];
        });

        // Without agent permissions the pieces still exist, and the kit panel lets someone who has them start each draft.
        if ($actor->can(PublishingPermission::Develop->value)) {
            foreach ($result['drafts'] as $piece) {
                $this->draft($actor, $piece);
            }
        }

        return array_map(static fn (Article $piece): Article => $piece->fresh() ?? $piece, $result['pieces']);
    }

    /**
     * Queues the kit draft for a piece's current attempt. While publishing agents are paused the activity waits as
     * pending, and recovery runs it once they resume.
     */
    public function draft(User $actor, Article $piece): EditorialActivity
    {
        $attempt = PublishingAttempt::query()->findOrFail($piece->current_attempt_id);

        return $this->activities->start($actor, $attempt, EditorialActivityKind::DraftKit, [], 'draft-kit-'.$attempt->review_cycle.'-'.data_get($attempt->brief, 'flagship_release_id'));
    }

    /**
     * Uses a kit draft that waited as a proposal because the owner had edited the piece: the draft replaces the piece's
     * text at the owner's request, then is linted and reviewed like any draft that landed on its own.
     */
    public function useDraft(User $actor, EditorialActivity $draft): ArticleRevision
    {
        if (! $actor->can(PublishingPermission::Write->value)) {
            throw new AuthorizationException('This user is not allowed to change distribution kit pieces.');
        }

        /** @var ArticleRevision $revision */
        $revision = DB::transaction(function () use ($actor, $draft): ArticleRevision {
            $locked = EditorialActivity::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $article = Article::query()->whereKey($locked->article_id)->lockForUpdate()->firstOrFail();
            $attempt = PublishingAttempt::query()->whereKey($locked->attempt_id)->lockForUpdate()->firstOrFail();
            $proposal = is_array($locked->proposal) ? $locked->proposal : [];
            $document = $proposal['document'] ?? null;
            if ($locked->kind !== EditorialActivityKind::DraftKit
                || $locked->status !== EditorialActivityStatus::Completed
                || ! is_array($document)
                || isset($proposal['applied_revision_id'])
                || (int) ($article->current_attempt_id ?? 0) !== (int) $attempt->id
                || (int) $locked->review_cycle !== (int) $attempt->review_cycle
                || $attempt->stage !== EditorialStage::InReview) {
                throw new RuntimeException('This kit draft is no longer waiting to be used.');
            }

            $metadata = $article->workingRevision()->first()?->getAttribute('metadata');
            $revision = $this->writer->save($actor, $article, $article->working_revision_id, $document, is_array($metadata) ? $metadata : [], 'kit-draft-'.$locked->id, 'agent-accepted');
            $locked->forceFill(['proposal' => [...$proposal, 'applied_revision_id' => $revision->id]])->save();
            app(LintRevision::class)->finishAndQueueReviews($actor, $attempt->refresh(), $revision, (int) $locked->id);

            return $revision;
        });

        return $revision;
    }

    /**
     * What a kit attempt freezes about its flagship: the release it is cut from, the public URL that release will live at,
     * and the search phrase the post answers.
     *
     * @return array{flagship_article_id: int, flagship_release_id: int, canonical_url: string|null, target_query: string|null}
     */
    private function brief(Article $flagship, ArticleRelease $release): array
    {
        $slug = data_get($release->payload, 'canonical_slug');
        $targetQuery = $release->attempt_id === null ? null : data_get(PublishingAttempt::query()->select(['id', 'brief'])->find($release->attempt_id)?->brief, 'target_query');

        return [
            'flagship_article_id' => (int) $flagship->id,
            'flagship_release_id' => (int) $release->id,
            'canonical_url' => is_string($slug) && $slug !== '' ? app(MarketingSite::class)->url(route('public.article', ['slug' => $slug], absolute: false)) : null,
            'target_query' => is_string($targetQuery) && trim($targetQuery) !== '' ? trim($targetQuery) : null,
        ];
    }

    /**
     * The kit piece belongs to the post's author, like the post, whoever approved the release.
     *
     * @param  array<string, mixed>  $brief
     */
    private function createPiece(User $actor, ContentIdea $idea, PieceKind $kind, Article $flagship, array $brief): Article
    {
        $piece = $this->writer->addPiece($actor, $idea, $kind, $flagship);
        if ($flagship->author_id !== null && (int) $piece->author_id !== (int) $flagship->author_id) {
            $piece->forceFill(['author_id' => $flagship->author_id])->save();
        }

        $attempt = PublishingAttempt::create([
            'article_id' => $piece->id,
            'user_id' => $actor->id,
            'stage' => EditorialStage::InReview,
            'input_version' => null,
            'brief' => $brief,
            'angle' => [],
            'plan' => [],
            'interview_context' => [],
            'review_cycle' => 1,
            'recheck_used' => false,
        ]);
        $piece->forceFill(['current_attempt_id' => $attempt->id])->save();

        return $piece;
    }

    /**
     * A piece still in review moves to the new release: a new review cycle, its unfinished agent work stale, and a new
     * draft to follow. Approved and posted pieces, and pieces already on this release, are left alone.
     *
     * @param  array<string, mixed>  $brief
     */
    private function restart(Article $piece, array $brief): bool
    {
        $attempt = $piece->current_attempt_id === null ? null : PublishingAttempt::query()->whereKey($piece->current_attempt_id)->lockForUpdate()->first();
        if (! $attempt instanceof PublishingAttempt
            || $attempt->stage !== EditorialStage::InReview
            || (int) data_get($attempt->brief, 'flagship_release_id') === (int) $brief['flagship_release_id']) {
            return false;
        }

        EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->whereIn('status', [EditorialActivityStatus::Pending->value, EditorialActivityStatus::Paused->value, EditorialActivityStatus::Failed->value, EditorialActivityStatus::AwaitingApproval->value])
            ->update([
                'status' => EditorialActivityStatus::Stale->value,
                'completed_at' => now(),
                'error_reason' => 'The flagship post has a new approved release, so this piece is drafted again from it.',
            ]);

        $attempt->forceFill([
            'brief' => [...($attempt->brief ?? []), ...$brief],
            'review_cycle' => (int) $attempt->review_cycle + 1,
        ])->save();

        return true;
    }
}
