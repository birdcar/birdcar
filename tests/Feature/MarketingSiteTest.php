<?php

use App\Actions\Publishing\ImportWritingArchive;
use App\Actions\Publishing\ReadPublishedWriting;
use App\Actions\ReadWriting;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-09-12');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('the homepage presents the approved offer and only the approved work story', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Better work, faster. Without another hire. — Birdcar</title>', false)
        ->assertSee('I find where work keeps landing on your desk')
        ->assertSeeTextInOrder(['I’m Nick Cannariato', 'Most people call me Birdcar', 'canary auto', 'more than twenty years'])
        ->assertSee('fifteen years')
        ->assertSee('GitHub, Heroku, and Zapier')
        ->assertSeeTextInOrder(['Where I’ve built systems', 'GitHub', 'Heroku', 'Zapier', 'Twilio', 'Salesforce'])
        ->assertSee('Implementation is a separate purchase')
        ->assertSee('working system, documentation, and training')
        ->assertSee('within three business days')
        ->assertSee('Craft &amp; Communicate', false)
        ->assertSee(route('public.walkthrough'), false)
        ->assertSee('Book a free Walkthrough')
        ->assertDontSeeText('Apple')
        ->assertDontSee('GHX')
        ->assertDontSee('DataDash')
        ->assertDontSee('WorkOS')
        ->assertDontSee('more than fifteen years')
        ->assertDontSee('Your AI wrote a bug');
});

test('the homepage explains the change, the offer, and the work in visible reading order', function () {
    $response = $this->get('/')->assertOk()->assertSeeTextInOrder([
        'Better work,',
        'Book a free Walkthrough',
        'Where I’ve built systems',
        'Here’s what changes when the work stops routing through you.',
        'Today, every question finds its way to your desk.',
        'In a Walkthrough, I follow the work through the business.',
        'Then I build what fixes it.',
        'What I build',
        'It starts with a free Walkthrough.',
        'The Walkthrough',
        'you’ll bring it up, not me',
        'The report',
        'within three business days',
        'where I’d start',
        'The build',
        'Implementation is a separate purchase',
        'Care',
        'Craft &amp; Communicate',
    ], false);

    $response->assertDontSee('future-horizon')->assertDontSee('Direction contract');
});

test('the homepage miniature keeps every state readable without scripts', function () {
    $response = $this->get('/')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $story = '//figure[contains(@class, "miniature-story")]';

    expect($xpath->query($story.'//input[@type="radio"][@name="miniature-state"]'))->toHaveCount(3);
    expect($xpath->query($story.'//input[@checked][@value="today"]'))->toHaveCount(1);
    expect($xpath->query($story.'//li/*[@data-when="today"]'))->toHaveCount(5);
    expect($xpath->query($story.'//li/a[@data-when="walkthrough"][contains(@href, "/tools/where-work-gets-stuck#")]'))->toHaveCount(5);
    expect($xpath->query($story.'//li/*[@data-when="fixed"]'))->toHaveCount(5);
    expect($xpath->query('//figure[@data-miniature]//canvas[not(@aria-hidden="true")]'))->toHaveCount(0);
    expect($xpath->query('//figure[@data-miniature]//img[contains(@class, "miniature-plate")][@alt!=""]'))->toHaveCount(2);
    $response->assertSeeTextInOrder(['Waiting on your yes', 'Waiting on a reply', 'It runs on remembering', 'Assembled by hand', 'The same information, typed twice']);
});

test('repeated walkthrough figures keep complete semantic explanations without duplicate identifiers', function () {
    $html = Blade::render('<x-marketing.walkthrough-diagram caption="First." /><x-marketing.walkthrough-diagram caption="Second." />');
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//figure'))->toHaveCount(2);
    expect($xpath->query('//figure/figcaption'))->toHaveCount(2);
    expect($xpath->query('//figure/ol/li/h3'))->toHaveCount(6);
    expect(Blade::render('<x-marketing.walkthrough-diagram />'))->not->toContain('<figcaption');
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
    ['/', ['Yours to keep, whatever you decide.', 'Implementation is a separate purchase.']],
    ['/walkthrough', ['Use the recommendations yourself', 'separate implementation purchase', 'or leave it there', 'not a working implementation']],
]);

