<?php

namespace Ahmadnajmdev\Cashier\Dodo\Enums;

enum ProrationBillingMode: string
{
    /**
     * Charge the prorated amount for the remainder of the current billing period.
     */
    case ProratedImmediately = 'prorated_immediately';

    /**
     * Charge the full price of the new plan straight away.
     */
    case FullImmediately = 'full_immediately';

    /**
     * Charge only the difference between the old and the new plan.
     */
    case DifferenceImmediately = 'difference_immediately';

    /**
     * Change the plan without charging anything until the next renewal.
     */
    case DoNotBill = 'do_not_bill';
}
