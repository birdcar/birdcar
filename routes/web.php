<?php

use App\Authorization\Admin\Permission as AdminPermission;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

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

/*
 * Marketing routes
 *
 * This is the collection of routes for the logged out marketing site.
 */
Route::name('public.')->group(function () {
    Route::view('/', 'welcome')->name('index');

    // Placeholder for routes related to case studies
    Route::get('/case-studies')->name('case-studies');

    // Placeholder for routes related to contacting us
    Route::get('/contact')->name('contact');

    // Placeholder related to the blog and writing
    Route::get('/blog')->name('blog');
});
