# Moneybag Payment Gateway for WHMCS

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF)](https://php.net)
[![WHMCS](https://img.shields.io/badge/WHMCS-8.x-005496)](https://www.whmcs.com)

Official [Moneybag](https://moneybag.com.bd) payment gateway module for **WHMCS**.
Accept secure payments for hosting, domains, and recurring services in Bangladesh.

> ⚠️ **Beta Release**: This module bundles a beta build of the Moneybag PHP SDK.
> Test thoroughly in the staging environment before going live.

## Features

- Hosted checkout — customers pay on Moneybag's secure payment page.
- Server-side payment **re-verification** on every callback before an invoice is marked paid.
- Browser redirect **and** server-to-server IPN handling.
- **Zero external dependencies** — the Moneybag PHP SDK is bundled and uses native cURL only (no Composer, no Guzzle required on the WHMCS host).
- Staging / Production environment switch.
- Optional debug logging to the WHMCS Gateway Log.

## Requirements

- WHMCS 8.0 or higher
- PHP 7.4 or higher
- PHP cURL extension enabled
- A Moneybag merchant account and API key

## Installation

1. Download or clone this repository.
2. Copy the contents of the `modules/` directory into your WHMCS installation's
   `modules/` directory, preserving the structure:

   ```
   <whmcs>/modules/gateways/moneybag.php
   <whmcs>/modules/gateways/callback/moneybag.php
   <whmcs>/modules/gateways/moneybag/lib/...
   ```

3. In the WHMCS admin area, go to **Configuration → System Settings →
   Payment Gateways** (or **Setup → Payments → Payment Gateways** on older
   versions).
4. Under **All Payment Gateways**, click **Moneybag Payment Gateway** to activate it.
5. Configure the module:
   - **Merchant API Key** — your Moneybag `X-Merchant-API-Key`.
   - **Environment** — `Staging / Sandbox` for testing, `Production (Live)` for real payments.
   - **Debug Logging** — enable while testing, disable in production.
6. Click **Save Changes**.

## How It Works

1. On the invoice page, the customer clicks **Pay Now**.
2. `moneybag_link()` creates a Moneybag checkout session via the bundled SDK
   and sends the customer to Moneybag's hosted payment page.
3. After payment, Moneybag redirects the customer back to
   `modules/gateways/callback/moneybag.php` (and also calls it server-to-server
   as an IPN).
4. The callback **independently verifies** the transaction with the Moneybag
   API. Only a verified `SUCCESS` / `COMPLETED` status results in
   `addInvoicePayment()` being called.
5. The customer is returned to the WHMCS invoice.

The WHMCS invoice ID is sent as the Moneybag `order_id`, and the Moneybag
`transaction_id` is recorded as the WHMCS transaction ID (duplicate-checked
via `checkCbTransID`).

## Refunds

Automated refunds are **not** supported by the Moneybag gateway API in this
release. `moneybag_refund()` returns a declined status; process refunds
manually from the Moneybag merchant dashboard.

## Bundled SDK

The `modules/gateways/moneybag/lib/` directory contains a self-contained build
of the [Moneybag PHP SDK](https://github.com/Moneybag-SDK/moneybag-sdk-php)
(flat `MoneybagSdk_` class prefix, native cURL transport, no external
dependencies) so the module works on any standard WHMCS host without Composer.

## Support

- Documentation: https://docs.moneybag.com.bd
- Issues: https://github.com/Moneybag-SDK/moneybag-plugin-whmcs/issues

## License

MIT — see [LICENSE](LICENSE).
