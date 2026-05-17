# Changelog

All notable changes to the Moneybag WHMCS payment gateway module are documented
in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.0.0-beta.2] - 2026-05-17

### Fixed

- `order_id` is now built as `WHMCS` + zero-padded invoice id
  (e.g. invoice 42 → `WHMCS00042`), guaranteeing the **10-character
  minimum** Moneybag requires. Previously the raw WHMCS invoice id
  (often 1–5 digits) was sent, which Moneybag rejects. Verified
  end-to-end against the Moneybag sandbox with a short invoice id.

## [1.0.0-beta.1] - 2026-05-17

### Added

- Initial release of the Moneybag payment gateway module for WHMCS.
- `moneybag_link()` — creates a Moneybag hosted checkout session and redirects
  the customer to the Moneybag payment page.
- `moneybag.php` callback handler with browser-redirect and server-to-server
  IPN support, including independent payment re-verification before
  `addInvoicePayment()`.
- Staging / Production environment switch and optional debug logging.
- Bundled self-contained Moneybag PHP SDK (native cURL, no Composer/Guzzle
  dependency).
- `moneybag_refund()` stub returning a declined status (manual refunds only).
- Zero-dependency test suite: offline structural tests and a live sandbox
  end-to-end test (`tests/`), plus `INSTALL.md` integration guide.

### Fixed

- Bundled SDK `CheckoutRequest` serialised unset optional fields
  (`ipn_url`, `shipping`, `order_items`, `payment_info`, `metadata`) as
  explicit `null`, which the Moneybag API rejects with HTTP 500
  ("Failed to create payment session"). These fields are now omitted when
  unset. Verified end-to-end against the Moneybag sandbox.
