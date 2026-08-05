<?php

use Ahmadnajmdev\Cashier\Dodo\Customer;
use Ahmadnajmdev\Cashier\Dodo\Events\PaymentSucceeded;
use Ahmadnajmdev\Cashier\Dodo\Events\RefundSucceeded;
use Ahmadnajmdev\Cashier\Dodo\Events\SubscriptionCancelled;
use Ahmadnajmdev\Cashier\Dodo\Events\SubscriptionCreated;
use Ahmadnajmdev\Cashier\Dodo\Events\SubscriptionRenewed;
use Ahmadnajmdev\Cashier\Dodo\Events\WebhookHandled;
use Ahmadnajmdev\Cashier\Dodo\Events\WebhookReceived;
use Ahmadnajmdev\Cashier\Dodo\Subscription;
use Ahmadnajmdev\Cashier\Dodo\Tests\Fixtures\User;
use Ahmadnajmdev\Cashier\Dodo\Transaction;
use Illuminate\Support\Facades\Event;

function postWebhook(array $envelope, ?array $headers = null)
{
    $payload = json_encode($envelope);

    return test()->call(
        'POST',
        '/dodo/webhook',
        [],
        [],
        [],
        collect($headers ?? signedWebhookHeaders($payload))
            ->mapWithKeys(fn ($value, $key) => ['HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value])
            ->merge(['CONTENT_TYPE' => 'application/json'])
            ->all(),
        $payload
    );
}

function billableUser(): User
{
    $user = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    Customer::create([
        'billable_type' => $user->getMorphClass(),
        'billable_id' => $user->getKey(),
        'dodo_id' => 'cus_123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    return $user;
}

it('rejects a webhook with no signature headers', function () {
    postWebhook(webhookEnvelope('payment.succeeded', dodoPayment()), [])->assertForbidden();
});

it('rejects a webhook signed with the wrong secret', function () {
    $envelope = webhookEnvelope('payment.succeeded', dodoPayment());
    $headers = signedWebhookHeaders(json_encode($envelope), 'whsec_'.base64_encode('not-the-secret'));

    postWebhook($envelope, $headers)->assertForbidden();
});

it('rejects a webhook whose timestamp is too old', function () {
    $envelope = webhookEnvelope('payment.succeeded', dodoPayment());
    $headers = signedWebhookHeaders(json_encode($envelope), null, time() - 3600);

    postWebhook($envelope, $headers)->assertForbidden();
});

it('accepts a correctly signed webhook', function () {
    Event::fake([WebhookReceived::class, WebhookHandled::class]);

    billableUser();

    postWebhook(webhookEnvelope('payment.succeeded', dodoPayment()))->assertOk();

    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
});

it('ignores event types it does not handle without erroring', function () {
    Event::fake([WebhookReceived::class, WebhookHandled::class]);

    postWebhook(webhookEnvelope('payout.success', ['payload_type' => 'Payout', 'payout_id' => 'po_1']))->assertOk();

    Event::assertDispatched(WebhookReceived::class);
    Event::assertNotDispatched(WebhookHandled::class);
});

it('creates a subscription when one becomes active', function () {
    Event::fake([SubscriptionCreated::class]);

    $user = billableUser();

    postWebhook(webhookEnvelope('subscription.active', dodoSubscription()))->assertOk();

    $subscription = Subscription::first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->dodo_id)->toBe('sub_123')
        ->and($subscription->type)->toBe('default')
        ->and($subscription->status)->toBe('active')
        ->and($subscription->product_id)->toBe('pdt_pro')
        ->and($subscription->recurring_amount)->toBe(2500)
        ->and($subscription->billable_id)->toBe($user->getKey())
        ->and($user->fresh()->subscribed())->toBeTrue();

    Event::assertDispatched(SubscriptionCreated::class);
});

it('honours the subscription type set at checkout', function () {
    billableUser();

    postWebhook(webhookEnvelope('subscription.active', dodoSubscription([
        'metadata' => ['subscription_type' => 'newsletter'],
    ])))->assertOk();

    expect(Subscription::first()->type)->toBe('newsletter');
});

it('links the subscription through checkout metadata when the customer is unknown', function () {
    $user = User::create(['name' => 'Grace', 'email' => 'grace@example.com']);

    postWebhook(webhookEnvelope('subscription.active', dodoSubscription([
        'customer' => ['customer_id' => 'cus_new', 'name' => 'Grace', 'email' => 'grace@example.com'],
        'metadata' => [
            'billable_type' => $user->getMorphClass(),
            'billable_id' => (string) $user->getKey(),
        ],
    ])))->assertOk();

    expect(Subscription::first()->billable_id)->toBe($user->getKey())
        ->and(Customer::first()->dodo_id)->toBe('cus_new');
});

it('does nothing when the subscription belongs to nobody it knows', function () {
    postWebhook(webhookEnvelope('subscription.active', dodoSubscription()))->assertOk();

    expect(Subscription::count())->toBe(0);
});

it('updates an existing subscription on renewal', function () {
    billableUser();

    postWebhook(webhookEnvelope('subscription.active', dodoSubscription()))->assertOk();

    Event::fake([SubscriptionRenewed::class]);

    postWebhook(webhookEnvelope('subscription.renewed', dodoSubscription([
        'next_billing_date' => '2026-03-01T00:00:00Z',
    ])))->assertOk();

    expect(Subscription::count())->toBe(1)
        ->and(Subscription::first()->next_billing_at->toDateString())->toBe('2026-03-01');

    Event::assertDispatched(SubscriptionRenewed::class);
});

it('puts a subscription into its grace period when it is cancelled', function () {
    $user = billableUser();

    postWebhook(webhookEnvelope('subscription.active', dodoSubscription()))->assertOk();

    Event::fake([SubscriptionCancelled::class]);

    postWebhook(webhookEnvelope('subscription.cancelled', dodoSubscription([
        'cancel_at_next_billing_date' => true,
        'next_billing_date' => now()->addDays(10)->toIso8601String(),
    ])))->assertOk();

    $subscription = Subscription::first();

    expect($subscription->cancelled())->toBeTrue()
        ->and($subscription->onGracePeriod())->toBeTrue()
        ->and($user->fresh()->subscribed())->toBeTrue();

    Event::assertDispatched(SubscriptionCancelled::class);
});

it('revokes access when a subscription expires', function () {
    $user = billableUser();

    postWebhook(webhookEnvelope('subscription.active', dodoSubscription()))->assertOk();
    postWebhook(webhookEnvelope('subscription.expired', dodoSubscription([
        'status' => 'expired',
        'expires_at' => now()->subDay()->toIso8601String(),
    ])))->assertOk();

    expect($user->fresh()->subscribed())->toBeFalse();
});

it('records a successful payment', function () {
    Event::fake([PaymentSucceeded::class]);

    $user = billableUser();

    postWebhook(webhookEnvelope('payment.succeeded', dodoPayment(['subscription_id' => 'sub_123'])))->assertOk();

    $transaction = Transaction::first();

    expect($transaction->dodo_id)->toBe('pay_123')
        ->and($transaction->total)->toBe(2500)
        ->and($transaction->currency)->toBe('USD')
        ->and($transaction->card_last_four)->toBe('4242')
        ->and($transaction->dodo_subscription_id)->toBe('sub_123')
        ->and($transaction->paid())->toBeTrue()
        ->and($user->fresh()->hasPaidTransactions())->toBeTrue();

    Event::assertDispatched(PaymentSucceeded::class);
});

it('updates rather than duplicates a payment it already knows', function () {
    billableUser();

    postWebhook(webhookEnvelope('payment.processing', dodoPayment(['status' => 'processing'])))->assertOk();
    postWebhook(webhookEnvelope('payment.succeeded', dodoPayment(['status' => 'succeeded'])))->assertOk();

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::first()->status)->toBe('succeeded');
});

it('marks a transaction as fully refunded', function () {
    Event::fake([RefundSucceeded::class]);

    billableUser();

    postWebhook(webhookEnvelope('payment.succeeded', dodoPayment()))->assertOk();
    postWebhook(webhookEnvelope('refund.succeeded', [
        'payload_type' => 'Refund',
        'refund_id' => 'ref_1',
        'payment_id' => 'pay_123',
        'amount' => 2500,
        'currency' => 'USD',
        'status' => 'succeeded',
    ]))->assertOk();

    $transaction = Transaction::first();

    expect($transaction->refunded())->toBeTrue()
        ->and($transaction->fullyRefunded())->toBeTrue()
        ->and($transaction->total)->toBe(2500);

    Event::assertDispatched(RefundSucceeded::class);
});

it('marks a transaction as partially refunded', function () {
    billableUser();

    postWebhook(webhookEnvelope('payment.succeeded', dodoPayment()))->assertOk();
    postWebhook(webhookEnvelope('refund.succeeded', [
        'payload_type' => 'Refund',
        'refund_id' => 'ref_2',
        'payment_id' => 'pay_123',
        'amount' => 500,
        'currency' => 'USD',
        'status' => 'succeeded',
    ]))->assertOk();

    expect(Transaction::first()->refund_status)->toBe('partial');
});

it('rejects a payload that is not a dodo event', function () {
    postWebhook(['not' => 'an event'])->assertStatus(400);
});
