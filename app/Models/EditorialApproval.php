<?php

namespace App\Models;

use App\Models\Publishing\ApprovalKind;
use Database\Factories\EditorialApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attempt_id', 'kind', 'input_hash', 'revision_id', 'release_id', 'user_id', 'approved_at', 'invalidated_at'])]
class EditorialApproval extends Model
{
    /** @use HasFactory<EditorialApprovalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ApprovalKind::class,
            'approved_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PublishingAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PublishingAttempt::class, 'attempt_id');
    }

    /**
     * @return BelongsTo<ArticleRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ArticleRevision::class);
    }

    /**
     * @return BelongsTo<ArticleRelease, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(ArticleRelease::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
