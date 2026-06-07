<?php
require_once __DIR__ . '/test_common.inc.php';
global $KONA3_TEST_FILE;

echo "+==============================================\n";
echo "| KONAWIKI3 TEST\n";
if (($argc <= 1) || ($argv && $argv[1] !== "go_test")) {
    echo "誤動作を防ぐため、`./test.sh go_test` を実行してください。\n";
    exit;
}

// test all script
$files = glob(__DIR__ . "/*.test.php");
foreach ($files as $no => $file) {
    // 数値 $no を0で埋める
    $no_ = str_pad($no, 2, "0", STR_PAD_LEFT);
    echo "+----------------------------------------------\n";
    $KONA3_TEST_FILE = basename($file);
    $tmp_ok = $KONA3_TEST_OK;
    $tmp_ng = $KONA3_TEST_NG;
    include $file;
    $cnt_ok = $KONA3_TEST_OK - $tmp_ok;
    $cnt_ng = $KONA3_TEST_NG - $tmp_ng;
    $cnt_total = $cnt_ok + $cnt_ng;
    $cnt_ok_ = str_pad($cnt_ok, 3, "0", STR_PAD_LEFT);
    $cnt_total_ = str_pad($cnt_total, 3, "0", STR_PAD_LEFT);
    if ($cnt_ng > 0) {
        echo "| 🍄 NG: $cnt_ok_/$cnt_total_ | $KONA3_TEST_FILE\n";
    } else {
        echo "| 🍵 OK: $cnt_ok_/$cnt_total_ | $KONA3_TEST_FILE\n";
    }
}

// show resuls
$ok_ = str_pad($KONA3_TEST_OK, 3, "0", STR_PAD_LEFT);
$total_ = str_pad($KONA3_TEST_OK + $KONA3_TEST_NG, 3, "0", STR_PAD_LEFT);
echo "+==============================================\n";
echo "| [TEST RESULT] {$ok_}/{$total_}\n";
if ($KONA3_TEST_NG > 0) {
    echo "+ ERRORS:\n";
    foreach ($KONA3_TEST_ERRORS as $err) {
        print_r($err);
    }
    exit(1);
} else {
    echo "| 😊 Success!\n";
    exit(0);
}
