<?php

namespace Ahmadnajmdev\Cashier\Dodo\Exceptions;

use Exception;

class CustomerAlreadyCreated extends Exception
{
    /**
     * Create an exception for a billable model that already has a Dodo customer.
     */
    public static function exists(object $owner): self
    {
        return new self(class_basename($owner).' is already a Dodo Payments customer with ID '.$owner->dodoId().'.');
    }
}
