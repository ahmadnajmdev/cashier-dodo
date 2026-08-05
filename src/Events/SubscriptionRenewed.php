<?php

namespace Ahmadnajmdev\Cashier\Dodo\Events;

use Ahmadnajmdev\Cashier\Dodo\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly Subscription $subscription,
        public readonly array $payload,
    ) {}
}
