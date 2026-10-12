<?php

namespace App\Actions\Publishing;

use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ExemplarSection;
use App\Models\Publishing\PieceKind;
use App\Services\Publishing\ExemplarEmbeddings;
use App\Services\Publishing\Lint\LintTarget;
use App\Services\Publishing\VoiceCanon;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a published blog post's exemplar sections in step with its live release. Sections are keyed by a hash of their
 * heading and text, so re-indexing an unchanged article embeds nothing and an edited section replaces only itself.
 * The fixed canon essays are frozen in full into every Drafter and VoiceReviewer input, so they are never indexed.
 */
class IndexExemplarSections
{
    /** Longer sections are split at block boundaries; a single longer block stays whole. */
    public const SECTION_CHARACTER_LIMIT = 4000;

    public const EMBEDDING_BATCH = 64;

    public function __construct(private readonly VoiceCanon $canon, private readonly ExemplarEmbeddings $embeddings) {}

    /** @return array{created: int, pruned: int, embedded: int} */
    public function index(Article $article): array
    {
        $synced = $this->sync($article);

        return [...$synced, 'embedded' => $this->embedMissing($article)];
    }

    /**
     * Upserts the live release's sections and prunes every row whose hash vanished. An ineligible article loses all its
     * rows. No provider call happens here.
     *
     * @return array{created: int, pruned: int}
     */
    public function sync(Article $article): array
    {
        $article = Article::query()->select(['id', 'slug', 'kind', 'published_release_id'])->whereKey($article->getKey())->firstOrFail();
        $sections = $this->isEligible($article) ? $this->sectionsForRelease((int) $article->published_release_id) : [];

        /** @var array{created: int, pruned: int} $counts */
        $counts = DB::transaction(function () use ($article, $sections): array {
            $hashes = array_column($sections, 'content_hash');
            $existing = ExemplarSection::query()->where('article_id', $article->id)->pluck('content_hash')->all();

            if ($sections !== []) {
                ExemplarSection::query()->upsert(
                    array_map(fn (array $section): array => [
                        ...$section,
                        'article_id' => $article->id,
                        'release_id' => $article->published_release_id,
                        // upsert bypasses casts, so the enum is written as its value.
                        'kind' => PieceKind::BlogPost->value,
                    ], $sections),
                    ['article_id', 'content_hash'],
                    // Never embedding or embedded_at: a re-index must keep vectors that are already paid for.
                    ['release_id', 'position', 'heading'],
                );
            }

            $pruned = ExemplarSection::query()
                ->where('article_id', $article->id)
                ->whereNotIn('content_hash', $hashes)
                ->delete();

            return ['created' => count(array_diff($hashes, $existing)), 'pruned' => $pruned];
        });

        return $counts;
    }

    /**
     * Embeds the article's sections that have no vector yet, in batches. A provider failure leaves the remaining rows
     * without a vector and propagates, so a later run embeds only what is still missing.
     */
    public function embedMissing(Article $article): int
    {
        $embedded = 0;
        $missing = ExemplarSection::query()
            ->select(['id', 'heading', 'text'])
            ->where('article_id', $article->getKey())
            ->whereNull('embedding')
            ->orderBy('position')
            ->get();

        foreach ($missing->chunk(self::EMBEDDING_BATCH) as $batch) {
            $sections = $batch->values();
            $vectors = $this->embeddings->embed(array_values($sections->map(fn (ExemplarSection $section): string => self::embeddingInput($section->heading, $section->text))->all()));

            foreach ($sections as $index => $section) {
                $section->forceFill(['embedding' => $vectors[$index], 'embedded_at' => now()])->save();
                $embedded++;
            }
        }

        return $embedded;
    }

    public function isEligible(Article $article): bool
    {
        return $article->kind === PieceKind::BlogPost
            && $article->published_release_id !== null
            && ! in_array($article->slug, $this->canonSlugs(), true);
    }

    /** @return list<string> */
    public function canonSlugs(): array
    {
        return $this->canon->snapshot()->exemplarManifest['archive'];
    }

    /**
     * An H2 and the blocks after it up to the next H2 form one section; text before the first H2 is a section with no
     * heading. H3s stay inside their section. Blocks come from the same walker the voice linter reads, so payload
     * nodes prefer their `content` over the legacy `text` key and code is left out.
     *
     * @param  array<array-key, mixed>  $document
     * @return list<array{position: int, heading: string|null, text: string, content_hash: string}>
     */
    public function sections(array $document): array
    {
        $groups = [];
        $current = ['heading' => null, 'blocks' => []];

        foreach (LintTarget::fromDocument($document, [], PieceKind::BlogPost)->blocks as $block) {
            $text = trim(preg_replace('/\s+/u', ' ', $block['text']) ?? $block['text']);

            if ($block['type'] === 'heading' && $block['level'] === 2) {
                $groups[] = $current;
                $current = ['heading' => $text === '' ? null : mb_substr($text, 0, 255), 'blocks' => []];

                continue;
            }

            if ($text !== '') {
                $current['blocks'][] = $text;
            }
        }
        $groups[] = $current;

        $sections = [];
        foreach ($groups as $group) {
            foreach ($this->chunks($group['blocks']) as $blocks) {
                $text = implode("\n\n", $blocks);
                $hash = hash('sha256', ($group['heading'] ?? '')."\n".$text);

                // Postgres refuses an upsert that touches the same row twice, so a repeated section is kept once.
                if (array_key_exists($hash, $sections)) {
                    continue;
                }

                $sections[$hash] = ['position' => count($sections), 'heading' => $group['heading'], 'text' => $text, 'content_hash' => $hash];
            }
        }

        return array_values($sections);
    }

    public static function embeddingInput(?string $heading, string $text): string
    {
        return $heading === null ? $text : $heading."\n\n".$text;
    }

    /**
     * @param  list<string>  $blocks
     * @return list<non-empty-list<string>>
     */
    private function chunks(array $blocks): array
    {
        $chunks = [];
        $chunk = [];
        $length = 0;

        foreach ($blocks as $block) {
            $added = mb_strlen($block) + ($chunk === [] ? 0 : 2);
            if ($chunk !== [] && $length + $added > self::SECTION_CHARACTER_LIMIT) {
                $chunks[] = $chunk;
                $chunk = [];
                $added = mb_strlen($block);
                $length = 0;
            }

            $chunk[] = $block;
            $length += $added;
        }

        if ($chunk !== []) {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    /** @return list<array{position: int, heading: string|null, text: string, content_hash: string}> */
    private function sectionsForRelease(int $releaseId): array
    {
        $payload = ArticleRelease::query()->select(['id', 'payload'])->find($releaseId)?->payload;
        $document = is_array($payload) && is_array($payload['document'] ?? null) ? $payload['document'] : [];

        return $this->sections($document);
    }
}
