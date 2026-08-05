<?php

namespace Ahmadnajmdev\Cashier\Dodo;

use Ahmadnajmdev\Cashier\Dodo\Exceptions\DodoApiException;
use Ahmadnajmdev\Cashier\Dodo\Resources\AddonsResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\BrandsResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\CheckoutSessionsResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\CustomersResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\DiscountsResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\DisputesResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\LicenseKeysResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\MetersResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\PaymentsResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\PayoutsResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\ProductsResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\RefundsResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\SubscriptionsResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\UsageEventsResource;
use Ahmadnajmdev\Cashier\Dodo\Resources\WebhooksResource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * A thin, fully typed client for the Dodo Payments REST API.
 */
class DodoClient
{
    public const TEST_MODE = 'test_mode';

    public const LIVE_MODE = 'live_mode';

    public const TEST_BASE_URL = 'https://test.dodopayments.com';

    public const LIVE_BASE_URL = 'https://live.dodopayments.com';

    /**
     * The resolved resource instances, keyed by accessor name.
     *
     * @var array<string, object>
     */
    protected array $resources = [];

    /**
     * @param  array{times?: int, sleep?: int}  $retry
     */
    public function __construct(
        protected ?string $apiKey = null,
        protected string $environment = self::TEST_MODE,
        protected ?string $baseUrl = null,
        protected int $timeout = 30,
        protected array $retry = ['times' => 2, 'sleep' => 250],
    ) {}

    /**
     * Determine whether the client is pointed at live mode.
     */
    public function isLiveMode(): bool
    {
        return $this->environment === self::LIVE_MODE;
    }

    /**
     * Get the environment the client is configured for.
     */
    public function environment(): string
    {
        return $this->environment;
    }

    /**
     * Get the API key the client authenticates with.
     */
    public function apiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * Get the base URL every request is sent to.
     */
    public function baseUrl(): string
    {
        return rtrim($this->baseUrl ?: ($this->isLiveMode() ? self::LIVE_BASE_URL : self::TEST_BASE_URL), '/');
    }

    /**
     * Send a GET request to the Dodo Payments API.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('get', $path, query: $query);
    }

    /**
     * Send a POST request to the Dodo Payments API.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = [], array $query = []): array
    {
        return $this->send('post', $path, $payload, $query);
    }

    /**
     * Send a PATCH request to the Dodo Payments API.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function patch(string $path, array $payload = []): array
    {
        return $this->send('patch', $path, $payload);
    }

    /**
     * Send a PUT request to the Dodo Payments API.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload = []): array
    {
        return $this->send('put', $path, $payload);
    }

    /**
     * Send a DELETE request to the Dodo Payments API.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function delete(string $path, array $payload = []): array
    {
        return $this->send('delete', $path, $payload);
    }

    /**
     * Send a request to the Dodo Payments API and decode the response.
     *
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws DodoApiException
     */
    public function send(string $method, string $path, ?array $payload = null, array $query = []): array
    {
        $options = [];

        if (! is_null($payload)) {
            $options['json'] = (object) $payload;
        }

        if ($query !== []) {
            $options['query'] = $query;
        }

        $response = $this->request()->send(strtoupper($method), $this->url($path), $options);

        if ($response->failed()) {
            throw DodoApiException::fromResponse($response, $method, $path);
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build the pending HTTP request used for every API call.
     */
    public function request(): PendingRequest
    {
        $request = Http::withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->withUserAgent('cashier-dodo/'.Cashier::VERSION.' php/'.PHP_VERSION);

        $times = (int) ($this->retry['times'] ?? 0);

        if ($times > 0) {
            $request->retry(
                $times + 1,
                (int) ($this->retry['sleep'] ?? 0),
                fn ($exception) => ! $exception instanceof RequestException
                    || $exception->response->serverError()
                    || $exception->response->status() === 429,
                throw: false,
            );
        }

        return $request;
    }

    /**
     * Build a fully qualified URL for the given API path.
     */
    public function url(string $path): string
    {
        return $this->baseUrl().'/'.ltrim($path, '/');
    }

    /**
     * Fluently set the API key for a copy of the client.
     */
    public function withApiKey(string $apiKey): static
    {
        $clone = clone $this;
        $clone->apiKey = $apiKey;
        $clone->resources = [];

        return $clone;
    }

    /**
     * Fluently switch a copy of the client to another environment.
     */
    public function withEnvironment(string $environment): static
    {
        $clone = clone $this;
        $clone->environment = $environment;
        $clone->resources = [];

        return $clone;
    }

    public function checkoutSessions(): CheckoutSessionsResource
    {
        return $this->resource(CheckoutSessionsResource::class);
    }

    public function payments(): PaymentsResource
    {
        return $this->resource(PaymentsResource::class);
    }

    public function subscriptions(): SubscriptionsResource
    {
        return $this->resource(SubscriptionsResource::class);
    }

    public function customers(): CustomersResource
    {
        return $this->resource(CustomersResource::class);
    }

    public function products(): ProductsResource
    {
        return $this->resource(ProductsResource::class);
    }

    public function discounts(): DiscountsResource
    {
        return $this->resource(DiscountsResource::class);
    }

    public function refunds(): RefundsResource
    {
        return $this->resource(RefundsResource::class);
    }

    public function disputes(): DisputesResource
    {
        return $this->resource(DisputesResource::class);
    }

    public function licenseKeys(): LicenseKeysResource
    {
        return $this->resource(LicenseKeysResource::class);
    }

    public function webhooks(): WebhooksResource
    {
        return $this->resource(WebhooksResource::class);
    }

    public function addons(): AddonsResource
    {
        return $this->resource(AddonsResource::class);
    }

    public function brands(): BrandsResource
    {
        return $this->resource(BrandsResource::class);
    }

    public function meters(): MetersResource
    {
        return $this->resource(MetersResource::class);
    }

    public function usageEvents(): UsageEventsResource
    {
        return $this->resource(UsageEventsResource::class);
    }

    public function payouts(): PayoutsResource
    {
        return $this->resource(PayoutsResource::class);
    }

    /**
     * Resolve, and memoize, an API resource instance.
     *
     * @template TResource of object
     *
     * @param  class-string<TResource>  $resource
     * @return TResource
     */
    protected function resource(string $resource): object
    {
        /** @var TResource */
        return $this->resources[$resource] ??= new $resource($this);
    }
}
