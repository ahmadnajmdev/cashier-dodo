<?php

namespace Ahmadnajmdev\Cashier\Dodo\Http\Middleware;

use Ahmadnajmdev\Cashier\Dodo\Exceptions\InvalidWebhookSignature;
use Closure;
use Illuminate\Http\Request;
use StandardWebhooks\Exception\WebhookVerificationException;
use StandardWebhooks\Webhook;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject any request that is not a genuine, recent Dodo Payments webhook.
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('cashier-dodo.webhook_secret');

        if (blank($secret)) {
            throw InvalidWebhookSignature::missingSecret();
        }

        try {
            (new Webhook($secret))->verify($request->getContent(), [
                'webhook-id' => $request->header('webhook-id'),
                'webhook-timestamp' => $request->header('webhook-timestamp'),
                'webhook-signature' => $request->header('webhook-signature'),
            ]);
        } catch (WebhookVerificationException $exception) {
            throw InvalidWebhookSignature::make($exception);
        }

        return $next($request);
    }
}
