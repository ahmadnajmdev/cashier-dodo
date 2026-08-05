<?php

namespace Ahmadnajmdev\Cashier\Dodo\Concerns;

use Ahmadnajmdev\Cashier\Dodo\Cashier;
use Ahmadnajmdev\Cashier\Dodo\Transaction;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait ManagesTransactions
{
    /**
     * Get all of the transactions billed to this model.
     *
     * @return MorphMany<Transaction, $this>
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Cashier::$transactionModel, 'billable')->orderByDesc('billed_at');
    }

    /**
     * Determine whether the model has ever paid successfully.
     */
    public function hasPaidTransactions(): bool
    {
        return $this->transactions()->paid()->exists();
    }

    /**
     * Get the most recent successful transaction.
     */
    public function lastPayment(): ?Transaction
    {
        return $this->transactions()->paid()->first();
    }
}
