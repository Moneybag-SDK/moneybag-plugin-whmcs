<?php

/**
 * Moneybag Payment Gateway Callback Handler for WHMCS.
 *
 * Handles both the browser redirect (success / fail / cancel) and the
 * server-to-server IPN from Moneybag. Every reported success is independently
 * re-verified against the Moneybag API before the invoice is marked paid.
 *
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 */

require_once __DIR__ . '/../../../init.php';

App::load_function('gateway');
App::load_function('invoice');

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams     = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

require_once __DIR__ . '/../moneybag/lib/loader.php';

$debugLogging = !empty($gatewayParams['debugLogging']);
$systemUrl    = rtrim($gatewayParams['systemurl'], '/');

// Inputs from Moneybag redirect / IPN.
$invoiceId     = isset($_REQUEST['invoice_id']) ? (int) $_REQUEST['invoice_id'] : 0;
$status        = isset($_REQUEST['status']) ? strtolower((string) $_REQUEST['status']) : '';
$transactionId = isset($_REQUEST['transaction_id'])
    ? preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $_REQUEST['transaction_id'])
    : '';
$isIpn = ($status === 'ipn');

if ($debugLogging) {
    logTransaction($gatewayParams['name'], $_REQUEST, 'Callback Received');
}

/**
 * Redirect a browser back to the WHMCS invoice (no-op for IPN calls).
 */
function moneybag_return($systemUrl, $invoiceId, $isIpn, $message)
{
    if ($isIpn) {
        header('Content-Type: text/plain');
        echo $message;
        exit;
    }

    $target = $invoiceId > 0
        ? $systemUrl . '/viewinvoice.php?id=' . $invoiceId
        : $systemUrl . '/clientarea.php';

    header('Location: ' . $target);
    exit;
}

// Validate the invoice exists and belongs to this gateway.
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

// Customer abandoned or failed the payment at Moneybag.
if ($status === 'cancel' || $status === 'fail') {
    if ($debugLogging) {
        logTransaction($gatewayParams['name'], [
            'invoice_id' => $invoiceId,
            'status'     => $status,
        ], 'Payment ' . ucfirst($status));
    }
    moneybag_return($systemUrl, $invoiceId, $isIpn, 'Payment ' . $status);
}

// At this point we expect a success/ipn notification: re-verify with Moneybag.
try {
    $client = new MoneybagSdk(
        $gatewayParams['apiKey'],
        $gatewayParams['environment'] === 'production' ? 'production' : 'staging'
    );

    if ($transactionId === '') {
        throw new MoneybagSdk_MoneybagException('Missing transaction_id in Moneybag callback.');
    }

    $verify = $client->verify($transactionId);

    if ($debugLogging) {
        logTransaction($gatewayParams['name'], [
            'invoice_id'     => $invoiceId,
            'transaction_id' => $transactionId,
            'verify_status'  => $verify->getStatus(),
            'amount'         => $verify->getAmount(),
            'currency'       => $verify->getCurrency(),
        ], 'Verification Response');
    }

    $verifiedStatus = strtoupper((string) $verify->getStatus());

    if ($verifiedStatus === 'SUCCESS' || $verifiedStatus === 'COMPLETED') {
        // Prevent duplicate transaction processing.
        checkCbTransID($transactionId);

        $paymentAmount = $verify->getAmount();
        $paymentFee    = 0;

        addInvoicePayment(
            $invoiceId,
            $transactionId,
            $paymentAmount,
            $paymentFee,
            $gatewayModuleName
        );

        logTransaction($gatewayParams['name'], [
            'invoice_id'     => $invoiceId,
            'transaction_id' => $transactionId,
            'amount'         => $paymentAmount,
            'status'         => $verifiedStatus,
        ], 'Successful');

        moneybag_return($systemUrl, $invoiceId, $isIpn, 'OK');
    }

    logTransaction($gatewayParams['name'], [
        'invoice_id'     => $invoiceId,
        'transaction_id' => $transactionId,
        'status'         => $verifiedStatus,
    ], 'Unsuccessful');

    moneybag_return($systemUrl, $invoiceId, $isIpn, 'Payment not completed');
} catch (MoneybagSdk_MoneybagException $e) {
    logTransaction($gatewayParams['name'], [
        'invoice_id'     => $invoiceId,
        'transaction_id' => $transactionId,
        'error'          => $e->getMessage(),
    ], 'Verification Error');

    moneybag_return($systemUrl, $invoiceId, $isIpn, 'Verification error');
}
