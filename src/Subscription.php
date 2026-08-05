<?php

namespace Ahmadnajmdev\Cashier\Dodo;

use Ahmadnajmdev\Cashier\Dodo\Enums\PlanChangeSchedule;
use Ahmadnajmdev\Cashier\Dodo\Enums\ProrationBillingMode;
use Ahmadnajmdev\Cashier\Dodo\Enums\SubscriptionStatus;
use Ahmadnajmdev\Cashier\Dodo\Exceptions\SubscriptionUpdateFailure;
use Ahmadnajmdev\Cashier\Dodo\Facades\Dodo;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $type
 * @property string $dodo_id
 * @property string|null $dodo_customer_id
 * @property string $status
 * @property string $product_id
 * @property int $quantity
 * @property string|null $currency
 * @property int|null $recurring_amount
 * @property bool $tax_inclusive
 * @property string|null $payment_frequency_interval
 * @property int|null $payment_frequency_count
 * @property array<int, array<string, mixed>>|null $addons
 * @property array<string, mixed>|null $metadata
 * @property bool $on_demand
 * @property bool $cancel_at_next_billing_date
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $previous_billing_at
 * @property Carbon|null $next_billing_at
 * @property Carbon|null $paused_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $ends_at
 */
class Subscription extends Model
{
    /**
     * The subscription type used when none is given.
     */
    public const DEFAULT_TYPE = 'default';

    protected $table = 'dodo_subscriptions';

    protected $guarded = [];

    /**
     * How the next plan change should be billed.
     */
    protected ProrationBillingMode $prorationBehavior = ProrationBillingMode::ProratedImmediately;

