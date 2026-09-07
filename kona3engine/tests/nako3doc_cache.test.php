<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/plugins/nako3doc.inc.php';

// --- nako3doc cache=0 deletion test ---
$test_type = 'test_' . uniqid();
$cache_dir = KONA3_DIR_CACHE;
$cache_file = $cache_dir . "/nako3doc.cache.list_func_{$test_type}.html";

// 1. ダミーのキャッシュファイルを作成
file_put_contents($cache_file, 'dummy cache content');
test_assert(__LINE__, file_exists($cache_file), "キャッシュファイルが作成されていること");

// 2. cache=0 を指定して実行
$_GET['cache'] = '0';
nako3doc_list_func($test_type);

// 3. キャッシュファイルが削除されているか確認
test_assert(__LINE__, !file_exists($cache_file), "cache=0 指定時にキャッシュファイルが削除されること");

// クリーンアップ
if (file_exists($cache_file)) {
    @unlink($cache_file);
}
unset($_GET['cache']);
