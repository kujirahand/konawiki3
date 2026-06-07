<?php
/**
 * Test for SQLite-based Tag Cache System
 */
require_once __DIR__ . '/test_common.inc.php';
require_once __DIR__ . '/../kona3tags.inc.php';

echo "=== SQLite Tag Cache Specific Test ===\n";

// テスト用データ初期化
$page = "SqliteTagTestPage";
kona3tags_clearPageTags($page);

echo "Test 1: DB Table and Index existence\n";
kona3tags_initDb();
test_assert(__LINE__, db_table_exists('tags', 'tags'), "Table 'tags' should exist");

// インデックスの存在確認
$indexes = db_get("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='tags'", [], 'tags');
$index_names = array_column($indexes, 'name');
test_assert(__LINE__, in_array('idx_tag', $index_names), "Index 'idx_tag' should exist");
test_assert(__LINE__, in_array('idx_page', $index_names), "Index 'idx_page' should exist");

echo "Test 2: Page tag bulk updates (updatePageTags)\n";
$tags = ['SQL', 'SQLite', 'Test'];
kona3tags_updatePageTags($page, $tags);

// SQLiteから直接確認
$db_rows = db_get("SELECT * FROM tags WHERE page=? ORDER BY tag ASC", [$page], 'tags');
test_eq(__LINE__, 3, count($db_rows), "Should have 3 records in DB");
test_eq(__LINE__, 'SQL', $db_rows[0]['tag'], "First tag should be SQL");
test_eq(__LINE__, 'SQLite', $db_rows[1]['tag'], "Second tag should be SQLite");
test_eq(__LINE__, 'Test', $db_rows[2]['tag'], "Third tag should be Test");

echo "Test 3: Query mtime (updated_at) mapping\n";
// USORTによるソートが正しく機能するか確認
$pages = kona3tags_getPages('SQLite', 'mtime');
test_eq(__LINE__, 1, count($pages), "Should retrieve 1 page");
test_eq(__LINE__, $page, $pages[0]['page'], "Page name should match");
test_assert(__LINE__, isset($pages[0]['mtime']), "mtime should be returned");

echo "Test 4: Tag removal\n";
kona3tags_removePageTag($page, 'Test');
$db_rows2 = db_get("SELECT * FROM tags WHERE page=? ORDER BY tag ASC", [$page], 'tags');
test_eq(__LINE__, 2, count($db_rows2), "Should have 2 records in DB after removal");

echo "Test 5: Clear page tags\n";
kona3tags_clearPageTags($page);
$db_rows3 = db_get("SELECT * FROM tags WHERE page=?", [$page], 'tags');
test_eq(__LINE__, 0, count($db_rows3), "Should have 0 records in DB after clearing");

echo "\n=== SQLite Tag Cache Specific Test Completed ===\n";
