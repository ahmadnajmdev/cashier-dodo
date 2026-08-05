<?php

namespace Ahmadnajmdev\Cashier\Dodo\Tests;

use Ahmadnajmdev\Cashier\Dodo\CashierServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * The webhook signing secret used throughout the test suite.
     */
    public const WEBHOOK_SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

    protected function getPackageProviders($app): array
    {
        return [CashierServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cashier-dodo.api_key', 'sk_test_abc123');
        $app['config']->set('cashier-dodo.environment', 'test_mode');
        $app['config']->set('cashier-dodo.webhook_secret', static::WEBHOOK_SECRET);
        $app['config']->set('cashier-dodo.currency', 'USD');
        $app['config']->set('cashier-dodo.retry.times', 0);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }
}
