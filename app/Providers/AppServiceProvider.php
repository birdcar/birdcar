<?php

namespace App\Providers;

use App\Actions\Publishing\RunEditorialActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if (app()->environment('local')) {
            $this->configureDevCommands();
        }
    }

    /**
     * Run only the local processes the site manager lacks: Herd (macOS) and lerd (Linux) already serve PHP and Reverb.
     */
    protected function configureDevCommands(): void
    {
        DevCommands::except('server', 'reverb');

        $defaultQueue = config()->string('queue.connections.'.config()->string('queue.default').'.queue', 'default');

        DevCommands::artisan('queue:listen --queue='.RunEditorialActivity::QUEUE.','.$defaultQueue.' --tries=1 --timeout=0', 'queue');
        DevCommands::artisan('schedule:work', 'schedule');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        $this->configureModelStrictness();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        Livewire::addPersistentMiddleware([
            PermissionMiddleware::class,
        ]);
    }

    /**
     * Fail on N+1 lazy loading and silently dropped attributes outside production; in production, report N+1s to Nightwatch and keep serving.
     */
    protected function configureModelStrictness(): void
    {
        Model::preventLazyLoading();
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::preventAccessingMissingAttributes(! app()->isProduction());

        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            $violation = new LazyLoadingViolationException($model, $relation);

            if (! app()->isProduction()) {
                throw $violation;
            }

            report($violation);
        });
    }
}
