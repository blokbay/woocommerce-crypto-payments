# Blokbay Crypto Gateway (WooCommerce)

Payment method for WooCommerce (WordPress). License GPL-2.0-or-later.

## Install
1. WordPress: Plugins > Add New > Upload plugin > `blokbay-woocommerce.zip` > Activate.
2. Blokbay dashboard > **Payments on your site**: create the **secret key** (shown once), copy the **public key** and the **webhook signing secret**.
3. WooCommerce > Settings > Payments > **Crypto (Blokbay)**: paste secret key, public key, webhook signing secret; enable.
4. Back in Blokbay set the webhook URL to `https://your-store/?wc-api=blokbay` (shown in the plugin settings) and press **Send a test event**.
5. Place a small real order to test (there is no test mode with fake money).

## How it works
- At checkout the plugin creates a payment (`POST /api/v1/payments`, idempotent per order and amount) and redirects to the Blokbay payment page.
- The customer pays and returns to the order received page.
- The signed webhook `payment.paid` (HMAC SHA-256, checked in constant time, 5 minute tolerance, public key checked) marks the order paid with `payment_complete()`. If the webhook is late, the thank-you page asks the API.
- `payment.expired` cancels a pending order. A payment that arrives after expiry is still applied (the funds are already in your wallet).
- The amount must match the order total, otherwise the order gets a note and is not marked paid.

## Requirements
WordPress 6.6+, WooCommerce 9.0+ (tested up to WordPress 7.1 and WooCommerce 11.3), PHP 7.4+, store currency **USD**. Classic checkout and checkout block, HPOS compatible.
