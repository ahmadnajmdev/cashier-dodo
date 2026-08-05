<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class ProductsResource extends Resource
{
    /**
     * List products.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('products', $query);
    }

    /**
     * Retrieve a single product.
     *
     * @return array<string, mixed>
     */
    public function find(string $productId): array
    {
        return $this->client->get('products/'.$productId);
    }

    /**
     * Create a product.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('products', $payload);
    }

    /**
     * Update a product.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(string $productId, array $payload): array
    {
        return $this->client->patch('products/'.$productId, $payload);
    }

    /**
     * Archive a product so it can no longer be purchased.
     *
     * @return array<string, mixed>
     */
    public function archive(string $productId): array
    {
        return $this->client->delete('products/'.$productId);
    }

    /**
     * Restore a previously archived product.
     *
     * @return array<string, mixed>
     */
    public function unarchive(string $productId): array
    {
        return $this->client->post('products/'.$productId.'/unarchive');
    }

    /**
     * List the localized prices configured for a product.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function localizedPrices(string $productId, array $query = []): array
    {
        return $this->client->get('products/'.$productId.'/localized-prices', $query);
    }
}
