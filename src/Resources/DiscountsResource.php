<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class DiscountsResource extends Resource
{
    /**
     * List discounts.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('discounts', $query);
    }

    /**
     * Retrieve a discount by its identifier.
     *
     * @return array<string, mixed>
     */
    public function find(string $discountId): array
    {
        return $this->client->get('discounts/'.$discountId);
    }

    /**
     * Retrieve a discount by the code customers type at checkout.
     *
     * @return array<string, mixed>
     */
    public function findByCode(string $code): array
    {
        return $this->client->get('discounts/code/'.rawurlencode($code));
    }

    /**
     * Create a discount.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('discounts', $payload);
    }

    /**
     * Update a discount.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(string $discountId, array $payload): array
    {
        return $this->client->patch('discounts/'.$discountId, $payload);
    }

    /**
     * Delete a discount.
     *
     * @return array<string, mixed>
     */
    public function delete(string $discountId): array
    {
        return $this->client->delete('discounts/'.$discountId);
    }
}
