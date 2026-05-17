# Installation & Integration Guide

A brief, merchant-facing guide to integrating the Moneybag payment gateway
into WHMCS.

## Prerequisites

- WHMCS 8.0+ on PHP 7.4+ with the **cURL** extension enabled
- A Moneybag merchant account and **Merchant API Key** (`X-Merchant-API-Key`)
- No Composer/Guzzle required — the SDK is bundled

## 1. Upload the module files

Copy the `modules/` tree into your WHMCS root, keeping the structure intact:

```
<whmcs>/modules/gateways/moneybag.php
<whmcs>/modules/gateways/callback/moneybag.php
<whmcs>/modules/gateways/moneybag/lib/...
```

(Upload via SFTP/SSH, or `git clone` and copy. Nothing to build or compile.)

## 2. Activate & configure

In the WHMCS admin area:

**Configuration → System Settings → Payment Gateways → All Payment Gateways →
Moneybag Payment Gateway**

| Setting | Value |
| --- | --- |
| Merchant API Key | Your Moneybag `X-Merchant-API-Key` |
| Environment | `Staging / Sandbox` first, `Production (Live)` when ready |
| Debug Logging | `On` while testing, `Off` in production |

Save changes. The callback URL is derived automatically — nothing to paste.

## 3. (Only if Moneybag requires URL registration)

Register this callback/IPN URL in the Moneybag dashboard:

```
https://<your-whmcs-domain>/modules/gateways/callback/moneybag.php
```

`invoice_id` and `status` are appended automatically per transaction.

## 4. Test in Staging

1. Create a test invoice → select **Moneybag** → **Pay Now**.
2. Complete a sandbox payment on Moneybag's hosted page.
3. Confirm the invoice becomes **Paid** and a transaction is recorded.
4. Review **Billing → Gateway Log** (with Debug Logging on) for the
   checkout/verification round-trip.

## 5. Go live

Switch **Environment** to `Production (Live)`, enter the production API key,
turn **Debug Logging** off, and run one small real transaction to confirm.

## How it works

- WHMCS invoice ID → Moneybag `order_id`; Moneybag `transaction_id` → WHMCS
  transaction (duplicate-protected via `checkCbTransID`).
- After payment, Moneybag both redirects the browser back **and** sends a
  server-to-server IPN. The module **independently re-verifies every success
  with the Moneybag API** before calling `addInvoicePayment()`, so a spoofed
  redirect cannot mark an invoice paid.

## Notes & limitations

- **Beta**: bundled SDK is a beta build — test thoroughly before going live.
- **Refunds are manual** — process them from the Moneybag merchant dashboard;
  the WHMCS refund action returns *declined* (no refund endpoint in the
  gateway API yet).
- Customer billing name, email, address, and phone are sent to Moneybag;
  missing optional fields are sent as safe placeholders.
- Re-test the module after major WHMCS upgrades (standard for any gateway).

## Support

- Docs: https://docs.moneybag.com.bd
- Issues: https://github.com/Moneybag-SDK/moneybag-plugin-whmcs/issues
