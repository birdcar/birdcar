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

    /** Origins of the revisions an agent wrote. An owner save straight after one records its edit distance. */
    public const AGENT_ORIGINS = ['agent-initial', 'agent-repair', 'agent-accepted'];

    /**
     * Set once, from null, by RecordEditDistance after the owner's save; that job is their only writer. Once recorded,
     * a distance is evidence and never changes.
     *
     * @var list<string>
     */
    private const RECORDED_ONCE_ATTRIBUTES = ['agent_edit_distance', 'agent_baseline_revision_id'];

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

            // A column this model never loaded cannot be shown to be null, so it cannot be set here either.
            $original = $revision->getRawOriginal();
            foreach (self::RECORDED_ONCE_ATTRIBUTES as $attribute) {
                if ($revision->isDirty($attribute) && (! array_key_exists($attribute, $original) || $original[$attribute] !== null)) {
                    throw new RuntimeException('An edit distance is recorded once and cannot be changed.');
                }
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
            'agent_edit_distance' => 'integer',
            'agent_baseline_revision_id' => 'integer',
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
