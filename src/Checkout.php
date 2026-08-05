<?php

namespace Ahmadnajmdev\Cashier\Dodo;

use Ahmadnajmdev\Cashier\Dodo\Contracts\BillableContract;
use Ahmadnajmdev\Cashier\Dodo\Facades\Dodo;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

/**
 * A fluent builder for Dodo Payments hosted checkout sessions.
 *
 * @phpstan-consistent-constructor
 */
class Checkout implements Responsable
{
    /**
     * The items being purchased.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $products = [];

    /**
     * Everything else that gets sent to the checkout session endpoint.
     *
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * The billable model the checkout belongs to, when there is one.
     */
    protected ?Model $billable = null;

    /**
     * Start a checkout for a guest, someone who is not signed in.
     */
    public static function guest(): static
    {
        return new static;
    }

    /**
     * Start a checkout for a billable model.
     *
     * @param  Model&BillableContract  $billable
     */
    public static function customer(Model $billable): static
    {
        $checkout = new static;
        $checkout->billable = $billable;

        $customer = $billable->createOrGetCustomer();

        if ($customer->dodo_id) {
            $checkout->options['customer'] = ['customer_id' => $customer->dodo_id];
        }

        return $checkout;
    }

    /**
     * Buy on behalf of a customer that already exists in Dodo Payments.
     */
    public static function forDodoCustomer(string $customerId): static
    {
        $checkout = new static;
        $checkout->options['customer'] = ['customer_id' => $customerId];

        return $checkout;
    }

    /**
     * Add the products being purchased.
     *
     * Accepts a product ID, a list of product IDs, a map of product ID to quantity,
     * or a list of fully formed cart items.
     *
     * @param  string|array<int|string, mixed>  $products
     */
    public function withProducts(string|array $products, int $quantity = 1): static
    {
        foreach (static::normalizeProducts($products, $quantity) as $item) {
            $this->products[] = $item;
        }

        return $this;
    }

    /**
     * Charge a custom amount for a product that has pay what you want enabled.
     */
    public function withCustomAmount(string $productId, int $amount, int $quantity = 1): static
    {
        $this->products[] = [
            'product_id' => $productId,
            'quantity' => $quantity,
            'amount' => $amount,
        ];

        return $this;
    }

    /**
     * Attach addons to the subscription being purchased.
     *
     * @param  array<int|string, mixed>  $addons
     */
    public function withAddons(array $addons): static
    {
        if ($this->products === []) {
            throw new \LogicException('Add a product before attaching addons to the checkout.');
        }

        $normalized = [];

        foreach ($addons as $key => $value) {
            $normalized[] = is_array($value)
                ? $value
                : (is_string($key) ? ['addon_id' => $key, 'quantity' => (int) $value] : ['addon_id' => $value, 'quantity' => 1]);
        }

        $last = array_key_last($this->products);
        $this->products[$last]['addons'] = array_merge($this->products[$last]['addons'] ?? [], $normalized);

        return $this;
    }

    /**
     * Describe the customer buying when they do not exist in Dodo Payments yet.
     */
    public function withCustomer(string $email, ?string $name = null, ?string $phoneNumber = null): static
    {
        $this->options['customer'] = array_filter([
            'email' => $email,
            'name' => $name,
            'phone_number' => $phoneNumber,
        ], fn ($value) => ! is_null($value));

        return $this;
    }

    /**
     * Prefill the billing address.
     *
     * @param  array<string, mixed>  $address
     */
    public function withBillingAddress(string $country, array $address = []): static
    {
        $this->options['billing_address'] = array_merge(['country' => strtoupper($country)], $address);

        return $this;
    }

    /**
     * Give the customer a free trial before the first charge.
     */
    public function trialDays(int $days): static
    {
        $this->options['subscription_data'] = array_merge(
            $this->options['subscription_data'] ?? [],
            ['trial_period_days' => $days]
        );

        return $this;
    }

    /**
     * Set up the subscription so it is charged on demand rather than on a schedule.
     *
     * @param  array<string, mixed>  $options
     */
    public function onDemand(bool $mandateOnly = true, array $options = []): static
    {
        $this->options['subscription_data'] = array_merge(
            $this->options['subscription_data'] ?? [],
            ['on_demand' => array_merge(['mandate_only' => $mandateOnly], $options)]
        );

        return $this;
    }

    /**
     * Pass any other subscription settings straight through.
     *
     * @param  array<string, mixed>  $data
     */
    public function withSubscriptionData(array $data): static
    {
        $this->options['subscription_data'] = array_merge($this->options['subscription_data'] ?? [], $data);

        return $this;
    }

    /**
     * Set where the customer is sent after a successful or failed payment.
     */
    public function returnUrl(string $url): static
    {
        $this->options['return_url'] = $url;

        return $this;
    }

    /**
     * Set where the customer is sent if they abandon the checkout.
     */
    public function cancelUrl(string $url): static
    {
        $this->options['cancel_url'] = $url;

        return $this;
    }

