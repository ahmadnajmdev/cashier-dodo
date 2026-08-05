# Laravel Cashier for Dodo Payments

[![Tests](https://github.com/ahmadnajmdev/cashier-dodo/actions/workflows/tests.yml/badge.svg)](https://github.com/ahmadnajmdev/cashier-dodo/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/ahmadnajmdev/cashier-dodo.svg)](https://packagist.org/packages/ahmadnajmdev/cashier-dodo)
[![License](https://img.shields.io/packagist/l/ahmadnajmdev/cashier-dodo.svg)](LICENSE.md)

An expressive, fluent billing layer for [Dodo Payments](https://dodopayments.com), modelled on Laravel Cashier. It
handles subscriptions, one time payments, hosted checkout, the customer billing portal, and the webhook plumbing that
keeps your database in step with Dodo — so you write `$user->subscribed()` instead of a REST client.

Dodo Payments is a Merchant of Record, which means it handles sales tax, VAT and GST on your behalf. This package
does not try to replicate any of that; it gives you a Laravel-shaped way to talk to it.

- [Installation](#installation)
- [Configuration](#configuration)
- [Webhooks](#webhooks)
- [Customers](#customers)
- [Checkout](#checkout)
- [Subscriptions](#subscriptions)
- [Transactions and refunds](#transactions-and-refunds)
- [The billing portal](#the-billing-portal)
- [Events](#events)
- [Talking to the API directly](#talking-to-the-api-directly)
- [Testing](#testing)

## Installation

```bash
composer require ahmadnajmdev/cashier-dodo
```

Publish and run the migrations. They create three tables: `dodo_customers`, `dodo_subscriptions` and
`dodo_transactions`. Your own tables are left alone.

```bash
php artisan vendor:publish --tag=cashier-dodo-migrations
php artisan migrate
```

Publish the config file if you want to change the defaults:

```bash
php artisan vendor:publish --tag=cashier-dodo-config
```

Add your credentials to `.env`:

```dotenv
DODO_API_KEY=sk_test_...
DODO_ENVIRONMENT=test_mode
DODO_WEBHOOK_SECRET=whsec_...
```

Finally, add the `Billable` trait and the matching contract to your billable model:

```php
use Ahmadnajmdev\Cashier\Dodo\Billable;
use Ahmadnajmdev\Cashier\Dodo\Contracts\BillableContract;

class User extends Authenticatable implements BillableContract
{
    use Billable;
}
```

The trait supplies every method the contract requires. Declaring the interface is what lets the rest of the package,
and static analysis, know the model can be billed. Any model can be billable, not just `User` — a `Team` works
exactly the same way.

## Configuration

| Key | Env | Default | What it does |
| --- | --- | --- | --- |
| `api_key` | `DODO_API_KEY` | — | The key every request authenticates with. |
| `environment` | `DODO_ENVIRONMENT` | `test_mode` | `test_mode` hits `test.dodopayments.com`, `live_mode` hits `live.dodopayments.com`. |
| `base_url` | `DODO_BASE_URL` | `null` | Override the host entirely, for a local mock server. |
| `timeout` | `DODO_TIMEOUT` | `30` | Seconds to wait for the API. |
| `retry.times` | `DODO_RETRY_TIMES` | `2` | Extra attempts for connection failures, 5xx and 429 responses. 4xx is never retried. |
| `webhook_secret` | `DODO_WEBHOOK_SECRET` | — | The signing key used to verify incoming webhooks. |
| `path` | `CASHIER_DODO_PATH` | `dodo` | URI prefix for the webhook route. Set to `null` to register your own. |
| `currency` | `CASHIER_DODO_CURRENCY` | `USD` | Default currency when formatting money. |
| `currency_locale` | `CASHIER_DODO_CURRENCY_LOCALE` | `en` | Locale used when formatting money. |
| `return_url` | `CASHIER_DODO_RETURN_URL` | `null` | Where customers land after a checkout. |

## Webhooks

Dodo tells your application what happened through webhooks, and this is the part you should set up first. Without it
a subscription created at checkout never reaches your database.

The package registers `POST /dodo/webhook` for you. Point Dodo at it:

```bash
php artisan cashier-dodo:webhook
```

The command registers the endpoint through the API, subscribes it to the events Cashier handles, and prints the
signing key to put in `DODO_WEBHOOK_SECRET`. Pass `--url=` to override the URL, or `--all-events` to subscribe to
every event type Dodo publishes.

Every request is verified against the [Standard Webhooks](https://www.standardwebhooks.com) specification before it
reaches the controller: the signature must match, and the timestamp must be within five minutes. Anything else gets
a `403`. The signing key from your dashboard begins with `whsec_` — paste it in whole.

The controller keeps `dodo_subscriptions` and `dodo_transactions` in step with these events:

| Event | What Cashier does |
| --- | --- |
| `subscription.active` | Creates the local subscription and fires `SubscriptionCreated`. |
| `subscription.renewed` | Rolls the billing dates forward, fires `SubscriptionRenewed`. |
| `subscription.plan_changed`, `subscription.updated` | Syncs the plan, quantity and addons, fires `SubscriptionUpdated`. |
| `subscription.on_hold` | Marks the failed payment state, fires `SubscriptionOnHold`. Access is kept. |
| `subscription.paused` | Records the pause. |
| `subscription.cancelled`, `subscription.expired` | Sets the end date, fires `SubscriptionCancelled`. |
| `subscription.failed` | Syncs the status. |
| `payment.succeeded`, `payment.failed`, `payment.processing`, `payment.cancelled` | Records the transaction, fires `PaymentSucceeded` / `PaymentFailed`. |
| `refund.succeeded` | Marks the transaction partly or fully refunded, fires `RefundSucceeded`. |

Every other event still fires `WebhookReceived`, so you can listen for disputes, license keys, payouts, credits,
abandoned checkouts and dunning without the package getting in your way.

## Customers

```php
$user->createAsCustomer();          // register with Dodo
$user->createOrGetCustomer();       // idempotent
$user->hasDodoId();                 // bool
$user->dodoId();                    // 'cus_...'
$user->asDodoCustomer();            // the raw customer from the API
$user->paymentMethods();            // saved cards
```

Cashier reads the name, email and phone from your model's `name`, `email` and `phone` attributes. Override
`dodoName()`, `dodoEmail()` or `dodoPhone()` on the model to change that.

You can also grant a trial that never touches Dodo, which is useful when you want people to try the product before
they see a payment page at all:

```php
$user->trialUntil(now()->addDays(14));

$user->onGenericTrial();    // true
$user->hasTrial();          // true for a generic or a real subscription trial
```

## Checkout

Checkout is a fluent builder. It returns a `Responsable`, so a controller can return it directly and Laravel will
redirect the customer to Dodo's hosted payment page.

```php
use Ahmadnajmdev\Cashier\Dodo\Checkout;

// One time purchase.
return $user->checkout('pdt_ebook')
    ->returnUrl(route('orders.thanks'));

// Several products at once, with quantities.
return $user->checkout(['pdt_ebook' => 1, 'pdt_course' => 2]);

// A subscription with a trial.
return $user->subscribe('pdt_pro')
    ->trialDays(14)
    ->withMetadata(['plan' => 'pro']);

// A guest who is not signed in.
return Checkout::guest()
    ->withProducts('pdt_ebook')
    ->withCustomer('ada@example.com', 'Ada Lovelace')
    ->withBillingAddress('IQ', ['city' => 'Erbil'])
    ->returnUrl(route('orders.thanks'));
```

Everything the checkout session endpoint supports is available:

```php
$user->checkout('pdt_pro')
    ->withAddons(['addon_seats' => 5])
    ->withDiscounts(['LAUNCH', 'FRIEND'])
    ->allowedPaymentMethods(['credit', 'debit', 'apple_pay'])
    ->withCustomFields([['key' => 'company', 'label' => 'Company', 'type' => 'text']])
    ->withFeatureFlags(['allow_discount_code' => false])
    ->withSavedPaymentMethods()
    ->locale('ar')
    ->theme('dark')
    ->currency('USD')
    ->asShortLink()
    ->cancelUrl(route('pricing'));
```

If you need the URL rather than a redirect:

```php
$session = $user->checkout('pdt_pro')->create();

$session->id();             // 'cks_...'
$session->url();            // the hosted checkout URL
$session->clientSecret();   // for Dodo's client side SDKs

$url = $user->checkout('pdt_pro')->url();
```

You can also preview the totals — tax included — without creating anything:

```php
$totals = $user->checkout('pdt_pro')->preview();
```

For products with pay what you want enabled:

```php
return $user->chargeCustomAmount('pdt_tip', 2500);   // 25.00 in the smallest currency unit
```

Cashier attaches `billable_id` and `billable_type` metadata to every checkout it builds, which is how the incoming
webhook finds its way back to the right model even for a brand new customer.

## Subscriptions

### Checking state

```php
$user->subscribed();                        // has a usable 'default' subscription
$user->subscribed('newsletter');            // a second subscription type
$user->subscribedToProduct('pdt_pro');
$user->onTrial();
$user->onGracePeriod();
$user->subscriptionOnHold();                // a payment failed and Dodo is retrying

$subscription = $user->subscription();

$subscription->valid();          // active, trialling, on hold, or in its grace period
$subscription->active();
$subscription->onTrial();
$subscription->onHold();
$subscription->cancelled();
$subscription->onGracePeriod();
$subscription->ended();
$subscription->expired();
$subscription->recurring();
$subscription->hasProduct('pdt_pro');
$subscription->hasAddon('addon_seats');
```

A subscription cancelled at the end of its period stays `valid()` until the period actually ends, so people keep
what they paid for.

### Changing plans

Dodo offers four proration modes; Cashier exposes each as a method you chain before the swap.

```php
$subscription->swap('pdt_enterprise');                      // prorated_immediately (default)
$subscription->chargeFullPrice()->swap('pdt_enterprise');   // full_immediately
$subscription->chargeDifference()->swap('pdt_enterprise');  // difference_immediately
$subscription->noProrate()->swap('pdt_enterprise');         // do_not_bill

// Defer the change to the end of the current period.
$subscription->scheduleForNextBillingDate()->swap('pdt_enterprise');
$subscription->cancelScheduledPlanChange();

// See what a swap would cost before committing.
$subscription->previewSwap('pdt_enterprise');
```

Quantities go through the same endpoint:

```php
$subscription->updateQuantity(5);
$subscription->incrementQuantity();
$subscription->decrementQuantity(2);
```

### Cancelling and resuming

```php
$subscription->cancel();            // at the end of the billing period
$subscription->cancelNow();         // immediately
$subscription->resume();            // undo a pending cancellation
$subscription->extendTo(now()->addMonth());
```

### Payment methods

```php
// Send the customer somewhere to enter a new card.
return redirect($subscription->updatePaymentMethodUrl(route('billing')));

// Or point the subscription at a card they already saved.
$subscription->useSavedPaymentMethod('pm_123');
```

### On demand and metered billing

An on demand subscription authorizes a mandate up front and is charged whenever you say so.

```php
return $user->subscribe('pdt_usage')->onDemand();

// Later, charge 45.00.
$subscription->charge(4500, ['product_description' => 'March usage']);

$subscription->usageHistory();
```

Report metered usage through the usage events resource:

```php
use Ahmadnajmdev\Cashier\Dodo\Facades\Dodo;

Dodo::usageEvents()->ingest([
    ['event_id' => (string) Str::uuid(), 'customer_id' => $user->dodoId(), 'event_name' => 'api_call'],
]);
```

### Query scopes

```php
Subscription::query()->active()->count();
Subscription::query()->onTrial()->get();
Subscription::query()->onHold()->get();
Subscription::query()->onGracePeriod()->get();
Subscription::query()->ended()->get();
Subscription::query()->ofType('newsletter')->get();
```

## Transactions and refunds

```php
$user->transactions;                // every payment, newest first
$user->hasPaidTransactions();
$user->lastPayment();

$transaction->paid();
$transaction->failed();
$transaction->refunded();
$transaction->fullyRefunded();
$transaction->total();              // '$25.00'
$transaction->tax();
$transaction->invoiceUrl();
$transaction->subscription;

$transaction->refund('Customer changed their mind');
```

## The billing portal

Dodo hosts a self service portal where customers can see invoices, update their card and manage their subscription.

```php
return $user->redirectToBillingPortal(route('dashboard'));

$url = $user->billingPortalUrl(route('dashboard'));
```

## Events

```php
use Ahmadnajmdev\Cashier\Dodo\Events\SubscriptionCreated;

class GrantAccess
{
    public function handle(SubscriptionCreated $event): void
    {
        $event->billable->notify(new WelcomeToPro($event->subscription));
    }
}
```

| Event | Fired when |
| --- | --- |
| `WebhookReceived` | Any verified webhook arrives, handled or not. |
| `WebhookHandled` | Cashier finished handling one it recognises. |
| `SubscriptionCreated` | A subscription is stored for the first time. |
| `SubscriptionUpdated` | The plan, quantity or addons changed. |
| `SubscriptionRenewed` | A renewal succeeded. |
| `SubscriptionOnHold` | A renewal payment failed. |
| `SubscriptionCancelled` | The subscription was cancelled or expired. |
| `PaymentSucceeded` | Money arrived. |
| `PaymentFailed` | A payment failed. |
| `RefundSucceeded` | A refund went through. |

## Talking to the API directly

Everything Cashier does goes through a small typed client, and it is yours to use for the parts of the Dodo API this
package does not model.

```php
use Ahmadnajmdev\Cashier\Dodo\Facades\Dodo;

Dodo::products()->list(['page_size' => 50]);
Dodo::products()->find('pdt_pro');
Dodo::discounts()->findByCode('LAUNCH');
Dodo::refunds()->create(['payment_id' => 'pay_1', 'reason' => 'Duplicate']);
Dodo::disputes()->list();
Dodo::licenseKeys()->validate('2b1f8e2d-c41e-4e8f-b2d3-d9fd61c38f43');
Dodo::payouts()->list();
Dodo::meters()->list();
Dodo::brands()->list();
```

Available resources: `checkoutSessions`, `payments`, `subscriptions`, `customers`, `products`, `discounts`,
`refunds`, `disputes`, `licenseKeys`, `webhooks`, `addons`, `brands`, `meters`, `usageEvents`, `payouts`.

For anything not covered, the raw verbs are there too:

```php
Dodo::get('entitlements', ['page_size' => 10]);
Dodo::post('entitlements', [...]);
Dodo::patch('entitlements/ent_1', [...]);
Dodo::delete('entitlements/ent_1');
```

Failed requests throw `DodoApiException`, which carries the status, the error code and the decoded body:

```php
use Ahmadnajmdev\Cashier\Dodo\Exceptions\DodoApiException;

try {
    $user->checkout('pdt_missing')->create();
} catch (DodoApiException $e) {
    $e->status;             // 422
    $e->errorCode;
    $e->errors;
    $e->isRateLimited();
}
```

## Customising

```php
use Ahmadnajmdev\Cashier\Dodo\Cashier;

// In a service provider.
Cashier::useSubscriptionModel(YourSubscription::class);
Cashier::useCustomerModel(YourCustomer::class);
Cashier::useTransactionModel(YourTransaction::class);

Cashier::ignoreRoutes();        // register the webhook route yourself
Cashier::ignoreMigrations();    // publish and manage the migrations yourself

Cashier::formatCurrencyUsing(fn (int $amount, string $currency) => ...);
```

To handle webhook events yourself, extend the controller and point your own route at it:

```php
use Ahmadnajmdev\Cashier\Dodo\Http\Controllers\WebhookController as CashierController;

class WebhookController extends CashierController
{
    protected function handleDisputeOpened(array $payload): void
    {
        // ...
    }
}
```

Any `handle*` method named after the studly form of an event type is picked up automatically —
`dispute.opened` becomes `handleDisputeOpened`.

## Testing

Cashier is built on Laravel's HTTP client, so `Http::fake()` covers everything:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    '*/customers' => Http::response(['customer_id' => 'cus_test']),
    '*/checkouts' => Http::response([
        'session_id' => 'cks_test',
        'checkout_url' => 'https://checkout.dodopayments.com/cks_test',
    ]),
]);

expect($user->checkout('pdt_pro')->url())->toBe('https://checkout.dodopayments.com/cks_test');
```

To test webhooks, sign the payload the way Dodo does:

```php
use StandardWebhooks\Webhook;

$payload = json_encode(['type' => 'payment.succeeded', 'data' => [...]]);
$id = 'msg_1';
$timestamp = time();

$this->call('POST', '/dodo/webhook', server: [
    'HTTP_WEBHOOK_ID' => $id,
    'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp,
    'HTTP_WEBHOOK_SIGNATURE' => (new Webhook(config('cashier-dodo.webhook_secret')))->sign($id, $timestamp, $payload),
    'CONTENT_TYPE' => 'application/json',
], content: $payload)->assertOk();
```

Run the package's own suite with:

```bash
composer test
composer analyse
composer lint
```

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- `ext-intl` if you want `Cashier::formatAmount()` to localise currency, otherwise register your own formatter with
  `Cashier::formatCurrencyUsing()`

## A note on amounts

Dodo returns money in the smallest unit of the currency — 2500 is $25.00, and for zero decimal currencies such as
IQD or JPY the number is the amount itself. Cashier stores what Dodo sends without converting it. Use
`Cashier::formatAmount()` or `$transaction->total()` when you show it to someone.

## License

MIT. See [LICENSE.md](LICENSE.md).
