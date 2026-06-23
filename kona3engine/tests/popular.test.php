<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/plugins/popular.inc.php';

// 1. テスト用ページの設定
$page1 = "TestPopularPage1_" . time();
$page2 = "TestPopularPage2_" . time();

$id1 = kona3db_getPageId($page1, TRUE);
$id2 = kona3db_getPageId($page2, TRUE);

test_assert(__LINE__, $id1 > 0 && $id2 > 0, "Page IDs created successfully");

// 2. カウンターを登録
$time = time();
subdb_exec("INSERT OR REPLACE INTO counter (page_id, value, mtime) VALUES (?, ?, ?)", [$id1, 100, $time]);
subdb_exec("INSERT OR REPLACE INTO counter (page_id, value, mtime) VALUES (?, ?, ?)", [$id2, 200, $time]);

// 3. popularプラグインの実行
$html = kona3plugins_popular_execute([2]); // limit = 2
test_assert(__LINE__, strpos($html, $page1) !== FALSE, "Page 1 should be in popular list");
test_assert(__LINE__, strpos($html, $page2) !== FALSE, "Page 2 should be in popular list");
test_assert(__LINE__, strpos($html, '(100)') !== FALSE, "Page 1 count should be displayed");
test_assert(__LINE__, strpos($html, '(200)') !== FALSE, "Page 2 count should be displayed");

// 4. レガシーJSON.bakを元にした復元テスト
// pagesテーブルから page2 のデータを一時的に消す
db_exec("DELETE FROM pages WHERE page_id=?", [$id2]);
test_eq(__LINE__, kona3db_getPageNameById($id2, ''), '', "Page 2 name should not be resolved directly since it is deleted from pages table");

// レガシーJSONファイルをモックして作成
$original_json_data = null;
$original_bak_data = null;
$json_path = KONA3_PAGE_ID_JSON;
$bak_path = $json_path . '.bak';
if (file_exists($json_path)) {
    $original_json_data = file_get_contents($json_path);
    unlink($json_path);
}
if (file_exists($bak_path)) {
    $original_bak_data = file_get_contents($bak_path);
    unlink($bak_path);
}

// .json.bak に登録
$dummy_legacy = [
    $page2 => $id2
];
file_put_contents($bak_path, json_encode($dummy_legacy));

// キャッシュをクリア
kona3db_loadLegacyPageIds(TRUE);

// 再度 popular プラグインを実行。このとき、kona3db_getPageNameById($id2) が内部で legacy json.bak を探し出し、
// 自動的に pages テーブルにインサートしてページ名を解決するはず。
$html2 = kona3plugins_popular_execute([2]);
test_assert(__LINE__, strpos($html2, $page2) !== FALSE, "Page 2 should be resolved from legacy .json.bak and shown in popular list");
test_assert(__LINE__, strpos($html2, '(200)') !== FALSE, "Page 2 count should still be shown");

// pages テーブルに復元されていることを検証
$resolved_name = db_get1("SELECT name FROM pages WHERE page_id=?", [$id2]);
test_eq(__LINE__, $resolved_name ? $resolved_name['name'] : '', $page2, "Page 2 should be automatically restored to pages table");

// 5. クリーンアップ
subdb_exec("DELETE FROM counter WHERE page_id IN (?, ?)", [$id1, $id2]);
db_exec("DELETE FROM pages WHERE page_id IN (?, ?)", [$id1, $id2]);
if (file_exists($bak_path)) {
    unlink($bak_path);
}
if ($original_json_data !== null) {
    file_put_contents($json_path, $original_json_data);
}
if ($original_bak_data !== null) {
    file_put_contents($bak_path, $original_bak_data);
}
echo "popular.test.php completed successfully\n";
