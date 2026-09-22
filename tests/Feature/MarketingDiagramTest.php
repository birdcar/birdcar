<?php

use App\Actions\ReadWriting;
use App\Services\Publishing\ArticleDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

test('curated diagrams keep their complete explanation between adjacent markdown', function (string $name, array $meaning) {
    $html = app(ReadWriting::class)->render("Before **the figure**.\n\n@figure kind=diagram name={$name} caption=\"A reading figure.\"\n@endfigure\n\nAfter *the figure*.");

    expect($html)->toContain('<strong>the figure</strong>', '<em>the figure</em>', 'A reading figure.', ...$meaning)
        ->not->toContain('@figure', 'BIRDCARBLOCK', 'data-walkthrough-diagram');
})->with([
    'walkthrough' => ['walkthrough', ['Show me the work', 'Keep the report', 'Choose what happens next', 'three business days', 'Where I’d start', 'separate implementation', 'No purchase obligation']],
    'reporting' => ['reporting', ['Finding the numbers by hand', 'Performance data', 'Client management', 'Part of the agency’s service', 'not a product screenshot or measured results']],
]);

test('repeated article diagrams do not duplicate identifiers or lose captions', function () {
    $body = "@figure kind=diagram name=walkthrough caption=\"First.\"\n@endfigure\n\n@figure kind=diagram name=walkthrough caption=\"Second.\"\n@endfigure";
    $html = app(ReadWriting::class)->render($body);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//figure'))->toHaveCount(2);
    expect($xpath->query('//figure/ol/li/h3'))->toHaveCount(6);
    expect($xpath->query('//*[@id or @aria-labelledby or @aria-describedby]'))->toHaveCount(0);
    expect($html)->toContain('First.', 'Second.');
});

test('diagram captions accept escaped quotes without interpreting markup or blade', function () {
    $html = app(ReadWriting::class)->render(<<<'MARKDOWN'
@figure kind=diagram name=reporting caption="A \"quoted\" <script>alert('x')</script> & {{ 7 * 7 }} caption."
@endfigure
MARKDOWN);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//figcaption'))->toHaveCount(1);
    expect($xpath->query('//figcaption/script'))->toHaveCount(0);
    expect($xpath->query('//figcaption')->item(0)->textContent)->toContain('A "quoted" <script>alert(\'x\')</script> & {{ 7 * 7 }} caption.');
});

test('unknown and malformed diagrams remain inert text', function (string $directive) {
    $html = app(ReadWriting::class)->render($directive);

    expect($html)->not->toContain('<figure', '<script', '<svg', 'onload=', 'href="javascript:', 'BIRDCARBLOCK');
    expect($html)->toContain('@figure');
})->with([
    'unknown' => "@figure kind=diagram name=unapproved caption=\"No.\"\n@endfigure",
    'traversal' => "@figure kind=diagram name=../../.env caption=\"No.\"\n@endfigure",
    'view path' => "@figure kind=diagram name=marketing.layout caption=\"No.\"\n@endfigure",
    'missing close' => '@figure kind=diagram name=walkthrough caption="No."',
    'extra attribute' => "@figure kind=diagram name=walkthrough caption=\"No.\" src=secret\n@endfigure",
    'multiline caption' => "@figure kind=diagram name=walkthrough caption=\"Not\nallowed\"\n@endfigure",
    'unescaped quotes' => "@figure kind=diagram name=walkthrough caption=\"A \"broken\" caption\"\n@endfigure",
    'raw payload' => "@figure kind=diagram name=walkthrough caption=\"No.\"\n<svg onload=\"alert(1)\"><script>alert(1)</script></svg>\n@endfigure",
]);

