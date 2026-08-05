<?php

namespace Ahmadnajmdev\Cashier\Dodo;

use Ahmadnajmdev\Cashier\Dodo\Enums\PaymentStatus;
use Ahmadnajmdev\Cashier\Dodo\Facades\Dodo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $dodo_id
 * @property string|null $dodo_customer_id
 * @property string|null $dodo_subscription_id
 * @property string $status
 * @property int $total
 * @property int|null $tax
 * @property string $currency
 * @property string|null $refund_status
 * @property string|null $payment_method
 * @property string|null $card_last_four
 * @property string|null $card_network
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $billed_at
 */
class Transaction extends Model
{
    protected $table = 'dodo_transactions';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'tax' => 'integer',
            'metadata' => 'array',
            'billed_at' => 'datetime',
        ];
    }

    /**
     * Get the model that was billed.
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the subscription this transaction was billed against, if any.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Cashier::$subscriptionModel, 'dodo_subscription_id', 'dodo_id');
    }

    /**
     * Get the status as a typed enum, or null when Dodo sends a status we do not know about yet.
     */
    public function statusEnum(): ?PaymentStatus
    {
        return PaymentStatus::tryFrom($this->status);
    }

    /**
     * Determine whether the money actually arrived.
     */
    public function paid(): bool
    {
        return $this->status === PaymentStatus::Succeeded->value;
    }

    /**
     * Determine whether the payment failed.
     */
    public function failed(): bool
    {
        return $this->status === PaymentStatus::Failed->value;
    }

    /**
     * Determine whether the payment is still in flight.
     */
    public function pending(): bool
    {
        return $this->statusEnum()?->isOpen() ?? false;
    }

    /**
     * Determine whether any money has been refunded.
     */
    public function refunded(): bool
    {
        return ! is_null($this->refund_status);
    }

    /**
     * Determine whether the whole transaction has been refunded.
     */
    public function fullyRefunded(): bool
    {
        return $this->refund_status === 'full';
    }

    /**
     * Determine whether the transaction belongs to a subscription.
     */
    public function hasSubscription(): bool
    {
        return ! is_null($this->dodo_subscription_id);
    }

    /**
     * Get the total, formatted for display.
     */
    public function total(): string
    {
        return Cashier::formatAmount($this->total, $this->currency);
    }

    /**
     * Get the tax, formatted for display.
     */
    public function tax(): string
    {
        return Cashier::formatAmount($this->tax ?? 0, $this->currency);
    }

    /**
     * Refund this transaction, in full or in part.
     *
     * @param  array<int, array<string, mixed>>|null  $items
     * @return array<string, mixed>
     */
    public function refund(?string $reason = null, ?array $items = null): array
    {
        return Dodo::refunds()->create(array_filter([
            'payment_id' => $this->dodo_id,
            'reason' => $reason,
            'items' => $items,
        ], fn ($value) => ! is_null($value)));
    }

    /**
     * Get the URL of the invoice PDF for this transaction.
     */
    public function invoiceUrl(): string
    {
        return Dodo::payments()->invoiceUrl($this->dodo_id);
    }

    /**
     * Fetch the payment as it currently exists in Dodo Payments.
     *
     * @return array<string, mixed>
     */
    public function asDodoPayment(): array
    {
        return Dodo::payments()->find($this->dodo_id);
    }

    /**
     * Fill the model from a Dodo payment payload without saving it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fillFromDodo(array $payload): static
    {
        return $this->forceFill(static::mapFromDodo($payload));
    }

    /**
     * Translate a Dodo payment payload into this model's attributes.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mapFromDodo(array $payload): array
    {
        return array_merge(array_filter([
            'dodo_id' => $payload['payment_id'] ?? null,
            'dodo_customer_id' => $payload['customer']['customer_id'] ?? null,
            'status' => $payload['status'] ?? PaymentStatus::Processing->value,
            'total' => $payload['total_amount'] ?? 0,
            'currency' => $payload['currency'] ?? config('cashier-dodo.currency', 'USD'),
            'metadata' => $payload['metadata'] ?? null,
        ], fn ($value) => ! is_null($value)), [
            'dodo_subscription_id' => $payload['subscription_id'] ?? null,
            'tax' => $payload['tax'] ?? null,
            'refund_status' => $payload['refund_status'] ?? null,
            'payment_method' => $payload['payment_method'] ?? null,
            'card_last_four' => $payload['card_last_four'] ?? null,
            'card_network' => $payload['card_network'] ?? null,
            'billed_at' => isset($payload['created_at']) ? Carbon::parse($payload['created_at']) : null,
        ]);
    }

    /**
     * Scope the query to transactions where the money arrived.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Succeeded->value);
    }

    /**
     * Scope the query to refunded transactions.
     */
    public function scopeRefunded(Builder $query): Builder
    {
        return $query->whereNotNull('refund_status');
    }
}
