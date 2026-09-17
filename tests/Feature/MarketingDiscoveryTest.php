<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-09-13');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('canonical and social metadata use the configured origin without tracking parameters', function () {
    config(['marketing.url' => 'https://birdcar.dev', 'marketing.indexable' => true]);

    $this->get('/walkthrough?utm_source=example')
        ->assertSee('<link rel="canonical" href="https://birdcar.dev/walkthrough">', false)
        ->assertSee('property="og:url" content="https://birdcar.dev/walkthrough"', false)
        ->assertSee('name="robots" content="index, follow, max-image-preview:large"', false);
});

test('structured article data identifies its real author and original publication date', function () {
    config(['marketing.url' => 'https://birdcar.dev']);

    $response = $this->get('/writing/just-build-it-twice/?ref=reader')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $scripts = (new DOMXPath($document))->query('//script[@type="application/ld+json"]');
    $graph = collect(iterator_to_array($scripts))->map(fn ($script): array => json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR))->keyBy('@type');

    expect($scripts)->toHaveCount(3);
    expect($graph['Person'])->toMatchArray(['name' => 'Birdcar', '@id' => 'https://birdcar.dev/#person']);
    expect($graph['BlogPosting'])->toMatchArray([
        'headline' => 'Just build it twice',
        'url' => 'https://birdcar.dev/writing/just-build-it-twice/',
        'datePublished' => '2026-05-26T00:00:00+00:00',
        'author' => ['@id' => 'https://birdcar.dev/#person'],
    ])->not->toHaveKey('dateModified');
});

test('the walkthrough exposes the service described in its visible offer', function () {
    $response = $this->get('/walkthrough')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $scripts = (new DOMXPath($document))->query('//script[@type="application/ld+json"]');
    $graph = collect(iterator_to_array($scripts))->map(fn ($script): array => json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR))->keyBy('@type');

    expect($graph['Service'])->toMatchArray(['name' => 'The Walkthrough', 'serviceType' => 'Business process assessment']);
    expect($graph['Service']['provider']['@id'])->toBe($graph['Person']['@id']);
    $response->assertSee('The conversation and the written report are free.');
});

test('public production pages are indexable and preview pages are not', function (bool $indexable) {
    config(['marketing.indexable' => $indexable]);

    $response = $this->get('/work')->assertOk();

    if ($indexable) {
        $response->assertHeaderMissing('X-Robots-Tag');
    } else {
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
})->with([true, false]);

test('marketing pages and their sitemap do not appear on application hosts', function (string $host, string $path) {
    $this->get('https://'.$host.$path)->assertNotFound();
})->with([
    ['admin.birdcar.dev', '/work'],
    ['customer.birdcar.dev', '/walkthrough'],
    ['customer.birdcar.dev', '/writing/just-build-it-twice/'],
    ['admin.birdcar.dev', '/sitemap.xml'],
]);

test('private application responses are noindex even when public indexing is enabled', function () {
    config(['marketing.indexable' => true]);
    Route::domain('customer.birdcar.dev')->get('/discovery-test', fn () => response('Private application'))->name('customer.discovery-test');

    $this->get('https://customer.birdcar.dev/discovery-test')
        ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('robots publishes the canonical sitemap only for the public indexable site', function (bool $indexable) {
    config(['marketing.indexable' => $indexable]);

    $response = $this->get('/robots.txt')->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    if ($indexable) {
        $response->assertSee("Allow: /\n", false)->assertSee(rtrim(config('marketing.url'), '/').'/sitemap.xml', false);
    } else {
        $response->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap:');
    }
})->with([true, false]);

test('the sitemap contains the public pages and original archive with canonical URLs', function () {
    config(['marketing.url' => 'https://birdcar.dev']);

    $response = $this->get('/sitemap.xml')->assertOk();
    $xml = simplexml_load_string($response->getContent());
    $urls = array_map(fn ($url): string => (string) $url->loc, iterator_to_array($xml->url, false));

    expect($xml->url)->toHaveCount(14);
    expect($urls)->toContain('https://birdcar.dev/', 'https://birdcar.dev/work', 'https://birdcar.dev/walkthrough', 'https://birdcar.dev/writing/', 'https://birdcar.dev/writing/just-build-it-twice/');
    $response->assertDontSee('admin.')->assertDontSee('customer.')->assertDontSee('<lastmod>');
});

test('future dated writing stays out of the public sitemap', function () {
    CarbonImmutable::setTestNow('2026-04-18');

    $this->get('/sitemap.xml')->assertOk()
        ->assertDontSee('/writing/just-build-it-twice/')
        ->assertSee('/writing/your-ai-wrote-a-bug/');
});