    /**
     * When the next plan change should take effect.
     */
    protected PlanChangeSchedule $planChangeSchedule = PlanChangeSchedule::Immediately;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'recurring_amount' => 'integer',
            'payment_frequency_count' => 'integer',
            'tax_inclusive' => 'boolean',
            'on_demand' => 'boolean',
            'cancel_at_next_billing_date' => 'boolean',
            'addons' => 'array',
            'metadata' => 'array',
            'trial_ends_at' => 'datetime',
            'previous_billing_at' => 'datetime',
            'next_billing_at' => 'datetime',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Get the model that owns the subscription.
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the transactions billed against this subscription.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Cashier::$transactionModel, 'dodo_subscription_id', 'dodo_id')->orderByDesc('billed_at');
    }

    /**
     * Get the status as a typed enum, or null when Dodo sends a status we do not know about yet.
     */
    public function statusEnum(): ?SubscriptionStatus
    {
        return SubscriptionStatus::tryFrom($this->status);
    }

    /**
     * Determine whether the subscription entitles the customer to the product right now.
     */
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onHold() || $this->onGracePeriod();
    }

    /**
     * Determine whether the subscription is active and being billed normally.
     */
    public function active(): bool
    {
        return $this->status === SubscriptionStatus::Active->value && ! $this->ended();
    }

    /**
     * Determine whether the subscription has not been activated yet.
     */
    public function pending(): bool
    {
        return $this->status === SubscriptionStatus::Pending->value;
    }

    /**
     * Determine whether the subscription is within its trial period.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at?->isFuture() ?? false;
    }

    /**
     * Determine whether a payment failed and Dodo has put the subscription on hold.
     */
    public function onHold(): bool
    {
        return $this->status === SubscriptionStatus::OnHold->value;
    }

    /**
     * Determine whether the subscription is paused.
     */
    public function paused(): bool
    {
        return $this->status === SubscriptionStatus::Paused->value;
    }

    /**
     * Determine whether the subscription has been cancelled.
     */
    public function cancelled(): bool
    {
        return $this->status === SubscriptionStatus::Cancelled->value || $this->cancel_at_next_billing_date;
    }

    /**
     * Determine whether the subscription has been cancelled.
     */
    public function canceled(): bool
    {
        return $this->cancelled();
    }

    /**
     * Determine whether the subscription expired at the end of its term.
     */
    public function expired(): bool
    {
        return $this->status === SubscriptionStatus::Expired->value;
    }

    /**
     * Determine whether the subscription failed outright.
     */
    public function failed(): bool
    {
        return $this->status === SubscriptionStatus::Failed->value;
    }

    /**
     * Determine whether the subscription is cancelled but still running until the period ends.
     */
    public function onGracePeriod(): bool
    {
        return $this->cancelled() && ($this->ends_at?->isFuture() ?? false);
    }

    /**
     * Determine whether the subscription has fully run out.
     */
    public function ended(): bool
    {
        return ($this->cancelled() && ! $this->onGracePeriod()) || $this->expired();
    }

    /**
     * Determine whether the subscription will keep renewing.
     */
    public function recurring(): bool
    {
        return ! $this->onTrial() && ! $this->cancelled() && ! $this->expired() && ! $this->paused();
    }

    /**
     * Determine whether the subscription is billed on demand rather than on a schedule.
     */
    public function onDemand(): bool
    {
        return (bool) $this->on_demand;
    }

    /**
     * Determine whether the subscription is for the given product.
     */
    public function hasProduct(string $productId): bool
    {
        return $this->product_id === $productId;
    }

    /**
     * Determine whether the given addon is attached to the subscription.
     */
    public function hasAddon(string $addonId): bool
    {
        foreach ($this->addons ?? [] as $addon) {
            if (($addon['addon_id'] ?? null) === $addonId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bill the remainder of the current period at a prorated rate when the plan changes.
     */
    public function prorate(): static
    {
        $this->prorationBehavior = ProrationBillingMode::ProratedImmediately;

        return $this;
    }

    /**
     * Charge the full price of the new plan when it changes.
     */
    public function chargeFullPrice(): static
    {
        $this->prorationBehavior = ProrationBillingMode::FullImmediately;

        return $this;
    }

    /**
     * Charge only the difference between the plans when the plan changes.
     */
    public function chargeDifference(): static
    {
        $this->prorationBehavior = ProrationBillingMode::DifferenceImmediately;

        return $this;
    }

    /**
     * Change the plan without charging anything until the next renewal.
     */
    public function noProrate(): static
    {
        $this->prorationBehavior = ProrationBillingMode::DoNotBill;

        return $this;
    }

    /**
     * Hold the next plan change until the current period ends.
     */
    public function scheduleForNextBillingDate(): static
    {
        $this->planChangeSchedule = PlanChangeSchedule::NextBillingDate;

        return $this;
    }

    /**
     * Move the subscription onto another product.
     *
     * @param  array<string, mixed>  $options
     */
    public function swap(string $productId, array $options = []): static
    {
        $this->guardAgainstPlanChanges();

        Dodo::subscriptions()->changePlan($this->dodo_id, $this->planChangePayload($productId, $options));

        return $this->syncDodoStatus();
    }

    /**
     * Preview what moving the subscription onto another product would cost.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function previewSwap(string $productId, array $options = []): array
    {
        $this->guardAgainstPlanChanges();

        return Dodo::subscriptions()->previewChangePlan($this->dodo_id, $this->planChangePayload($productId, $options));
    }

    /**
     * Change how many units of the product the subscription covers.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateQuantity(int $quantity, array $options = []): static
    {
        return $this->swap($this->product_id, array_merge($options, ['quantity' => $quantity]));
    }

    /**
     * Add units to the subscription.
     */
    public function incrementQuantity(int $count = 1): static
    {
        return $this->updateQuantity($this->quantity + $count);
    }

    /**
     * Remove units from the subscription.
     */
    public function decrementQuantity(int $count = 1): static
    {
        return $this->updateQuantity(max(0, $this->quantity - $count));
    }

    /**
     * Cancel a plan change that was scheduled for the next billing date.
     */
    public function cancelScheduledPlanChange(): static
    {
        Dodo::subscriptions()->cancelScheduledChangePlan($this->dodo_id);

        return $this->syncDodoStatus();
    }

    /**
     * Cancel the subscription at the end of the current billing period.
     */
    public function cancel(): static
    {
        $this->guardAgainstUpdates();

        $subscription = Dodo::subscriptions()->update($this->dodo_id, [
            'cancel_at_next_billing_date' => true,
        ]);

        $this->fillFromDodo($subscription)->save();

        return $this;
    }

    /**
     * Cancel the subscription immediately, ending access straight away.
     */
    public function cancelNow(): static
    {
        $subscription = Dodo::subscriptions()->update($this->dodo_id, [
            'status' => SubscriptionStatus::Cancelled->value,
        ]);

        $this->fillFromDodo($subscription);
        $this->forceFill(['ends_at' => $this->freshTimestamp()])->save();

        return $this;
    }

    /**
     * Undo a pending cancellation and keep the subscription running.
     */
    public function stopCancelation(): static
    {
        $subscription = Dodo::subscriptions()->update($this->dodo_id, [
            'cancel_at_next_billing_date' => false,
        ]);

        $this->fillFromDodo($subscription);
        $this->forceFill(['ends_at' => null, 'cancelled_at' => null])->save();

        return $this;
    }

    /**
     * Undo a pending cancellation and keep the subscription running.
     */
    public function resume(): static
    {
        return $this->stopCancelation();
    }

    /**
     * Push the next billing date out, effectively extending the current period.
     */
    public function extendTo(CarbonInterface $date): static
    {
        $subscription = Dodo::subscriptions()->update($this->dodo_id, [
            'next_billing_date' => $date->toIso8601String(),
        ]);

        $this->fillFromDodo($subscription)->save();

        return $this;
    }

    /**
     * Charge an on-demand subscription for a one off amount.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function charge(int $amount, array $options = []): array
    {
        return Dodo::subscriptions()->charge($this->dodo_id, array_merge([
            'product_price' => $amount,
        ], $options));
    }

    /**
     * Get a link the customer can use to replace the card behind this subscription.
     *
     * @param  array<string, mixed>  $options
     */
    public function updatePaymentMethodUrl(?string $returnUrl = null, array $options = []): ?string
    {
        $response = Dodo::subscriptions()->updatePaymentMethod($this->dodo_id, array_merge([
            'type' => 'new',
            'return_url' => $returnUrl ?: config('cashier-dodo.return_url'),
        ], $options));

        return $response['payment_link'] ?? null;
    }

    /**
     * Point the subscription at a payment method the customer has already saved.
     *
     * @return array<string, mixed>
     */
    public function useSavedPaymentMethod(string $paymentMethodId): array
    {
        return Dodo::subscriptions()->updatePaymentMethod($this->dodo_id, [
            'type' => 'existing',
            'payment_method_id' => $paymentMethodId,
        ]);
    }

    /**
     * Retrieve the metered usage recorded against this subscription.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function usageHistory(array $query = []): array
    {
        return Dodo::subscriptions()->usageHistory($this->dodo_id, $query);
    }

    /**
     * Fetch the subscription as it currently exists in Dodo Payments.
     *
     * @return array<string, mixed>
     */
    public function asDodoSubscription(): array
    {
        return Dodo::subscriptions()->find($this->dodo_id);
    }

    /**
     * Pull the latest state from Dodo Payments into the local record.
     */
    public function syncDodoStatus(): static
    {
        $this->fillFromDodo($this->asDodoSubscription())->save();

        return $this;
    }

    /**
     * Fill the model from a Dodo subscription payload without saving it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fillFromDodo(array $payload): static
    {
        return $this->forceFill(static::mapFromDodo($payload));
    }

    /**
     * Translate a Dodo subscription payload into this model's attributes.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mapFromDodo(array $payload): array
    {
        $createdAt = isset($payload['created_at']) ? Carbon::parse($payload['created_at']) : null;
        $trialDays = (int) ($payload['trial_period_days'] ?? 0);
        $nextBillingAt = isset($payload['next_billing_date']) ? Carbon::parse($payload['next_billing_date']) : null;
        $cancelAtNextBillingDate = (bool) ($payload['cancel_at_next_billing_date'] ?? false);

        $endsAt = isset($payload['expires_at']) ? Carbon::parse($payload['expires_at']) : null;

        if (is_null($endsAt) && $cancelAtNextBillingDate) {
            $endsAt = $nextBillingAt;
        }

        $attributes = array_filter([
            'dodo_id' => $payload['subscription_id'] ?? null,
            'dodo_customer_id' => $payload['customer']['customer_id'] ?? null,
            'status' => $payload['status'] ?? null,
            'product_id' => $payload['product_id'] ?? null,
            'quantity' => $payload['quantity'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'recurring_amount' => $payload['recurring_pre_tax_amount'] ?? null,
            'tax_inclusive' => $payload['tax_inclusive'] ?? null,
            'payment_frequency_interval' => $payload['payment_frequency_interval'] ?? null,
            'payment_frequency_count' => $payload['payment_frequency_count'] ?? null,
            'addons' => $payload['addons'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
            'on_demand' => $payload['on_demand'] ?? null,
        ], fn ($value) => ! is_null($value));

        // These may legitimately become null again — a resumed subscription clears
        // its end date — so they are always written rather than filtered away.
        return array_merge($attributes, [
            'cancel_at_next_billing_date' => $cancelAtNextBillingDate,
            'trial_ends_at' => $trialDays > 0 && $createdAt ? $createdAt->copy()->addDays($trialDays) : null,
            'previous_billing_at' => isset($payload['previous_billing_date']) ? Carbon::parse($payload['previous_billing_date']) : null,
            'next_billing_at' => $nextBillingAt,
            'cancelled_at' => isset($payload['cancelled_at']) ? Carbon::parse($payload['cancelled_at']) : null,
            'ends_at' => $endsAt,
        ]);
    }

    /**
     * Build the payload sent to the change plan endpoint.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function planChangePayload(string $productId, array $options): array
    {
        return array_merge([
            'product_id' => $productId,
            'quantity' => $this->quantity,
            'proration_billing_mode' => $this->prorationBehavior->value,
            'effective_at' => $this->planChangeSchedule->value,
        ], $options);
    }

    /**
     * Make sure the subscription is in a state Dodo will accept updates for.
     *
     * @throws SubscriptionUpdateFailure
     */
    protected function guardAgainstUpdates(): void
    {
        if ($this->ended() || $this->expired() || $this->failed()) {
            throw SubscriptionUpdateFailure::cannotBeModified($this);
        }
    }

    /**
     * Make sure the subscription is one Dodo will accept a plan change for.
     *
     * @throws SubscriptionUpdateFailure
     */
    protected function guardAgainstPlanChanges(): void
    {
        $this->guardAgainstUpdates();

        if ($this->onDemand()) {
            throw SubscriptionUpdateFailure::onDemandCannotSwap($this);
        }
    }

    /**
     * Scope the query to subscriptions of a given type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope the query to subscriptions that currently grant access.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::OnHold->value,
            ])->orWhere('trial_ends_at', '>', Carbon::now())
                ->orWhere('ends_at', '>', Carbon::now());
        });
    }

    /**
     * Scope the query to active subscriptions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Active->value);
    }

    /**
     * Scope the query to subscriptions on trial.
     */
    public function scopeOnTrial(Builder $query): Builder
    {
        return $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Scope the query to subscriptions Dodo has put on hold.
     */
    public function scopeOnHold(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::OnHold->value);
    }

    /**
     * Scope the query to cancelled subscriptions.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Cancelled->value);
    }

    /**
     * Scope the query to subscriptions that are cancelled but still running.
     */
    public function scopeOnGracePeriod(Builder $query): Builder
    {
        return $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * Scope the query to subscriptions that have fully run out.
     */
    public function scopeEnded(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SubscriptionStatus::Cancelled->value,
            SubscriptionStatus::Expired->value,
            SubscriptionStatus::Failed->value,
        ])->where(function (Builder $query) {
            $query->whereNull('ends_at')->orWhere('ends_at', '<=', Carbon::now());
        });
    }
}
