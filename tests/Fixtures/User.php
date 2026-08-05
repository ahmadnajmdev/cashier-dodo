<?php

namespace Ahmadnajmdev\Cashier\Dodo\Tests\Fixtures;

use Ahmadnajmdev\Cashier\Dodo\Billable;
use Ahmadnajmdev\Cashier\Dodo\Contracts\BillableContract;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements BillableContract
{
    use Billable;

    protected $table = 'users';

    protected $guarded = [];
}
