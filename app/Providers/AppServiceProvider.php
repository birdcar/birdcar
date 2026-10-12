<?php

namespace App\Providers;

use App\Actions\Publishing\RunEditorialActivity;
use App\Services\Publishing\Lint\Rules\AiVocabulary;
use App\Services\Publishing\Lint\Rules\BoldLimit;
use App\Services\Publishing\Lint\Rules\ChatbotArtifacts;
use App\Services\Publishing\Lint\Rules\CorporateVocabulary;
use App\Services\Publishing\Lint\Rules\CorporateWe;
use App\Services\Publishing\Lint\Rules\CurlyQuotes;
use App\Services\Publishing\Lint\Rules\EmDash;
use App\Services\Publishing\Lint\Rules\Emoji;
use App\Services\Publishing\Lint\Rules\ExclamationLimit;
use App\Services\Publishing\Lint\Rules\ExcludedNames;
use App\Services\Publishing\Lint\Rules\ExperienceClaim;
use App\Services\Publishing\Lint\Rules\FillerPhrases;
use App\Services\Publishing\Lint\Rules\HeadingOrder;
use App\Services\Publishing\Lint\Rules\InlineHeaderList;
use App\Services\Publishing\Lint\Rules\LabelHeadings;
use App\Services\Publishing\Lint\Rules\NegativeParallelism;
use App\Services\Publishing\Lint\Rules\PublicVocabulary;
use App\Services\Publishing\Lint\Rules\SentenceCaseHeadings;
use App\Services\Publishing\Lint\Rules\StockOpeners;
use App\Services\Publishing\Lint\Rules\UnresolvedMarkers;
use App\Services\Publishing\Lint\Rules\WalkthroughLink;
use App\Services\Publishing\Lint\VoiceLinter;
use App\Services\Publishing\VoiceCanon;
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
        $this->app->scoped(VoiceCanon::class);

        $this->app->tag([
            EmDash::class,
            CurlyQuotes::class,
            AiVocabulary::class,
            CorporateVocabulary::class,
            PublicVocabulary::class,
            FillerPhrases::class,
            NegativeParallelism::class,
            ChatbotArtifacts::class,
            StockOpeners::class,
            SentenceCaseHeadings::class,
            LabelHeadings::class,
            HeadingOrder::class,
            InlineHeaderList::class,
            BoldLimit::class,
            ExclamationLimit::class,
            Emoji::class,
            CorporateWe::class,
            ExcludedNames::class,
            ExperienceClaim::class,
            WalkthroughLink::class,
            UnresolvedMarkers::class,
        ], VoiceLinter::TAG);
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
