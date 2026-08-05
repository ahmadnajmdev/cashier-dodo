<?php

namespace Ahmadnajmdev\Cashier\Dodo\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookHandled
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    /**
     * Get the Dodo event type that triggered this webhook.
     */
    public function type(): ?string
    {
        return $this->payload['type'] ?? null;
    }
}
