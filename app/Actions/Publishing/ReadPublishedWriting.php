<?php

namespace App\Actions\Publishing;

use App\Models\Article;
use App\Models\ArticleRelease;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * @phpstan-type PublicArticle array{slug: string, title: string, description: string, date: CarbonImmutable, tags: list<string>, html: string, readMinutes: int}
 */
class ReadPublishedWriting
{
    /** @return Collection<int, PublicArticle> */
    public function all(): Collection
    {
        return Article::query()
            ->whereNotNull('published_release_id')
            ->whereHas('publishedRelease', function ($query): void {
                $query->where('status', 'published')->whereNotNull('published_at');
            })
            ->with('publishedRelease')
            ->get()
            ->map(fn (Article $article): ?array => $this->fromArticle($article))
            ->filter()
            ->sortByDesc(fn (array $article): string => $article['date']->format('Y-m-d H:i:s').$article['slug'])
            ->values();
    }

    /** @return PublicArticle|null */
    public function find(string $slug): ?array
    {
        $article = Article::query()
            ->where(function ($query) use ($slug): void {
                $query->where('slug', $slug)
                    ->orWhereHas('publishedRelease', function ($query) use ($slug): void {
                        $query->where('payload->canonical_slug', $slug);
                    });
            })
            ->whereNotNull('published_release_id')
            ->whereHas('publishedRelease', function ($query): void {
                $query->where('status', 'published')->whereNotNull('published_at');
            })
            ->with('publishedRelease')
            ->first();

        return $article instanceof Article ? $this->fromArticle($article) : null;
    }

    /** @return PublicArticle|null */
    private function fromArticle(Article $article): ?array
    {
        $release = $article->publishedRelease;

        if (! $release instanceof ArticleRelease || $release->getAttribute('status') !== 'published' || $release->getAttribute('published_at') === null) {
            return null;
        }

        $payload = $this->arrayValue($release->getAttribute('payload'));
        $metadata = $this->arrayValue($payload['metadata'] ?? []);
        $rendered = $this->arrayValue($payload['rendered_document'] ?? []);
        $html = $rendered['html'] ?? null;

        if (! is_string($html) || $html === '') {
            return null;
        }

        $date = $this->dateFromPayload($payload, $release, $article);
        $tags = $metadata['tags'] ?? [];

        return [
            'slug' => is_string($payload['canonical_slug'] ?? null) ? $payload['canonical_slug'] : (string) $article->slug,
            'title' => (string) ($metadata['title'] ?? $article->idea ?? 'Untitled'),
            'description' => (string) ($metadata['description'] ?? ''),
            'date' => $date,
            'tags' => is_array($tags) ? array_values(array_filter($tags, 'is_string')) : [],
            'html' => $html,
            'readMinutes' => $this->readMinutes($payload, $html),
        ];
    }

    /** @return array<string, mixed> */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @param array<string, mixed> $payload */
    private function dateFromPayload(array $payload, ArticleRelease $release, Article $article): CarbonImmutable
    {
        $value = $payload['original_public_date'] ?? $release->getAttribute('published_at') ?? $article->getAttribute('first_published_at');

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value);
        }

        return CarbonImmutable::parse('now');
    }

    /** @param array<string, mixed> $payload */
    private function readMinutes(array $payload, string $html): int
    {
        $document = $this->arrayValue($payload['document'] ?? []);
        $text = $this->documentText($document);

        if ($text === '') {
            $text = strip_tags($html);
        }

        return max(1, (int) ceil(str_word_count($text) / 220));
    }

    /** @param array<string, mixed> $document */
    private function documentText(array $document): string
    {
        $text = [];
        $walk = function (mixed $nodes) use (&$walk, &$text): void {
            if (! is_array($nodes)) {
                return;
            }

            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                if (is_string($node['text'] ?? null)) {
                    $text[] = $node['text'];
                }

                $walk($node['content'] ?? []);
            }
        };
        $walk($document['content'] ?? []);

        return trim(implode(' ', $text));
    }
}
