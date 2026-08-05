<?php

namespace Ahmadnajmdev\Cashier\Dodo\Console;

use Ahmadnajmdev\Cashier\Dodo\Enums\WebhookEvent;
use Ahmadnajmdev\Cashier\Dodo\Facades\Dodo;
use Illuminate\Console\Command;

class WebhookCommand extends Command
{
    protected $signature = 'cashier-dodo:webhook
                            {--url= : The endpoint Dodo Payments should call. Defaults to your app URL plus the configured path.}
                            {--all-events : Subscribe to every event type instead of only the ones Cashier handles.}
                            {--disabled : Create the endpoint in a disabled state.}
                            {--description= : A description shown in the Dodo dashboard.}';

    protected $description = 'Register this application\'s webhook endpoint with Dodo Payments';

    public function handle(): int
    {
        $url = $this->option('url') ?: $this->defaultUrl();

        if (blank($url)) {
            $this->components->error('Could not work out a webhook URL. Pass one with --url.');

            return static::FAILURE;
        }

        $webhook = Dodo::webhooks()->create(array_filter([
            'url' => $url,
            'description' => $this->option('description') ?: config('app.name').' (Cashier for Dodo Payments)',
            'disabled' => (bool) $this->option('disabled') ?: null,
            'filter_types' => $this->option('all-events') ? WebhookEvent::values() : WebhookEvent::cashierEvents(),
        ], fn ($value) => ! is_null($value)));

        $this->components->info('Webhook endpoint created.');

        $this->components->twoColumnDetail('URL', $webhook['url'] ?? $url);
        $this->components->twoColumnDetail('Webhook ID', $webhook['id'] ?? '-');
        $this->components->twoColumnDetail('Events', (string) count($webhook['filter_types'] ?? []));

        $signingKey = $this->signingKeyFor($webhook['id'] ?? null);

        if ($signingKey) {
            $this->newLine();
            $this->components->warn('Add this to your .env file, it is how Cashier verifies incoming webhooks:');
            $this->line('DODO_WEBHOOK_SECRET='.$signingKey);
        } else {
            $this->newLine();
            $this->components->warn('Copy the signing key from the Dodo dashboard into DODO_WEBHOOK_SECRET.');
        }

        return static::SUCCESS;
    }

    /**
     * Work out the webhook URL from the application URL and the configured path.
     */
    protected function defaultUrl(): ?string
    {
        $path = config('cashier-dodo.path');

        if (is_null($path)) {
            return null;
        }

        return rtrim(config('app.url'), '/').'/'.trim($path.'/webhook', '/');
    }

    /**
     * Try to read the signing key back out of the API.
     */
    protected function signingKeyFor(?string $webhookId): ?string
    {
        if (! $webhookId) {
            return null;
        }

        try {
            return Dodo::webhooks()->signingKey($webhookId)['signing_key'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
