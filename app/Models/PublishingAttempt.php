<?php

namespace App\Models;

use App\Models\Publishing\EditorialStage;
use Database\Factories\PublishingAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['article_id', 'user_id', 'stage', 'input_version', 'brief', 'angle', 'plan', 'interview_context', 'paused_at', 'pause_reason', 'parked_at', 'parked_reason', 'abandoned_at', 'abandoned_reason', 'review_cycle', 'recheck_used'])]
class PublishingAttempt extends Model
{
    /** @use HasFactory<PublishingAttemptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => EditorialStage::class,
            'brief' => 'array',
            'angle' => 'array',
            'plan' => 'array',
            'interview_context' => 'array',
            'paused_at' => 'datetime',
            'parked_at' => 'datetime',
            'abandoned_at' => 'datetime',
            'review_cycle' => 'integer',
            'recheck_used' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<ArticleRevision, $this>
     */
    public function inputRevision(): BelongsTo
    {
        return $this->belongsTo(ArticleRevision::class, 'input_version');
    }

    /**
     * @return HasMany<EditorialApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(EditorialApproval::class, 'attempt_id');
    }

    /**
     * @return HasMany<ArticleRelease, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(ArticleRelease::class, 'attempt_id');
    }

    /**
     * @return HasMany<EditorialActivity, $this>
     */
    public function editorialActivities(): HasMany
    {
        return $this->hasMany(EditorialActivity::class, 'attempt_id');
    }

    /**
     * @return HasMany<EvidenceSource, $this>
     */
    public function evidenceSources(): HasMany
    {
        return $this->hasMany(EvidenceSource::class, 'attempt_id');
    }

    /**
     * @return HasMany<EditorialFinding, $this>
     */
    public function editorialFindings(): HasMany
    {
        return $this->hasMany(EditorialFinding::class, 'attempt_id');
    }
}
