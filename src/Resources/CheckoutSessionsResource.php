<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class CheckoutSessionsResource extends Resource
{
    /**
     * Create a hosted checkout session.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('checkouts', $payload);
    }

    /**
     * Retrieve a checkout session.
     *
     * @return array<string, mixed>
     */
    public function find(string $sessionId): array
    {
        return $this->client->get('checkouts/'.$sessionId);
    }

    /**
     * Preview the totals of a checkout session without creating it.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function preview(array $payload): array
    {
        return $this->client->post('checkouts/preview', $payload);
    }
}
