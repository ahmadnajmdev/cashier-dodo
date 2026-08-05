<?php

namespace Ahmadnajmdev\Cashier\Dodo;

use Ahmadnajmdev\Cashier\Dodo\Console\WebhookCommand;
use Ahmadnajmdev\Cashier\Dodo\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CashierServiceProvider extends ServiceProvider
{
    /**
     * Register the package's services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cashier-dodo.php', 'cashier-dodo');

        $this->app->singleton(DodoClient::class, function ($app) {
            $config = $app['config']['cashier-dodo'];

            return new DodoClient(
                apiKey: $config['api_key'] ?? null,
                environment: $config['environment'] ?? DodoClient::TEST_MODE,
                baseUrl: $config['base_url'] ?? null,
                timeout: (int) ($config['timeout'] ?? 30),
                retry: $config['retry'] ?? ['times' => 2, 'sleep' => 250],
            );
        });

        $this->app->alias(DodoClient::class, 'dodo');
    }

    /**
     * Bootstrap the package's services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerCommands();
    }

    /**
     * Register the webhook route.
     */
    protected function registerRoutes(): void
    {
        if (! Cashier::$registersRoutes || is_null($path = config('cashier-dodo.path'))) {
            return;
        }

        Route::group([
            'prefix' => $path,
            'as' => 'cashier-dodo.',
            'middleware' => array_merge(
                [VerifyWebhookSignature::class],
                (array) config('cashier-dodo.webhook.middleware', [])
            ),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');
        });
    }

    /**
     * Register the package's migrations.
     */
    protected function registerMigrations(): void
    {
        if (Cashier::$runsMigrations && $this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/cashier-dodo.php' => config_path('cashier-dodo.php'),
        ], 'cashier-dodo-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'cashier-dodo-migrations');
    }

    /**
     * Register the package's Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                WebhookCommand::class,
            ]);
        }
    }
}
