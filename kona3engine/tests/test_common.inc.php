<?php
$engine_dir = dirname(__DIR__);
require_once $engine_dir . '/konawiki_version.inc.php';
require_once $engine_dir . '/kona3conf.inc.php';
require_once $engine_dir . '/kona3lib.inc.php';
require_once $engine_dir . '/kona3db.inc.php';
require_once $engine_dir . '/index.inc.php'; // main

global $KONA3_TEST_OK;
global $KONA3_TEST_NG;
global $KONA3_TEST_ERRORS;
global $KONA3_TEST_FILE;

$KONA3_TEST_OK = 0;
$KONA3_TEST_NG = 0;
$KONA3_TEST_ERRORS = [];
$KONA3_TEST_FILE = '';

function test_assert($line, $b, $desc = 'assert')
{
    global $KONA3_TEST_OK, $KONA3_TEST_NG, $KONA3_TEST_FILE, $KONA3_TEST_ERRORS;
    if (!$b) {
        $KONA3_TEST_NG++;
        $KONA3_TEST_ERRORS[] = [
            "file" => $KONA3_TEST_FILE,
            "line" => $line,
            "error" => "$desc"
        ];
        return FALSE;
    }
    $KONA3_TEST_OK++;
    return TRUE;
}


function test_eq($line, $a, $b, $desc = 'eq')
{
    return test_assert($line, $a === $b, "$desc: `$a` === `$b`");
}

function test_ne($line, $a, $b, $desc = 'eq')
{
    return test_assert($line, $a !== $b, "$desc: `$a` !== `$b`");
}
