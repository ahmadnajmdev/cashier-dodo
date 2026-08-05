<?php

namespace Ahmadnajmdev\Cashier\Dodo\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class InvalidWebhookSignature extends AccessDeniedHttpException
{
    /**
     * Create an exception for a webhook whose signature could not be verified.
     */
    public static function make(?Throwable $previous = null): self
    {
        return new self('The webhook signature could not be verified.', $previous);
    }

    /**
     * Create an exception for an application that has not configured a webhook secret.
     */
    public static function missingSecret(): self
    {
        return new self('No Dodo Payments webhook secret is configured. Set DODO_WEBHOOK_SECRET in your environment.');
    }
}
