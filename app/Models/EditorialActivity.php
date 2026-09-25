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

/**
 * @property EditorialActivityKind $kind
 * @property EditorialActivityStatus $status
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $model_snapshot
 * @property list<array{id: string, tool: string, arguments: array<string, mixed>, reason: string|null}>|null $pending_tool_approvals
 * @property array<string, array{action: 'edit', arguments: array{questions: list<string>, answers: string}}|array{action: 'reject'}>|null $tool_decisions
 */
#[Fillable(['article_id', 'attempt_id', 'initiating_user_id', 'kind', 'status', 'stage', 'input_version', 'revision_id', 'revision_hash', 'review_cycle', 'batch_key', 'idempotency_key', 'prompt_version', 'prompt_hash', 'input', 'model_snapshot', 'response', 'proposal', 'run_count', 'available_at', 'started_at', 'completed_at', 'paused_at', 'pause_reason', 'error_reason', 'generation_id', 'ai_conversation_id', 'pending_tool_approvals', 'tool_decisions'])]
class EditorialActivity extends Model
{
    /** @use HasFactory<EditorialActivityFactory> */
    use HasFactory;

    public const ATTEMPT_PAUSE_REASON = 'Publishing attempt paused.';

    public const LEGACY_PRE_CALL_PAUSE_REASON = 'Paused before any provider request by the removed agent budget checks. Resume the publishing attempt to run it again.';

    /** Pause reasons an explicit attempt resume may return to pending work. */
    public const RESUMABLE_PAUSE_REASONS = [self::ATTEMPT_PAUSE_REASON, self::LEGACY_PRE_CALL_PAUSE_REASON];

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
            'pending_tool_approvals' => 'array',
            'tool_decisions' => 'array',
            'review_cycle' => 'integer',
            'run_count' => 'integer',
            'available_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
    }

    public function pendingApprovalHash(): string
    {
        return hash('sha256', json_encode($this->pending_tool_approvals ?? [], JSON_THROW_ON_ERROR));
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
