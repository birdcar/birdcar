<?php

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
