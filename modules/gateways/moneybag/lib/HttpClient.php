<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Native cURL HTTP client for the bundled Moneybag SDK.
 *
 * This is a dependency-free replacement for the WordPress-based HttpClient
 * shipped with the WooCommerce plugin. It uses PHP's cURL extension only,
 * so the SDK is fully self-contained inside the WHMCS module (no Composer,
 * no Guzzle, no WordPress).
 *
 * It preserves the exact return contract expected by MoneybagSdk:
 *  - 2xx: the decoded JSON body (associative array)
 *  - non-2xx / transport error: an array with keys
 *      success (false), message, body (raw response), status_code
 */
class MoneybagSdk_HttpClient
{
    protected $timeout;
    protected $retry_attempts;
    protected $retry_delay_base = 1; // seconds

    public function __construct($timeout = 30, $retry_attempts = 3)
    {
        $this->timeout        = (int) $timeout;
        $this->retry_attempts = (int) $retry_attempts;
    }

    /**
     * Makes a GET request.
     *
     * @param string $url
     * @param array  $headers Associative array of header name => value.
     * @return array
     * @throws MoneybagSdk_MoneybagException
     */
    public function get(string $url, array $headers = []): array
    {
        return $this->request($url, 'GET', $headers);
    }

    /**
     * Makes a POST request.
     *
     * @param string $url
     * @param array  $headers Associative array of header name => value.
     * @param string $body
     * @return array
     * @throws MoneybagSdk_MoneybagException
     */
    public function post(string $url, array $headers = [], string $body = ''): array
    {
        return $this->request($url, 'POST', $headers, $body);
    }

    /**
     * Generic request method with retry logic and SSL error handling.
     *
     * @param string $url
     * @param string $method
     * @param array  $headers
     * @param string $body
     * @return array
     * @throws MoneybagSdk_MoneybagException
     */
    protected function request(string $url, string $method, array $headers = [], string $body = ''): array
    {
        if (!function_exists('curl_init')) {
            throw new MoneybagSdk_MoneybagException(
                'The PHP cURL extension is required by the Moneybag gateway but is not installed/enabled.'
            );
        }

        $curl_headers = [];
        foreach ($headers as $name => $value) {
            $curl_headers[] = $name . ': ' . $value;
        }

        for ($attempt = 0; $attempt <= $this->retry_attempts; $attempt++) {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER     => $curl_headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CUSTOMREQUEST  => $method,
            ]);

            if ('POST' === $method) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $response_body = curl_exec($ch);
            $status_code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno         = curl_errno($ch);
            $error_message = curl_error($ch);
            curl_close($ch);

            if ($errno !== 0) {
                // cURL error 60 == SSL certificate problem.
                if ($errno === 60) {
                    throw new MoneybagSdk_MoneybagException(
                        'SSL certificate verification failed (cURL error 60). ' .
                        'Your server might be missing a valid CA certificate bundle. ' .
                        'Please contact your hosting provider or server admin to fix this issue.'
                    );
                }

                if ($attempt < $this->retry_attempts) {
                    $delay = pow(2, $attempt) * $this->retry_delay_base;
                    sleep($delay);
                    continue;
                }

                throw new MoneybagSdk_MoneybagException(
                    'Network error during API request: ' . $error_message
                );
            }

            $decoded_body = json_decode((string) $response_body, true);

            if ($status_code >= 200 && $status_code < 300) {
                return $decoded_body ?: [
                    'success'     => false,
                    'message'     => 'Empty or invalid JSON response.',
                    'body'        => $response_body,
                    'status_code' => $status_code,
                ];
            }

            // Non-2xx: return full error info including raw body so the SDK
            // can surface a meaningful message.
            return [
                'success'     => false,
                'message'     => isset($decoded_body['message'])
                    ? $decoded_body['message']
                    : 'API error occurred.',
                'body'        => $response_body,
                'status_code' => $status_code,
            ];
        }

        // Should be unreachable, but keep a defined contract.
        throw new MoneybagSdk_MoneybagException('Network error: request could not be completed.');
    }
}
