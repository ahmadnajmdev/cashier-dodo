<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class RefundsResource extends Resource
{
    /**
     * List refunds.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('refunds', $query);
    }

    /**
     * Retrieve a single refund.
     *
     * @return array<string, mixed>
     */
    public function find(string $refundId): array
    {
        return $this->client->get('refunds/'.$refundId);
    }

    /**
     * Refund a payment, either fully or partially.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('refunds', $payload);
    }
}