    /**
     * Attach metadata that comes back on the payment and its webhooks.
     *
     * @param  array<string, string|int|float|bool>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        $this->options['metadata'] = array_merge($this->options['metadata'] ?? [], $metadata);

        return $this;
    }

    /**
     * Apply discount codes, up to the twenty Dodo allows to stack.
     *
     * @param  string|array<int, string>  $codes
     */
    public function withDiscounts(string|array $codes): static
    {
        $this->options['discount_codes'] = array_values(array_merge(
            $this->options['discount_codes'] ?? [],
            (array) $codes
        ));

        return $this;
    }

    /**
     * Limit which payment methods the customer may use.
     *
     * @param  array<int, string>  $types
     */
    public function allowedPaymentMethods(array $types): static
    {
        $this->options['allowed_payment_method_types'] = array_values($types);

        return $this;
    }

    /**
     * Ask the customer extra questions on the payment page.
     *
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function withCustomFields(array $fields): static
    {
        $this->options['custom_fields'] = array_values($fields);

        return $this;
    }

    /**
     * Restyle the hosted payment page.
     *
     * @param  array<string, mixed>  $customization
     */
    public function withCustomization(array $customization): static
    {
        $this->options['customization'] = array_merge($this->options['customization'] ?? [], $customization);

        return $this;
    }

    /**
     * Show the payment page in a specific language.
     */
    public function locale(string $locale): static
    {
        return $this->withCustomization(['force_language' => $locale]);
    }

    /**
     * Show the payment page in light, dark, or the customer's system theme.
     */
    public function theme(string $theme): static
    {
        return $this->withCustomization(['theme' => $theme]);
    }

    /**
     * Toggle the checkout feature flags.
     *
     * @param  array<string, bool>  $flags
     */
    public function withFeatureFlags(array $flags): static
    {
        $this->options['feature_flags'] = array_merge($this->options['feature_flags'] ?? [], $flags);

        return $this;
    }

    /**
     * Bill in a specific currency, where adaptive pricing allows it.
     */
    public function currency(string $currency): static
    {
        $this->options['billing_currency'] = strtoupper($currency);

        return $this;
    }

    /**
     * Ask Dodo for a shortened checkout URL.
     */
    public function asShortLink(bool $shortLink = true): static
    {
        $this->options['short_link'] = $shortLink;

        return $this;
    }

    /**
     * Show the customer the cards they have already saved.
     */
    public function withSavedPaymentMethods(bool $show = true): static
    {
        $this->options['show_saved_payment_methods'] = $show;

        return $this;
    }

    /**
     * Charge a payment method the customer has already saved, with no payment page.
     */
    public function usingPaymentMethod(string $paymentMethodId): static
    {
        $this->options['payment_method_id'] = $paymentMethodId;

        return $this;
    }

    /**
     * Finalize every detail up front so the session is confirmed on creation.
     */
    public function confirm(bool $confirm = true): static
    {
        $this->options['confirm'] = $confirm;

        return $this;
    }

    /**
     * Set any option the builder does not cover explicitly.
     */
    public function withOption(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Build the payload sent to Dodo Payments.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = array_merge($this->options, ['product_cart' => $this->products]);

        if (! isset($payload['return_url']) && $returnUrl = config('cashier-dodo.return_url')) {
            $payload['return_url'] = $returnUrl;
        }

        if ($this->billable && ! isset($payload['metadata']['billable_id'])) {
            $payload['metadata'] = array_merge($payload['metadata'] ?? [], [
                'billable_id' => (string) $this->billable->getKey(),
                'billable_type' => $this->billable->getMorphClass(),
            ]);
        }

        return $payload;
    }

    /**
     * Create the checkout session with Dodo Payments.
     */
    public function create(): CheckoutSession
    {
        return new CheckoutSession(Dodo::checkoutSessions()->create($this->payload()));
    }

    /**
     * Preview the totals of this checkout without creating it.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        return Dodo::checkoutSessions()->preview($this->payload());
    }

    /**
     * Create the session and return the URL the customer should visit.
     */
    public function url(): string
    {
        return $this->create()->url();
    }

    /**
     * Create the session and redirect the customer to it.
     */
    public function redirect(): RedirectResponse
    {
        return new RedirectResponse($this->url());
    }

    /**
     * Allow the builder to be returned straight from a controller.
     */
    public function toResponse($request): RedirectResponse
    {
        return $this->redirect();
    }

    /**
     * Turn the many accepted product shapes into Dodo's cart item format.
     *
     * @param  string|array<int|string, mixed>  $products
     * @return array<int, array<string, mixed>>
     */
    protected static function normalizeProducts(string|array $products, int $quantity = 1): array
    {
        if (is_string($products)) {
            return [['product_id' => $products, 'quantity' => $quantity]];
        }

        $items = [];

        foreach ($products as $key => $value) {
            if (is_array($value)) {
                $items[] = $value + ['quantity' => 1];
            } elseif (is_string($key)) {
                $items[] = ['product_id' => $key, 'quantity' => (int) $value];
            } else {
                $items[] = ['product_id' => $value, 'quantity' => $quantity];
            }
        }

        return $items;
    }
}
