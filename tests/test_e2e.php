<?php

/**
 * End-to-end sandbox test — hits the real Moneybag API.
 *
 * Exercises the exact integration path the WHMCS module uses:
 *   build Customer + CheckoutRequest  ->  MoneybagSdk::checkout()
 *   (optionally) MoneybagSdk::verify() for a known transaction id.
 *
 * Requires a sandbox API key. Nothing is charged — checkout only creates a
 * payment session.
 *
 * Usage:
 *   MONEYBAG_API_KEY=sk_sandbox_xxx php tests/test_e2e.php
 *
 * Optional env:
 *   MONEYBAG_ENV=staging|production         (default: staging)
 *   MONEYBAG_TRANSACTION_ID=<txn>           also test verify() on this txn
 */

require __DIR__ . '/bootstrap.php';
// Require the gateway module so we exercise the real order_id builder
// (it pulls in the bundled SDK loader itself).
require MONEYBAG_MODULE_ROOT . '/modules/gateways/moneybag.php';

$apiKey = getenv('MONEYBAG_API_KEY');
if (!$apiKey) {
    fwrite(STDERR, "SKIP: MONEYBAG_API_KEY not set — skipping live e2e test.\n");
    exit(0);
}

$env = getenv('MONEYBAG_ENV') ?: 'staging';
$env = $env === 'production' ? 'production' : 'staging';

echo "Moneybag e2e test (environment: {$env})\n";

$client = new MoneybagSdk($apiKey, $env);
echo "Base URL: " . $client->getBaseUrl() . "\n";

/* --------------------------------------------------------------------- */
t_section('checkout() — create a payment session');

// Use a short numeric invoice id (like a real WHMCS invoice) and let the
// gateway's _moneybag_order_id() build the >= 10 char order_id Moneybag
// requires. A time-derived number keeps each run's order_id unique.
$invoiceId = (int) substr((string) time(), -6);
$orderId   = _moneybag_order_id($invoiceId);
echo "Invoice id: {$invoiceId}  ->  order_id: {$orderId} (" . strlen($orderId) . " chars)\n";
t_ok(strlen($orderId) >= 10, 'order_id meets Moneybag 10-char minimum');

$customer = new MoneybagSdk_Customer();
$customer->setName('WHMCS E2E Tester');
$customer->setEmail('e2e@example.com');
$customer->setAddress('123 Test Road');
$customer->setCity('Dhaka');
$customer->setPostcode('1207');
$customer->setCountry('Bangladesh');
$customer->setPhone('+8801700000000');

$req = new MoneybagSdk_CheckoutRequest();
$req->setOrderId($orderId);
$req->setCurrency('BDT');
$req->setOrderAmount('10.00');
$req->setOrderDescription('WHMCS module e2e test ' . $invoiceId);
$req->setSuccessUrl('https://example.com/modules/gateways/callback/moneybag.php?invoice_id=1&status=success');
$req->setFailUrl('https://example.com/modules/gateways/callback/moneybag.php?invoice_id=1&status=fail');
$req->setCancelUrl('https://example.com/modules/gateways/callback/moneybag.php?invoice_id=1&status=cancel');
$req->setIpnUrl('https://example.com/modules/gateways/callback/moneybag.php?invoice_id=1&status=ipn');
$req->setCustomer($customer);

try {
    $resp = $client->checkout($req);

    $checkoutUrl = $resp->getCheckoutUrl();
    $sessionId   = $resp->getSessionId();

    t_ok(is_string($checkoutUrl) && $checkoutUrl !== '', 'checkout() returned a checkout URL');
    t_ok(filter_var($checkoutUrl, FILTER_VALIDATE_URL) !== false, 'Checkout URL is a valid URL');
    t_ok(is_string($sessionId) && $sessionId !== '', 'checkout() returned a session id');

    echo "  -> session_id : {$sessionId}\n";
    echo "  -> checkout_url: {$checkoutUrl}\n";
    echo "  (open the checkout URL in a browser to complete a sandbox payment)\n";
} catch (MoneybagSdk_MoneybagException $e) {
    t_ok(false, 'checkout() failed: ' . $e->getMessage());
}

/* --------------------------------------------------------------------- */
$txn = getenv('MONEYBAG_TRANSACTION_ID');
if ($txn) {
    t_section('verify() — re-verify a known transaction');
    try {
        $v = $client->verify($txn);
        t_ok(is_string($v->getStatus()) && $v->getStatus() !== '', 'verify() returned a status');
        echo "  -> status     : " . $v->getStatus() . "\n";
        echo "  -> amount     : " . $v->getAmount() . "\n";
        echo "  -> order_id   : " . $v->getOrderId() . "\n";
    } catch (MoneybagSdk_MoneybagException $e) {
        t_ok(false, 'verify() failed: ' . $e->getMessage());
    }
} else {
    echo "\n(Set MONEYBAG_TRANSACTION_ID to also test verify())\n";
}

exit(t_summary());
