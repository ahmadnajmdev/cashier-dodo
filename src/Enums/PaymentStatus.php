<?php

namespace Ahmadnajmdev\Cashier\Dodo\Enums;

enum PaymentStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Processing = 'processing';
    case RequiresCustomerAction = 'requires_customer_action';
    case RequiresMerchantAction = 'requires_merchant_action';
    case RequiresPaymentMethod = 'requires_payment_method';
    case RequiresConfirmation = 'requires_confirmation';
    case RequiresCapture = 'requires_capture';
    case PartiallyCaptured = 'partially_captured';
    case PartiallyCapturedAndCapturable = 'partially_captured_and_capturable';

    /**
     * Determine whether the money has actually arrived.
     */
    public function isPaid(): bool
    {
        return $this === self::Succeeded;
    }

    /**
     * Determine whether the payment is still waiting on someone or something.
     */
    public function isOpen(): bool
    {
        return in_array($this, [
            self::Processing,
            self::RequiresCustomerAction,
            self::RequiresMerchantAction,
            self::RequiresPaymentMethod,
            self::RequiresConfirmation,
            self::RequiresCapture,
        ], true);
    }
}
