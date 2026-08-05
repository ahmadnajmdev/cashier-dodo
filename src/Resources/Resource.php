<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

use Ahmadnajmdev\Cashier\Dodo\DodoClient;

abstract class Resource
{
    public function __construct(protected DodoClient $client) {}

    /**
     * Get the underlying API client.
     */
    public function client(): DodoClient
    {
        return $this->client;
    }

    /**
     * Strip null values so optional parameters are simply omitted.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function filter(array $parameters): array
    {
        return array_filter($parameters, fn ($value) => ! is_null($value));
    }
}
