<?php

use Laravel\Boost\Install\GuidelineComposer;

test('boost discovers the custom authorization guideline', function (): void {
    $composer = app(GuidelineComposer::class);

    expect($composer->used())->toContain('.ai/authorization');
});

test('boost composes the required authorization rule categories', function (): void {
    $guidance = app(GuidelineComposer::class)->compose();

    expect($guidance)->toContain('one User identity')
        ->and($guidance)->toContain('Spatie Teams disabled')
        ->and($guidance)->toContain('first-class `organization_memberships` model')
        ->and($guidance)->toContain('domain-local roles')
        ->and($guidance)->toContain('app/Authorization/{Domain}/{Permission,Role,Catalog}.php')
        ->and($guidance)->toContain('authorization:sync')
        ->and($guidance)->toContain('stale web-guard definitions')
        ->and($guidance)->toContain('`admin.view` only to admit the Admin surface')
        ->and($guidance)->toContain('matching membership')
        ->and($guidance)->toContain('future entitlement models')
        ->and($guidance)->toContain('shouldRegister()')
        ->and($guidance)->toContain('handle()')
        ->and($guidance)->toContain('machine and delegated agent identities')
        ->and($guidance)->toContain('cross-tenant denial tests');
});
