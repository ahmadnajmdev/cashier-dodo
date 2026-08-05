<?php

namespace Ahmadnajmdev\Cashier\Dodo\Exceptions;

use Exception;

class InvalidCustomer extends Exception
{
    /**
     * Create an exception for a billable model that has no Dodo customer yet.
     */
    public static function notYetCreated(object $owner): self
    {
        return new self(class_basename($owner).' is not a Dodo Payments customer yet. See the createAsCustomer method.');
    }
}
