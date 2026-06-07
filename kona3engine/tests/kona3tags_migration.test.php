<?php
/**
 * Test for tags rebuild/migration (from Meta files to SQLite cache)
 */
require_once __DIR__ . '/test_common.inc.php';
require_once __DIR__ . '/../kona3tags.inc.php';

echo "=== Tag Rebuild/Migration Test ===\n";

// テスト用のページ名
$test_page1 = 'MigrationTest1';
$test_page2 = 'MigrationTest2';

// 既存のメタファイルやWikiファイルを削除
$meta_file1 = kona3db_getPageMetaFile($test_page1);
$meta_file2 = kona3db_getPageMetaFile($test_page2);
if (file_exists($meta_file1)) unlink($meta_file1);
if (file_exists($meta_file2)) unlink($meta_file2);

$wiki_file1 = koan3getWikiFileText($test_page1);
$wiki_file2 = koan3getWikiFileText($test_page2);
if (file_exists($wiki_file1)) unlink($wiki_file1);
if (file_exists($wiki_file2)) unlink($wiki_file2);

// SQLiteキャッシュをクリア
kona3tags_initDb();
db_exec("DELETE FROM tags", [], 'tags');

echo "Test 1: Create test pages and save meta with tags\n";
// テスト用データ保存
kona3lock_save($wiki_file1, "Test page 1");
kona3lock_save($wiki_file2, "Test page 2");

// メタ情報保存
$meta1 = [
    'page' => $test_page1,
    'tags' => ['OldTag1', 'CommonTag'],
    'created_at' => time(),
    'updated_at' => time()
];
kona3db_savePageMeta($test_page1, $meta1);

$meta2 = [
    'page' => $test_page2,
    'tags' => ['OldTag2', 'CommonTag'],
    'created_at' => time(),
    'updated_at' => time()
];
kona3db_savePageMeta($test_page2, $meta2);

echo "Test 2: Execute rebuild\n";
// 再構築処理を実行
kona3tags_rebuildAll();

echo "Test 3: Verify rebuild results\n";

// OldTag1
$pages = kona3tags_getPages('OldTag1');
test_eq(__LINE__, 1, count($pages), "OldTag1 should have 1 page");
test_eq(__LINE__, $test_page1, $pages[0]['page'], "OldTag1 page name should match");

// OldTag2
$pages = kona3tags_getPages('OldTag2');
test_eq(__LINE__, 1, count($pages), "OldTag2 should have 1 page");
test_eq(__LINE__, $test_page2, $pages[0]['page'], "OldTag2 page name should match");

// CommonTag
$pages = kona3tags_getPages('CommonTag', 'page');
test_eq(__LINE__, 2, count($pages), "CommonTag should have 2 pages");
test_eq(__LINE__, $test_page1, $pages[0]['page'], "First page of CommonTag should be MigrationTest1");
test_eq(__LINE__, $test_page2, $pages[1]['page'], "Second page of CommonTag should be MigrationTest2");

// クリーンアップ
echo "Test 4: Cleanup test data\n";
if (file_exists($meta_file1)) unlink($meta_file1);
if (file_exists($meta_file2)) unlink($meta_file2);
if (file_exists($wiki_file1)) unlink($wiki_file1);
if (file_exists($wiki_file2)) unlink($wiki_file2);

kona3tags_clearPageTags($test_page1);
kona3tags_clearPageTags($test_page2);

echo "\n=== Tag Rebuild/Migration Test Completed ===\n";
