#!/usr/bin/env bash
set -euo pipefail

# ============================================================================
# Wtips – Stripe account bootstrap
#
# Idempotently creates everything the credit system needs on the Stripe side:
#   * Product "Kredit Wtips"           (fixed id: wtips_credit)
#   * Price   1 CZK per credit         (lookup_key: wtips_credit_czk)
#   * Webhook endpoint                 (optional, via --webhook-url)
#
# Safe to re-run at any time – existing objects are detected and reused.
# Works against both sandbox (sk_test_...) and live (sk_live_...) accounts.
#
# Usage:
#   STRIPE_SECRET_KEY=sk_test_... bin/stripe-bootstrap.sh
#   STRIPE_SECRET_KEY=sk_live_... bin/stripe-bootstrap.sh \
#       --webhook-url=https://wtips.cz/webhooks/stripe
#
# The webhook signing secret is printed ONLY when the endpoint is first
# created – store it immediately (STRIPE_WEBHOOK_SECRET).
# For local development use the Stripe CLI instead of a webhook endpoint:
#   stripe listen --forward-to localhost:39080/webhooks/stripe
# ============================================================================

API='https://api.stripe.com/v1'
PRODUCT_ID='wtips_credit'
PRICE_LOOKUP_KEY='wtips_credit_czk'
WEBHOOK_URL=''

# Customer-facing text — shown on the Stripe Checkout line item and on the
# Stripe-issued invoice, so it is Czech (checkout runs with locale=cs).
PRODUCT_NAME='Kredit Wtips'
PRODUCT_DESCRIPTION='Kredity pro tipovací soutěže na Wtips. 1 kredit = 1 Kč.'
PRICE_NICKNAME='Kredit (1 Kč)'

for arg in "$@"; do
    case "$arg" in
        --webhook-url=*) WEBHOOK_URL="${arg#*=}" ;;
        -h|--help) grep '^#' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown argument: $arg" >&2; exit 1 ;;
    esac
done

if [[ -z "${STRIPE_SECRET_KEY:-}" ]]; then
    echo 'Error: STRIPE_SECRET_KEY environment variable is required.' >&2
    echo 'Usage: STRIPE_SECRET_KEY=sk_test_... bin/stripe-bootstrap.sh [--webhook-url=https://...]' >&2
    exit 1
fi

# stripe_api METHOD PATH [curl -d args...] -> body on stdout, fails on non-2xx
stripe_api() {
    local method="$1" path="$2"
    shift 2
    local response http_code body
    response=$(curl -sS -w '\n%{http_code}' -X "$method" -u "${STRIPE_SECRET_KEY}:" "$@" "${API}${path}")
    http_code="${response##*$'\n'}"
    body="${response%$'\n'*}"
    if [[ "$http_code" -lt 200 || "$http_code" -ge 300 ]]; then
        echo "Stripe API ${method} ${path} failed (HTTP ${http_code}):" >&2
        echo "$body" >&2
        return 1
    fi
    echo "$body"
}

json_get() { # json_get '<json>' '<python expression on d>'
    python3 -c "import json,sys; d=json.loads(sys.argv[1]); print($2)" "$1"
}

MODE='live'
[[ "$STRIPE_SECRET_KEY" == sk_test_* ]] && MODE='sandbox (test)'
echo "==> Bootstrapping Stripe account in ${MODE} mode"

# ----------------------------------------------------------------------------
# 1. Product
# ----------------------------------------------------------------------------
if product=$(stripe_api GET "/products/${PRODUCT_ID}" 2>/dev/null); then
    echo "  ✓ Product '${PRODUCT_ID}' already exists"
    if [[ "$(json_get "$product" "d['active']")" != 'True' ]]; then
        stripe_api POST "/products/${PRODUCT_ID}" -d 'active=true' >/dev/null
        echo "  ✓ Product '${PRODUCT_ID}' re-activated"
    fi
    # Converge the customer-facing text — the constants above are the source of
    # truth, so editing them here and re-running renames the live product.
    if [[ "$(json_get "$product" "d['name']")" != "$PRODUCT_NAME" \
       || "$(json_get "$product" "d['description'] or ''")" != "$PRODUCT_DESCRIPTION" ]]; then
        stripe_api POST "/products/${PRODUCT_ID}" \
            --data-urlencode "name=${PRODUCT_NAME}" \
            --data-urlencode "description=${PRODUCT_DESCRIPTION}" \
            >/dev/null
        echo "  ✓ Product '${PRODUCT_ID}' text updated to '${PRODUCT_NAME}'"
    fi
