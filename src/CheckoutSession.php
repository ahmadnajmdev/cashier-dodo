<?php

namespace Ahmadnajmdev\Cashier\Dodo;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use LogicException;

/**
 * The result of creating a hosted checkout session.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
class CheckoutSession implements Arrayable, ArrayAccess
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(protected array $payload) {}

    /**
     * Get the identifier of the session.
     */
    public function id(): string
    {
        return $this->payload['session_id'];
    }

    /**
     * Get the URL the customer should be sent to.
     *
     * @throws LogicException
     */
    public function url(): string
    {
        $url = $this->payload['checkout_url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new LogicException(
                'This checkout session has no URL. Sessions created with a payment method attached are confirmed '.
                'server side and expose a client secret instead.'
            );
        }

        return $url;
    }

    /**
     * Determine whether the session has a hosted checkout URL.
     */
    public function hasUrl(): bool
    {
        return is_string($this->payload['checkout_url'] ?? null) && $this->payload['checkout_url'] !== '';
    }

    /**
     * Get the client secret used to finish the payment with Dodo's client SDKs.
     */
    public function clientSecret(): ?string
    {
        return $this->payload['client_secret'] ?? null;
    }

    /**
     * Get the publishable key used alongside the client secret.
     */
    public function publishableKey(): ?string
    {
        return $this->payload['publishable_key'] ?? null;
    }

    /**
     * Get the payment created for a confirmed session.
     */
    public function paymentId(): ?string
    {
        return $this->payload['payment_id'] ?? null;
    }

    /**
     * Get the raw payload returned by Dodo Payments.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->payload[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->payload[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('A checkout session is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('A checkout session is immutable.');
    }

    public function __get(string $name): mixed
    {
        return $this->payload[$name] ?? null;
    }
}
