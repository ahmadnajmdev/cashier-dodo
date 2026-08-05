<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class PayoutsResource extends Resource
{
    /**
     * List payouts.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('payouts', $query);
    }

    /**
     * Retrieve the breakup of a payout.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function breakup(string $payoutId, array $query = []): array
    {
        return $this->client->get('payouts/'.$payoutId.'/breakup', $query);
    }
}
