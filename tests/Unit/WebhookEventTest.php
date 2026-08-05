<?php

use Ahmadnajmdev\Cashier\Dodo\Enums\WebhookEvent;
use Ahmadnajmdev\Cashier\Dodo\Http\Controllers\WebhookController;
use Illuminate\Support\Str;

it('exposes every event type as a string', function () {
    expect(WebhookEvent::values())
        ->toContain('payment.succeeded')
        ->toContain('subscription.plan_changed')
        ->toContain('dispute.won')
        ->toContain('credit.balance_low')
        ->toContain('payout.success');
});

it('groups events by resource', function () {
    expect(WebhookEvent::SubscriptionRenewed->group())->toBe('subscription')
        ->and(WebhookEvent::AbandonedCheckoutDetected->group())->toBe('abandoned_checkout');
});

it('only asks dodo for the events cashier can handle', function () {
    $handled = WebhookEvent::cashierEvents();

    expect($handled)->toContain('subscription.active')
        ->and($handled)->toContain('payment.succeeded')
        ->and($handled)->not->toContain('payout.success')
        ->and($handled)->toBe(WebhookController::handledEvents());
});

it('has a controller method for every event it subscribes to', function () {
    foreach (WebhookEvent::cashierEvents() as $event) {
        $method = 'handle'.Str::studly(str_replace('.', '_', $event));

        expect(method_exists(WebhookController::class, $method))
            ->toBeTrue("WebhookController is missing {$method} for {$event}");
    }
});
