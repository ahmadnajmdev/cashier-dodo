<?php

namespace Ahmadnajmdev\Cashier\Dodo\Events;

use Ahmadnajmdev\Cashier\Dodo\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly Transaction $transaction,
        public readonly array $payload,
    ) {}
}
