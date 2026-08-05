<?php

namespace Ahmadnajmdev\Cashier\Dodo\Enums;

enum WebhookEvent: string
{
    case PaymentSucceeded = 'payment.succeeded';
    case PaymentFailed = 'payment.failed';
    case PaymentProcessing = 'payment.processing';
    case PaymentCancelled = 'payment.cancelled';

    case RefundSucceeded = 'refund.succeeded';
    case RefundFailed = 'refund.failed';

    case DisputeOpened = 'dispute.opened';
    case DisputeExpired = 'dispute.expired';
    case DisputeAccepted = 'dispute.accepted';
    case DisputeCancelled = 'dispute.cancelled';
    case DisputeChallenged = 'dispute.challenged';
    case DisputeWon = 'dispute.won';
    case DisputeLost = 'dispute.lost';

    case SubscriptionActive = 'subscription.active';
    case SubscriptionUpdated = 'subscription.updated';
    case SubscriptionRenewed = 'subscription.renewed';
    case SubscriptionOnHold = 'subscription.on_hold';
    case SubscriptionPaused = 'subscription.paused';
    case SubscriptionPlanChanged = 'subscription.plan_changed';
    case SubscriptionCancelled = 'subscription.cancelled';
    case SubscriptionFailed = 'subscription.failed';
    case SubscriptionExpired = 'subscription.expired';

    case LicenseKeyCreated = 'license_key.created';

    case EntitlementGrantCreated = 'entitlement_grant.created';
    case EntitlementGrantDelivered = 'entitlement_grant.delivered';
    case EntitlementGrantFailed = 'entitlement_grant.failed';
    case EntitlementGrantRevoked = 'entitlement_grant.revoked';

    case CreditAdded = 'credit.added';
    case CreditDeducted = 'credit.deducted';
    case CreditExpired = 'credit.expired';
    case CreditRolledOver = 'credit.rolled_over';
    case CreditRolloverForfeited = 'credit.rollover_forfeited';
    case CreditOverageCharged = 'credit.overage_charged';
    case CreditOverageReset = 'credit.overage_reset';
    case CreditManualAdjustment = 'credit.manual_adjustment';
    case CreditBalanceLow = 'credit.balance_low';

    case AbandonedCheckoutDetected = 'abandoned_checkout.detected';
    case AbandonedCheckoutRecovered = 'abandoned_checkout.recovered';

    case DunningStarted = 'dunning.started';
    case DunningRecovered = 'dunning.recovered';

    case PayoutNotInitiated = 'payout.not_initiated';
    case PayoutCreated = 'payout.created';
    case PayoutInProgress = 'payout.in_progress';
    case PayoutOnHold = 'payout.on_hold';
    case PayoutSuccess = 'payout.success';
    case PayoutFailed = 'payout.failed';

    /**
     * Get every event type as its raw string value.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $event) => $event->value, self::cases());
    }

    /**
     * Get the events Cashier needs in order to keep its own tables in sync.
     *
     * @return array<int, string>
     */
    public static function cashierEvents(): array
    {
        return [
            self::PaymentSucceeded->value,
            self::PaymentFailed->value,
            self::PaymentProcessing->value,
            self::PaymentCancelled->value,
            self::RefundSucceeded->value,
            self::SubscriptionActive->value,
            self::SubscriptionUpdated->value,
            self::SubscriptionRenewed->value,
            self::SubscriptionOnHold->value,
            self::SubscriptionPaused->value,
            self::SubscriptionPlanChanged->value,
            self::SubscriptionCancelled->value,
            self::SubscriptionFailed->value,
            self::SubscriptionExpired->value,
        ];
    }

    /**
     * Get the resource family this event belongs to.
     */
    public function group(): string
    {
        return explode('.', $this->value)[0];
    }
}
