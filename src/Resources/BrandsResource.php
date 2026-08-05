<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class BrandsResource extends Resource
{
    /**
     * List brands.
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->client->get('brands');
    }

    /**
     * Retrieve a single brand.
     *
     * @return array<string, mixed>
     */
    public function find(string $brandId): array
    {
        return $this->client->get('brands/'.$brandId);
    }

    /**
     * Create a brand.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('brands', $payload);
    }

    /**
     * Update a brand.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(string $brandId, array $payload): array
    {
        return $this->client->patch('brands/'.$brandId, $payload);
    }
}
