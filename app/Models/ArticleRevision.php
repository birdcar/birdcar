<?php

namespace App\Models;

use Database\Factories\ArticleRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

#[Fillable(['article_id', 'number', 'parent_revision_id', 'created_by', 'origin', 'document', 'metadata', 'content_hash', 'client_mutation_id'])]
class ArticleRevision extends Model
{
    /** @use HasFactory<ArticleRevisionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    private const IMMUTABLE_ATTRIBUTES = [
        'article_id',
        'number',
        'parent_revision_id',
        'created_by',
        'origin',
        'document',
        'metadata',
        'content_hash',
        'client_mutation_id',
    ];

    protected static function booted(): void
    {
        static::updating(function (ArticleRevision $revision): void {
            if ($revision->isDirty(self::IMMUTABLE_ATTRIBUTES)) {
                throw new RuntimeException('Article revisions are append-only and cannot be mutated.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document' => 'array',
            'metadata' => 'array',
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
     * @return BelongsTo<ArticleRevision, $this>
     */
    public function parentRevision(): BelongsTo
    {
        return $this->belongsTo(ArticleRevision::class, 'parent_revision_id');
    }

    /**
     * @return HasMany<ArticleRevision, $this>
     */
    public function childRevisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class, 'parent_revision_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
