<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

test('writing subscriptions keep a touch sized target without enlarging the label', function () {
    $stylesheet = (new Filesystem)->get(__DIR__.'/../../resources/css/writing.css');
    $subscription = Str::match('/\\.wr-rss\\s*\\{([^}]+)\\}/', $stylesheet);

    expect($subscription)->not->toBe('');

    expect($subscription)
        ->toContain('min-height: 44px', 'align-items: center')
        ->and($subscription)->not->toContain('font-size:');
});

test('essay titles keep a shrinkable reading column beside their dates', function () {
    $stylesheet = (new Filesystem)->get(__DIR__.'/../../resources/css/writing.css');

    $row = Str::match('/\\.wr-essay\\s*\\{([^}]+)\\}/', $stylesheet);
    $title = Str::match('/\\.wr-essay h3\\s*\\{([^}]+)\\}/', $stylesheet);

    expect($row)->not->toBe('')
        ->and($title)->not->toBe('');

    expect($row)->toContain('display: grid', 'grid-template-columns: minmax(0, 1fr) auto');
    expect($title)->toContain('min-width: 0');
});
