<?php

namespace Ahmadnajmdev\Cashier\Dodo\Concerns;

use Ahmadnajmdev\Cashier\Dodo\Cashier;
use Ahmadnajmdev\Cashier\Dodo\Customer;
use Ahmadnajmdev\Cashier\Dodo\Exceptions\CustomerAlreadyCreated;
use Ahmadnajmdev\Cashier\Dodo\Exceptions\InvalidCustomer;
use Ahmadnajmdev\Cashier\Dodo\Facades\Dodo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

trait ManagesCustomer
{
    /**
     * Get the Dodo customer record attached to this model.
     *
     * @return MorphOne<Customer, $this>
     */
    public function customer(): MorphOne
    {
        return $this->morphOne(Cashier::$customerModel, 'billable');
    }

    /**
     * Determine whether the model has been registered with Dodo Payments.
     */
    public function hasDodoId(): bool
    {
        return ! is_null($this->customer?->dodo_id);
    }

    /**
     * Get the Dodo customer ID for this model.
     */
    public function dodoId(): ?string
    {
        return $this->customer?->dodo_id;
    }

    /**
     * Get the name sent to Dodo Payments when the customer is created.
     */
    public function dodoName(): ?string
    {
        return $this->name ?? null;
    }

    /**
     * Get the email sent to Dodo Payments when the customer is created.
     */
    public function dodoEmail(): ?string
    {
        return $this->email ?? null;
    }

    /**
     * Get the phone number sent to Dodo Payments when the customer is created.
     */
    public function dodoPhone(): ?string
    {
        return $this->phone ?? null;
    }

    /**
     * Register this model as a customer with Dodo Payments.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws CustomerAlreadyCreated
     */
    public function createAsCustomer(array $attributes = []): Customer
    {
        if ($this->hasDodoId()) {
            throw CustomerAlreadyCreated::exists($this);
        }

        $payload = array_filter(array_merge([
            'email' => $this->dodoEmail(),
            'name' => $this->dodoName(),
            'phone_number' => $this->dodoPhone(),
        ], $attributes), fn ($value) => ! is_null($value));

        $response = Dodo::customers()->create($payload);

        $customer = $this->customer ?: $this->customer()->make();

        $customer->forceFill([
            'dodo_id' => $response['customer_id'],
            'name' => $response['name'] ?? $payload['name'] ?? null,
            'email' => $response['email'] ?? $payload['email'] ?? null,
        ]);

        $this->customer()->save($customer);

        $this->unsetRelation('customer');

        return $customer;
    }

    /**
     * Get the Dodo customer record, creating it if this is the first time.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createOrGetCustomer(array $attributes = []): Customer
    {
        if ($this->hasDodoId()) {
            return $this->customer;
        }

        return $this->createAsCustomer($attributes);
    }

    /**
     * Give the model a trial that lives only in your own database.
     */
    public function trialUntil(\DateTimeInterface $date): Customer
    {
        $customer = $this->customer ?: $this->customer()->make();

        $customer->forceFill(['trial_ends_at' => $date]);

        $this->customer()->save($customer);

        $this->unsetRelation('customer');

        return $customer;
    }

    /**
     * Determine whether the model is on a trial that has no subscription behind it.
     */
    public function onGenericTrial(): bool
    {
        return $this->customer?->onGenericTrial() ?? false;
    }

    /**
     * Get the date the generic trial ends.
     */
    public function trialEndsAt(): ?Carbon
    {
        return $this->customer?->trial_ends_at;
    }

    /**
     * Get a self service billing portal link for this model.
     *
     * @throws InvalidCustomer
     */
    public function billingPortalUrl(?string $returnUrl = null, bool $sendEmail = false): string
    {
        $this->assertCustomerExists();

        return $this->customer->billingPortalUrl($returnUrl, $sendEmail);
    }

    /**
     * Send the model straight to their billing portal.
     *
     * @throws InvalidCustomer
     */
    public function redirectToBillingPortal(?string $returnUrl = null): RedirectResponse
    {
        return new RedirectResponse($this->billingPortalUrl($returnUrl));
    }

    /**
     * List the payment methods this model has saved with Dodo Payments.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidCustomer
     */
    public function paymentMethods(): array
    {
        $this->assertCustomerExists();

        return $this->customer->paymentMethods();
    }

    /**
     * Fetch this model's customer record from Dodo Payments.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidCustomer
     */
    public function asDodoCustomer(): array
    {
        $this->assertCustomerExists();

        return $this->customer->asDodoCustomer();
    }

    /**
     * Make sure the model is a Dodo customer before talking to the API about it.
     *
     * @throws InvalidCustomer
     */
    protected function assertCustomerExists(): void
    {
        if (! $this->hasDodoId()) {
            throw InvalidCustomer::notYetCreated($this);
        }
    }
}
