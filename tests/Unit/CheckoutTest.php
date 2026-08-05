<?php

use Ahmadnajmdev\Cashier\Dodo\Checkout;
use Ahmadnajmdev\Cashier\Dodo\CheckoutSession;
use Illuminate\Support\Facades\Http;

it('accepts a single product id', function () {
    $payload = Checkout::guest()->withProducts('pdt_pro')->payload();

    expect($payload['product_cart'])->toBe([['product_id' => 'pdt_pro', 'quantity' => 1]]);
});

it('accepts a list of product ids', function () {
    $payload = Checkout::guest()->withProducts(['pdt_a', 'pdt_b'])->payload();

    expect($payload['product_cart'])->toBe([
        ['product_id' => 'pdt_a', 'quantity' => 1],
        ['product_id' => 'pdt_b', 'quantity' => 1],
    ]);
});

it('accepts a map of product id to quantity', function () {
    $payload = Checkout::guest()->withProducts(['pdt_a' => 3, 'pdt_b' => 1])->payload();

    expect($payload['product_cart'])->toBe([
        ['product_id' => 'pdt_a', 'quantity' => 3],
        ['product_id' => 'pdt_b', 'quantity' => 1],
    ]);
});

it('passes fully formed cart items straight through', function () {
    $payload = Checkout::guest()
        ->withProducts([['product_id' => 'pdt_a', 'quantity' => 2, 'amount' => 5000]])
        ->payload();

    expect($payload['product_cart'][0])->toBe(['product_id' => 'pdt_a', 'quantity' => 2, 'amount' => 5000]);
});

it('supports pay what you want amounts', function () {
    $payload = Checkout::guest()->withCustomAmount('pdt_tip', 1500)->payload();

    expect($payload['product_cart'][0])->toBe(['product_id' => 'pdt_tip', 'quantity' => 1, 'amount' => 1500]);
});

it('attaches addons to the most recent product', function () {
    $payload = Checkout::guest()
        ->withProducts('pdt_pro')
        ->withAddons(['addon_seats' => 5])
        ->payload();

    expect($payload['product_cart'][0]['addons'])->toBe([['addon_id' => 'addon_seats', 'quantity' => 5]]);
});

it('refuses to attach addons before a product', function () {
    expect(fn () => Checkout::guest()->withAddons(['addon_seats' => 5]))->toThrow(LogicException::class);
});

it('nests trial days under subscription data', function () {
    $payload = Checkout::guest()->withProducts('pdt_pro')->trialDays(14)->payload();

    expect($payload['subscription_data'])->toBe(['trial_period_days' => 14]);
});

it('keeps trial days when on demand billing is added', function () {
    $payload = Checkout::guest()->withProducts('pdt_pro')->trialDays(7)->onDemand()->payload();

    expect($payload['subscription_data'])->toBe([
        'trial_period_days' => 7,
        'on_demand' => ['mandate_only' => true],
    ]);
});

it('stacks discount codes', function () {
    $payload = Checkout::guest()
        ->withProducts('pdt_pro')
        ->withDiscounts('LAUNCH')
        ->withDiscounts(['FRIEND', 'SUMMER'])
        ->payload();

    expect($payload['discount_codes'])->toBe(['LAUNCH', 'FRIEND', 'SUMMER']);
});

it('merges metadata across calls', function () {
    $payload = Checkout::guest()
        ->withProducts('pdt_pro')
        ->withMetadata(['order_id' => '1'])
        ->withMetadata(['source' => 'pricing-page'])
        ->payload();

    expect($payload['metadata'])->toBe(['order_id' => '1', 'source' => 'pricing-page']);
});

it('uppercases the billing country and currency', function () {
    $payload = Checkout::guest()
        ->withProducts('pdt_pro')
        ->withBillingAddress('iq', ['city' => 'Erbil'])
        ->currency('usd')
        ->payload();

    expect($payload['billing_address'])->toBe(['country' => 'IQ', 'city' => 'Erbil'])
        ->and($payload['billing_currency'])->toBe('USD');
});

it('falls back to the configured return url', function () {
    config()->set('cashier-dodo.return_url', 'https://example.test/thanks');

    $payload = Checkout::guest()->withProducts('pdt_pro')->payload();

    expect($payload['return_url'])->toBe('https://example.test/thanks');
});

it('prefers an explicit return url over the configured one', function () {
    config()->set('cashier-dodo.return_url', 'https://example.test/thanks');

    $payload = Checkout::guest()->withProducts('pdt_pro')->returnUrl('https://example.test/welcome')->payload();

    expect($payload['return_url'])->toBe('https://example.test/welcome');
});

it('describes a guest customer', function () {
    $payload = Checkout::guest()
        ->withProducts('pdt_pro')
        ->withCustomer('ada@example.com', 'Ada Lovelace')
        ->payload();

    expect($payload['customer'])->toBe(['email' => 'ada@example.com', 'name' => 'Ada Lovelace']);
});

it('targets an existing dodo customer', function () {
    $payload = Checkout::forDodoCustomer('cus_9')->withProducts('pdt_pro')->payload();

    expect($payload['customer'])->toBe(['customer_id' => 'cus_9']);
});

it('creates a session and exposes its url', function () {
    Http::fake(['*/checkouts' => Http::response([
        'session_id' => 'cks_1',
        'checkout_url' => 'https://checkout.dodopayments.com/cks_1',
    ])]);

    $session = Checkout::guest()->withProducts('pdt_pro')->create();

    expect($session)->toBeInstanceOf(CheckoutSession::class)
        ->and($session->id())->toBe('cks_1')
        ->and($session->url())->toBe('https://checkout.dodopayments.com/cks_1')
        ->and($session->hasUrl())->toBeTrue();
});

it('explains itself when a confirmed session has no url', function () {
    Http::fake(['*/checkouts' => Http::response([
        'session_id' => 'cks_2',
        'checkout_url' => null,
        'client_secret' => 'secret_2',
    ])]);

    $session = Checkout::guest()->withProducts('pdt_pro')->usingPaymentMethod('pm_1')->confirm()->create();

    expect($session->hasUrl())->toBeFalse()
        ->and($session->clientSecret())->toBe('secret_2')
        ->and(fn () => $session->url())->toThrow(LogicException::class);
});

it('redirects to the hosted checkout', function () {
    Http::fake(['*/checkouts' => Http::response([
        'session_id' => 'cks_3',
        'checkout_url' => 'https://checkout.dodopayments.com/cks_3',
    ])]);

    $response = Checkout::guest()->withProducts('pdt_pro')->redirect();

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getTargetUrl())->toBe('https://checkout.dodopayments.com/cks_3');
});
