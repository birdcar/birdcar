<?php

namespace App\Models;

use App\Models\Publishing\PieceKind;
use Database\Factories\ContentIdeaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['author_id', 'summary_title', 'summary_title_source', 'body'])]
class ContentIdea extends Model
{
    /** @use HasFactory<ContentIdeaFactory> */
    use HasFactory;

    public const DERIVED_TITLE_LIMIT = 80;

    public const TITLE_LIMIT = 120;

    public const UNTITLED = 'Untitled idea';

    public const SOURCE_DERIVED = 'derived';

    public const SOURCE_OWNER = 'owner';

    public const SOURCE_INTERVIEWER = 'interviewer';

    /**
     * Derives a short summary title from the first line of the idea that still has text once Markdown is stripped,
     * cut at a word boundary to at most 80 characters plus an ellipsis.
     */
    public static function deriveSummaryTitle(string $body): string
    {
        foreach (preg_split('/\R/u', $body) ?: [] as $line) {
            $text = self::plainText($line);

            if ($text !== '') {
                return self::cutAtWordBoundary($text);
            }
        }

        return self::UNTITLED;
    }

    /**
     * Keeps an already published title as the summary title when it fits, so archive essays read as they were published.
     */
    public static function summaryTitleFromPublishedTitle(string $title): string
    {
        $title = trim($title);

        return $title !== '' && mb_strlen($title) <= self::TITLE_LIMIT ? $title : self::deriveSummaryTitle($title);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<Article, $this>
     */
    public function pieces(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * The oldest blog post piece without a parent.
     */
    public function flagship(): ?Article
    {
        return $this->pieces()
            ->where('kind', PieceKind::BlogPost->value)
            ->whereNull('parent_article_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    private static function plainText(string $line): string
    {
        $text = preg_replace('/^(?:\s*(?:#{1,6}(?=\s|$)|>|[-*+](?=\s|$)|\d+[.)](?=\s|$)))+/u', '', $line) ?? $line;
        $text = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/\*\*|__|~~|`/u', '', $text) ?? $text;
        $text = preg_replace('/(?<![\p{L}\p{N}])[*_]+(?=\S)|(?<=\S)[*_]+(?![\p{L}\p{N}])/u', '', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private static function cutAtWordBoundary(string $text): string
    {
        if (mb_strlen($text) <= self::DERIVED_TITLE_LIMIT) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::DERIVED_TITLE_LIMIT);

        if (mb_substr($text, self::DERIVED_TITLE_LIMIT, 1) !== ' ') {
            $boundary = mb_strrpos($cut, ' ');

            if ($boundary !== false && $boundary > 0) {
                $cut = mb_substr($cut, 0, $boundary);
            }
        }

        $cut = rtrim($cut, " \t,;:-");

        return ($cut === '' ? mb_substr($text, 0, self::DERIVED_TITLE_LIMIT) : $cut).'…';
    }
}