test('curated diagrams coexist with legacy notes and source charts without admitting unsafe html', function () {
    $html = app(ReadWriting::class)->render(<<<'MARKDOWN'
Before.

@aside title="About this post"
By (@birdcar). <script>alert('x')</script> [unsafe](javascript:alert(1))
@endaside

@figure kind=diagram name=walkthrough caption="The process."
@endfigure

@figure kind=chart type=bar src=./data/bug-fix-loops.json width=wide caption="Original chart."
@endfigure

@callout type=key
Keep **the meaning**.
@endcallout

@figure kind=diagram name=reporting caption="The reporting."
@endfigure

After.
MARKDOWN);

    expect($html)->toContain('Before.', 'After.', 'About this post', 'href="/authors/birdcar"', 'Key takeaway', '<strong>the meaning</strong>', '<td>20</td>', '<td>15</td>', '<td>10</td>', '<td>7</td>', 'The process.', 'The reporting.')
        ->not->toContain('<script', 'href="javascript:', '@figure', '@aside', '@callout', 'BIRDCARBLOCK');
});

test('walkthrough motion requires an explicit marketing opt in', function () {
    expect(Blade::render('<x-marketing.walkthrough-diagram />'))->not->toContain('data-walkthrough-diagram');
    expect(Blade::render('<x-marketing.walkthrough-diagram :animate="true" />'))->toContain('data-walkthrough-diagram');
});

test('the specimen is a standalone noindex reading view only in allowed environments', function (string $environment) {
    $original = app()->environment();

    try {
        app()->instance('env', $environment);
        config(['marketing.indexable' => true]);

        $this->get(route('public.figure-specimen'))
            ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Development specimen')->assertSee('Illustrative reading excerpt')
            ->assertSee('Keep the report')->assertSee('Client management')->assertSee('View chart data')
            ->assertDontSee('data-posthog')->assertDontSee('data-cal-')->assertDontSee('cal.com')
            ->assertDontSee('resources/js/app.js')->assertDontSee('application/ld+json');
    } finally {
        app()->instance('env', $original);
    }
})->with(['local', 'testing']);

test('a registered specimen route refuses production and staging at request time', function (string $environment) {
    $route = Route::getRoutes()->getByName('public.figure-specimen');
    expect($route)->not->toBeNull();
    $original = app()->environment();

    try {
        app()->instance('env', $environment);
        $this->get(route('public.figure-specimen'))->assertNotFound();
    } finally {
        app()->instance('env', $original);
    }
})->with(['production', 'staging']);

test('truthful charts render zero and one row without false divisions', function () {
    $zeroChart = view('components.marketing.article-chart', [
        'type' => 'bar',
        'caption' => 'Zero values.',
        'chart' => ['x' => 'label', 'series' => [['key' => 'value', 'label' => 'Value']], 'data' => [['label' => 'Only', 'value' => 0]]],
    ])->render();
    $lineChart = view('components.marketing.article-chart', [
        'type' => 'line',
        'caption' => 'One point.',
        'chart' => ['x' => 'month', 'series' => [['key' => 'prompts', 'label' => 'Prompts']], 'data' => [['month' => 'Now', 'prompts' => 0]]],
    ])->render();

    expect($zeroChart)->toContain('width: 0%')
        ->and($lineChart)->toContain('320')
        ->not->toContain('NAN', 'INF', 'nan', 'inf');
});

test('server validated source svg diagrams keep safe static meaning only', function () {
    $document = [
        'version' => 1,
        'type' => 'doc',
        'content' => [[
            'type' => 'diagram',
            'attrs' => [
                'id' => 'blk_0123456789abcdef',
                'sourceType' => 'svg',
                'caption' => 'Safe diagram.',
                'source' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><title>Safe</title><desc>Safe</desc><circle cx="5" cy="5" r="4" fill="none" stroke="currentColor" /></svg>',
            ],
        ]],
    ];

    $html = app(ArticleDocument::class)->renderHtml($document);

    expect($html)->toContain('<svg', '<title>Safe</title>', 'Safe diagram.')
        ->not->toContain('onload', '<script', 'foreignObject', 'href=');

    $document['content'][0]['attrs']['source'] = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
    expect(fn () => app(ArticleDocument::class)->validate($document))->toThrow(InvalidArgumentException::class);
});

test('the specimen is not reachable on application or arbitrary hosts', function (string $host) {
    $this->get('https://'.$host.'/__design/figures')->assertNotFound();
})->with(['admin.birdcar.dev', 'customer.birdcar.dev', 'unrelated.example']);
