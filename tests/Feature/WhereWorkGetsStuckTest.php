<?php

use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-09-24');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

$patterns = [
    'it-runs-on-remembering' => 'It runs on remembering',
    'the-same-information-typed-twice' => 'The same information, typed twice',
    'nobody-trusts-the-numbers' => 'Nobody trusts the numbers',
    'assembled-by-hand' => 'Assembled by hand',
    'waiting-on-a-reply' => 'Waiting on a reply',
    'only-one-person-knows-how' => 'Only one person knows how',
    'waiting-on-your-yes' => 'Waiting on your yes',
    'the-workaround-became-the-process' => 'The workaround became the process',
];

function stuckPageXPath($test): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML($test->get('/tools/where-work-gets-stuck')->assertOk()->getContent());

    return new DOMXPath($document);
}

test('the map links each of the eight patterns to its permanent anchor in order', function () use ($patterns) {
    $links = stuckPageXPath($this)->query('//nav[@id="map"]//ol/li/a');

    expect(collect(iterator_to_array($links))->map(fn (DOMElement $link): array => [
        ltrim($link->getAttribute('href'), '#'),
        trim($link->getElementsByTagName('span')->item(1)->textContent),
    ])->all())->toBe(collect($patterns)->map(fn (string $name, string $slug): array => [$slug, $name])->values()->all());
});

test('every pattern entry is present at its anchor with its definition, signals, and next step', function () use ($patterns) {
    $xpath = stuckPageXPath($this);

    foreach ($patterns as $slug => $name) {
        $entry = $xpath->query('//article[@id="'.$slug.'"]');

        expect($entry)->toHaveCount(1);
        expect(trim($xpath->query('.//h2', $entry->item(0))->item(0)->textContent))->toBe($name);
        expect($xpath->query('.//p[@class="stuck-definition"]', $entry->item(0)))->toHaveCount(1);
        expect($xpath->query('.//ul/li', $entry->item(0))->length)->toBeGreaterThanOrEqual(3);
        expect($xpath->query('.//a[@href="#map"]', $entry->item(0)))->toHaveCount(1);
    }
});

test('structured data describes the patterns as a defined term set', function () use ($patterns) {
    config(['marketing.url' => 'https://birdcar.dev']);

    $scripts = stuckPageXPath($this)->query('//script[@type="application/ld+json"]');
    $entities = collect(iterator_to_array($scripts))->map(fn ($script): array => json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR));
    $page = 'https://birdcar.dev/tools/where-work-gets-stuck';

    expect($entities->firstWhere('@type', 'WebPage')['mainEntity'])->toBe(['@id' => $page.'#patterns']);
    expect($entities->firstWhere('@type', 'DefinedTermSet')['hasDefinedTerm'])->toHaveCount(8);
    expect($entities->where('@type', 'DefinedTerm')->pluck('url')->all())
        ->toBe(array_map(fn (string $slug): string => $page.'#'.$slug, array_keys($patterns)));
});

test('the page offers the walkthrough without claims the site has not approved', function () {
    $this->get('/tools/where-work-gets-stuck')->assertOk()
        ->assertSee('data-cal-link="birdcar/walkthrough"', false)
        ->assertSee('within three business days')
        ->assertSee(route('public.work').'#craft-and-communicate', false)
        ->assertDontSee('GHX')
        ->assertDontSee('DataDash')
        ->assertDontSee('WorkOS');
});

test('the homepage, walkthrough, and footer link to the patterns', function (string $path) {
    $xpath = new DOMXPath(tap(new DOMDocument, fn (DOMDocument $document) => @$document->loadHTML($this->get($path)->assertOk()->getContent())));
    $href = route('public.where-work-gets-stuck');

    expect($xpath->query('//main//a[@href="'.$href.'"]'))->toHaveCount(1);
    expect($xpath->query('//footer//a[@href="'.$href.'"]'))->toHaveCount(1);
})->with(['/', '/walkthrough']);

test('the footer marks the patterns page as current', function () {
    $current = stuckPageXPath($this)->query('//footer/nav/a[@aria-current="page"]');

    expect($current)->toHaveCount(1);
    expect(trim($current->item(0)->textContent))->toBe('Where work gets stuck');
});
