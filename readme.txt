=== Blokbay Crypto Payments for WooCommerce ===
Contributors: blokbay
Tags: crypto payments, bitcoin, usdt, usdc, woocommerce
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept crypto payments in WooCommerce: USDT, USDC, ETH, BTC and SOL, paid straight to your own wallet. 1% per payment, no monthly fee.

== Description ==

Blokbay adds a "Pay with crypto" method to your WooCommerce checkout. Your customer pays on a Blokbay payment page with your store name and logo, then returns to your store. On that page the customer chooses the coin and the network (and can change them before paying), then pays from a connected wallet, or by address and QR code from any wallet or exchange. A confirmation page opens as soon as the payment arrives. The order is marked as paid when the signed confirmation (webhook) arrives.

= Funds go directly to your wallet =

Blokbay never holds your money. When the buyer pays from a connected wallet, each payment is split in the same transaction (by a public smart contract on the EVM networks): you receive your share in your own wallet the moment the buyer pays. When the buyer pays by address and QR code, they make two exact transfers, one of them to your address (Bitcoin always works this way). On Base, orders from 10 USD can also be paid with a single transfer to a one-time payment address: a public contract with no owner and no withdrawal function, which forwards your share to your wallet. There is no balance on our side, so there are no funds to be held, frozen or released later.

= Simple pricing =

* 1% of each payment, withheld automatically: you receive 99%.
* No monthly fee, no setup fee, no fixed cost per transaction.
* The buyer pays the network fee of their own transfer. With the one-time payment address on Base the buyer also pays a small network cost for the split, which they avoid by connecting a wallet.

= Coins and networks =

* USDT and USDC on Ethereum, BNB Smart Chain (BSC), Arbitrum, Polygon and Solana.
* USDC on Base.
* ETH on Ethereum, Base and Arbitrum.
* SOL on Solana.
* Bitcoin (BTC), for orders from the equivalent of 100 USD.
* Payment from a connected wallet (browser or phone), or by address and QR code from any wallet or exchange.

= Final payments =

Blockchain transfers cannot be reversed by the payer, so there are no chargebacks. Refunds, when you decide to give one, are sent by you from your wallet.

= Start right away =

Open your Blokbay account with your email, add your wallet address, create your keys and paste them in WooCommerce. No paperwork and no documents to send.

= Features =

* Classic checkout and the Cart and Checkout blocks.
* Compatible with High-Performance Order Storage (HPOS).
* Signed webhooks (HMAC SHA-256 with a 5 minute tolerance), checked in constant time; the order amount is checked before the order is marked as paid.
* If the webhook is late, the order received page asks the Blokbay API for the payment status.
* An expired payment cancels the pending order; a payment that arrives after expiry is still applied, because the funds are already in your wallet.
* Idempotent payment creation: a repeated checkout for the same order and amount reuses the same payment.

= Requirements =

* Store currency: USD, EUR, GBP, CHF, JPY, CAD, AUD, SGD, HKD or AED. The buyer pays in crypto at the current exchange rate.
* A Blokbay account (free) and a wallet address for each coin you want to accept: an EVM address (USDT, USDC and ETH), a Solana address (USDT, USDC and SOL on Solana) and a Bitcoin address.

You remain the seller: you are responsible for your store, your products and your obligations towards your customers, including taxes and invoices.

== External services ==

