<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class PaymentsResource extends Resource
{
    /**
     * List payments.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('payments', $query);
    }

    /**
     * Retrieve a single payment.
     *
     * @return array<string, mixed>
     */
    public function find(string $paymentId): array
    {
        return $this->client->get('payments/'.$paymentId);
    }

    /**
     * Create a one time payment directly, bypassing a hosted checkout.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('payments', $payload);
    }

    /**
     * Retrieve the line items of a payment.
     *
     * @return array<string, mixed>
     */
    public function lineItems(string $paymentId): array
    {
        return $this->client->get('payments/'.$paymentId.'/line-items');
    }

    /**
     * Build the URL of the invoice PDF for a payment.
     */
    public function invoiceUrl(string $paymentId): string
    {
        return $this->client->url('invoices/payments/'.$paymentId);
    }
}
