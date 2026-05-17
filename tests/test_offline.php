<?php

/**
 * Offline structural tests — no network, no real API key required.
 *
 * Verifies the WHMCS module wiring and that the bundled SDK is fully
 * self-contained (loads with plain require_once, zero dependencies).
 *
 * Run:  php tests/test_offline.php
 */

require __DIR__ . '/bootstrap.php';
require MONEYBAG_MODULE_ROOT . '/modules/gateways/moneybag.php';

/* --------------------------------------------------------------------- */
t_section('Bundled SDK loads with zero dependencies');

t_ok(class_exists('MoneybagSdk'), 'MoneybagSdk class is available');
t_ok(class_exists('MoneybagSdk_HttpClient'), 'MoneybagSdk_HttpClient class is available');
t_ok(class_exists('MoneybagSdk_CheckoutRequest'), 'MoneybagSdk_CheckoutRequest class is available');
t_ok(class_exists('MoneybagSdk_Customer'), 'MoneybagSdk_Customer class is available');
t_ok(class_exists('MoneybagSdk_VerifyResponse'), 'MoneybagSdk_VerifyResponse class is available');
t_ok(class_exists('MoneybagSdk_MoneybagException'), 'MoneybagSdk_MoneybagException class is available');

$staging = new MoneybagSdk('test_key', 'staging');
$prod    = new MoneybagSdk('test_key', 'production');
t_equals('https://sandbox.api.moneybag.com.bd/api/v2', $staging->getBaseUrl(), 'Staging base URL');
t_equals('https://api.moneybag.com.bd/api/v2', $prod->getBaseUrl(), 'Production base URL');

/* --------------------------------------------------------------------- */
t_section('moneybag_MetaData()');

$meta = moneybag_MetaData();
t_equals('Moneybag Payment Gateway', $meta['DisplayName'], 'DisplayName');
t_equals('1.1', $meta['APIVersion'], 'APIVersion');
t_equals(true, $meta['DisableLocalCreditCardInput'], 'DisableLocalCreditCardInput');

/* --------------------------------------------------------------------- */
t_section('moneybag_config()');

$config = moneybag_config();
t_equals('System', $config['FriendlyName']['Type'], 'FriendlyName is a System field');
t_equals('password', $config['apiKey']['Type'], 'apiKey is a password field');
t_equals('dropdown', $config['environment']['Type'], 'environment is a dropdown');
t_ok(
    isset($config['environment']['Options']['staging'], $config['environment']['Options']['production']),
    'environment has staging + production options'
);
t_equals('staging', $config['environment']['Default'], 'environment defaults to staging');
t_equals('yesno', $config['debugLogging']['Type'], 'debugLogging is a yesno field');

/* --------------------------------------------------------------------- */
t_section('moneybag_refund() — manual refunds only');

$refund = moneybag_refund([]);
t_equals('declined', $refund['status'], 'Refund is declined (no gateway refund endpoint)');
t_ok(!empty($refund['rawdata']), 'Refund includes an explanatory message');

/* --------------------------------------------------------------------- */
t_section('Callback URL builder');

$url = _moneybag_callback_url('https://shop.example.com/modules/gateways/callback/moneybag.php', 1042, 'success');
t_ok(strpos($url, 'invoice_id=1042') !== false, 'Callback URL carries invoice_id');
t_ok(strpos($url, 'status=success') !== false, 'Callback URL carries status');

/* --------------------------------------------------------------------- */
t_section('order_id meets Moneybag 10-char minimum');

$oidShort = _moneybag_order_id(42);
$oidOne   = _moneybag_order_id(1);
$oidBig   = _moneybag_order_id(1234567);

t_equals('WHMCS00042', $oidShort, 'Short invoice id is prefixed and zero-padded');
t_ok(strlen($oidOne) >= 10, 'Single-digit invoice id yields >= 10 chars');
t_ok(strlen($oidShort) >= 10, 'Typical invoice id yields >= 10 chars');
t_ok(strlen($oidBig) >= 10 && strpos($oidBig, '1234567') !== false, 'Large invoice id stays >= 10 and traceable');

/* --------------------------------------------------------------------- */
t_section('CheckoutRequest assembles a valid payload');

$customer = new MoneybagSdk_Customer();
$customer->setName('Jane Merchant');
$customer->setEmail('jane@example.com');
$customer->setAddress('123 Test Road');
$customer->setCity('Dhaka');
$customer->setPostcode('1207');
$customer->setCountry('Bangladesh');
$customer->setPhone('+8801700000000');

$req = new MoneybagSdk_CheckoutRequest();
$req->setOrderId('1042');
$req->setCurrency('BDT');
$req->setOrderAmount('1280.00');
$req->setOrderDescription('Invoice #1042');
$req->setSuccessUrl('https://shop.example.com/ok');
$req->setFailUrl('https://shop.example.com/fail');
$req->setCancelUrl('https://shop.example.com/cancel');
$req->setCustomer($customer);

$json = method_exists($req, 'toJson') ? $req->toJson() : json_encode($req);
$decoded = json_decode($json, true);

t_ok(is_array($decoded), 'CheckoutRequest serialises to JSON');
t_equals('1042', $decoded['order_id'], 'Payload order_id matches invoice id');
t_equals('BDT', $decoded['currency'], 'Payload currency');
t_equals('1280.00', (string) $decoded['order_amount'], 'Payload order_amount');
t_ok(
    isset($decoded['customer']['email']) && $decoded['customer']['email'] === 'jane@example.com',
    'Payload carries customer email'
);

exit(t_summary());
