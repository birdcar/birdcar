<?php

namespace App\Models;

use App\Models\Publishing\PieceKind;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[RouteKey('slug')]
#[Fillable(['slug', 'author_id', 'content_idea_id', 'kind', 'parent_article_id', 'working_revision_id', 'published_release_id', 'current_attempt_id', 'first_published_at'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PieceKind::class,
            'first_published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<ContentIdea, $this>
     */
    public function contentIdea(): BelongsTo
    {
        return $this->belongsTo(ContentIdea::class);
    }

    /**
     * @return BelongsTo<Article, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'parent_article_id');
    }

    /**
     * @return HasMany<Article, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Article::class, 'parent_article_id');
    }

    /**
     * @return HasMany<ArticleRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class);
    }

    /**
     * @return BelongsTo<ArticleRevision, $this>
     */
    public function workingRevision(): BelongsTo
    {
        return $this->belongsTo(ArticleRevision::class, 'working_revision_id');
    }

    /**
     * @return HasMany<PublishingAttempt, $this>
     */
    public function publishingAttempts(): HasMany
    {
        return $this->hasMany(PublishingAttempt::class);
    }

    /**
     * @return BelongsTo<PublishingAttempt, $this>
     */
    public function currentAttempt(): BelongsTo
    {
        return $this->belongsTo(PublishingAttempt::class, 'current_attempt_id');
    }

    /**
     * @return HasMany<ArticleRelease, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(ArticleRelease::class);
    }

    /**
     * @return BelongsTo<ArticleRelease, $this>
     */
    public function publishedRelease(): BelongsTo
    {
        return $this->belongsTo(ArticleRelease::class, 'published_release_id');
    }
}
