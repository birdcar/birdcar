<?php

test('writing subscriptions keep a touch sized target without enlarging the label', function () {
    $stylesheet = file_get_contents(__DIR__.'/../../resources/css/writing.css');

    preg_match('/\\.wr-rss\\s*\\{([^}]+)\\}/', $stylesheet, $subscription);

    expect($subscription[1])
        ->toContain('min-height: 44px', 'align-items: center')
        ->not->toContain('font-size:');
});

test('essay titles keep a shrinkable reading column beside their dates', function () {
    $stylesheet = file_get_contents(__DIR__.'/../../resources/css/writing.css');

    preg_match('/\\.wr-essay\\s*\\{([^}]+)\\}/', $stylesheet, $row);
    preg_match('/\\.wr-essay h3\\s*\\{([^}]+)\\}/', $stylesheet, $title);

    expect($row[1])->toContain('display: grid', 'grid-template-columns: minmax(0, 1fr) auto');
    expect($title[1])->toContain('min-width: 0');
});
