<?php

namespace Ahmadnajmdev\Cashier\Dodo\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class DodoApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $errorCode = null,
        public readonly array $errors = [],
        public readonly ?string $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Build an exception from a failed Dodo Payments API response.
     */
    public static function fromResponse(Response $response, string $method, string $path): self
    {
        $payload = is_array($decoded = $response->json()) ? $decoded : [];

        $message = $payload['message']
            ?? $payload['error']
            ?? $payload['detail']
            ?? $response->reason()
            ?? 'The Dodo Payments API returned an unsuccessful response.';

        if (! is_string($message)) {
            $message = json_encode($message) ?: 'The Dodo Payments API returned an unsuccessful response.';
        }

        return new self(
            message: sprintf('[%d] %s %s failed: %s', $response->status(), strtoupper($method), $path, $message),
            status: $response->status(),
            errorCode: is_string($payload['code'] ?? null) ? $payload['code'] : null,
            errors: is_array($payload['errors'] ?? null) ? $payload['errors'] : $payload,
            body: $response->body(),
        );
    }

    /**
     * Determine whether the failure was caused by the request itself.
     */
    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * Determine whether the failure happened on the Dodo Payments side.
     */
    public function isServerError(): bool
    {
        return $this->status >= 500;
    }

    /**
     * Determine whether the request was rejected for exceeding a rate limit.
     */
    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }
}
