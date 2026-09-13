<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * @phpstan-type Article array{slug: string, title: string, description: string, date: CarbonImmutable, tags: list<string>, body: string, readMinutes: int}
 */
class ReadWriting
{
    /** @return Collection<int, Article> */
    public function all(): Collection
    {
        return collect(glob(resource_path('writing/*.md')) ?: [])
            ->map(fn (string $path): ?array => $this->read($path))
            ->filter()
            ->sortByDesc(fn (array $article): string => $article['date']->format('Y-m-d').$article['slug'])
            ->values();
    }

    /** @return Article|null */
    public function find(string $slug): ?array
    {
        return $this->all()->firstWhere('slug', $slug);
    }

    public function render(string $body): string
    {
        $blocks = [];
        $markdown = preg_replace_callback(
            '/^@figure kind=chart type=(line|bar) src=\.\/data\/([a-z0-9-]+\.json) width=wide caption="([^"]+)"\R@endfigure\s*$/m',
            function (array $match) use (&$blocks): string {
                $key = 'BIRDCARBLOCK'.count($blocks).'END';
                /** @var array{x: string, series: list<array{key: string, label: string}>, data: list<array<string, int|string>>} $data */
                $data = json_decode(File::get(resource_path('writing/data/'.$match[2])), true, flags: JSON_THROW_ON_ERROR);
                $blocks['<p>'.$key.'</p>'] = view('components.marketing.article-chart', [
                    'chart' => $data,
                    'type' => $match[1],
                    'caption' => $match[3],
                ])->render();

                return "\n\n{$key}\n\n";
            },
            $body,
        ) ?? $body;

        $markdown = preg_replace_callback(
            '/^@(aside|callout)(?: title="([^"\r\n]+)"| type=key)\R(.*?)\R@end\1[\t ]*$/ms',
            function (array $match) use (&$blocks): string {
                $key = 'BIRDCARBLOCK'.count($blocks).'END';
                $noteWithAuthorLinks = preg_replace('/\(@([a-z][a-z0-9_-]*)\)/i', '([@$1](/authors/$1))', $match[3]) ?? $match[3];
                $blocks['<p>'.$key.'</p>'] = view('components.marketing.article-note', [
                    'title' => $match[1] === 'callout' ? 'Key takeaway' : $match[2],
                    'html' => Str::markdown($noteWithAuthorLinks, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
                ])->render();

                return "\n\n{$key}\n\n";
            },
            $markdown,
        ) ?? $markdown;

        return strtr(Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]), $blocks);
    }

    /** @return Article|null */
    private function read(string $path): ?array
    {
        $parts = preg_split('/\A---\R|\R---\R/', File::get($path), 3);

        if ($parts === false || count($parts) !== 3) {
            return null;
        }

        /** @var array{title: string, description?: string, date: int|string, tags?: list<string>, draft?: bool} $metadata */
        $metadata = Yaml::parse($parts[1]);

        if ($metadata['draft'] ?? false) {
            return null;
        }

        $date = is_int($metadata['date'])
            ? CarbonImmutable::createFromTimestampUTC($metadata['date'])
            : CarbonImmutable::parse($metadata['date']);

        if ($date->isFuture()) {
            return null;
        }

        return [
            'slug' => pathinfo($path, PATHINFO_FILENAME),
            'title' => $metadata['title'],
            'description' => $metadata['description'] ?? '',
            'date' => $date,
            'tags' => $metadata['tags'] ?? [],
            'body' => $parts[2],
            'readMinutes' => max(1, (int) ceil(str_word_count(strip_tags($parts[2])) / 220)),
        ];
    }
}
