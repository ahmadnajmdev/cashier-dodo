<?php

namespace Ahmadnajmdev\Cashier\Dodo;

use Illuminate\Database\Eloquent\Model;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;

class Cashier
{
    /**
     * The Cashier for Dodo Payments version.
     */
    public const VERSION = '1.0.0';

    /**
     * The customer model class name.
     *
     * @var class-string<Customer>
     */
    public static string $customerModel = Customer::class;

    /**
     * The subscription model class name.
     *
     * @var class-string<Subscription>
     */
    public static string $subscriptionModel = Subscription::class;

    /**
     * The transaction model class name.
     *
     * @var class-string<Transaction>
     */
    public static string $transactionModel = Transaction::class;

    /**
     * Indicates whether the package's migrations should run.
     */
    public static bool $runsMigrations = true;

    /**
     * Indicates whether the package's webhook route should be registered.
     */
    public static bool $registersRoutes = true;

    /**
     * The custom currency formatter, if one has been registered.
     *
     * @var (callable(int, string): string)|null
     */
    public static $formatCurrencyUsing;

    /**
     * Use another model to represent Dodo customers.
     *
     * @param  class-string<Customer>  $model
     */
    public static function useCustomerModel(string $model): void
    {
        static::$customerModel = $model;
    }

    /**
     * Use another model to represent subscriptions.
     *
     * @param  class-string<Subscription>  $model
     */
    public static function useSubscriptionModel(string $model): void
    {
        static::$subscriptionModel = $model;
    }

    /**
     * Use another model to represent transactions.
     *
     * @param  class-string<Transaction>  $model
     */
    public static function useTransactionModel(string $model): void
    {
        static::$transactionModel = $model;
    }

    /**
     * Stop Cashier from running its own migrations.
     */
    public static function ignoreMigrations(): void
    {
        static::$runsMigrations = false;
    }

    /**
     * Stop Cashier from registering its webhook route.
     */
    public static function ignoreRoutes(): void
    {
        static::$registersRoutes = false;
    }

    /**
     * Register a callback that formats money for display.
     *
     * @param  callable(int, string): string  $callback
     */
    public static function formatCurrencyUsing(callable $callback): void
    {
        static::$formatCurrencyUsing = $callback;
    }

    /**
     * Format an amount, given in the smallest unit of the currency, for display.
     */
    public static function formatAmount(int $amount, ?string $currency = null, ?string $locale = null, array $options = []): string
    {
        $currency = strtoupper($currency ?: config('cashier-dodo.currency', 'USD'));

        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $amount, $currency);
        }

        $locale = $locale ?: config('cashier-dodo.currency_locale', 'en');

        if (! class_exists(NumberFormatter::class)) {
            return $currency.' '.number_format($amount / 100, 2);
        }

        $money = new Money($amount, new Currency($currency));

        $numberFormatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        foreach ($options as $key => $value) {
            $numberFormatter->setAttribute($key, $value);
        }

        return (new IntlMoneyFormatter($numberFormatter, new ISOCurrencies))->format($money);
    }

    /**
     * Find the billable model that owns the given Dodo customer.
     */
    public static function findBillable(string $dodoId): ?Model
    {
        return static::$customerModel::where('dodo_id', $dodoId)->first()?->billable;
    }
}
