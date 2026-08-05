<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dodo Payments API Key
    |--------------------------------------------------------------------------
    |
    | The API key used to authenticate every request against the Dodo Payments
    | API. Generate one from your dashboard under Developer -> API Keys. Keys
    | are scoped to a single mode, so a test key only works in test mode.
    |
    */

    'api_key' => env('DODO_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Either "test_mode" or "live_mode". This decides which Dodo Payments host
    | the package talks to. Keep this on "test_mode" until you are ready to
    | take real money, then flip it together with a live mode API key.
    |
    */

    'environment' => env('DODO_ENVIRONMENT', 'test_mode'),

    /*
    |--------------------------------------------------------------------------
    | Base URL Override
    |--------------------------------------------------------------------------
    |
    | Leave this null to derive the host from the environment above. Set it
    | when you need to point the client somewhere else entirely, such as a
    | local mock server used during automated tests.
    |
    */

    'base_url' => env('DODO_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Request Handling
    |--------------------------------------------------------------------------
    |
    | How long, in seconds, to wait for the Dodo Payments API before giving up,
    | and how many times to retry a failed request. Retries only fire for
    | connection problems and 5xx responses, never for 4xx responses.
    |
    */

    'timeout' => (int) env('DODO_TIMEOUT', 30),

    'retry' => [
        'times' => (int) env('DODO_RETRY_TIMES', 2),
        'sleep' => (int) env('DODO_RETRY_SLEEP', 250),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | The signing key of the webhook endpoint you registered with Dodo. It is
    | used to verify the Standard Webhooks signature on every incoming call,
    | so requests that are not genuinely from Dodo are rejected outright.
    |
    */

    'webhook_secret' => env('DODO_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Route
    |--------------------------------------------------------------------------
    |
    | The URI prefix the package registers its webhook route under, giving you
    | "/dodo/webhook" out of the box. Set the path to null to stop the route
    | from being registered at all and wire up your own instead.
    |
    */

    'path' => env('CASHIER_DODO_PATH', 'dodo'),

    'webhook' => [
        'middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The default currency and the locale used when formatting money for
    | display. Dodo returns amounts in the smallest unit of the currency,
    | and Cashier converts them for you when you format them.
    |
    */

    'currency' => env('CASHIER_DODO_CURRENCY', 'USD'),

    'currency_locale' => env('CASHIER_DODO_CURRENCY_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Default Redirect URL
    |--------------------------------------------------------------------------
    |
    | Where customers land after finishing, or abandoning, a Dodo checkout.
    | Individual checkouts may override this, and when it is left null no
    | return URL is sent and Dodo falls back to your dashboard setting.
    |
    */

    'return_url' => env('CASHIER_DODO_RETURN_URL'),

];
