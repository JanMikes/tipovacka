# Stripe — credit purchases

Users buy **credits** (1 kredit = 1 Kč, minimum 100) via Stripe-hosted Checkout.
Invoices are issued by Stripe (`invoice_creation`), fulfillment happens via webhook
(plus an idempotent fallback on the checkout return page).

## Architecture

| Piece | Where |
|---|---|
| Wallet + immutable ledger | `CreditWallet`, `CreditTransaction` (balance changes only together with a ledger row — `balanceAfter` always reconciles) |
| Purchase lifecycle | `CreditPurchase` (`pending → completed / expired / failed`), unique per Stripe checkout session |
| Stripe access | `Service/Payment/PaymentGateway` (interface) → `StripePaymentGateway`; tests use `FakePaymentGateway` |
| Webhook endpoint | `POST /webhooks/stripe` (`StripeWebhookController`), signature-verified by `StripeWebhookParser` |
| Fulfillment | `FulfillCreditPurchaseCommand` — idempotent, verifies payment state against the Stripe API, row-locks purchase + wallet |
| Admin adjustments | `AdjustUserCreditsCommand` (note required, audited in the user's history) |

The credit price is resolved at runtime via **lookup key `wtips_credit_czk`**
(quantity = number of credits) — no environment-specific price id in config.

## Environment variables

```
STRIPE_SECRET_KEY=sk_test_...        # live: prefer a restricted key (rk_...)
STRIPE_PUBLISHABLE_KEY=pk_test_...   # not currently used server-side (hosted checkout)
STRIPE_WEBHOOK_SECRET=whsec_...      # webhook endpoint signing secret
STRIPE_DASHBOARD_URL=https://dashboard.stripe.com/test   # no /test in production
```

Real values live in `.env.local` (dev) / deployment env vars (prod). `.env.test`
uses dummies; the webhook secret there (`whsec_test_secret`) is used by tests to
compute real signatures.

## Bootstrapping a Stripe account

Idempotent, safe to re-run, works for sandbox and live:

```bash
# product + price only (local dev — webhook via Stripe CLI):
STRIPE_SECRET_KEY=sk_test_... bin/stripe-bootstrap.sh

# production — also creates the webhook endpoint and prints its signing secret:
STRIPE_SECRET_KEY=sk_live_... bin/stripe-bootstrap.sh --webhook-url=https://wtips.cz/webhooks/stripe
```

Creates:
- Product `wtips_credit` („Wtips kredit")
- Price 1 CZK, lookup key `wtips_credit_czk`
- Webhook endpoint (optional) subscribed to `checkout.session.completed`,
  `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`,
  `checkout.session.expired`

## Local development

```bash
stripe listen --forward-to localhost:39080/webhooks/stripe
# put the printed whsec_... into .env.local as STRIPE_WEBHOOK_SECRET
```

Test card: `4242 4242 4242 4242`, any future expiry, any CVC.

## Fulfillment & safety properties

- **Idempotent**: purchase row is `SELECT … FOR UPDATE`-locked; an already
  `completed` purchase no-ops. Webhook retries and the return-page fallback can
  race safely.
- **Trust boundary**: the handler re-fetches the session from the Stripe API and
  only credits when `payment_status=paid`; amounts/currency are cross-checked
  against our purchase record (`CreditPurchaseMismatch` → 500 → retry + Sentry).
- **Auditable**: every balance change is a `CreditTransaction` with `balanceAfter`,
  actor (`performedBy`), note, and purchase link. Wallet balance can never go negative.
- Foreign checkout sessions (another app on the same Stripe account) are ignored;
  sessions claiming `metadata.app=wtips` without a matching purchase fail loudly.

## Go-live checklist

1. Run the bootstrap script against the live account with `--webhook-url`.
2. Set `STRIPE_SECRET_KEY` (restricted key recommended: Checkout Sessions,
   Customers, Prices, Invoices — write; everything else none),
   `STRIPE_WEBHOOK_SECRET`, `STRIPE_DASHBOARD_URL=https://dashboard.stripe.com`.
3. In the Stripe dashboard: set business name/address (appears on invoices),
   enable desired payment methods (payment methods are dynamic — no code change),
   configure invoice numbering if needed.
