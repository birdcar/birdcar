<?php

namespace App\Services\Publishing;

use App\Models\Article;
use App\Models\Publishing\PieceKind;
use App\Models\PublishingAttempt;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finds the owner's published sections closest to an approved blog brief. Retrieval adds topical craft on top of the
 * fixed canon, so it never blocks an approval: a provider failure comes back as `unavailable` with its reason.
 *
 * @phpstan-type RetrievedSection array{section_id: int, article_slug: string, heading: string|null, text: string, similarity: float}
 * @phpstan-type RetrievalContext array{status: 'ok'|'insufficient'|'unavailable', reason: string|null, query_hash: string, sections: list<RetrievedSection>}
 */
class ExemplarRetriever
{
    public const LIMIT = 4;

    public const MIN_SECTIONS = 2;

    public const MIN_SIMILARITY = 0.2;

    /** The call runs inside the owner's approval request, so it gives up well before the SDK's 30 second default. */
    public const TIMEOUT_SECONDS = 15;

    public function __construct(private readonly VoiceCanon $canon, private readonly ExemplarEmbeddings $embeddings) {}

    /** @return RetrievalContext */
    public function forAttempt(PublishingAttempt $attempt): array
    {
        $query = $this->queryText($attempt);
        $queryHash = hash('sha256', $query);

        if ($query === '') {
            return $this->context('insufficient', 'The brief, angle and plan have no text to match.', $queryHash);
        }

        $ideaId = Article::query()->whereKey($attempt->article_id)->value('content_idea_id');
        $candidates = $this->candidates(is_numeric($ideaId) ? (int) $ideaId : null);

        if (! $candidates->clone()->exists()) {
            return $this->context('insufficient', 'No embedded sections from other published posts are available yet.', $queryHash);
        }

        try {
            $vector = $this->embeddings->embed([$query], self::TIMEOUT_SECONDS)[0];
            $rows = $candidates
                ->select(['exemplar_sections.id', 'exemplar_sections.heading', 'exemplar_sections.text', 'articles.slug as article_slug'])
                ->selectVectorDistance('exemplar_sections.embedding', $vector, as: 'distance')
                ->whereVectorSimilarTo('exemplar_sections.embedding', $vector, minSimilarity: self::MIN_SIMILARITY)
                ->limit(self::LIMIT)
                ->get();
        } catch (Throwable $exception) {
            return $this->context('unavailable', Str::limit(class_basename($exception).': '.$exception->getMessage(), 300), $queryHash);
        }

        $sections = array_values($rows->map(fn (object $row): array => [
            'section_id' => (int) $row->id,
            'article_slug' => (string) $row->article_slug,
            'heading' => is_string($row->heading) ? $row->heading : null,
            'text' => (string) $row->text,
            'similarity' => round(1 - (float) $row->distance, 4),
        ])->all());

        if (count($sections) < self::MIN_SECTIONS) {
            return $this->context('insufficient', count($sections).' section(s) passed the similarity cut.', $queryHash, $sections);
        }

        return $this->context('ok', null, $queryHash, $sections);
    }

    /**
     * The brief summary, target query, angle thesis and plan argument, one per line. Owner-edited arrays can hold other
     * shapes, so only non-empty strings count.
     */
    public function queryText(PublishingAttempt $attempt): string
    {
        $parts = [
            data_get($attempt->brief, 'summary'),
            data_get($attempt->brief, 'target_query'),
            data_get($attempt->angle, 'thesis'),
            data_get($attempt->plan, 'argument'),
        ];

        return implode("\n", array_values(array_filter(
            array_map(static fn (mixed $part): string => is_string($part) ? trim($part) : '', $parts),
            static fn (string $part): bool => $part !== '',
        )));
    }

    /**
     * Embedded sections of published blog posts outside the canon and outside this attempt's own idea. Canon slugs
     * are filtered here too, because the canon list can change after a section was indexed.
     */
    private function candidates(?int $ideaId): Builder
    {
        return DB::table('exemplar_sections')
            ->join('articles', 'articles.id', '=', 'exemplar_sections.article_id')
            ->whereNotNull('exemplar_sections.embedding')
            ->where('articles.kind', PieceKind::BlogPost->value)
            ->whereNotNull('articles.published_release_id')
            ->whereNotIn('articles.slug', $this->canon->snapshot()->exemplarManifest['archive'])
            ->when($ideaId !== null, fn (Builder $query) => $query->where('articles.content_idea_id', '!=', $ideaId));
    }

    /**
     * @param  'ok'|'insufficient'|'unavailable'  $status
     * @param  list<RetrievedSection>  $sections
     * @return RetrievalContext
     */
    private function context(string $status, ?string $reason, string $queryHash, array $sections = []): array
    {
        return ['status' => $status, 'reason' => $reason, 'query_hash' => $queryHash, 'sections' => $sections];
    }
}
