<?php

namespace Ahmadnajmdev\Cashier\Dodo\Resources;

class LicenseKeysResource extends Resource
{
    /**
     * List license keys.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        return $this->client->get('license_keys', $query);
    }

    /**
     * Retrieve a single license key.
     *
     * @return array<string, mixed>
     */
    public function find(string $licenseKeyId): array
    {
        return $this->client->get('license_keys/'.$licenseKeyId);
    }

    /**
     * Update a license key.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(string $licenseKeyId, array $payload): array
    {
        return $this->client->patch('license_keys/'.$licenseKeyId, $payload);
    }

    /**
     * Activate a license key against a named instance.
     *
     * @return array<string, mixed>
     */
    public function activate(string $licenseKey, string $name): array
    {
        return $this->client->post('licenses/activate', [
            'license_key' => $licenseKey,
            'name' => $name,
        ]);
    }

    /**
     * Deactivate a previously activated license key instance.
     *
     * @return array<string, mixed>
     */
    public function deactivate(string $licenseKey, string $licenseKeyInstanceId): array
    {
        return $this->client->post('licenses/deactivate', [
            'license_key' => $licenseKey,
            'license_key_instance_id' => $licenseKeyInstanceId,
        ]);
    }

    /**
     * Determine whether a license key is currently valid.
     */
    public function validate(string $licenseKey, ?string $licenseKeyInstanceId = null): bool
    {
        $response = $this->client->post('licenses/validate', $this->filter([
            'license_key' => $licenseKey,
            'license_key_instance_id' => $licenseKeyInstanceId,
        ]));

        return (bool) ($response['valid'] ?? false);
    }

    /**
     * List the instances a license key has been activated on.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function instances(array $query = []): array
    {
        return $this->client->get('license_key_instances', $query);
    }
}
