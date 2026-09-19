<?php

use App\Actions\ReadWriting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;

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
        ->assertSee('GitHub, Heroku, and Zapier')
        ->assertSee('another hire, another subscription, or another hour of your evening')
        ->assertSee('Implementation is a separate purchase')
        ->assertSee('working system, documentation, and training')
        ->assertSee('within three business days')
        ->assertSee('Craft &amp; Communicate', false)
        ->assertSee(route('public.walkthrough'), false)
        ->assertSee('Book a free Walkthrough')
        ->assertDontSee('GHX')
        ->assertDontSee('DataDash')
        ->assertDontSee('WorkOS')
        ->assertDontSee('more than fifteen years')
        ->assertDontSee('Your AI wrote a bug');
});

test('the homepage explains the whole walkthrough in visible reading order before the work story', function () {
    $response = $this->get('/')->assertOk()->assertSeeTextInOrder([
        'Why does everything',
        'Book a free Walkthrough',
        'Bring the work',
        'You don’t need to diagnose the problem or specify software.',
        'Talk it through',
        'roughly one hour',
        'Free and pitch-free.',
        'Keep the report',
        'Within three business days of our conversation',
        'What’s getting in the way',
        'What I’d change',
        'Where I’d start',
        'The first change and why it comes first.',
        'Choose what happens next',
        'Use the recommendations independently.',
        'Discuss separate implementation with me.',
        'Or do nothing. No purchase obligation.',
        'A real process, a free conversation, a report you keep.',
        'Craft &amp; Communicate',
    ], false);

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//figure[@data-walkthrough-diagram]/ol/li'))->toHaveCount(4);
    expect($xpath->query('//figure[@data-walkthrough-diagram]//*[@hidden or @aria-hidden="true"]//p'))->toHaveCount(0);
    expect($xpath->query('//figure[@data-walkthrough-diagram]//svg[not(@aria-hidden="true" or ancestor::*[@aria-hidden="true"])]'))->toHaveCount(0);
    $response->assertDontSee('future-horizon')->assertDontSee('Direction contract');
});

test('repeated walkthrough figures keep complete semantic explanations without duplicate identifiers', function () {
    $html = Blade::render('<x-marketing.walkthrough-diagram /><x-marketing.walkthrough-diagram />');
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//figure'))->toHaveCount(2);
    expect($xpath->query('//figure/figcaption'))->toHaveCount(2);
    expect($xpath->query('//figure/ol/li/h3'))->toHaveCount(8);
    expect($xpath->query('//*[@id or @aria-labelledby or @aria-describedby]'))->toHaveCount(0);
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
        ->assertSee('I start with the people doing the work')
        ->assertSee('you’ll bring it up, not me')
        ->assertSee('within three business days')
        ->assertSee('Where I’d start')
        ->assertSee('the expensive version of standing still')
        ->assertSee('if the reminders sent themselves')
        ->assertSee('The conversation and the written report are free.')
        ->assertSee('I’m not the right fit')
        ->assertSee('separate implementation engagement')
        ->assertSee('paid discovery week')
        ->assertSee('the shape of the problem and where I’d look first')
        ->assertSee('room for work that’s worth their time')
        ->assertDontSee('You’re booked');
});

test('conversion pages make the readers independent next steps visible without opening a disclosure', function (string $path, array $choices) {
    $response = $this->get($path);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $visible = '';

    foreach ($xpath->query('//main//text()[not(ancestor::details or ancestor::*[@hidden or @aria-hidden="true"])]') as $text) {
        $visible .= $text->textContent.' ';
    }

    expect($visible)->toContain(...$choices);
})->with([
    ['/', ['Use the recommendations independently.', 'Discuss separate implementation with me.', 'Or do nothing. No purchase obligation.']],
    ['/walkthrough', ['Use the recommendations yourself', 'separate implementation purchase', 'or leave it there', 'not a working implementation']],
]);

test('the homepage connects a recognizable problem to help without requiring a case study visit', function () {
    $this->get('/')->assertSeeTextInOrder([
        'Why does everything',
        'Book a free Walkthrough',
        'Keep the report',
        'The follow-up that depends on your memory.',
        'Book a free Walkthrough',
        'Craft &amp; Communicate',
        'Understand',
        'Build',
        'Care',
        'fifteen years',
        'Book a free Walkthrough',
    ], false);
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

test('mobile and footer navigation identify the current destination without relying on JavaScript', function (string $path, string $label) {
    $response = $this->get($path)->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    $currentLinks = $xpath->query('//details[@class="mobile-menu"]/nav/a[@aria-current="page"]');

    expect($currentLinks)->toHaveCount(1);
    expect(trim($currentLinks->item(0)->textContent))->toBe($label);

    $footerLinks = $xpath->query('//footer/nav/a[@aria-current="page"]');

    expect($footerLinks)->toHaveCount(1);
    expect(trim($footerLinks->item(0)->textContent))->toBe($path === '/walkthrough' ? 'The Walkthrough' : $label);
})->with([
    ['/work', 'Selected work'],
    ['/writing/', 'Writing'],
    ['/writing/your-ai-wrote-a-bug/', 'Writing'],
    ['/walkthrough', 'Book a free Walkthrough'],
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

test('the reporting illustration connects only the supplied project facts in static reading order', function (string $path) {
    $this->get($path)->assertSeeTextInOrder([
        'Finding the numbers by hand',
        'Performance numbers had to be gathered for reporting.',
        'A client-facing reporting platform',
        'Performance data',
        'Live-updating data',
        'Client management',
        'Part of the agency’s service',
        'An offering Craft &amp; Communicate can sell to its customers.',
        'not a product screenshot or measured results',
    ], false)
        ->assertDontSee('GHX')
        ->assertDontSee('WorkOS')
        ->assertDontSee('DataDash')
        ->assertDontSee('hours saved')
        ->assertDontSee('revenue increased')
        ->assertDontSee('fully automated');
})->with(['/', '/work']);

test('repeated reporting figures preserve their full explanation without identifier collisions', function () {
    $html = Blade::render('<x-marketing.reporting-diagram /><x-marketing.reporting-diagram />');
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//figure'))->toHaveCount(2);
    expect($xpath->query('//figure/figcaption'))->toHaveCount(2);
    expect($xpath->query('//figure//dl/div'))->toHaveCount(4);
    expect($xpath->query('//figure//svg[not(@aria-hidden="true")]'))->toHaveCount(0);
    expect($xpath->query('//figure//*[@hidden or @aria-hidden="true"]//*[self::p or self::dt or self::dd]'))->toHaveCount(0);
    expect($xpath->query('//*[@id or @aria-labelledby or @aria-describedby]'))->toHaveCount(0);
});

test('the deeper paid discovery week is mentioned once without a price', function () {
    $response = $this->get('/walkthrough');

    expect(substr_count($response->getContent(), 'paid discovery week'))->toBe(1);
    $response->assertDontSee('$')->assertDontSee('£')->assertDontSee('€');
});