else
    stripe_api POST '/products' \
        -d "id=${PRODUCT_ID}" \
        --data-urlencode "name=${PRODUCT_NAME}" \
        --data-urlencode "description=${PRODUCT_DESCRIPTION}" \
        -d 'metadata[app]=wtips' \
        >/dev/null
    echo "  ✓ Product '${PRODUCT_ID}' created ('${PRODUCT_NAME}')"
fi

# ----------------------------------------------------------------------------
# 2. Price (1 CZK per credit, quantity = number of credits)
# ----------------------------------------------------------------------------
prices=$(stripe_api GET "/prices?lookup_keys[]=${PRICE_LOOKUP_KEY}&active=true")
price_id=$(json_get "$prices" "d['data'][0]['id'] if d['data'] else ''")

if [[ -n "$price_id" ]]; then
    echo "  ✓ Price '${PRICE_LOOKUP_KEY}' already exists (${price_id})"
else
    price=$(stripe_api POST '/prices' \
        -d "product=${PRODUCT_ID}" \
        -d 'currency=czk' \
        -d 'unit_amount=100' \
        -d "lookup_key=${PRICE_LOOKUP_KEY}" \
        --data-urlencode "nickname=${PRICE_NICKNAME}" \
        -d 'metadata[app]=wtips')
    price_id=$(json_get "$price" "d['id']")
    echo "  ✓ Price '${PRICE_LOOKUP_KEY}' created (${price_id})"
fi

# ----------------------------------------------------------------------------
# 3. Webhook endpoint (optional)
# ----------------------------------------------------------------------------
webhook_secret=''
if [[ -n "$WEBHOOK_URL" ]]; then
    endpoints=$(stripe_api GET '/webhook_endpoints?limit=100')
    existing_id=$(json_get "$endpoints" "next((e['id'] for e in d['data'] if e['url'] == '${WEBHOOK_URL}'), '')")

    if [[ -n "$existing_id" ]]; then
        echo "  ✓ Webhook endpoint for ${WEBHOOK_URL} already exists (${existing_id})"
        echo '    (signing secret is only shown on creation – see the Stripe dashboard)'
    else
        endpoint=$(stripe_api POST '/webhook_endpoints' \
            --data-urlencode "url=${WEBHOOK_URL}" \
            -d 'enabled_events[]=checkout.session.completed' \
            -d 'enabled_events[]=checkout.session.async_payment_succeeded' \
            -d 'enabled_events[]=checkout.session.async_payment_failed' \
            -d 'enabled_events[]=checkout.session.expired' \
            --data-urlencode 'description=Nákupy kreditů Wtips')
        webhook_secret=$(json_get "$endpoint" "d['secret']")
        echo "  ✓ Webhook endpoint created for ${WEBHOOK_URL}"
    fi
fi

# ----------------------------------------------------------------------------
# Summary
# ----------------------------------------------------------------------------
echo
echo "==> Done. The app resolves the price at runtime via lookup key '${PRICE_LOOKUP_KEY}' (${price_id})."
if [[ -n "$webhook_secret" ]]; then
    echo
    echo 'Environment variable for .env.local:'
    echo "STRIPE_WEBHOOK_SECRET=${webhook_secret}"
elif [[ -z "$WEBHOOK_URL" ]]; then
    echo '# STRIPE_WEBHOOK_SECRET: for local dev run `stripe listen --forward-to localhost:39080/webhooks/stripe`'
    echo '# and use the whsec_... it prints; for production re-run with --webhook-url=https://.../webhooks/stripe'
fi