test('the homepage connects a recognizable problem to help without requiring a case study visit', function () {
    $this->get('/')->assertSeeTextInOrder([
        'Better work,',
        'Book a free Walkthrough',
        'The Friday report someone builds by hand.',
        'Book a free Walkthrough',
        'Craft &amp; Communicate',
        'fifteen years',
        'Book a free Walkthrough',
    ], false);
});

test('booking buttons away from the walkthrough page lead to its fit check instead of opening a calendar', function (string $path) {
    if ($path === '/writing/') {
        marketingSiteImportArchive($this);
    }

    $this->get($path)->assertOk()
        ->assertSee('href="'.route('public.walkthrough').'"', false)
        ->assertDontSee('data-cal-link')
        ->assertDontSee('data-cal-namespace')
        ->assertDontSee('data-cal-config')
        ->assertDontSee('data-cal-inline');
})->with(['/', '/work', '/writing/', '/tools/where-work-gets-stuck']);

test('no page still calls the offer a free assessment', function (string $path) {
    if ($path === '/writing/') {
        marketingSiteImportArchive($this);
    }

    $response = $this->get($path)->assertOk();

    expect(mb_strtolower($response->getContent()))->not->toContain('free assessment')->not->toContain('free-assessment');
})->with(['/', '/walkthrough', '/work', '/writing/']);

test('the archive lists all essays in chronological order under the business introduction', function () {
    marketingSiteImportArchive($this);

    $this->get('/writing/')->assertOk()
        ->assertSee('Better work, fewer workarounds.')
        ->assertSee('A report rebuilt by hand. A follow-up someone has to remember.')
        ->assertSee('what software can and can’t help with')
        ->assertSee('how to make the work better for the people doing it.')
        ->assertSeeInOrder(['Just build it twice', 'Your AI wrote a bug', 'The tools that build the tools', 'Six months of talking to a machine', 'The other side of empathy', 'Your metrics are bullshit', 'Yetto values: Joy Matters', 'Data is a curse', 'You&#039;re already doing all hands support', 'Stop giving me take homes'], false);
});

test('each original essay remains available at its published URL and date', function (string $slug, string $title, string $date) {
    marketingSiteImportArchive($this);

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
    marketingSiteImportArchive($this);

    $this->get('/writing/just-build-it-twice/')->assertOk()
        ->assertSee('The agent has lowered the cost of building the same thing twice.')
        ->assertSee('href="https://www.birdcar.dev/writing/your-ai-wrote-a-bug/"', false);
});

test('original article notes keep their titles and formatted content', function () {
    marketingSiteImportArchive($this);

    $this->get('/writing/stop-giving-me-take-homes/')->assertOk()
        ->assertSee('<aside', false)
        ->assertSee('<h3 class="article-note-title">About this post</h3>', false)
        ->assertSee('href="/authors/balevine"', false)
        ->assertSee('href="/authors/birdcar"', false)
        ->assertSee('<em>Are you a support professional who has something to say?', false);

    $this->get('/writing/your-ai-wrote-a-bug/')->assertOk()
        ->assertSee('<aside', false)
        ->assertSee('The generation step got faster. The verification step expanded');
});

test('charts retain their source values and expose accessible data tables', function (string $slug, array $values) {
    marketingSiteImportArchive($this);

    $response = $this->get('/writing/'.$slug.'/')->assertOk()->assertSee('View chart data');
    foreach ($values as $value) {
        $response->assertSee('<td>'.$value.'</td>', false);
    }
})->with([
    ['your-ai-wrote-a-bug', [20, 15, 10, 7]],
    ['six-months-talking-to-a-machine', [410, 580, 720, 690, 780, 796]],
]);

