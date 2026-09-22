<?php

namespace App\Models;

use Database\Factories\ArticleReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

#[Fillable(['article_id', 'attempt_id', 'revision_id', 'origin', 'payload', 'release_hash', 'status', 'scheduled_at', 'published_by', 'published_at', 'withdrawn_at'])]
class ArticleRelease extends Model
{
    /** @use HasFactory<ArticleReleaseFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    private const IMMUTABLE_PACKAGE_ATTRIBUTES = [
        'article_id',
        'attempt_id',
        'revision_id',
        'origin',
        'payload',
        'release_hash',
        'scheduled_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (ArticleRelease $release): void {
            if ($release->isDirty(self::IMMUTABLE_PACKAGE_ATTRIBUTES)) {
                throw new RuntimeException('Article release packages are immutable after creation.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'withdrawn_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return HasMany<EditorialApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(EditorialApproval::class, 'release_id');
    }
}
