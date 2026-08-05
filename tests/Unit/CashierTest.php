<?php

use Ahmadnajmdev\Cashier\Dodo\Cashier;

afterEach(function () {
    Cashier::$formatCurrencyUsing = null;
});

it('formats amounts from the smallest currency unit', function () {
    expect(Cashier::formatAmount(2500))->toContain('25');
})->skip(fn () => ! class_exists(NumberFormatter::class), 'Requires ext-intl.');

it('falls back to a plain format without ext-intl', function () {
    Cashier::formatCurrencyUsing(fn (int $amount, string $currency) => $currency.' '.number_format($amount / 100, 2));

    expect(Cashier::formatAmount(123456, 'IQD'))->toBe('IQD 1,234.56');
});

it('uses the configured currency by default', function () {
    Cashier::formatCurrencyUsing(fn (int $amount, string $currency) => $currency);

    config()->set('cashier-dodo.currency', 'EUR');

    expect(Cashier::formatAmount(100))->toBe('EUR');
});

it('reports its version', function () {
    expect(Cashier::VERSION)->toBe('1.0.0');
});
