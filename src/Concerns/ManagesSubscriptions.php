<?php

namespace Ahmadnajmdev\Cashier\Dodo\Concerns;

use Ahmadnajmdev\Cashier\Dodo\Cashier;
use Ahmadnajmdev\Cashier\Dodo\Checkout;
use Ahmadnajmdev\Cashier\Dodo\Subscription;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait ManagesSubscriptions
{
    /**
     * Get all of the subscriptions belonging to this model.
     *
     * @return MorphMany<Subscription, $this>
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Cashier::$subscriptionModel, 'billable')->orderByDesc('created_at');
    }

    /**
     * Get a subscription of the given type.
     */
    public function subscription(string $type = Subscription::DEFAULT_TYPE): ?Subscription
    {
        return $this->subscriptions->where('type', $type)->first();
    }

    /**
     * Determine whether the model has a usable subscription of the given type.
     */
    public function subscribed(string $type = Subscription::DEFAULT_TYPE, ?string $productId = null): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return is_null($productId) || $subscription->hasProduct($productId);
    }

    /**
     * Determine whether the model is subscribed to any of the given products.
     *
     * @param  string|array<int, string>  $products
     */
    public function subscribedToProduct(string|array $products, string $type = Subscription::DEFAULT_TYPE): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $products as $product) {
            if ($subscription->hasProduct($product)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the model is within the trial of a real subscription.
     */
    public function onTrial(string $type = Subscription::DEFAULT_TYPE): bool
    {
        return $this->subscription($type)?->onTrial() ?? false;
    }

    /**
     * Determine whether the model is on a trial of any kind.
     */
    public function hasTrial(string $type = Subscription::DEFAULT_TYPE): bool
    {
        return $this->onTrial($type) || $this->onGenericTrial();
    }

    /**
     * Determine whether a subscription is cancelled but still running.
     */
    public function onGracePeriod(string $type = Subscription::DEFAULT_TYPE): bool
    {
        return $this->subscription($type)?->onGracePeriod() ?? false;
    }

    /**
     * Determine whether Dodo has put a subscription on hold after a failed payment.
     */
    public function subscriptionOnHold(string $type = Subscription::DEFAULT_TYPE): bool
    {
        return $this->subscription($type)?->onHold() ?? false;
    }

    /**
     * Start a checkout that subscribes this model to a product.
     *
     * @param  array<string, mixed>  $options
     */
    public function subscribe(string $productId, string $type = Subscription::DEFAULT_TYPE, array $options = []): Checkout
    {
        return $this->checkout($productId, $options)
            ->withMetadata(['subscription_type' => $type]);
    }
}
