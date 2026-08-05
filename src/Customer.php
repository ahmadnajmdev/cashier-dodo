<?php

namespace Ahmadnajmdev\Cashier\Dodo;

use Ahmadnajmdev\Cashier\Dodo\Exceptions\InvalidCustomer;
use Ahmadnajmdev\Cashier\Dodo\Facades\Dodo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string|null $dodo_id
 * @property string|null $name
 * @property string|null $email
 * @property Carbon|null $trial_ends_at
 */
class Customer extends Model
{
    protected $table = 'dodo_customers';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Get the model that owns this Dodo customer record.
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Determine whether the customer is on a generic trial, one that exists only in your database.
     */
    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at?->isFuture() ?? false;
    }

    /**
     * Determine whether a generic trial has already run out.
     */
    public function hasExpiredGenericTrial(): bool
    {
        return $this->trial_ends_at?->isPast() ?? false;
    }

    /**
     * Fetch the customer as it currently exists in Dodo Payments.
     *
     * @return array<string, mixed>
     */
    public function asDodoCustomer(): array
    {
        return Dodo::customers()->find($this->dodoIdOrFail());
    }

    /**
     * Update the customer in Dodo Payments.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function updateDodoCustomer(array $attributes): array
    {
        $customer = Dodo::customers()->update($this->dodoIdOrFail(), $attributes);

        $this->forceFill([
            'name' => $customer['name'] ?? $this->name,
            'email' => $customer['email'] ?? $this->email,
        ])->save();

        return $customer;
    }

    /**
     * Generate a self service billing portal link for the customer.
     */
    public function billingPortalUrl(?string $returnUrl = null, bool $sendEmail = false): string
    {
        $query = array_filter([
            'send_email' => $sendEmail ?: null,
            'return_url' => $returnUrl ?: config('cashier-dodo.return_url'),
        ], fn ($value) => ! is_null($value));

        return Dodo::customers()->portalSession($this->dodoIdOrFail(), $query)['link'];
    }

    /**
     * List the payment methods the customer has saved with Dodo Payments.
     *
     * @return array<string, mixed>
     */
    public function paymentMethods(): array
    {
        return Dodo::customers()->paymentMethods($this->dodoIdOrFail());
    }

    /**
     * Get the Dodo customer ID, failing loudly when the customer has not been created yet.
     *
     * @throws InvalidCustomer
     */
    public function dodoIdOrFail(): string
    {
        if (! $this->dodo_id) {
            throw InvalidCustomer::notYetCreated($this);
        }

        return $this->dodo_id;
    }

    /**
     * Get the Dodo customer ID.
     */
    public function dodoId(): ?string
    {
        return $this->dodo_id;
    }
}
