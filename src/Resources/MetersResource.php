<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class MetersResource extends Resource
{
    /**
     * List meters.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('meters', $query);
    }

    /**
     * Retrieve a single meter.
     *
     * @return array<string, mixed>
     */
    public function find(string $meterId): array
    {
        return $this->client->get('meters/'.$meterId);
    }

    /**
     * Create a meter.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->client->post('meters', $payload);
    }

    /**
     * Archive a meter.
     *
     * @return array<string, mixed>
     */
    public function archive(string $meterId): array
    {
        return $this->client->delete('meters/'.$meterId);
    }

    /**
     * Restore a previously archived meter.
     *
     * @return array<string, mixed>
     */
    public function unarchive(string $meterId): array
    {
        return $this->client->post('meters/'.$meterId.'/unarchive');
    }
}
