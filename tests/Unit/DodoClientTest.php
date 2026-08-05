<?php

use Ahmadnajmdev\Cashier\Dodo\DodoClient;
use Ahmadnajmdev\Cashier\Dodo\Exceptions\DodoApiException;
use Ahmadnajmdev\Cashier\Dodo\Facades\Dodo;
use Illuminate\Support\Facades\Http;

it('targets the test host in test mode', function () {
    expect(app(DodoClient::class)->baseUrl())->toBe('https://test.dodopayments.com');
});

it('targets the live host in live mode', function () {
    config()->set('cashier-dodo.environment', 'live_mode');
    app()->forgetInstance(DodoClient::class);

    expect(app(DodoClient::class)->baseUrl())->toBe('https://live.dodopayments.com');
});

it('honours a base url override', function () {
    config()->set('cashier-dodo.base_url', 'http://localhost:9999/');
    app()->forgetInstance(DodoClient::class);

    expect(app(DodoClient::class)->baseUrl())->toBe('http://localhost:9999');
});

it('authenticates every request with the configured api key', function () {
    Http::fake(['*' => Http::response(['items' => []])]);

    Dodo::products()->list();

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer sk_test_abc123')
            && $request->url() === 'https://test.dodopayments.com/products';
    });
});

it('sends json payloads on writes', function () {
    Http::fake(['*' => Http::response(['session_id' => 'cks_1', 'checkout_url' => 'https://checkout.test/1'])]);

    Dodo::checkoutSessions()->create(['product_cart' => [['product_id' => 'pdt_1', 'quantity' => 2]]]);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://test.dodopayments.com/checkouts'
            && $request->data() === ['product_cart' => [['product_id' => 'pdt_1', 'quantity' => 2]]];
    });
});

it('appends query parameters to reads', function () {
    Http::fake(['*' => Http::response(['items' => []])]);

    Dodo::payments()->list(['page_size' => 5, 'customer_id' => 'cus_1']);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'page_size=5')
        && str_contains($request->url(), 'customer_id=cus_1'));
});

it('raises a descriptive exception when the api rejects a request', function () {
    Http::fake(['*' => Http::response(['message' => 'product_cart is required'], 422)]);

    expect(fn () => Dodo::checkoutSessions()->create([]))
        ->toThrow(function (DodoApiException $exception) {
            expect($exception->status)->toBe(422)
                ->and($exception->isClientError())->toBeTrue()
                ->and($exception->getMessage())->toContain('product_cart is required');
        });
});

it('flags rate limited responses', function () {
    Http::fake(['*' => Http::response(['message' => 'slow down'], 429)]);

    expect(fn () => Dodo::products()->list())
        ->toThrow(fn (DodoApiException $exception) => expect($exception->isRateLimited())->toBeTrue());
});

it('retries server errors', function () {
    config()->set('cashier-dodo.retry', ['times' => 2, 'sleep' => 0]);
    app()->forgetInstance(DodoClient::class);

    Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

    expect(fn () => Dodo::products()->list())->toThrow(DodoApiException::class);

    Http::assertSentCount(3);
});

it('does not retry client errors', function () {
    config()->set('cashier-dodo.retry', ['times' => 2, 'sleep' => 0]);
    app()->forgetInstance(DodoClient::class);

    Http::fake(['*' => Http::response(['message' => 'nope'], 422)]);

    expect(fn () => Dodo::products()->list())->toThrow(DodoApiException::class);

    Http::assertSentCount(1);
});

it('builds invoice urls without calling the api', function () {
    Http::fake();

    expect(Dodo::payments()->invoiceUrl('pay_9'))
        ->toBe('https://test.dodopayments.com/invoices/payments/pay_9');

    Http::assertNothingSent();
});
