<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class AddonsResource extends Resource
{
    /**
     * List addons.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('addons', $query);
    }

    /**
     * Retrieve a single addon.
     *
     * @return array<string, mixed>
     */
    public function find(string $addonId): array
    {
        return $this->client->get('addons/'.$addonId);
    }

    /**
     * Create an addon.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('addons', $payload);
    }

    /**
     * Update an addon.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(string $addonId, array $payload): array
    {
        return $this->client->patch('addons/'.$addonId, $payload);
    }
}
