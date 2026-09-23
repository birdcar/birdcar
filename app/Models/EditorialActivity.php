<?php

namespace App\Models;

use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use Database\Factories\EditorialActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['article_id', 'attempt_id', 'initiating_user_id', 'kind', 'status', 'stage', 'input_version', 'revision_id', 'revision_hash', 'review_cycle', 'batch_key', 'idempotency_key', 'prompt_version', 'prompt_hash', 'input', 'model_snapshot', 'response', 'proposal', 'run_count', 'available_at', 'started_at', 'completed_at', 'paused_at', 'pause_reason', 'error_reason', 'generation_id'])]
class EditorialActivity extends Model
{
    /** @use HasFactory<EditorialActivityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => EditorialActivityKind::class,
            'status' => EditorialActivityStatus::class,
            'input' => 'array',
            'model_snapshot' => 'array',
            'response' => 'array',
            'proposal' => 'array',
            'review_cycle' => 'integer',
            'run_count' => 'integer',
            'available_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiating_user_id');
    }

    /** @return BelongsTo<ArticleRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ArticleRevision::class, 'revision_id');
    }

    /** @return HasMany<AgentBudgetReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(AgentBudgetReservation::class, 'activity_id');
    }

    /** @return HasMany<EvidenceSource, $this> */
    public function evidenceSources(): HasMany
    {
        return $this->hasMany(EvidenceSource::class, 'activity_id');
    }

    /** @return HasMany<EditorialFinding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(EditorialFinding::class, 'activity_id');
    }
}
