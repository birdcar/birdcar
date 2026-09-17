<?php

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

test('every booking control on the homepage carries its placement marker', function () {
    $this->get('/')
        ->assertSee('data-booking-cta="header"', false)
        ->assertSee('data-booking-cta="mobile-menu"', false)
        ->assertSee('data-booking-cta="hero"', false)
        ->assertSee('data-booking-cta="homepage-strip"', false)
        ->assertSee('data-booking-cta="closing-invitation"', false);
});

test('the assessment page marks its hero control and the plain Cal.com fallback link', function () {
    $this->get('/assessment')
        ->assertSee('data-booking-cta="assessment-hero"', false)
        ->assertSee('data-booking-fallback', false);
});
