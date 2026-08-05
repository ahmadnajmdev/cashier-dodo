<?php

namespace Ahmadnajmdev\Cashier\Dodo\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Expired = 'expired';

    /**
     * Determine whether the subscription still entitles the customer to the product.
     */
    public function grantsAccess(): bool
    {
        return in_array($this, [self::Active, self::OnHold], true);
    }

    /**
     * Determine whether the subscription has reached a state it cannot recover from.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Failed, self::Expired], true);
    }

    /**
     * Determine whether the subscription may still be modified through the API.
     */
    public function isModifiable(): bool
    {
        return ! $this->isTerminal();
    }
}
