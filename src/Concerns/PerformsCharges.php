<?php

namespace Ahmadnajmdev\Cashier\Dodo\Concerns;

use Ahmadnajmdev\Cashier\Dodo\Checkout;

trait PerformsCharges
{
    /**
     * Start a checkout for this model.
     *
     * @param  string|array<int|string, mixed>  $products
     * @param  array<string, mixed>  $options
     */
    public function checkout(string|array $products = [], array $options = []): Checkout
    {
        $checkout = Checkout::customer($this);

        if ($products !== [] && $products !== '') {
            $checkout->withProducts($products);
        }

        foreach ($options as $key => $value) {
            $checkout->withOption($key, $value);
        }

        return $checkout;
    }

    /**
     * Charge this model a custom amount for a pay what you want product.
     *
     * @param  array<string, mixed>  $options
     */
    public function chargeCustomAmount(string $productId, int $amount, array $options = []): Checkout
    {
        return $this->checkout([], $options)->withCustomAmount($productId, $amount);
    }
}