This plugin connects to the Blokbay payments service (https://blokbay.com), which is needed to create and confirm crypto payments. Nothing is sent until a customer chooses "Pay with crypto" at checkout.

What is sent and when:

* When the customer places an order with this method, the plugin sends to `https://blokbay.com/api/v1/payments`, with your secret key: the order total in the store currency, the currency, the order number, a description ("Order 123"), the billing email of the customer, the address of the order received page and the address of the cancel page (both contain the order key), and an `Idempotency-Key` header built from the order id, the order key and the amount, so that a repeated checkout reuses the same payment. The customer is then redirected to the Blokbay payment page `https://blokbay.com/pay/<payment id>`.
* When the customer returns to the order received page and the order is not yet paid, the plugin asks `https://blokbay.com/api/v1/payments/<payment id>` for the payment status (no customer data is sent).
* Blokbay sends signed webhook requests to your store (`https://your-store/?wc-api=blokbay`) when a payment is paid or expires. The plugin only reads them.

No data is sent to any other service, and the plugin does not collect usage statistics.

* Terms of service: https://blokbay.com/terms
* Privacy policy: https://blokbay.com/privacy
* API documentation: https://blokbay.com/docs/api

== Installation ==

1. Install the plugin from Plugins > Add New (search "Blokbay"), or upload the zip in Plugins > Add New > Upload plugin, then activate it. WooCommerce must be active.
2. Open your Blokbay dashboard at https://blokbay.com/dashboard/gateway ("Payments on your site"): add your wallet addresses, create the secret key (shown only once) and copy the public key and the webhook signing secret.
3. In WooCommerce > Settings > Payments > Crypto (Blokbay): paste the secret key, the public key and the webhook signing secret, then enable the method.
4. In the Blokbay dashboard set the webhook URL shown under the "Webhook signing secret" field (`https://your-store/?wc-api=blokbay`) and press "Send a test event".
5. Place a small real order (from the equivalent of 1 USD with stablecoins on Polygon or Base the network fee is a few cents). There is no test mode with fake money.

== Frequently Asked Questions ==

= Does Blokbay hold my money? =

No. Payments from a connected wallet are split in the same transaction and your share goes straight to your wallet. Payments by address and QR code are two exact transfers made by the buyer, one of them to your address (Bitcoin always works this way); the one-time payment address on Base is a public contract with no owner and no withdrawal function, which forwards your share to your wallet. Blokbay has no balance in your name.

= How much does it cost? =

1% of each payment, withheld automatically. There is no monthly fee and no fixed cost.

= Which currency must my store use? =

USD, EUR, GBP, CHF, JPY, CAD, AUD, SGD, HKD or AED. The amount is converted to crypto at the current public exchange rate, locked when the buyer chooses the coin. For other store currencies the method is hidden at checkout and the settings page shows a notice.

= Which coins can my customers use? =

USDT and USDC on Ethereum, BNB Smart Chain, Arbitrum, Polygon and Solana, USDC on Base, ETH on Ethereum, Base and Arbitrum, SOL on Solana and Bitcoin (from the equivalent of 100 USD). The customer can pay from a connected wallet or by address and QR code from any wallet or exchange, and sees only the coins for which you added an address.

= Can a buyer reverse a payment? =

No. A confirmed blockchain transfer cannot be charged back by the payer. If you want to refund a customer, you send the refund from your wallet.

= When is the order marked as paid? =

When the payment is confirmed on the blockchain and the signed webhook reaches your store. If the webhook is late, the order received page checks the status with the Blokbay API.

= What happens if the customer does not pay? =

The payment page stays open for 24 hours. Once the customer picks a coin, the page shows how long the amount is locked (from 15 minutes to a few hours, depending on the coin and on how the customer pays). If nothing arrives in time, the payment expires and the pending order is cancelled.

= Does it work with the checkout block and HPOS? =

Yes. The plugin supports the classic checkout, the Cart and Checkout blocks and High-Performance Order Storage.

= Is there a test mode? =

There is no test mode with fake money, so that a test can never mark real orders as paid. A real payment of 1 USD with USDC on Polygon or Base costs a few cents of network fee.

= Where do I get support? =

Use the support forum of this plugin or write to mail@blokbay.com.

== Screenshots ==

1. "Pay with crypto" in the WooCommerce checkout block.
2. The Blokbay payment page: the customer chooses the coin and pays from their wallet.
3. Plugin settings in WooCommerce > Settings > Payments.
4. Keys and webhook in the Blokbay dashboard.

== Changelog ==

= 1.2.0 =
* Updated texts: USDT, USDC, ETH, BTC and SOL, all the networks of the payment page, payment from a connected wallet or by address and QR code from any wallet or exchange.
* New default checkout description for new installations: "Pay in crypto: USDT, USDC, ETH, BTC or SOL, from your wallet or an exchange". A description you already saved is not changed.
* No change to how the plugin works.

= 1.1.2 =
* Hardening: every value received from the Blokbay API and from webhooks is sanitized and validated before use (payment id, payment page address, order note fields); the keys saved in the settings are sanitized on save.
* Uninstall removes the settings on every site of a multisite network.

= 1.1.1 =
* Webhook: the raw body is read through the WordPress core helper, limited in size and validated (signature format, HMAC, JSON) before use.

= 1.1.0 =
* Store currencies USD, EUR, GBP, CHF, JPY, CAD, AUD, SGD, HKD and AED: the order total is sent in the store currency and checked in the same currency on every webhook. Notice in the settings for other currencies.

= 1.0.0 =
* First release: classic checkout and checkout block, HPOS, signed webhooks, status check on the order received page.

== Upgrade Notice ==

= 1.2.0 =
Text update only (coins, networks and ways to pay). Your saved settings are not changed.

= 1.1.2 =
Stricter sanitization and validation of API answers, webhooks and settings.

= 1.1.1 =
Webhook input hardening.

= 1.1.0 =
Stores in EUR, GBP, CHF, JPY, CAD, AUD, SGD, HKD and AED can now accept crypto payments.

= 1.0.0 =
First release.
