<?php

namespace Ahmadnajmdev\Cashier\Dodo\Http\Controllers;

use Ahmadnajmdev\Cashier\Dodo\Cashier;
use Ahmadnajmdev\Cashier\Dodo\Enums\WebhookEvent;
use Ahmadnajmdev\Cashier\Dodo\Events\PaymentFailed;
use Ahmadnajmdev\Cashier\Dodo\Events\PaymentSucceeded;
use Ahmadnajmdev\Cashier\Dodo\Events\RefundSucceeded;
use Ahmadnajmdev\Cashier\Dodo\Events\SubscriptionCancelled;
use Ahmadnajmdev\Cashier\Dodo\Events\SubscriptionCreated;
use Ahmadnajmdev\Cashier\Dodo\Events\SubscriptionOnHold;
use Ahmadnajmdev\Cashier\Dodo\Events\SubscriptionRenewed;
use Ahmadnajmdev\Cashier\Dodo\Events\SubscriptionUpdated;
use Ahmadnajmdev\Cashier\Dodo\Events\WebhookHandled;
use Ahmadnajmdev\Cashier\Dodo\Events\WebhookReceived;
use Ahmadnajmdev\Cashier\Dodo\Subscription;
use Ahmadnajmdev\Cashier\Dodo\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class WebhookController
{
    /**
     * Handle an incoming Dodo Payments webhook.
     */
    public function __invoke(Request $request): Response
    {
        $payload = $request->json()->all();

        if (! is_array($payload) || ! isset($payload['type'])) {
            return new Response('Malformed webhook payload.', 400);
        }

        WebhookReceived::dispatch($payload);

        $method = 'handle'.Str::studly(str_replace('.', '_', $payload['type']));

        if (method_exists($this, $method)) {
            $this->{$method}($payload);

            WebhookHandled::dispatch($payload);
        }

        return new Response('Webhook handled.', 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionActive(array $payload): void
    {
        $this->syncSubscription($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionUpdated(array $payload): void
    {
        if ($subscription = $this->syncSubscription($payload)) {
            SubscriptionUpdated::dispatch($subscription, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionPlanChanged(array $payload): void
    {
        if ($subscription = $this->syncSubscription($payload)) {
            SubscriptionUpdated::dispatch($subscription, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionRenewed(array $payload): void
    {
        if ($subscription = $this->syncSubscription($payload)) {
            SubscriptionRenewed::dispatch($subscription, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionOnHold(array $payload): void
    {
        if ($subscription = $this->syncSubscription($payload)) {
            SubscriptionOnHold::dispatch($subscription, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionPaused(array $payload): void
    {
        if ($subscription = $this->syncSubscription($payload)) {
            $subscription->forceFill(['paused_at' => $subscription->freshTimestamp()])->save();

            SubscriptionUpdated::dispatch($subscription, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionCancelled(array $payload): void
    {
        if ($subscription = $this->syncSubscription($payload)) {
            SubscriptionCancelled::dispatch($subscription, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionFailed(array $payload): void
    {
        if ($subscription = $this->syncSubscription($payload)) {
            SubscriptionUpdated::dispatch($subscription, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionExpired(array $payload): void
    {
        if ($subscription = $this->syncSubscription($payload)) {
            SubscriptionCancelled::dispatch($subscription, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handlePaymentSucceeded(array $payload): void
    {
        if ($transaction = $this->syncTransaction($payload)) {
            PaymentSucceeded::dispatch($transaction, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handlePaymentFailed(array $payload): void
    {
        if ($transaction = $this->syncTransaction($payload)) {
            PaymentFailed::dispatch($transaction, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handlePaymentProcessing(array $payload): void
    {
        $this->syncTransaction($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handlePaymentCancelled(array $payload): void
    {
        $this->syncTransaction($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleRefundSucceeded(array $payload): void
    {
        $data = $payload['data'] ?? [];

        $transaction = isset($data['payment_id'])
            ? Cashier::$transactionModel::where('dodo_id', $data['payment_id'])->first()
            : null;

        $transaction?->forceFill([
            'refund_status' => $this->refundStatusFor($transaction, $data),
        ])->save();

        RefundSucceeded::dispatch($transaction, $payload);
    }

    /**
     * Bring a subscription in the database into line with the webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function syncSubscription(array $payload): ?Subscription
    {
        $data = $payload['data'] ?? [];

        if (! isset($data['subscription_id'])) {
            return null;
        }

        /** @var Subscription|null $subscription */
        $subscription = Cashier::$subscriptionModel::where('dodo_id', $data['subscription_id'])->first();

        if ($subscription) {
            $subscription->fillFromDodo($data)->save();

            return $subscription;
        }

        $billable = $this->resolveBillable($data);

        if (! $billable) {
            return null;
        }

        $this->syncCustomerRecord($billable, $data);

        $subscription = new Cashier::$subscriptionModel;

        $subscription->forceFill(array_merge(Subscription::mapFromDodo($data), [
            'type' => $data['metadata']['subscription_type'] ?? Subscription::DEFAULT_TYPE,
        ]));

        $subscription->billable()->associate($billable);
        $subscription->save();

        SubscriptionCreated::dispatch($billable, $subscription, $payload);

        return $subscription;
    }

    /**
     * Bring a transaction in the database into line with the webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function syncTransaction(array $payload): ?Transaction
    {
        $data = $payload['data'] ?? [];

        if (! isset($data['payment_id'])) {
            return null;
        }

        /** @var Transaction|null $transaction */
        $transaction = Cashier::$transactionModel::where('dodo_id', $data['payment_id'])->first();

        if (! $transaction) {
            $billable = $this->resolveBillable($data);

            if (! $billable) {
                return null;
            }

            $this->syncCustomerRecord($billable, $data);

            $transaction = new Cashier::$transactionModel;
            $transaction->billable()->associate($billable);
        }

        $transaction->fillFromDodo($data)->save();

        return $transaction;
    }

    /**
     * Work out which of your models this webhook belongs to.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveBillable(array $data): ?Model
    {
        if ($customerId = $data['customer']['customer_id'] ?? null) {
            if ($billable = Cashier::findBillable($customerId)) {
                return $billable;
            }
        }

        return $this->billableFromMetadata($data['metadata'] ?? []);
    }

    /**
     * Resolve the billable model from the metadata Cashier attaches at checkout.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function billableFromMetadata(array $metadata): ?Model
    {
        if (! isset($metadata['billable_type'], $metadata['billable_id'])) {
            return null;
        }

        $class = Relation::getMorphedModel($metadata['billable_type']) ?: $metadata['billable_type'];

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class::query()->find($metadata['billable_id']);
    }

    /**
     * Make sure the billable model is linked to the Dodo customer in the payload.
     *
     * @param  array<string, mixed>  $data
     */
    protected function syncCustomerRecord(Model $billable, array $data): void
    {
        $customerId = $data['customer']['customer_id'] ?? null;

        if (! $customerId) {
            return;
        }

        $customer = Cashier::$customerModel::firstOrNew([
            'billable_type' => $billable->getMorphClass(),
            'billable_id' => $billable->getKey(),
        ]);

        if ($customer->dodo_id === $customerId) {
            return;
        }

        $customer->forceFill(array_filter([
            'dodo_id' => $customerId,
            'name' => $data['customer']['name'] ?? null,
            'email' => $data['customer']['email'] ?? null,
        ], fn ($value) => ! is_null($value)))->save();
    }

    /**
     * Work out whether a refund covered the whole transaction or only part of it.
     *
     * @param  array<string, mixed>  $data
     */
    protected function refundStatusFor(Transaction $transaction, array $data): string
    {
        $amount = $data['amount'] ?? null;

        return is_numeric($amount) && (int) $amount < $transaction->total ? 'partial' : 'full';
    }

    /**
     * Get the event types this controller knows how to handle.
     *
     * @return array<int, string>
     */
    public static function handledEvents(): array
    {
        return WebhookEvent::cashierEvents();
    }
}
