<?php
$engine_dir = dirname(__DIR__);

// テストは手元の data/ private/ cache/ に左右されないよう、一時ディレクトリで実行する
// (実データ制御: KONA3_TEST_REAL_DIRS=1 で従来どおり実ディレクトリを使う)
if (!getenv('KONA3_TEST_REAL_DIRS') && !defined('KONA3_DIR_DATA')) {
    $sandbox = realpath(sys_get_temp_dir()) . '/kona3_test_sandbox_' . getmypid();
    foreach (['data', 'private', 'cache'] as $d) {
        @mkdir("$sandbox/$d", 0777, true);
    }
    define('KONA3_DIR_DATA', "$sandbox/data");
    define('KONA3_DIR_PRIVATE', "$sandbox/private");
    define('KONA3_DIR_CACHE', "$sandbox/cache");
    register_shutdown_function(function () use ($sandbox) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sandbox, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($sandbox);
    });
}
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


// 配列などの値も警告なしで文字列化する
function test_str($v)
{
    if (is_scalar($v) || $v === null) {
        return (string)$v;
    }
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function test_eq($line, $a, $b, $desc = 'eq')
{
    return test_assert($line, $a === $b, $desc . ": `" . test_str($a) . "` === `" . test_str($b) . "`");
}

function test_ne($line, $a, $b, $desc = 'eq')
{
    return test_assert($line, $a !== $b, $desc . ": `" . test_str($a) . "` !== `" . test_str($b) . "`");
}
