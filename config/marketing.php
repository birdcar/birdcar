<?php

return [
    'url' => env('BIRDCAR_MARKETING_URL', env('APP_URL', 'https://birdcar.dev')),
    'indexable' => env('BIRDCAR_MARKETING_INDEXABLE', env('APP_ENV') === 'production'),
    'booking_url' => 'https://cal.com/birdcar/walkthrough',
    'booking_calendar' => 'birdcar/walkthrough',
    'booking_namespace' => 'walkthrough',
    'mail' => [
        'mailer' => env('BIRDCAR_MARKETING_MAIL_MAILER', 'log'),
        'sender_domains' => ['birdcar.dev'],
    ],
];
