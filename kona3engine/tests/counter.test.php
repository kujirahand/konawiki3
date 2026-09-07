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
test_assert(__LINE__, preg_match('/href=[\'"][^\'"]*counter/', $html_logged_in) === 1, "Logged in link should point to counter action");

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

// 4. 他のプロセスが先に行を作っていてもエラーにならず加算されること
//    (以前はSELECT→INSERTの競合でUNIQUE制約違反の致命的エラーになっていた)
$test_page2 = "TestCounterRace_" . time();
$kona3conf['page'] = $test_page2;
$filepath2 = kona3getWikiFile($test_page2);
kona3lock_save($filepath2, "test counter race");
$page_id2 = kona3db_getPageId($test_page2, TRUE);
subdb_exec("INSERT INTO counter (page_id, value, mtime) VALUES (?,5,?)", [$page_id2, time()]);
subdb_exec(
    "INSERT INTO counter_month (page_id, year, month, value, mtime) VALUES (?,?,?,7,?)",
    [$page_id2, $year, $month, time()]
);
$race_ok = TRUE;
try {
    $html_race = kona3plugins_counter_execute([]);
} catch (Exception $e) {
    $race_ok = FALSE;
    $html_race = '';
}
test_assert(__LINE__, $race_ok, "既に行がある場合も例外にならない");
$r2 = subdb_get1("SELECT value FROM counter WHERE page_id=?", [$page_id2]);
test_eq(__LINE__, intval($r2['value']), 6, "既存の合計カウントに加算される");
$m2 = subdb_get1(
    "SELECT value FROM counter_month WHERE page_id=? AND year=? AND month=?",
    [$page_id2, $year, $month]
);
test_eq(__LINE__, intval($m2['value']), 8, "既存の月間カウントに加算される");

// 5. DBが一時的に使えなくてもページ全体が落ちないこと
//    (以前はカウンターのDBエラーがそのまま致命的エラーになっていた)
$blocker = new PDO('sqlite:' . KONA3_DIR_PRIVATE . '/subdb.sqlite');
$blocker->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$blocker->beginTransaction(); // 他プロセスが書き込み中の状態を作る
$blocker->exec("UPDATE counter SET mtime=0 WHERE page_id=" . intval($page_id2));
subdb_exec('PRAGMA busy_timeout=300'); // ロック待ちを短くしてテストを速く終わらせる
$fatal = FALSE;
try {
    $html_locked = kona3plugins_counter_execute([]);
} catch (Exception $e) {
    $fatal = TRUE;
    $html_locked = '';
}
subdb_exec('PRAGMA busy_timeout=60000'); // 既定値に戻す
$blocker->rollBack();
$blocker = null;
test_assert(__LINE__, !$fatal, "DBがロックされていても例外を投げない");
test_assert(__LINE__, $html_locked !== '', "DBがロックされていても表示は継続する");

// クリーンアップ
subdb_exec("DELETE FROM counter WHERE page_id=?", [$page_id2]);
subdb_exec("DELETE FROM counter_month WHERE page_id=?", [$page_id2]);
if (file_exists($filepath2)) {
    unlink($filepath2);
}
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
$kona3conf['page'] = $original_page;
if (file_exists($filepath)) {
    unlink($filepath);
}
subdb_exec("DELETE FROM counter WHERE page_id=?", [$page_id]);
subdb_exec("DELETE FROM counter_month WHERE page_id=?", [$page_id]);
