<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/plugins/counter.inc.php';

// テスト用のダミーのPage名
global $kona3conf;
$original_page = isset($kona3conf['page']) ? $kona3conf['page'] : 'FrontPage';

$test_page = "TestCounterPage_" . time();
$kona3conf['page'] = $test_page;

// ダミーファイルを作成
$filepath = kona3getWikiFile($test_page);
kona3lock_save($filepath, "test counter");

// 1. ログインしていない状態でのテスト
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
test_eq(__LINE__, kona3isLogin(), FALSE, "Not logged in check");

// counterプラグインを実行して出力を確認
$html_not_logged_in = kona3plugins_counter_execute([]);
test_assert(__LINE__, strpos($html_not_logged_in, 'href=') === FALSE, "Not logged in should NOT have links in counter");

// 2. ログインした状態でのテスト
kona3login('test-counter-user', 'test-counter@example.com', 'normal', 99999);
test_eq(__LINE__, kona3isLogin(), TRUE, "Logged in check");

// counterプラグインを実行して出力を確認
$html_logged_in = kona3plugins_counter_execute([]);
test_assert(__LINE__, strpos($html_logged_in, 'href=') !== FALSE, "Logged in should have links in counter");
test_assert(__LINE__, strpos($html_logged_in, 'counter') !== FALSE, "Logged in link should point to counter action");

// 3. データベースに正しくカウントが保存されているか確認
$page_id = kona3db_getPageId($test_page, FALSE);
test_assert(__LINE__, $page_id > 0, "Page ID should be created");

$total_r = subdb_get1("SELECT * FROM counter WHERE page_id=?", [$page_id]);
test_assert(__LINE__, isset($total_r['value']), "Counter record should exist");
test_eq(__LINE__, $total_r['value'], 2, "Counter value should be 2 (run twice)");

$year  = intval(date('Y'));
$month = intval(date('n'));
$month_r = subdb_get1("SELECT * FROM counter_month WHERE page_id=? AND year=? AND month=?", [$page_id, $year, $month]);
test_assert(__LINE__, isset($month_r['value']), "Monthly counter record should exist");
test_eq(__LINE__, $month_r['value'], 2, "Monthly counter value should be 2");

// クリーンアップ
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
$kona3conf['page'] = $original_page;
if (file_exists($filepath)) {
    unlink($filepath);
}
db_exec("DELETE FROM users WHERE user_id=?", [99999]);
subdb_exec("DELETE FROM counter WHERE page_id=?", [$page_id]);
subdb_exec("DELETE FROM counter_month WHERE page_id=?", [$page_id]);
