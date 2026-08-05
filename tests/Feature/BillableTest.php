<?php

use Ahmadnajmdev\Cashier\Dodo\Checkout;
use Ahmadnajmdev\Cashier\Dodo\Customer;
use Ahmadnajmdev\Cashier\Dodo\Exceptions\CustomerAlreadyCreated;
use Ahmadnajmdev\Cashier\Dodo\Exceptions\InvalidCustomer;
use Ahmadnajmdev\Cashier\Dodo\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
});

it('registers the model as a dodo customer', function () {
    Http::fake(['*/customers' => Http::response([
        'customer_id' => 'cus_abc',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ])]);

    $customer = $this->user->createAsCustomer();

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->dodo_id)->toBe('cus_abc')
        ->and($this->user->fresh()->dodoId())->toBe('cus_abc')
        ->and($this->user->fresh()->hasDodoId())->toBeTrue();

    Http::assertSent(fn ($request) => $request->data() === [
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
    ]);
});

it('refuses to register the same model twice', function () {
    Http::fake(['*/customers' => Http::response(['customer_id' => 'cus_abc'])]);

    $this->user->createAsCustomer();

    expect(fn () => $this->user->fresh()->createAsCustomer())->toThrow(CustomerAlreadyCreated::class);
});

it('only calls the api the first time a customer is needed', function () {
    Http::fake(['*/customers' => Http::response(['customer_id' => 'cus_abc'])]);

    $this->user->createOrGetCustomer();
    $this->user->fresh()->createOrGetCustomer();

    Http::assertSentCount(1);
});

it('complains when talking to the api about an unregistered model', function () {
    expect(fn () => $this->user->billingPortalUrl())->toThrow(InvalidCustomer::class);
});

it('builds a billing portal link', function () {
    Http::fake([
        '*/customers' => Http::response(['customer_id' => 'cus_abc']),
        '*/customer-portal/session*' => Http::response(['link' => 'https://portal.dodopayments.com/s/1']),
    ]);

    $this->user->createAsCustomer();

    expect($this->user->fresh()->billingPortalUrl('https://example.test/account'))
        ->toBe('https://portal.dodopayments.com/s/1');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'customers/cus_abc/customer-portal/session')
        && str_contains($request->url(), 'return_url=https%3A%2F%2Fexample.test%2Faccount'));
});

it('starts a checkout that knows who is buying', function () {
    Http::fake(['*/customers' => Http::response(['customer_id' => 'cus_abc'])]);

    $checkout = $this->user->checkout('pdt_pro');
    $payload = $checkout->payload();

    expect($checkout)->toBeInstanceOf(Checkout::class)
        ->and($payload['customer'])->toBe(['customer_id' => 'cus_abc'])
        ->and($payload['product_cart'])->toBe([['product_id' => 'pdt_pro', 'quantity' => 1]])
        ->and($payload['metadata'])->toBe([
            'billable_id' => (string) $this->user->getKey(),
            'billable_type' => $this->user->getMorphClass(),
        ]);
});

it('tags a subscription checkout with its type', function () {
    Http::fake(['*/customers' => Http::response(['customer_id' => 'cus_abc'])]);

    $payload = $this->user->subscribe('pdt_pro', 'newsletter')->payload();

    expect($payload['metadata']['subscription_type'])->toBe('newsletter');
});

it('supports a trial that lives only in your database', function () {
    expect($this->user->onGenericTrial())->toBeFalse();

    $this->user->trialUntil(now()->addDays(14));

    expect($this->user->fresh()->onGenericTrial())->toBeTrue()
        ->and($this->user->fresh()->hasTrial())->toBeTrue();
});

it('reports no subscription for a fresh model', function () {
    expect($this->user->subscribed())->toBeFalse()
        ->and($this->user->subscription())->toBeNull()
        ->and($this->user->onTrial())->toBeFalse()
        ->and($this->user->subscribedToProduct('pdt_pro'))->toBeFalse();
});
