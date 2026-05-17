<?php

/**
 * Moneybag Payment Gateway Module for WHMCS.
 *
 * Accepts payments through the Moneybag payment gateway (Bangladesh) using
 * the bundled, dependency-free Moneybag PHP SDK.
 *
 * @see https://docs.moneybag.com.bd
 * @see https://developers.whmcs.com/payment-gateways/
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/moneybag/lib/loader.php';

/**
 * Define module related meta data.
 *
 * @return array
 */
function moneybag_MetaData()
{
    return [
        'DisplayName'                => 'Moneybag Payment Gateway',
        'APIVersion'                 => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage'           => false,
    ];
}

/**
 * Define gateway configuration options.
 *
 * @return array
 */
function moneybag_config()
{
    return [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'Moneybag Payment Gateway',
        ],
        'apiKey' => [
            'FriendlyName' => 'Merchant API Key',
            'Type'         => 'password',
            'Size'         => '60',
            'Description'  => 'Your Moneybag merchant API key (X-Merchant-API-Key).',
        ],
        'environment' => [
            'FriendlyName' => 'Environment',
            'Type'         => 'dropdown',
            'Options'      => [
                'staging'    => 'Staging / Sandbox',
                'production' => 'Production (Live)',
            ],
            'Default'     => 'staging',
            'Description' => 'Use Staging for testing and Production for live payments.',
        ],
        'debugLogging' => [
            'FriendlyName' => 'Debug Logging',
            'Type'         => 'yesno',
            'Description'  => 'Log gateway API requests/responses to the WHMCS Gateway Log.',
        ],
    ];
}

/**
 * Payment link.
 *
 * Creates a Moneybag checkout session and renders a button that sends the
 * customer to the hosted Moneybag payment page.
 *
 * @param array $params Payment Gateway Module Parameters.
 *
 * @return string
 */
function moneybag_link($params)
{
    // Gateway configuration.
    $apiKey       = $params['apiKey'];
    $environment  = $params['environment'] === 'production' ? 'production' : 'staging';
    $debugLogging = !empty($params['debugLogging']);

    // Invoice parameters.
    $invoiceId = $params['invoiceid'];
    $amount    = $params['amount'];
    $currency  = $params['currency'];

    // Client parameters.
    $firstname = $params['clientdetails']['firstname'];
    $lastname  = $params['clientdetails']['lastname'];
    $email     = $params['clientdetails']['email'];
    $address1  = $params['clientdetails']['address1'];
    $city      = $params['clientdetails']['city'];
    $postcode  = $params['clientdetails']['postcode'];
    $country   = $params['clientdetails']['country'];
    $phone     = $params['clientdetails']['phonenumber'];

    // System parameters.
    $systemUrl    = rtrim($params['systemurl'], '/');
    $returnUrl    = $params['returnurl'];
    $langPayNow   = $params['langpaynow'];
    $moduleName   = $params['paymentmethod'];

    $callbackUrl = $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php';

    try {
        $client = new MoneybagSdk($apiKey, $environment);

        $customer = new MoneybagSdk_Customer();
        $customer->setName(trim($firstname . ' ' . $lastname));
        $customer->setEmail($email);
        $customer->setAddress($address1 !== '' ? $address1 : 'N/A');
        $customer->setCity($city !== '' ? $city : 'N/A');
        $customer->setPostcode($postcode !== '' ? $postcode : '0000');
        $customer->setCountry($country !== '' ? $country : 'Bangladesh');
        $customer->setPhone($phone !== '' ? $phone : '+8800000000000');

        $request = new MoneybagSdk_CheckoutRequest();
        $request->setOrderId(_moneybag_order_id($invoiceId));
        $request->setCurrency(strtoupper($currency));
        $request->setOrderAmount(number_format((float) $amount, 2, '.', ''));
        $request->setOrderDescription('Invoice #' . $invoiceId);
        $request->setSuccessUrl(_moneybag_callback_url($callbackUrl, $invoiceId, 'success'));
        $request->setFailUrl(_moneybag_callback_url($callbackUrl, $invoiceId, 'fail'));
        $request->setCancelUrl(_moneybag_callback_url($callbackUrl, $invoiceId, 'cancel'));
        $request->setIpnUrl(_moneybag_callback_url($callbackUrl, $invoiceId, 'ipn'));
        $request->setCustomer($customer);

        $response = $client->checkout($request);

        if ($debugLogging) {
            logTransaction($moduleName, [
                'action'      => 'checkout',
                'invoice_id'  => $invoiceId,
                'session_id'  => $response->getSessionId(),
                'checkout_url' => $response->getCheckoutUrl(),
            ], 'Checkout Created');
        }

        $checkoutUrl = htmlspecialchars($response->getCheckoutUrl(), ENT_QUOTES, 'UTF-8');

        return '<form action="' . $checkoutUrl . '" method="get">'
            . '<input type="submit" value="' . htmlspecialchars($langPayNow, ENT_QUOTES, 'UTF-8') . '" />'
            . '</form>';
    } catch (MoneybagSdk_MoneybagException $e) {
        if ($debugLogging) {
            logTransaction($moduleName, [
                'action'     => 'checkout',
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ], 'Checkout Failed');
        }

        return '<p style="color:#c00;">Unable to start the Moneybag payment at this time. '
            . 'Please try again later or contact support.</p>';
    }
}

/**
 * Refund transaction.
 *
 * Moneybag's current SDK release does not expose a refund endpoint, so refunds
 * must be processed from the Moneybag merchant dashboard. Returning a declined
 * status keeps the WHMCS invoice/transaction state consistent.
 *
 * @param array $params Payment Gateway Module Parameters.
 *
 * @return array
 */
function moneybag_refund($params)
{
    return [
        'status'  => 'declined',
        'rawdata' => 'Refunds for Moneybag must be processed manually from the '
            . 'Moneybag merchant dashboard. Automated refunds are not supported '
            . 'by the gateway API yet.',
    ];
}

/**
 * Build a Moneybag order_id from a WHMCS invoice id.
 *
 * Moneybag requires order_id to be at least 10 characters, but WHMCS invoice
 * IDs are short integers (often 1-5 digits). We prefix with "WHMCS" and
 * zero-pad the invoice id to a minimum of 5 digits, guaranteeing >= 10
 * characters (e.g. invoice 42 -> "WHMCS00042") while keeping it deterministic
 * and traceable back to the invoice. Larger invoice IDs grow naturally and
 * stay >= 10 characters.
 *
 * The callback identifies the invoice via its own `invoice_id` query
 * parameter (not this order_id), so this mapping is purely informational and
 * safe to keep deterministic across retries.
 *
 * @param int|string $invoiceId
 *
 * @return string
 */
function _moneybag_order_id($invoiceId)
{
    return 'WHMCS' . str_pad((string) $invoiceId, 5, '0', STR_PAD_LEFT);
}

/**
 * Build a callback URL with invoice id and status query parameters.
 *
 * @param string $callbackUrl
 * @param int    $invoiceId
 * @param string $status
 *
 * @return string
 */
function _moneybag_callback_url($callbackUrl, $invoiceId, $status)
{
    return $callbackUrl . '?' . http_build_query([
        'invoice_id' => $invoiceId,
        'status'     => $status,
    ]);
}
