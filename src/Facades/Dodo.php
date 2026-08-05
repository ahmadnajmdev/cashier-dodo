<?php

namespace Ahmadnajmdev\Cashier\Dodo\Facades;

use Ahmadnajmdev\Cashier\Dodo\DodoClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array get(string $path, array $query = [])
 * @method static array post(string $path, array $payload = [], array $query = [])
 * @method static array patch(string $path, array $payload = [])
 * @method static array put(string $path, array $payload = [])
 * @method static array delete(string $path, array $payload = [])
 * @method static array send(string $method, string $path, ?array $payload = null, array $query = [])
 * @method static string url(string $path)
 * @method static string baseUrl()
 * @method static string environment()
 * @method static bool isLiveMode()
 * @method static \Ahmadnajmdev\Cashier\Dodo\DodoClient withApiKey(string $apiKey)
 * @method static \Ahmadnajmdev\Cashier\Dodo\DodoClient withEnvironment(string $environment)
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\CheckoutSessionsResource checkoutSessions()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\PaymentsResource payments()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\SubscriptionsResource subscriptions()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\CustomersResource customers()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\ProductsResource products()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\DiscountsResource discounts()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\RefundsResource refunds()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\DisputesResource disputes()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\LicenseKeysResource licenseKeys()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\WebhooksResource webhooks()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\AddonsResource addons()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\BrandsResource brands()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\MetersResource meters()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\UsageEventsResource usageEvents()
 * @method static \Ahmadnajmdev\Cashier\Dodo\Resources\PayoutsResource payouts()
 *
 * @see DodoClient
 */
class Dodo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DodoClient::class;
    }
}
