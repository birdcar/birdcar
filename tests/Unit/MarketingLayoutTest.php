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

test('homepage work and walkthrough sections share horizontal layout rules at every breakpoint', function () {
    $stylesheet = file_get_contents(__DIR__.'/../../resources/css/marketing.css');
    $sections = ['.home-work', '.assessment-strip'];
    $layoutRules = [];

    preg_match_all('/([^{}]+)\{([^{}]+)\}/', $stylesheet, $rules, PREG_SET_ORDER);

    foreach ($rules as $rule) {
        $selectors = array_map('trim', explode(',', $rule[1]));

        if (array_intersect($sections, $selectors) === []) {
            continue;
        }

        if (preg_match('/(?:^|;)\s*(?:display|grid-template-columns|(?:column-)?gap|padding(?:-inline(?:-start|-end)?|-left|-right)?)\s*:/', $rule[2])) {
            $layoutRules[] = $selectors;
        }
    }

    expect($layoutRules)->not->toBeEmpty();

    foreach ($layoutRules as $selectors) {
        expect($selectors)->toContain(...$sections);
    }
});
