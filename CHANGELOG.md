# Changelog

All notable changes to `ahmadnajmdev/cashier-dodo` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-08-05

First release.

### Added

- `Billable` trait and `BillableContract` interface for any Eloquent model.
- Fluent `Checkout` builder covering the full Dodo Payments checkout session API: multiple products, quantities,
  addons, pay what you want amounts, trials, on demand mandates, stacked discount codes, billing address prefill,
  allowed payment methods, custom fields, feature flags, theming, localisation, short links and saved cards.
- `Subscription` model with plan changes across all four Dodo proration modes, scheduled plan changes and their
  cancellation, plan change previews, quantity changes, cancel at period end, cancel now, resume, billing date
  extension, on demand charges, payment method updates and usage history.
- `Transaction` model with refunds, invoice URLs and formatted totals.
- `Customer` model plus billing portal session links and saved payment method listing.
- Webhook controller that verifies Standard Webhooks signatures and syncs subscriptions and transactions, with
  `WebhookReceived`, `WebhookHandled`, `SubscriptionCreated`, `SubscriptionUpdated`, `SubscriptionRenewed`,
  `SubscriptionOnHold`, `SubscriptionCancelled`, `PaymentSucceeded`, `PaymentFailed` and `RefundSucceeded` events.
- `cashier-dodo:webhook` command that registers the endpoint with Dodo and reports its signing key.
- Typed API client and `Dodo` facade with resources for checkout sessions, payments, subscriptions, customers,
  products, discounts, refunds, disputes, license keys, webhooks, addons, brands, meters, usage events and payouts,
  plus raw verbs for everything else.
- Enums for subscription status, payment status, proration billing mode, plan change schedule, time interval and all
  46 Dodo webhook event types.
- Migrations for `dodo_customers`, `dodo_subscriptions` and `dodo_transactions`.

[1.0.0]: https://github.com/ahmadnajmdev/cashier-dodo/releases/tag/v1.0.0
