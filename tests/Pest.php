<?php

use Ahmadnajmdev\Cashier\Dodo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use StandardWebhooks\Webhook;

uses(TestCase::class)->in('Unit');
uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/**
 * Build the Standard Webhooks headers Dodo Payments would send for a payload.
 *
 * @return array<string, string>
 */
function signedWebhookHeaders(string $payload, ?string $secret = null, ?int $timestamp = null): array
{
    $secret ??= TestCase::WEBHOOK_SECRET;
    $timestamp ??= time();
    $id = 'msg_'.substr(md5($payload), 0, 16);

    return [
        'webhook-id' => $id,
        'webhook-timestamp' => (string) $timestamp,
        'webhook-signature' => (new Webhook($secret))->sign($id, $timestamp, $payload),
    ];
}

/**
 * Build a Dodo webhook envelope around an event payload.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function webhookEnvelope(string $type, array $data): array
{
    return [
        'business_id' => 'bus_123',
        'type' => $type,
        'timestamp' => now()->toIso8601String(),
        'data' => $data,
    ];
}

/**
 * Build a Dodo subscription payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dodoSubscription(array $overrides = []): array
{
    return array_merge([
        'payload_type' => 'Subscription',
        'subscription_id' => 'sub_123',
        'brand_id' => 'brand_123',
        'product_id' => 'pdt_pro',
        'status' => 'active',
        'quantity' => 1,
        'recurring_pre_tax_amount' => 2500,
        'tax_inclusive' => false,
        'currency' => 'USD',
        'trial_period_days' => 0,
        'subscription_period_interval' => 'Month',
        'subscription_period_count' => 1,
        'payment_frequency_interval' => 'Month',
        'payment_frequency_count' => 1,
        'created_at' => '2026-01-01T00:00:00Z',
        'previous_billing_date' => '2026-01-01T00:00:00Z',
        'next_billing_date' => '2026-02-01T00:00:00Z',
        'cancel_at_next_billing_date' => false,
        'cancelled_at' => null,
        'expires_at' => null,
        'on_demand' => false,
        'addons' => [],
        'metadata' => [],
        'billing' => ['country' => 'US'],
        'customer' => [
            'customer_id' => 'cus_123',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone_number' => null,
            'metadata' => [],
        ],
    ], $overrides);
}

/**
 * Build a Dodo payment payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dodoPayment(array $overrides = []): array
{
    return array_merge([
        'payload_type' => 'Payment',
        'payment_id' => 'pay_123',
        'business_id' => 'bus_123',
        'brand_id' => 'brand_123',
        'total_amount' => 2500,
        'settlement_amount' => 2500,
        'settlement_currency' => 'USD',
        'currency' => 'USD',
        'tax' => 0,
        'status' => 'succeeded',
        'created_at' => '2026-01-01T00:00:00Z',
        'subscription_id' => null,
        'payment_method' => 'card',
        'card_last_four' => '4242',
        'card_network' => 'VISA',
        'digital_products_delivered' => true,
        'metadata' => [],
        'billing' => ['country' => 'US'],
        'customer' => [
            'customer_id' => 'cus_123',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone_number' => null,
            'metadata' => [],
        ],
    ], $overrides);
}
