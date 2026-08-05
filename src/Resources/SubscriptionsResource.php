<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class SubscriptionsResource extends Resource
{
    /**
     * List subscriptions.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('subscriptions', $query);
    }

    /**
     * Retrieve a single subscription.
     *
     * @return array<string, mixed>
     */
    public function find(string $subscriptionId): array
    {
        return $this->client->get('subscriptions/'.$subscriptionId);
    }

    /**
     * Create a subscription directly, bypassing a hosted checkout.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('subscriptions', $payload);
    }

    /**
     * Update a subscription.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(string $subscriptionId, array $payload): array
    {
        return $this->client->patch('subscriptions/'.$subscriptionId, $payload);
    }

    /**
     * Move a subscription onto a different plan.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function changePlan(string $subscriptionId, array $payload): array
    {
        return $this->client->post('subscriptions/'.$subscriptionId.'/change-plan', $payload);
    }

    /**
     * Preview what a plan change would cost before committing to it.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function previewChangePlan(string $subscriptionId, array $payload): array
    {
        return $this->client->post('subscriptions/'.$subscriptionId.'/change-plan/preview', $payload);
    }

    /**
     * Cancel a plan change that was scheduled for the next billing date.
     *
     * @return array<string, mixed>
     */
    public function cancelScheduledChangePlan(string $subscriptionId): array
    {
        return $this->client->delete('subscriptions/'.$subscriptionId.'/change-plan/scheduled');
    }

    /**
     * Charge an on-demand subscription.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function charge(string $subscriptionId, array $payload): array
    {
        return $this->client->post('subscriptions/'.$subscriptionId.'/charge', $payload);
    }

    /**
     * Start the flow that lets a customer replace the card on a subscription.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updatePaymentMethod(string $subscriptionId, array $payload): array
    {
        return $this->client->post('subscriptions/'.$subscriptionId.'/update-payment-method', $payload);
    }

    /**
     * Retrieve the metered usage history of a subscription.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function usageHistory(string $subscriptionId, array $query = []): array
    {
        return $this->client->get('subscriptions/'.$subscriptionId.'/usage-history', $query);
    }
}
