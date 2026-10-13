<?php

namespace App\Models;

use App\Actions\Publishing\LintRevision;
use Database\Factories\EditorialFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['article_id', 'attempt_id', 'activity_id', 'review_cycle', 'revision_id', 'input_hash', 'lens', 'kind', 'severity', 'block_id', 'expected_subtree_hash', 'statement', 'rationale', 'supporting_source_ids', 'supporting_quotations', 'proposed_patch', 'reconciliation_state', 'reconciliation_group', 'reconciled_into_finding_id', 'reconciliation_payload', 'disposition', 'disposition_reason', 'disposition_actor_id', 'disposed_at', 'stale_at'])]
class EditorialFinding extends Model
{
    /** @use HasFactory<EditorialFindingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'review_cycle' => 'integer',
            'supporting_source_ids' => 'array',
            'supporting_quotations' => 'array',
            'proposed_patch' => 'array',
            'reconciliation_payload' => 'array',
            'disposed_at' => 'datetime',
            'stale_at' => 'datetime',
        ];
    }

    /**
     * The one definition of a finding the owner still has to decide, shared by the Admin home and the board. It
     * mirrors what blocks FinishEditorialReview: a blocking or conflicting finding in the attempt's current review
     * cycle, on the working revision's current content, with no disposition. Duplicate and superseded findings are
     * excluded while an unreconciled (NULL) state stays eligible, and voice-lint findings are left to release.
     * Apply it inside an attempt's withExists() so publishing_attempts is the outer row.
     *
     * @param  Builder<EditorialFinding>  $query
     */
    #[Scope]
    protected function awaitingOwnerDecision(Builder $query): void
    {
        $query
            ->whereColumn('editorial_findings.review_cycle', 'publishing_attempts.review_cycle')
            ->where('editorial_findings.lens', '!=', LintRevision::LENS)
            ->whereNull('editorial_findings.stale_at')
            ->whereNull('editorial_findings.disposition')
            ->where(fn (Builder $state) => $state
                ->whereNull('editorial_findings.reconciliation_state')
                ->orWhereNotIn('editorial_findings.reconciliation_state', ['duplicate', 'superseded']))
            ->where(fn (Builder $weight) => $weight
                ->where('editorial_findings.severity', 'blocking')
                ->orWhere('editorial_findings.reconciliation_state', 'conflict'))
            ->whereHas('article', fn (Builder $article) => $article
                ->whereColumn('articles.working_revision_id', 'editorial_findings.revision_id')
                ->whereHas('workingRevision', fn (Builder $revision) => $revision->whereColumn('article_revisions.content_hash', 'editorial_findings.input_hash')));
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<PublishingAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PublishingAttempt::class, 'attempt_id');
    }

    /** @return BelongsTo<EditorialActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(EditorialActivity::class, 'activity_id');
    }

    /** @return BelongsTo<ArticleRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ArticleRevision::class, 'revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function dispositionActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposition_actor_id');
    }

    /** @return BelongsTo<EditorialFinding, $this> */
    public function reconciledIntoFinding(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reconciled_into_finding_id');
    }
}
