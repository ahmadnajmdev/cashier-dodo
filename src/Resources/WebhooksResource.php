<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class WebhooksResource extends Resource
{
    /**
     * List the webhook endpoints registered on the account.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('webhooks', $query);
    }

    /**
     * Retrieve a single webhook endpoint.
     *
     * @return array<string, mixed>
     */
    public function find(string $webhookId): array
    {
        return $this->client->get('webhooks/'.$webhookId);
    }

    /**
     * Register a webhook endpoint.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('webhooks', $payload);
    }

    /**
     * Update a webhook endpoint.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(string $webhookId, array $payload): array
    {
        return $this->client->patch('webhooks/'.$webhookId, $payload);
    }

    /**
     * Delete a webhook endpoint.
     *
     * @return array<string, mixed>
     */
    public function delete(string $webhookId): array
    {
        return $this->client->delete('webhooks/'.$webhookId);
    }

    /**
     * Retrieve the signing key used to verify a webhook endpoint's signatures.
     *
     * @return array<string, mixed>
     */
    public function signingKey(string $webhookId): array
    {
        return $this->client->get('webhooks/'.$webhookId.'/secret');
    }

    /**
     * Retrieve the custom headers sent with a webhook endpoint's requests.
     *
     * @return array<string, mixed>
     */
    public function headers(string $webhookId): array
    {
        return $this->client->get('webhooks/'.$webhookId.'/headers');
    }
}
