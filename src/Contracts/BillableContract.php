<?php

namespace Ahmadnajmdev\Cashier\Dodo\Contracts;

use Ahmadnajmdev\Cashier\Dodo\Customer;

/**
 * Implemented for you by the Billable trait. Declare it on your model so the
 * rest of the package, and static analysis, know the model can be billed.
 */
interface BillableContract
{
    /**
     * Get the Dodo customer ID for this model, if it has one.
     */
    public function dodoId(): ?string;

    /**
     * Determine whether the model has been registered with Dodo Payments.
     */
    public function hasDodoId(): bool;

    /**
     * Get the Dodo customer record, creating it if this is the first time.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createOrGetCustomer(array $attributes = []): Customer;
}
