<?php

test('writing subscriptions keep a touch sized target without enlarging the label', function () {
    $stylesheet = file_get_contents(__DIR__.'/../../resources/css/marketing.css');

    preg_match('/\.archive-heading a\s*\{([^}]+)\}/', $stylesheet, $subscription);

    expect($subscription[1])
        ->toContain('min-height: 44px', 'align-items: center')
        ->not->toContain('font-size:');
});

test('essay arrows stay beside a shrinkable reading column and align with the titles', function () {
    $stylesheet = file_get_contents(__DIR__.'/../../resources/css/marketing.css');

    preg_match('/\.essay-row\s*\{([^}]+)\}/', $stylesheet, $row);
    preg_match('/\.essay-row > div\s*\{([^}]+)\}/', $stylesheet, $content);
    preg_match('/\.essay-row > \.arrow\s*\{([^}]+)\}/', $stylesheet, $arrow);

    expect($row[1])
        ->toContain('display: flex', 'align-items: flex-start')
        ->not->toContain('justify-content: space-between');
    expect($content[1])->toContain('flex: 0 1 66ch', 'min-width: 0');
    expect($arrow[1])->toContain('margin-top: .45rem');
});
