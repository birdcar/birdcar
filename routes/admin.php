<?php

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Http\Controllers\Admin\PreviewArticleController;
use App\Settings\SettingsSections;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::livewire('/', 'admin.index')->name('index');

Route::prefix('settings')
    ->name('settings.')
    ->group(function (): void {
        $sections = app(SettingsSections::class);

        Route::livewire('/', 'admin.settings.index')->name('index');

        foreach ($sections->all() as $section) {
            $route = Route::livewire('/'.$sections->path($section), $section->component())
                ->name(str($sections->routeName($section))->after('admin.settings.')->toString());

            $permission = $section->permission();
            if ($permission !== null) {
                $route->middleware(PermissionMiddleware::using($permission));
            }
        }
    });

Route::permanentRedirect('/mail', '/settings/mail');
Route::permanentRedirect('/publishing/settings', '/settings/publishing');

Route::prefix('publishing')
    ->name('publishing.')
    ->middleware(PermissionMiddleware::using(PublishingPermission::View))
    ->group(function (): void {
        Route::livewire('/', 'admin.publishing.dashboard')->name('dashboard');
        Route::livewire('/published', 'admin.publishing.published')->name('published');
        Route::livewire('/articles/{article}', 'admin.publishing.article-workspace')->name('articles.show');
        Route::get('/articles/{article}/preview', PreviewArticleController::class)->name('articles.preview');
    });
