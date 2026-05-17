# Changelog

All notable changes to the Moneybag WHMCS payment gateway module are documented
in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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
