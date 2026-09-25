<?php

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Http\Controllers\Admin\PreviewArticleController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::livewire('/', 'admin.index')->name('index');

Route::prefix('publishing')
    ->name('publishing.')
    ->middleware(PermissionMiddleware::using(PublishingPermission::View))
    ->group(function (): void {
        Route::livewire('/', 'admin.publishing.dashboard')->name('dashboard');
        Route::livewire('/published', 'admin.publishing.published')->name('published');
        Route::livewire('/settings', 'admin.publishing.settings')
            ->name('settings')
            ->middleware(PermissionMiddleware::using(PublishingPermission::ConfigureAgents));
        Route::livewire('/articles/{article}', 'admin.publishing.article-workspace')->name('articles.show');
        Route::get('/articles/{article}/preview', PreviewArticleController::class)->name('articles.preview');
    });
