<?php

use App\Actions\ReadWriting;
use App\Authorization\Admin\Permission as AdminPermission;
use App\Services\MarketingSite;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Sitemap\Sitemap;

/**
 * Admin Routes
 *
 * Allows the Birdcar.dev team to administer the application and get shit done
 *
 * Uses both the web middleware (to check if the user is authenticated) and the admin
 * middleware (to check if the user has permission to access the admin)
 */
Route::domain('admin.birdcar.dev')
    ->name('admin.')
    ->middleware([
        'auth',
        PermissionMiddleware::using(AdminPermission::View),
    ])
    ->group(function (): void {
        Route::get('/', fn (): Response => response()->noContent())
            ->name('index');

        // Add admin routes for global management here
        Route::prefix('settings')->name('settings.')->group(function (): void {});
    });

/**
 * Customer Routes
 *
 * Routes for onboarded customers to manage their projects with me
 */
Route::domain('{org:slug}.birdcar.dev')->name('customer.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        // CustomerAuth controller?
    });

    Route::prefix('projects')->name('projects.')->group(function () {
        // Projects controller
        // Project specific tasks
        // Project specific events
    });

    Route::prefix('tasks')->name('tasks.')->group(function () {
        // Tasks controller for global tasks
    });

    Route::prefix('files')->name('files.')->group(function () {
        // Files and Documents controllers and functionality
    });

    Route::prefix('calendar')->name('calendar.')->group(function () {});

    Route::prefix('notifications')->name('notifications.')->group(function () {});

    Route::prefix('settings')->name('settings.')->group(function () {});
});

Route::domain(app(MarketingSite::class)->host())->name('public.')->group(function (): void {
    Route::permanentRedirect('/case-studies', '/work');
    Route::permanentRedirect('/contact', '/walkthrough');
    Route::permanentRedirect('/assessment', '/walkthrough');
    Route::permanentRedirect('/blog', '/writing');
    Route::get('/__design/figures', function (): Response {
        abort_unless(app()->environment(['local', 'testing']), 404);

        return response()->view('figure-specimen')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    })->name('figure-specimen');
    Route::get('/sitemap.xml', function (ReadWriting $writing, MarketingSite $marketing): Sitemap {
        $sitemap = Sitemap::create();

        foreach (['public.index', 'public.work', 'public.walkthrough', 'public.writing'] as $route) {
            $sitemap->add($marketing->url(route($route, absolute: false)));
        }

        foreach ($writing->all() as $article) {
            $sitemap->add($marketing->url(route('public.article', ['slug' => $article['slug']], absolute: false)));
        }

        return $sitemap;
    })->name('sitemap');
    Route::get('/rss.xml', fn (ReadWriting $writing): Response => response()
        ->view('writing-feed', ['articles' => $writing->all()])
        ->header('Content-Type', 'application/rss+xml; charset=UTF-8'))
        ->name('feed');
});

Route::get('/robots.txt', function (MarketingSite $marketing): Response {
    $public = $marketing->indexable() && request()->getHost() === $marketing->host();
    $rules = $public
        ? "User-agent: *\nAllow: /\n\nSitemap: ".$marketing->url('/sitemap.xml')."\n"
        : "User-agent: *\nDisallow: /\n";

    return response($rules)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('robots');