test('source chart labels and values remain complete in reading order', function (string $slug, array $rows, array $columns) {
    marketingSiteImportArchive($this);

    $response = $this->get('/writing/'.$slug.'/')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $headers = array_map(fn ($cell): string => trim($cell->textContent), iterator_to_array($xpath->query('//details/table/thead/tr/th')));
    $actualRows = array_map(fn ($row): array => array_map(fn ($cell): string => trim($cell->textContent), iterator_to_array($xpath->query('./th|./td', $row))), iterator_to_array($xpath->query('//details/table/tbody/tr')));

    expect($headers)->toBe($columns);
    expect($actualRows)->toBe($rows);
})->with([
    'bar chart' => ['your-ai-wrote-a-bug', [['logic', '20'], ['integration', '15'], ['spec', '10'], ['edge cases', '7']], ['Category', 'Loops']],
    'line chart' => ['six-months-talking-to-a-machine', [['Oct', '410'], ['Nov', '580'], ['Dec', '720'], ['Jan', '690'], ['Feb', '780'], ['Mar', '796']], ['Month', 'Prompts']],
]);

test('the line chart retains its zero based axis and a keyboard accessible full size plot', function () {
    marketingSiteImportArchive($this);

    $response = $this->get('/writing/six-months-talking-to-a-machine/')->assertSee('Scroll for the full chart, or view the data below.');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//div[@class="chart-scroll" and @tabindex="0" and @role="region"]'))->toHaveCount(1);
    expect($xpath->query('//svg[@class="line-chart"]/circle'))->toHaveCount(6);
    expect(array_map(fn ($tick): string => $tick->textContent, iterator_to_array($xpath->query('//svg[@class="line-chart"]/text[@text-anchor="end"]'))))->toBe(['0', '400', '800']);
});

test('the complete archive preserves metadata and feed access in the reading layout', function () {
    marketingSiteImportArchive($this);

    $response = $this->get('/writing/')->assertSee(route('public.feed'), false);

    foreach (app(ReadPublishedWriting::class)->all() as $article) {
        $response->assertSee($article['title'])->assertSee($article['description'])
            ->assertSee('datetime="'.$article['date']->format('Y-m-d').'"', false)
            ->assertSee(route('public.article', ['slug' => $article['slug']]).'/', false);
    }

    $this->get('/writing/your-ai-wrote-a-bug/')->assertSee('Subscribe via RSS')->assertSee(route('public.feed'), false);
});

test('cms-mode imported archive preserves listing metadata links and original article content', function () {
    marketingSiteImportArchive($this);

    $this->get('/writing/')->assertOk()
        ->assertSeeInOrder(['Just build it twice', 'Your AI wrote a bug', 'Stop giving me take homes'], false)
        ->assertSee('href="'.route('public.article', ['slug' => 'just-build-it-twice']).'/"', false)
        ->assertSee('datetime="2026-05-26"', false);

    $this->get('/writing/just-build-it-twice/')->assertOk()
        ->assertSee('Just build it twice')
        ->assertSee('The agent has lowered the cost of building the same thing twice.')
        ->assertSee('href="https://www.birdcar.dev/writing/your-ai-wrote-a-bug/"', false)
        ->assertDontSee('@figure');
});

test('unknown essays return a not found response', function () {
    $this->get('/writing/not-a-published-essay/')->assertNotFound();
});

test('rendering markdown strips executable HTML and unsafe links', function () {
    $html = app(ReadWriting::class)->render('<script>alert("unsafe")</script>'."\n\n".'[bad](javascript:alert(1))');
    expect($html)->not->toContain('<script', 'href="javascript:');
});

test('the feed includes all original posts with XML-safe text', function () {
    marketingSiteImportArchive($this);

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
    if (str_starts_with($path, '/writing/')) {
        marketingSiteImportArchive($this);
    }

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
})->with(['/work']);

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

function marketingSiteImportArchive($test): void
{
    config(['marketing.url' => 'https://birdcar.dev']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $test->artisan('authorization:sync')->assertSuccessful();
    $operator = User::factory()->create();
    $operator->assignRole(PublishingRole::Author->value);

    app(ImportWritingArchive::class)->write('resources/writing', '72f7d8ad8521573cb224022c902447f9ca4c4351', $operator);
}
