<?php

namespace Ahmadnajmdev\Cashier\Dodo;

use Ahmadnajmdev\Cashier\Dodo\Concerns\ManagesCustomer;
use Ahmadnajmdev\Cashier\Dodo\Concerns\ManagesSubscriptions;
use Ahmadnajmdev\Cashier\Dodo\Concerns\ManagesTransactions;
use Ahmadnajmdev\Cashier\Dodo\Concerns\PerformsCharges;

trait Billable
{
    use ManagesCustomer;
    use ManagesSubscriptions;
    use ManagesTransactions;
    use PerformsCharges;
}
