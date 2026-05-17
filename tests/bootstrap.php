<?php

/**
 * Test bootstrap.
 *
 * WHMCS gateway modules run inside the WHMCS runtime, so for standalone tests
 * we stub the few WHMCS pieces the module touches:
 *  - the WHMCS constant guard
 *  - logTransaction() (Gateway Log writer)
 *
 * It also provides a tiny zero-dependency assertion harness so the test
 * suite needs no Composer/PHPUnit (matching the module's "no external
 * dependencies" guarantee).
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (!defined('WHMCS')) {
    define('WHMCS', true);
}

define('MONEYBAG_MODULE_ROOT', dirname(__DIR__));

/**
 * Captured calls to the WHMCS logTransaction() stub, for assertions.
 *
 * @var array<int,array{module:string,data:mixed,result:string}>
 */
$GLOBALS['__moneybag_logged'] = [];

if (!function_exists('logTransaction')) {
    function logTransaction($module, $data, $result)
    {
        $GLOBALS['__moneybag_logged'][] = [
            'module' => $module,
            'data'   => $data,
            'result' => $result,
        ];
    }
}

/* --------------------------------------------------------------------- */
/* Minimal assertion harness                                              */
/* --------------------------------------------------------------------- */

$GLOBALS['__tests_passed'] = 0;
$GLOBALS['__tests_failed'] = 0;

function t_ok($condition, $message)
{
    if ($condition) {
        $GLOBALS['__tests_passed']++;
        echo "  PASS  {$message}\n";
        return;
    }
    $GLOBALS['__tests_failed']++;
    echo "  FAIL  {$message}\n";
}

function t_equals($expected, $actual, $message)
{
    $ok = ($expected === $actual);
    if (!$ok) {
        $message .= sprintf(
            ' (expected %s, got %s)',
            var_export($expected, true),
            var_export($actual, true)
        );
    }
    t_ok($ok, $message);
}

function t_section($name)
{
    echo "\n== {$name} ==\n";
}

function t_summary()
{
    $p = $GLOBALS['__tests_passed'];
    $f = $GLOBALS['__tests_failed'];
    echo "\n----------------------------------------\n";
    echo "Passed: {$p}   Failed: {$f}\n";
    return $f === 0 ? 0 : 1;
}
