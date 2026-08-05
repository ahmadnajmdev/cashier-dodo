<?php

use Ahmadnajmdev\Cashier\Dodo\Cashier;
use Ahmadnajmdev\Cashier\Dodo\DodoClient;
use Ahmadnajmdev\Cashier\Dodo\Enums\WebhookEvent;
use Ahmadnajmdev\Cashier\Dodo\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

it('registers the webhook route under a predictable name', function () {
    $route = Route::getRoutes()->getByName('cashier-dodo.webhook');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('dodo/webhook')
        ->and($route->methods())->toBe(['POST'])
        ->and($route->gatherMiddleware())->toContain(VerifyWebhookSignature::class);
});

it('binds the api client as a singleton', function () {
    expect(app(DodoClient::class))->toBe(app(DodoClient::class))
        ->and(app('dodo'))->toBe(app(DodoClient::class));
});

it('publishes its config and migrations', function () {
    $groups = ServiceProvider::$publishGroups;

    expect($groups)->toHaveKey('cashier-dodo-config')
        ->and($groups)->toHaveKey('cashier-dodo-migrations');
});

it('registers the webhook endpoint with dodo', function () {
    Http::fake([
        '*/webhooks/wh_1/secret' => Http::response(['signing_key' => 'whsec_generated']),
        '*/webhooks' => Http::response([
            'id' => 'wh_1',
            'url' => 'https://example.test/dodo/webhook',
            'filter_types' => WebhookEvent::cashierEvents(),
        ]),
    ]);

    $this->artisan('cashier-dodo:webhook')
        ->expectsOutputToContain('DODO_WEBHOOK_SECRET=whsec_generated')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/webhooks')
        && $request['url'] === 'https://example.test/dodo/webhook'
        && $request['filter_types'] === WebhookEvent::cashierEvents());
});

it('can subscribe the endpoint to every event type', function () {
    Http::fake(['*' => Http::response(['id' => 'wh_2', 'url' => 'https://example.test/dodo/webhook'])]);

    $this->artisan('cashier-dodo:webhook', ['--all-events' => true])->assertSuccessful();

    Http::assertSent(fn ($request) => ! str_ends_with($request->url(), '/secret')
        && $request['filter_types'] === WebhookEvent::values());
});

it('knows which version it is', function () {
    Http::fake(['*' => Http::response([])]);

    app(DodoClient::class)->get('products');

    Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0], 'cashier-dodo/'.Cashier::VERSION));
});
