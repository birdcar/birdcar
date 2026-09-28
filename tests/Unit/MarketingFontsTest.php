<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

test('the application stylesheet imports the marketing fonts and exposes both families', function () {
    $stylesheet = (new Filesystem)->get(__DIR__.'/../../resources/css/app.css');

    expect($stylesheet)
        ->toContain("@import './fonts.css';")
        ->toMatch("/--font-alkaline:\\s*'Alkaline',\\s*cursive;/")
        ->toMatch("/--font-alkaline-caps:\\s*'Alkaline Caps',\\s*cursive;/");
});

test('marketing uses Mona Sans through the nonblocking font pipeline without changing operational typography', function () {
    $root = __DIR__.'/../..';
    $tokens = (new Filesystem)->get($root.'/resources/css/app.css');
    $marketing = (new Filesystem)->get($root.'/resources/css/marketing.css');
    $vite = (new Filesystem)->get($root.'/vite.config.js');
    $layout = (new Filesystem)->get($root.'/resources/views/components/marketing/layout.blade.php');

    expect($tokens)->toContain("--font-studio: 'Mona Sans',", "--font-sans: 'Inter',")
        ->and($tokens)->not->toContain('Barlow');
    expect($marketing)->toContain('font-family: var(--font-studio)')
        ->and($marketing)->not->toContain('font-family: var(--font-sans)');
    expect($vite)->toContain("bunny('Mona Sans'", "display: 'swap'")
        ->and($vite)->not->toContain('Barlow');
    expect($layout)->toContain('@fonts');

    $wordmark = Str::match('/\\.wordmark\\s*\\{([^}]+)\\}/', $marketing);
    expect($wordmark)->not->toBe('');
    expect($wordmark)->toContain('font-family: var(--font-alkaline)')
        ->and($wordmark)->not->toContain('letter-spacing');
});

test('marketing font weights resolve to local webfonts with nonblocking loading', function (string $family, string $filename, int $weight) {
    $directory = __DIR__.'/../../resources/css';
    $stylesheet = (new Filesystem)->get($directory.'/fonts.css');
    preg_match_all('/@font-face\\s*\\{([^}]+)\\}/', $stylesheet, $matches);
    $faces = array_values(array_filter($matches[1], fn (string $face): bool => preg_match('/font-family:\\s*'.preg_quote("'{$family}'", '/').'\\s*;/', $face)
        && preg_match('/font-weight:\\s*'.$weight.'\\s*;/', $face)
    ));

    expect($faces)->toHaveCount(1);
    expect($faces[0])
        ->toMatch('/font-style:\\s*normal;/')
        ->toMatch('/font-display:\\s*swap;/');

    preg_match_all("/url\\(['\"]([^'\"]+)['\"]\\)\\s*format\\(['\"]([^'\"]+)['\"]\\)/", $faces[0], $sources, PREG_SET_ORDER);

    expect(array_column($sources, 2))->toBe(['woff2', 'woff']);

    foreach ($sources as $source) {
        expect(basename($source[1]))->toBe($filename.'.'.$source[2]);
        expect($directory.'/'.$source[1])->toBeFile();
        expect(substr((new Filesystem)->get($directory.'/'.$source[1]), 0, 4))
            ->toBe($source[2] === 'woff2' ? 'wOF2' : 'wOFF');
    }
})->with([
    ['Alkaline', 'Alkaline-Regular', 400],
    ['Alkaline', 'Alkaline-Medium', 500],
    ['Alkaline', 'Alkaline-Demi', 600],
    ['Alkaline', 'Alkaline-Bold', 700],
    ['Alkaline', 'Alkaline-Heavy', 800],
    ['Alkaline Caps', 'AlkalineCaps-Regular', 400],
    ['Alkaline Caps', 'AlkalineCaps-Medium', 500],
    ['Alkaline Caps', 'AlkalineCaps-Demi', 600],
    ['Alkaline Caps', 'AlkalineCaps-Bold', 700],
    ['Alkaline Caps', 'AlkalineCaps-Heavy', 800],
]);
