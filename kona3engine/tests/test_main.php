<?php
require_once __DIR__ . '/test_common.inc.php';
global $KONA3_TEST_FILE;

echo "+===================\n";
echo "| KONAWIKI3 TEST    \n";
echo "+===================\n";

if (($argc <= 1) || ($argv && $argv[1] !== "go_test")) {
    echo "誤動作を防ぐため、`./test.sh go_test` を実行してください。\n";
    exit;
}

// test all script
$files = glob(__DIR__ . "/*.test.php");
foreach ($files as $no => $file) {
    // 数値 $no を0で埋める
    $no_ = str_pad($no, 3, "0", STR_PAD_LEFT);
    echo "+-------------------\n";
    echo "| FILE({$no_}): $file\n";
    echo "+-------------------\n";
    echo "- $file\n";
    $KONA3_TEST_FILE = $file;
    include $file;
}

// show resuls
echo "+===================\n";
echo "| TEST RESULT        \n";
echo "+===================\n";
echo "- OK: $KONA3_TEST_OK\n";
echo "- NG: $KONA3_TEST_NG\n";
if ($KONA3_TEST_NG > 0) {
    echo "+ ERRORS:\n";
    foreach ($KONA3_TEST_ERRORS as $err) {
        print_r($err);
    }
}
