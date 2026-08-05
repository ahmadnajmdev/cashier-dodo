<?php

namespace Ahmadnajmdev\Cashier\Dodo\Enums;

enum PlanChangeSchedule: string
{
    /**
     * Apply the plan change as soon as the request is accepted.
     */
    case Immediately = 'immediately';

    /**
     * Hold the plan change until the current billing period ends.
     */
    case NextBillingDate = 'next_billing_date';
}
