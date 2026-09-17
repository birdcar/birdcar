<?php

return [
    'url' => env('MARKETING_URL', env('APP_URL', 'https://birdcar.dev')),
    'indexable' => env('MARKETING_INDEXABLE', env('APP_ENV') === 'production'),
    'booking_url' => 'https://cal.com/birdcar/walkthrough',
    'booking_calendar' => 'birdcar/walkthrough',
    'booking_namespace' => 'walkthrough',
];
