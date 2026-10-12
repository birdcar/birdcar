<?php

namespace App\Models;

use App\Models\Publishing\PieceKind;
use Database\Factories\ExemplarSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One H2 section (or a chunk of a long one) of a published blog post, embedded so a new brief can retrieve the owner's
 * closest earlier writing as a voice exemplar.
 */
#[Fillable(['article_id', 'release_id', 'kind', 'position', 'heading', 'text', 'content_hash'])]
class ExemplarSection extends Model
{
    /** @use HasFactory<ExemplarSectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PieceKind::class,
            'position' => 'integer',
            'embedding' => AsVector::class,
            'embedded_at' => 'datetime',
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
     * @return BelongsTo<ArticleRelease, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(ArticleRelease::class, 'release_id');
    }
}
