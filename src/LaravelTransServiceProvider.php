<?php

namespace Nitro\Trans;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Nitro\Trans\Services\TransService;
use Nitro\Trans\Support\TranslationKeyExtractor;

class LaravelTransServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/trans.php', 'trans');

        $this->app->bind(TranslationKeyExtractor::class, function ($app) {
            return new TranslationKeyExtractor(
                $app['config']->get('trans.php_functions', ['__', 'trans']),
                $app['config']->get('trans.js_functions', ['t']),
                $app['config']->get('trans.scan_dirs', []),
                $app['config']->get('trans.scan_js_dirs', []),
                $app['config']->get('trans.exclude_paths', [])
            );
        });

        $this->app->singleton(TransService::class, function () {
            $service = new TransService(
                config('trans.default_locale', 'en'),
                config('app.fallback_locale', 'en')
            );
            $service->load($service->getLocale());

            return $service;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/trans.php' => config_path('trans.php'),
            ], 'trans-config');

            $this->commands([
                Commands\TransScanCommand::class,
                Commands\TransCleanCommand::class,
                Commands\TransExportCsvCommand::class,
                Commands\TransImportCsvCommand::class,
                Commands\TransMakeTranslatableCommand::class,
            ]);
        }

        Blade::directive('trans', function (string $expression) {
            return "<?php echo __({$expression}); ?>";
        });

        $this->loadJsonTranslationsFrom(storage_path('lang'));
    }
}
