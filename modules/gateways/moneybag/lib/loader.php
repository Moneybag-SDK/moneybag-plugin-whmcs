<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Loads the bundled, self-contained Moneybag PHP SDK.
 *
 * The SDK classes use the flat "MoneybagSdk_" prefix (no namespaces) and have
 * zero external dependencies (no Composer, no Guzzle), so they can be loaded
 * with plain require_once inside any WHMCS installation.
 */

$moneybag_sdk_dir = __DIR__;

$moneybag_sdk_files = [
    // Exceptions (base first).
    '/Exceptions/MoneybagException.php',
    '/Exceptions/ApiException.php',
    '/Exceptions/AuthenticationException.php',
    '/Exceptions/ValidationException.php',

    // HTTP transport.
    '/HttpClient.php',

    // Request models.
    '/Models/Request/Customer.php',
    '/Models/Request/Shipping.php',
    '/Models/Request/OrderItem.php',
    '/Models/Request/PaymentInfo.php',
    '/Models/Request/CheckoutRequest.php',

    // Response models.
    '/Models/Response/CheckoutResponse.php',
    '/Models/Response/VerifyResponse.php',
    '/Models/Response/RefundResponse.php',

    // Client.
    '/MoneybagSdk.php',
];

foreach ($moneybag_sdk_files as $moneybag_sdk_file) {
    require_once $moneybag_sdk_dir . $moneybag_sdk_file;
}
