<?php

use App\Actions\ReadWriting;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-09-12');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('the homepage presents the approved offer and only the approved work story', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('I help businesses untangle work')
        ->assertSee('fifteen years')
        ->assertSee('Craft &amp; Communicate', false)
        ->assertSee(route('public.walkthrough'), false)
        ->assertSee('Book a free Walkthrough')
        ->assertDontSee('GHX')
        ->assertDontSee('DataDash')
        ->assertDontSee('more than fifteen years')
        ->assertDontSee('Your AI wrote a bug');
});

test('the selected work page names the client without private engagement details', function () {
    $this->get('/work')->assertOk()
        ->assertSee('Craft &amp;', false)
        ->assertSee('client-facing platform')
        ->assertDontSee('GHX')
        ->assertDontSee('DataDash')
        ->assertDontSee('retainer');
});

test('the walkthrough explains the free report and embeds the actual Cal event with a plain fallback', function () {
    $this->get('/walkthrough')->assertOk()
        ->assertSee('id="choose-a-time"', false)
        ->assertSee('data-cal-inline data-cal-link="birdcar/walkthrough" data-cal-namespace="walkthrough"', false)
        ->assertSee('href="https://cal.com/birdcar/walkthrough"', false)
        ->assertSee('href="#choose-a-time"', false)
        ->assertDontSee('data-cal-config')
        ->assertDontSee('cal.com/birdcar/60min')
        ->assertSee('The conversation and the written report are free.')
        ->assertSee('separate implementation engagement')
        ->assertDontSee('You’re booked');
});

test('booking buttons away from the walkthrough page open the calendar in place and keep the walkthrough link as fallback', function (string $path) {
    $this->get($path)->assertOk()
        ->assertSee('href="'.route('public.walkthrough').'"', false)
        ->assertSee('data-cal-link="birdcar/walkthrough"', false)
        ->assertSee('data-cal-namespace="walkthrough"', false)
        ->assertSee('data-cal-config=', false)
        ->assertDontSee('data-cal-inline');
})->with(['/', '/work', '/writing/']);

test('no page still calls the offer a free assessment', function (string $path) {
    $response = $this->get($path)->assertOk();

    expect(mb_strtolower($response->getContent()))->not->toContain('free assessment')->not->toContain('free-assessment');
})->with(['/', '/walkthrough', '/work', '/writing/']);

test('the archive lists all essays in chronological order under the business introduction', function () {
    $this->get('/writing/')->assertOk()
        ->assertSee('Better ways to run the work.')
        ->assertSeeInOrder(['Just build it twice', 'Your AI wrote a bug', 'The tools that build the tools', 'Six months of talking to a machine', 'The other side of empathy', 'Your metrics are bullshit', 'Yetto values: Joy Matters', 'Data is a curse', 'You&#039;re already doing all hands support', 'Stop giving me take homes'], false);
});

test('each original essay remains available at its published URL and date', function (string $slug, string $title, string $date) {
    $this->get('/writing/'.$slug.'/')->assertOk()
        ->assertSee($title)
        ->assertSee('datetime="'.$date.'"', false)
        ->assertDontSee('@figure')
        ->assertDontSee('@endfigure')
        ->assertDontSee('@aside')
        ->assertDontSee('@endaside')
        ->assertDontSee('@callout')
        ->assertDontSee('@endcallout');
})->with([
    ['just-build-it-twice', 'Just build it twice', '2026-05-26'],
    ['your-ai-wrote-a-bug', 'Your AI wrote a bug', '2026-04-17'],
    ['the-tools-that-build-the-tools', 'The tools that build the tools', '2026-04-10'],
    ['six-months-talking-to-a-machine', 'Six months of talking to a machine', '2026-04-03'],
    ['the-other-side-of-empathy', 'The other side of empathy', '2024-09-25'],
    ['your-metrics-are-bullshit', 'Your metrics are bullshit', '2024-05-01'],
    ['joy-matters', 'Yetto values: Joy Matters', '2024-01-24'],
    ['data-is-a-curse', 'Data is a curse', '2023-07-26'],
    ['youre-already-doing-all-hands-support', "You're already doing all hands support", '2023-06-14'],
    ['stop-giving-me-take-homes', 'Stop giving me take homes', '2023-05-31'],
]);

test('original prose and link destinations survive article rendering', function () {
    $this->get('/writing/just-build-it-twice/')->assertOk()
        ->assertSee('The agent has lowered the cost of building the same thing twice.')
        ->assertSee('href="https://www.birdcar.dev/writing/your-ai-wrote-a-bug/"', false);
});

test('original article notes keep their titles and formatted content', function () {
    $this->get('/writing/stop-giving-me-take-homes/')->assertOk()
        ->assertSee('<aside', false)
        ->assertSee('About this post')
        ->assertSee('href="/authors/balevine"', false)
        ->assertSee('href="/authors/birdcar"', false)
        ->assertSee('<em>Are you a support professional who has something to say?', false);

    $this->get('/writing/your-ai-wrote-a-bug/')->assertOk()
        ->assertSee('<aside', false)
        ->assertSee('The generation step got faster. The verification step expanded');
});

test('charts retain their source values and expose accessible data tables', function (string $slug, array $values) {
    $response = $this->get('/writing/'.$slug.'/')->assertOk()->assertSee('View chart data');
    foreach ($values as $value) {
        $response->assertSee('<td>'.$value.'</td>', false);
    }
})->with([
    ['your-ai-wrote-a-bug', [20, 15, 10, 7]],
    ['six-months-talking-to-a-machine', [410, 580, 720, 690, 780, 796]],
]);

test('unknown essays return a not found response', function () {
    $this->get('/writing/not-a-published-essay/')->assertNotFound();
});

test('rendering markdown strips executable HTML and unsafe links', function () {
    $html = app(ReadWriting::class)->render('<script>alert("unsafe")</script>'."\n\n".'[bad](javascript:alert(1))');
    expect($html)->not->toContain('<script', 'href="javascript:');
});

test('the feed includes all original posts with XML-safe text', function () {
    $response = $this->get('/rss.xml')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    $feed = simplexml_load_string($response->getContent());
    expect($feed)->not->toBeFalse();
    expect($feed->channel->item)->toHaveCount(10);
    expect((string) $feed->channel->item[0]->title)->toBe('Just build it twice');
});

test('the old marketing entry points redirect to the new destinations', function (string $from, string $to) {
    $this->get($from)->assertStatus(301)->assertRedirect($to);
})->with([
    ['/blog', '/writing'],
    ['/case-studies', '/work'],
    ['/contact', '/walkthrough'],
    ['/assessment', '/walkthrough'],
]);

test('the approach navigation resolves to a real section and the logo appears only in the header', function () {
    $response = $this->get('/')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@id="how-i-work"]'))->toHaveCount(1);
    expect($xpath->query('//header/a[@class="wordmark"]'))->toHaveCount(1);
    expect($xpath->query('//footer//*[@class="wordmark" or @class="signature"]'))->toHaveCount(0);
    $response->assertSee('href="'.route('public.index').'#how-i-work"', false);
});
