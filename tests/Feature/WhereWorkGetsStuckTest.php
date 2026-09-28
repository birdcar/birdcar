<?php

use Carbon\CarbonImmutable;
use Tests\TestCase;

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

function stuckPageDocument(string|false $html): DOMDocument
{
    if ($html === false) {
        throw new RuntimeException('Expected string response content.');
    }

    $document = new DOMDocument;
    @$document->loadHTML($html);

    return $document;
}

function stuckPageXPath(TestCase $test): DOMXPath
{
    return new DOMXPath(stuckPageDocument($test->get('/tools/where-work-gets-stuck')->assertOk()->getContent()));
}

/**
 * @return list<DOMElement>
 */
function stuckPageElements(DOMXPath $xpath, string $expression, ?DOMNode $context = null): array
{
    $nodes = $xpath->query($expression, $context);

    if ($nodes === false) {
        throw new RuntimeException("XPath query failed: {$expression}");
    }

    $elements = [];

    foreach ($nodes as $node) {
        if ($node instanceof DOMElement) {
            $elements[] = $node;
        }
    }

    return $elements;
}

function stuckPageElement(DOMXPath $xpath, string $expression, ?DOMNode $context = null): DOMElement
{
    $elements = stuckPageElements($xpath, $expression, $context);

    if ($elements === []) {
        throw new RuntimeException("XPath query matched no element: {$expression}");
    }

    return $elements[0];
}

test('the map links each of the eight patterns to its permanent anchor in order', function () use ($patterns) {
    $links = stuckPageElements(stuckPageXPath($this), '//nav[@id="map"]//ol/li/a');

    expect(collect($links)->map(fn (DOMElement $link): array => [
        ltrim($link->getAttribute('href'), '#'),
        trim($link->getElementsByTagName('span')->item(1)->textContent),
    ])->all())->toBe(collect($patterns)->map(fn (string $name, string $slug): array => [$slug, $name])->values()->all());
});

test('every pattern entry is present at its anchor with its definition, signals, and next step', function () use ($patterns) {
    $xpath = stuckPageXPath($this);

    foreach ($patterns as $slug => $name) {
        $entries = stuckPageElements($xpath, '//article[@id="'.$slug.'"]');

        expect($entries)->toHaveCount(1);

        $entry = $entries[0];

        expect(trim(stuckPageElement($xpath, './/h2', $entry)->textContent))->toBe($name);
        expect(stuckPageElements($xpath, './/p[@class="stuck-definition"]', $entry))->toHaveCount(1);
        expect(count(stuckPageElements($xpath, './/ul/li', $entry)))->toBeGreaterThanOrEqual(3);
        expect(stuckPageElements($xpath, './/a[@href="#map"]', $entry))->toHaveCount(1);
    }
});

test('structured data describes the patterns as a defined term set', function () use ($patterns) {
    config(['marketing.url' => 'https://birdcar.dev']);

    $scripts = stuckPageElements(stuckPageXPath($this), '//script[@type="application/ld+json"]');
    $entities = collect($scripts)->map(fn (DOMElement $script): array => json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR));
    $page = 'https://birdcar.dev/tools/where-work-gets-stuck';

    expect($entities->firstWhere('@type', 'WebPage')['mainEntity'])->toBe(['@id' => $page.'#patterns']);
    expect($entities->firstWhere('@type', 'DefinedTermSet')['hasDefinedTerm'])->toHaveCount(8);
    expect($entities->where('@type', 'DefinedTerm')->pluck('url')->all())
        ->toBe(array_map(fn (string $slug): string => $page.'#'.$slug, array_keys($patterns)));
});

test('the page offers the walkthrough without claims the site has not approved', function () {
    $this->get('/tools/where-work-gets-stuck')->assertOk()
        ->assertSee('href="'.route('public.walkthrough').'" data-booking-cta="stuck-hero"', false)
        ->assertSee('within three business days')
        ->assertSee(route('public.work').'#craft-and-communicate', false)
        ->assertDontSee('GHX')
        ->assertDontSee('DataDash')
        ->assertDontSee('WorkOS');
});

test('the homepage, walkthrough, and footer link to the patterns', function (string $path) {
    $xpath = new DOMXPath(stuckPageDocument($this->get($path)->assertOk()->getContent()));
    $href = route('public.where-work-gets-stuck');

    expect(count(stuckPageElements($xpath, '//main//a[@href="'.$href.'"]')))->toBeGreaterThanOrEqual(1);
    expect(stuckPageElements($xpath, '//footer//a[@href="'.$href.'"]'))->toHaveCount(1);
})->with(['/', '/walkthrough']);

test('the footer marks the patterns page as current', function () {
    $current = stuckPageElements(stuckPageXPath($this), '//footer/nav/a[@aria-current="page"]');

    expect($current)->toHaveCount(1);
    expect(trim($current[0]->textContent))->toBe('Where work gets stuck');
});
