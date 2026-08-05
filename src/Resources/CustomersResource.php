<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class CustomersResource extends Resource
{
    /**
     * List customers.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('customers', $query);
    }

    /**
     * Retrieve a single customer.
     *
     * @return array<string, mixed>
     */
    public function find(string $customerId): array
    {
        return $this->client->get('customers/'.$customerId);
    }

    /**
     * Create a customer.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('customers', $payload);
    }

    /**
     * Update a customer.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(string $customerId, array $payload): array
    {
        return $this->client->patch('customers/'.$customerId, $payload);
    }

    /**
     * Create a self service billing portal session for a customer.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function portalSession(string $customerId, array $query = []): array
    {
        return $this->client->post('customers/'.$customerId.'/customer-portal/session', [], $query);
    }

    /**
     * List the payment methods a customer has saved.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function paymentMethods(string $customerId, array $query = []): array
    {
        return $this->client->get('customers/'.$customerId.'/payment-methods', $query);
    }

    /**
     * Delete a saved payment method.
     *
     * @return array<string, mixed>
     */
    public function deletePaymentMethod(string $customerId, string $paymentMethodId): array
    {
        return $this->client->delete('customers/'.$customerId.'/payment-methods/'.$paymentMethodId);
    }

    /**
     * List the wallets held by a customer.
     *
     * @return array<string, mixed>
     */
    public function wallets(string $customerId): array
    {
        return $this->client->get('customers/'.$customerId.'/wallets');
    }
}
