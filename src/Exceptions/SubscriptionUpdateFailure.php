<?php

namespace Ahmadnajmdev\Cashier\Dodo\Exceptions;

use Ahmadnajmdev\Cashier\Dodo\Subscription;
use Exception;

class SubscriptionUpdateFailure extends Exception
{
    /**
     * Create an exception for a subscription that is no longer modifiable.
     */
    public static function cannotBeModified(Subscription $subscription): self
    {
        return new self(
            "The subscription \"{$subscription->dodo_id}\" cannot be modified because its status is \"{$subscription->status}\"."
        );
    }

    /**
     * Create an exception for an on-demand subscription that was sent through the plan change flow.
     */
    public static function onDemandCannotSwap(Subscription $subscription): self
    {
        return new self(
            "The subscription \"{$subscription->dodo_id}\" is an on-demand subscription and does not support plan changes."
        );
    }

    /**
     * Create an exception for a subscription that already has a scheduled plan change.
     */
    public static function planChangeAlreadyScheduled(Subscription $subscription): self
    {
        return new self(
            "The subscription \"{$subscription->dodo_id}\" already has a scheduled plan change. Cancel it before scheduling another."
        );
    }
}
