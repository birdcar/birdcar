<?php

namespace App\Models;

use Database\Factories\EvidenceSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['article_id', 'attempt_id', 'activity_id', 'source_type', 'url', 'final_url', 'title', 'retrieved_at', 'retrieval_method', 'extracted_text', 'content_hash', 'origin_metadata', 'unresolved_reason', 'restricted_processing_consent', 'publication_permission', 'consent_actor_id', 'consented_at'])]
class EvidenceSource extends Model
{
    /** @use HasFactory<EvidenceSourceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retrieved_at' => 'datetime',
            'origin_metadata' => 'array',
            'restricted_processing_consent' => 'boolean',
            'publication_permission' => 'boolean',
            'consented_at' => 'datetime',
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

    /** @return BelongsTo<EditorialActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(EditorialActivity::class, 'activity_id');
    }

    /** @return BelongsTo<User, $this> */
    public function consentActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consent_actor_id');
    }
}
