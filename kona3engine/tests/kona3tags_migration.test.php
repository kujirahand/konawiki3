<?php
/**
 * Test for SQLite to File-based tag migration
 */
require_once __DIR__ . '/test_common.inc.php';

echo "=== Tag Migration Test ===\n";

// テスト用のタグディレクトリを削除
$test_tag_dir = KONA3_DIR_DATA . '/.kona3_tag';
if (file_exists($test_tag_dir)) {
    $files = glob($test_tag_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($test_tag_dir);
}

// SQLiteにテストデータを追加
echo "Test 1: Add test data to SQLite\n";
try {
    // テーブルが存在することを確認
    $result = db_get("SELECT name FROM sqlite_master WHERE type='table' AND name='tags'", []);
    if ($result) {
        // 既存のデータをクリア
        db_exec("DELETE FROM tags", []);
        
        // テストデータを追加
        $page_id = kona3db_getPageId('MigrationTest1', TRUE);
        db_exec("INSERT INTO tags (page_id, tag, mtime) VALUES (?, ?, ?)", [$page_id, 'OldTag1', time()]);
        
        $page_id2 = kona3db_getPageId('MigrationTest2', TRUE);
        db_exec("INSERT INTO tags (page_id, tag, mtime) VALUES (?, ?, ?)", [$page_id2, 'OldTag1', time()]);
        db_exec("INSERT INTO tags (page_id, tag, mtime) VALUES (?, ?, ?)", [$page_id2, 'OldTag2', time()]);
        
        echo "  Added test data to SQLite\n";
        
        // データ確認
        $count = db_get1("SELECT COUNT(*) as cnt FROM tags", []);
        test_eq(__LINE__, 3, $count['cnt'], "Should have 3 records in SQLite");
        
        // 移行処理を実行
        echo "Test 2: Execute migration\n";
        kona3tags_initDir(); // これが移行を実行する
        
        // 移行後の確認
        echo "Test 3: Verify migration\n";
        
        // ファイルベースのタグシステムにデータがあることを確認
        $pages = kona3tags_getPages('OldTag1');
        test_eq(__LINE__, 2, count($pages), "OldTag1 should have 2 pages after migration");
        
        $pages = kona3tags_getPages('OldTag2');
        test_eq(__LINE__, 1, count($pages), "OldTag2 should have 1 page after migration");
        
        // SQLiteのテーブルが空になっていることを確認
        $count = db_get1("SELECT COUNT(*) as cnt FROM tags", []);
        test_eq(__LINE__, 0, $count['cnt'], "SQLite tags table should be empty after migration");
        
        echo "  Migration successful!\n";
    } else {
        echo "  tags table does not exist in SQLite - skipping test\n";
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n=== Migration Test Completed ===\n";
