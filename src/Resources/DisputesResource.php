<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class DisputesResource extends Resource
{
    /**
     * List disputes.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('disputes', $query);
    }

    /**
     * Retrieve a single dispute.
     *
     * @return array<string, mixed>
     */
    public function find(string $disputeId): array
    {
        return $this->client->get('disputes/'.$disputeId);
    }
}
