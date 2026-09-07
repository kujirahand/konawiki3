<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/php_fw_simple/fw_database.lib.php';
require_once KONA3_DIR_ENGINE . '/php_fw_simple/fw_template_engine.lib.php';

// === 1. fw_database.lib.php のテスト ===

// db_retry_busy() の基本動作テスト
$count = 0;
$res = db_retry_busy(function () use (&$count) {
    $count++;
    return "ok";
});
test_eq(__LINE__, $res, "ok", "db_retry_busy: 正常終了時の戻り値");
test_eq(__LINE__, $count, 1, "db_retry_busy: 1回で成功");

// db_retry_busy() のリトライ動作テスト (SQLITE_BUSY エミュレーション)
$retry_count = 0;
$res2 = db_retry_busy(function () use (&$retry_count) {
    $retry_count++;
    if ($retry_count < 3) {
        throw new PDOException("SQLITE_BUSY: database is locked");
    }
    return "recovered";
});
test_eq(__LINE__, $res2, "recovered", "db_retry_busy: リトライ後に成功");
test_eq(__LINE__, $retry_count, 3, "db_retry_busy: 3回試行");

// WAL と busy_timeout の設定確認
$test_db = KONA3_DIR_CACHE . '/test_fw_db.sqlite';
$test_sql = KONA3_DIR_CACHE . '/test_fw_db.sql';
if (file_exists($test_db)) {
    @unlink($test_db);
}
file_put_contents($test_sql, 'CREATE TABLE test (id INTEGER PRIMARY KEY);');
database_set($test_db, $test_sql, 'test_wal');
$pdo = database_get('test_wal');
$wal_res = $pdo->query("PRAGMA journal_mode")->fetch(PDO::FETCH_ASSOC);
test_assert(__LINE__, in_array(strtolower($wal_res['journal_mode']), ['wal', 'memory']), "database_get: WALモードが設定されていること");
$timeout_res = $pdo->query("PRAGMA busy_timeout")->fetch(PDO::FETCH_ASSOC);
test_assert(__LINE__, intval($timeout_res['timeout']) >= 5000, "database_get: busy_timeoutが5000以上であること");

// cleanup db
unset($FW_DB_INFO['test_wal']);
@unlink($test_db);
@unlink($test_sql);
@unlink($test_db . '-wal');
@unlink($test_db . '-shm');

// === 2. fw_template_engine.lib.php のテスト ===

// 定数の確認
test_assert(__LINE__, defined('TEMPLATE_ENGINE_MTIME'), "TEMPLATE_ENGINE_MTIME が定義されていること");
test_assert(__LINE__, defined('TEMPLATE_USE_CACHE') && TEMPLATE_USE_CACHE === true, "TEMPLATE_USE_CACHE が true であること");
test_assert(__LINE__, strpos(TEMPLATE_VERSION, 'v3_') === 0, "TEMPLATE_VERSION が v3_ で始まっていること");

// 破損キャッシュ (ParseError) の自動リカバリテスト
$test_tpl_dir = KONA3_DIR_CACHE . '/test_tpl_' . uniqid();
$test_cache_dir = KONA3_DIR_CACHE . '/test_tpl_cache_' . uniqid();
@mkdir($test_tpl_dir, 0777, true);
@mkdir($test_cache_dir, 0777, true);

global $DIR_TEMPLATE, $DIR_TEMPLATE_CACHE;
$orig_tpl = $DIR_TEMPLATE;
$orig_cache = $DIR_TEMPLATE_CACHE;
$DIR_TEMPLATE = $test_tpl_dir;
$DIR_TEMPLATE_CACHE = $test_cache_dir;

$tpl_name = "broken_cache_test.html";
file_put_contents($test_tpl_dir . '/' . $tpl_name, 'Hello, {{' . '$name' . '}}!');

// 壊れたPHPキャッシュを事前作成
$cache_file = $test_cache_dir . '/' . $tpl_name . '.php';
file_put_contents($cache_file, "<?php syntax error !!! ;;;");
// mtime を新しくしてキャッシュ判定を通す
touch($cache_file, time() + 100);

// template_render 実行 -> ParseError を捕捉して壊れたキャッシュを削除・再生成して正常終了するはず
ob_start();
template_render($tpl_name, ['name' => 'KonaWiki']);
$out = ob_get_clean();

test_assert(__LINE__, strpos($out, "Hello, KonaWiki!") !== false, "ParseError キャッシュが自動リカバリされて正常描画されること");
test_assert(__LINE__, file_exists($cache_file), "キャッシュが再生成されていること");

// クリーンアップ
$DIR_TEMPLATE = $orig_tpl;
$DIR_TEMPLATE_CACHE = $orig_cache;
@unlink($test_tpl_dir . '/' . $tpl_name);
@rmdir($test_tpl_dir);
@unlink($cache_file);
@rmdir($test_cache_dir);
