<?php

use Ahmadnajmdev\Cashier\Dodo\Exceptions\SubscriptionUpdateFailure;
use Ahmadnajmdev\Cashier\Dodo\Subscription;
use Ahmadnajmdev\Cashier\Dodo\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $this->subscription = new Subscription;
    $this->subscription->forceFill(Subscription::mapFromDodo(dodoSubscription()) + ['type' => 'default']);
    $this->subscription->billable()->associate($this->user);
    $this->subscription->save();
});

it('swaps plans with prorated billing by default', function () {
    Http::fake([
        '*/change-plan' => Http::response([]),
        '*/subscriptions/sub_123' => Http::response(dodoSubscription(['product_id' => 'pdt_enterprise'])),
    ]);

    $this->subscription->swap('pdt_enterprise');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/subscriptions/sub_123/change-plan')
        && $request['product_id'] === 'pdt_enterprise'
        && $request['proration_billing_mode'] === 'prorated_immediately'
        && $request['effective_at'] === 'immediately');

    expect($this->subscription->fresh()->product_id)->toBe('pdt_enterprise');
});

it('can charge the full price of the new plan instead', function () {
    Http::fake([
        '*/change-plan' => Http::response([]),
        '*/subscriptions/sub_123' => Http::response(dodoSubscription()),
    ]);

    $this->subscription->chargeFullPrice()->swap('pdt_enterprise');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/change-plan')
        && $request['proration_billing_mode'] === 'full_immediately');
});

it('can defer the plan change to the next billing date', function () {
    Http::fake([
        '*/change-plan' => Http::response([]),
        '*/subscriptions/sub_123' => Http::response(dodoSubscription()),
    ]);

    $this->subscription->noProrate()->scheduleForNextBillingDate()->swap('pdt_enterprise');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/change-plan')
        && $request['proration_billing_mode'] === 'do_not_bill'
        && $request['effective_at'] === 'next_billing_date');
});

it('changes quantity through the plan change endpoint', function () {
    Http::fake([
        '*/change-plan' => Http::response([]),
        '*/subscriptions/sub_123' => Http::response(dodoSubscription(['quantity' => 4])),
    ]);

    $this->subscription->incrementQuantity(3);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/change-plan')
        && $request['product_id'] === 'pdt_pro'
        && $request['quantity'] === 4);

    expect($this->subscription->fresh()->quantity)->toBe(4);
});

it('previews a plan change without committing to it', function () {
    Http::fake(['*/change-plan/preview' => Http::response(['immediate_amount' => 1200])]);

    expect($this->subscription->previewSwap('pdt_enterprise'))->toBe(['immediate_amount' => 1200]);
});

it('cancels at the end of the billing period', function () {
    Http::fake(['*/subscriptions/sub_123' => Http::response(dodoSubscription([
        'cancel_at_next_billing_date' => true,
        'next_billing_date' => now()->addDays(20)->toIso8601String(),
    ]))]);

    $this->subscription->cancel();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request['cancel_at_next_billing_date'] === true);

    expect($this->subscription->cancelled())->toBeTrue()
        ->and($this->subscription->onGracePeriod())->toBeTrue()
        ->and($this->subscription->valid())->toBeTrue();
});

it('cancels immediately when asked to', function () {
    Http::fake(['*/subscriptions/sub_123' => Http::response(dodoSubscription([
        'status' => 'cancelled',
        'cancelled_at' => now()->toIso8601String(),
    ]))]);

    $this->subscription->cancelNow();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['status'] === 'cancelled');

    expect($this->subscription->ended())->toBeTrue()
        ->and($this->subscription->valid())->toBeFalse();
});

it('undoes a pending cancellation', function () {
    $this->subscription->forceFill([
        'cancel_at_next_billing_date' => true,
        'ends_at' => now()->addDays(5),
    ])->save();

    Http::fake(['*/subscriptions/sub_123' => Http::response(dodoSubscription())]);

    $this->subscription->resume();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request['cancel_at_next_billing_date'] === false);

    expect($this->subscription->fresh()->cancelled())->toBeFalse()
        ->and($this->subscription->fresh()->ends_at)->toBeNull();
});

it('refuses to modify a subscription that has already ended', function () {
    $this->subscription->forceFill(['status' => 'expired'])->save();

    Http::fake();

    expect(fn () => $this->subscription->swap('pdt_enterprise'))
        ->toThrow(SubscriptionUpdateFailure::class);

    Http::assertNothingSent();
});

it('refuses to change the plan of an on demand subscription', function () {
    $this->subscription->forceFill(['on_demand' => true])->save();

    Http::fake();

    expect(fn () => $this->subscription->swap('pdt_enterprise'))
        ->toThrow(SubscriptionUpdateFailure::class);
});

it('charges an on demand subscription', function () {
    Http::fake(['*/charge' => Http::response(['payment_id' => 'pay_9'])]);

    $result = $this->subscription->charge(4500, ['product_description' => 'Extra credits']);

    expect($result)->toBe(['payment_id' => 'pay_9']);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/subscriptions/sub_123/charge')
        && $request['product_price'] === 4500
        && $request['product_description'] === 'Extra credits');
});

it('builds a link for updating the payment method', function () {
    Http::fake(['*/update-payment-method' => Http::response([
        'payment_id' => 'pay_10',
        'payment_link' => 'https://checkout.dodopayments.com/update/1',
    ])]);

    expect($this->subscription->updatePaymentMethodUrl('https://example.test/billing'))
        ->toBe('https://checkout.dodopayments.com/update/1');

    Http::assertSent(fn ($request) => $request['type'] === 'new'
        && $request['return_url'] === 'https://example.test/billing');
});

it('cancels a scheduled plan change', function () {
    Http::fake([
        '*/change-plan/scheduled' => Http::response([]),
        '*/subscriptions/sub_123' => Http::response(dodoSubscription()),
    ]);

    $this->subscription->cancelScheduledPlanChange();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/subscriptions/sub_123/change-plan/scheduled'));
});

it('finds subscriptions through query scopes', function () {
    expect(Subscription::query()->active()->count())->toBe(1)
        ->and(Subscription::query()->ofType('default')->count())->toBe(1)
        ->and(Subscription::query()->onTrial()->count())->toBe(0)
        ->and(Subscription::query()->ended()->count())->toBe(0);
});
