<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Laravel\Head\Facades\Head;

class MarketingSite
{
    public function url(string $path = '/'): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/'.ltrim($path, '/');

        if (str_starts_with($path, '/writing')) {
            $path = rtrim($path, '/').'/';
        } elseif ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return rtrim(config('marketing.url'), '/').$path;
    }

    public function host(): string
    {
        return parse_url(config('marketing.url'), PHP_URL_HOST);
    }

    public function indexable(): bool
    {
        return (bool) config('marketing.indexable');
    }

    public function head(string $title, string $description, string $path, ?CarbonInterface $publishedAt = null, bool $walkthrough = false): void
    {
        $canonical = $this->url($path);
        $person = ['@type' => 'Person', '@id' => $this->url().'#person', 'name' => 'Birdcar', 'url' => $this->url()];
        $website = ['@type' => 'WebSite', '@id' => $this->url().'#website', 'name' => 'Birdcar', 'url' => $this->url(), 'publisher' => ['@id' => $person['@id']]];
        $page = [
            '@type' => $publishedAt ? 'BlogPosting' : 'WebPage',
            '@id' => $canonical.'#'.($publishedAt ? 'article' : 'page'),
            'url' => $canonical,
            'name' => $title,
            'description' => $description,
            'inLanguage' => 'en',
            'isPartOf' => ['@id' => $website['@id']],
        ];

        if ($publishedAt) {
            $page['headline'] = $title;
            $page['datePublished'] = $publishedAt->toIso8601String();
            $page['author'] = ['@id' => $person['@id']];
            $page['mainEntityOfPage'] = $canonical;
        }

        $graph = [$person, $website, $page];

        if ($walkthrough) {
            $serviceId = $canonical.'#service';
            $graph[2]['mainEntity'] = ['@id' => $serviceId];
            $graph[] = [
                '@type' => 'Service',
                '@id' => $serviceId,
                'name' => 'The Walkthrough',
                'serviceType' => 'Business process assessment',
                'description' => $description,
                'url' => $canonical,
                'provider' => ['@id' => $person['@id']],
            ];
        }

        Head::title($title.' — Birdcar', exact: true)
            ->description($description)
            ->canonical($canonical, forceHttps: false)
            ->themeColor('#291e2e')
            ->robots($this->indexable() ? 'index, follow, max-image-preview:large' : 'noindex, nofollow')
            ->og(type: $publishedAt ? 'article' : 'website', title: $title.' — Birdcar', description: $description, url: $canonical, siteName: 'Birdcar', locale: 'en_US')
            ->twitter(card: 'summary', title: $title.' — Birdcar', description: $description);

        foreach ($graph as $entity) {
            Head::schema(['@context' => 'https://schema.org', ...$entity]);
        }

        if ($publishedAt) {
            Head::meta('article:published_time', $publishedAt->toIso8601String(), property: true);
        }
    }
}
