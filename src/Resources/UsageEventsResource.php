<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class UsageEventsResource extends Resource
{
    /**
     * Send a batch of usage events to Dodo Payments.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    public function ingest(array $events): array
    {
        return $this->client->post('events/ingest', ['events' => array_values($events)]);
    }

    /**
     * List previously ingested usage events.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('events', $query);
    }

    /**
     * Retrieve a single usage event.
     *
     * @return array<string, mixed>
     */
    public function find(string $eventId): array
    {
        return $this->client->get('events/'.$eventId);
    }
}
