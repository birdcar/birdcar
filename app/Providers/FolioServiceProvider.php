<?php

namespace App\Providers;

use App\Services\MarketingSite;
use Illuminate\Support\ServiceProvider;
use Laravel\Folio\Folio;

class FolioServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Folio::domain(app(MarketingSite::class)->host())
            ->path(resource_path('views/pages'));
    }
}
