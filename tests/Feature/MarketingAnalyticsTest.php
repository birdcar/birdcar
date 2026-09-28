<?php

use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;

test('the marketing layout emits the PostHog browser config when PostHog is enabled', function () {
    config(['posthog.disabled' => false, 'posthog.api_key' => 'phc_test_token', 'posthog.host' => 'https://us.i.posthog.com']);

    $this->get('/')
        ->assertSee('data-posthog-token="phc_test_token"', false)
        ->assertSee('data-posthog-host="https://us.i.posthog.com"', false);
});

test('the marketing layout omits the PostHog browser config when PostHog is disabled', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('data-posthog-token');
});

test('the marketing layout omits the PostHog browser config when the token is blank', function () {
    config(['posthog.disabled' => false, 'posthog.api_key' => '', 'app.debug' => false]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('data-posthog-token');
});

test('booking controls preserve their exact placements and real route or anchor destinations', function (string $path, array $placements) {
    $response = $this->get($path);
    $xpath = marketingAnalyticsXpath($response);
    $controls = marketingAnalyticsElements($xpath, '//a[@data-booking-cta]');
    $actual = [];

    foreach ($controls as $control) {
        $actual[] = $control->getAttribute('data-booking-cta');
        expect($control->getAttribute('href'))->toBe($path === '/walkthrough' ? '#choose-a-time' : route('public.walkthrough'));
        expect($control->hasAttribute('data-cal-link'))->toBeFalse();
    }

    expect($actual)->toBe($placements);
})->with([
    ['/', ['header', 'mobile-menu', 'hero', 'homepage-strip', 'closing-invitation']],
    ['/work', ['header', 'mobile-menu', 'closing-invitation']],
    ['/walkthrough', ['header', 'mobile-menu', 'walkthrough-hero', 'walkthrough-close']],
]);

test('the walkthrough page marks its hero control and the plain Cal.com fallback link', function () {
    $this->get('/walkthrough')
        ->assertSee('data-booking-cta="walkthrough-hero"', false)
        ->assertSee('data-booking-fallback', false);
});

/**
 * @param  TestResponse<Response>  $response
 */
function marketingAnalyticsXpath(TestResponse $response): DOMXPath
{
    $content = $response->getContent();

    if ($content === false) {
        throw new RuntimeException('Response has no content.');
    }

    $document = new DOMDocument;
    @$document->loadHTML($content);

    return new DOMXPath($document);
}

/**
 * @return list<DOMElement>
 */
function marketingAnalyticsElements(DOMXPath $xpath, string $expression): array
{
    $nodes = $xpath->query($expression);

    if ($nodes === false) {
        throw new RuntimeException("XPath query failed: {$expression}");
    }

    $elements = [];

    foreach ($nodes as $node) {
        if (! $node instanceof DOMElement) {
            throw new RuntimeException("XPath query returned a non-element node: {$expression}");
        }

        $elements[] = $node;
    }

    return $elements;
}
