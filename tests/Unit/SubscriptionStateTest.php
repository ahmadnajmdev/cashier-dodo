<?php

use Ahmadnajmdev\Cashier\Dodo\Enums\SubscriptionStatus;
use Ahmadnajmdev\Cashier\Dodo\Subscription;

function subscription(array $attributes = []): Subscription
{
    return (new Subscription)->forceFill(array_merge([
        'dodo_id' => 'sub_1',
        'type' => 'default',
        'status' => 'active',
        'product_id' => 'pdt_pro',
        'quantity' => 1,
        'cancel_at_next_billing_date' => false,
        'on_demand' => false,
    ], $attributes));
}

it('treats an active subscription as valid', function () {
    $subscription = subscription();

    expect($subscription->active())->toBeTrue()
        ->and($subscription->valid())->toBeTrue()
        ->and($subscription->cancelled())->toBeFalse()
        ->and($subscription->ended())->toBeFalse()
        ->and($subscription->recurring())->toBeTrue();
});

it('treats a trialling subscription as on trial', function () {
    $subscription = subscription(['trial_ends_at' => now()->addDays(5)]);

    expect($subscription->onTrial())->toBeTrue()
        ->and($subscription->valid())->toBeTrue()
        ->and($subscription->recurring())->toBeFalse();
});

it('treats an expired trial as no longer on trial', function () {
    expect(subscription(['trial_ends_at' => now()->subDay()])->onTrial())->toBeFalse();
});

it('keeps access during the grace period after cancellation', function () {
    $subscription = subscription([
        'cancel_at_next_billing_date' => true,
        'ends_at' => now()->addDays(10),
    ]);

    expect($subscription->cancelled())->toBeTrue()
        ->and($subscription->onGracePeriod())->toBeTrue()
        ->and($subscription->ended())->toBeFalse()
        ->and($subscription->valid())->toBeTrue();
});

it('revokes access once the grace period is over', function () {
    $subscription = subscription([
        'status' => 'cancelled',
        'cancel_at_next_billing_date' => true,
        'ends_at' => now()->subDay(),
    ]);

    expect($subscription->onGracePeriod())->toBeFalse()
        ->and($subscription->ended())->toBeTrue()
        ->and($subscription->active())->toBeFalse()
        ->and($subscription->valid())->toBeFalse();
});

it('keeps access while a payment is being retried', function () {
    $subscription = subscription(['status' => 'on_hold']);

    expect($subscription->onHold())->toBeTrue()
        ->and($subscription->valid())->toBeTrue()
        ->and($subscription->active())->toBeFalse();
});

it('revokes access once the subscription expires', function () {
    $subscription = subscription(['status' => 'expired']);

    expect($subscription->expired())->toBeTrue()
        ->and($subscription->ended())->toBeTrue()
        ->and($subscription->valid())->toBeFalse();
});

it('matches products and addons', function () {
    $subscription = subscription([
        'addons' => [['addon_id' => 'addon_seats', 'quantity' => 3]],
    ]);

    expect($subscription->hasProduct('pdt_pro'))->toBeTrue()
        ->and($subscription->hasProduct('pdt_basic'))->toBeFalse()
        ->and($subscription->hasAddon('addon_seats'))->toBeTrue()
        ->and($subscription->hasAddon('addon_storage'))->toBeFalse();
});

it('exposes the status as an enum', function () {
    expect(subscription()->statusEnum())->toBe(SubscriptionStatus::Active)
        ->and(subscription(['status' => 'something_new'])->statusEnum())->toBeNull();
});

it('maps a dodo subscription payload onto model attributes', function () {
    $attributes = Subscription::mapFromDodo(dodoSubscription([
        'trial_period_days' => 14,
        'created_at' => '2026-03-01T00:00:00Z',
        'next_billing_date' => '2026-04-01T00:00:00Z',
    ]));

    expect($attributes['dodo_id'])->toBe('sub_123')
        ->and($attributes['dodo_customer_id'])->toBe('cus_123')
        ->and($attributes['product_id'])->toBe('pdt_pro')
        ->and($attributes['recurring_amount'])->toBe(2500)
        ->and($attributes['currency'])->toBe('USD')
        ->and($attributes['trial_ends_at']->toDateString())->toBe('2026-03-15')
        ->and($attributes['next_billing_at']->toDateString())->toBe('2026-04-01')
        ->and($attributes['ends_at'])->toBeNull();
});

it('derives the end date from the next billing date when cancelling', function () {
    $attributes = Subscription::mapFromDodo(dodoSubscription([
        'cancel_at_next_billing_date' => true,
        'next_billing_date' => '2026-04-01T00:00:00Z',
    ]));

    expect($attributes['ends_at']->toDateString())->toBe('2026-04-01');
});

it('clears the end date when a cancellation is undone', function () {
    $attributes = Subscription::mapFromDodo(dodoSubscription(['cancel_at_next_billing_date' => false]));

    expect($attributes)->toHaveKey('ends_at')
        ->and($attributes['ends_at'])->toBeNull();
});
