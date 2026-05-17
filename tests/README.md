# Tests

Zero-dependency test suite (no Composer/PHPUnit), matching the module's
"self-contained" guarantee. Plain PHP + a tiny assertion harness.

## Offline structural tests

No network or API key required. Validates module wiring and that the bundled
SDK loads with plain `require_once`.

```bash
php tests/test_offline.php
```

## End-to-end sandbox test

Hits the real Moneybag sandbox API. Creates a checkout session (nothing is
charged) and, optionally, verifies a known transaction.

```bash
MONEYBAG_API_KEY=<sandbox_key> php tests/test_e2e.php
```

Optional environment variables:

| Variable | Default | Purpose |
| --- | --- | --- |
| `MONEYBAG_API_KEY` | _(unset → test skips)_ | Sandbox merchant API key |
| `MONEYBAG_ENV` | `staging` | `staging` or `production` |
| `MONEYBAG_TRANSACTION_ID` | _(unset)_ | Also test `verify()` on this txn |

> Never commit a real API key. Always pass it via the environment.

## Run everything

```bash
MONEYBAG_API_KEY=<sandbox_key> ./tests/run.sh
```
