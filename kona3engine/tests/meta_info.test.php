<?php
require_once __DIR__ . '/test_common.inc.php';

// テスト用のページ名
$test_page = "TestMetaInfoPage";
$test_page_id = 999999; // テスト用のページID

// メタ情報ファイルのパスを取得
$metaFile = kona3db_getPageMetaFile($test_page);
echo "[Test] Meta file path: $metaFile\n";

// ディレクトリが書き込み可能か確認
$dir = dirname($metaFile);
if (!is_writable($dir)) {
    echo "[Error] Directory is not writable: $dir\n";
    exit(1);
}

// テスト用のメタ情報を作成
$meta = [
    'tags' => ['テスト', 'PHP', 'メタ情報'],
    'description' => 'これはテストページです',
];

// メタ情報を保存
$result = kona3db_savePageMeta($test_page, $meta);
if (!$result) {
    echo "[Error] Failed to save meta info\n";
    echo "[Debug] Meta file: $metaFile\n";
    echo "[Debug] Directory exists: " . (file_exists($dir) ? "yes" : "no") . "\n";
    echo "[Debug] Directory writable: " . (is_writable($dir) ? "yes" : "no") . "\n";
}
test_eq(__LINE__, $result, TRUE, "Meta info save");

// メタ情報ファイルが作成されたか確認
$exists = file_exists($metaFile);
if (!$exists) {
    echo "[Error] Meta file not created: $metaFile\n";
}
test_eq(__LINE__, $exists, TRUE, "Meta file exists");

// メタ情報を読み込み
$loaded_meta = kona3db_loadPageMeta($test_page);
if ($loaded_meta === null) {
    echo "[Error] Failed to load meta info\n";
    if (file_exists($metaFile)) {
        echo "[Debug] File exists but failed to load\n";
        echo "[Debug] File content: " . file_get_contents($metaFile) . "\n";
    }
}
test_eq(__LINE__, $loaded_meta !== null, TRUE, "Meta info loaded");

if ($loaded_meta === null) {
    echo "[Error] Stopping test due to failed meta info load\n";
    exit(1);
}

// ページIDが正しく設定されているか確認
test_eq(__LINE__, isset($loaded_meta['page_id']), TRUE, "page_id exists");
test_eq(__LINE__, isset($loaded_meta['page']), TRUE, "page exists");
test_eq(__LINE__, $loaded_meta['page'], $test_page, "page name match");

// タグが正しく保存されているか確認
test_eq(__LINE__, isset($loaded_meta['tags']), TRUE, "tags exists");
test_eq(__LINE__, is_array($loaded_meta['tags']), TRUE, "tags is array");
test_eq(__LINE__, count($loaded_meta['tags']), 3, "tags count");
test_eq(__LINE__, in_array('テスト', $loaded_meta['tags']), TRUE, "tag 'テスト' exists");
test_eq(__LINE__, in_array('PHP', $loaded_meta['tags']), TRUE, "tag 'PHP' exists");
test_eq(__LINE__, in_array('メタ情報', $loaded_meta['tags']), TRUE, "tag 'メタ情報' exists");

// タイムスタンプが設定されているか確認
test_eq(__LINE__, isset($loaded_meta['created_at']), TRUE, "created_at exists");
test_eq(__LINE__, isset($loaded_meta['updated_at']), TRUE, "updated_at exists");

// メタ情報を更新
$created_at_before = $loaded_meta['created_at'];
$meta['tags'] = ['更新テスト', 'PHP'];
$meta['description'] = '更新されたテストページです';
// 既存のcreated_atを保持
$meta['created_at'] = $created_at_before;
sleep(2); // タイムスタンプの違いを確認するため（2秒待つ）
$result = kona3db_savePageMeta($test_page, $meta);
test_eq(__LINE__, $result, TRUE, "Meta info update");

// 更新されたメタ情報を読み込み
$updated_meta = kona3db_loadPageMeta($test_page);
test_eq(__LINE__, count($updated_meta['tags']), 2, "updated tags count");
test_eq(__LINE__, in_array('更新テスト', $updated_meta['tags']), TRUE, "tag '更新テスト' exists");
test_eq(__LINE__, $updated_meta['description'], '更新されたテストページです', "description updated");
test_eq(__LINE__, $updated_meta['updated_at'] > $created_at_before, TRUE, "updated_at is newer");

// クリーンアップ: テストファイルを削除
@unlink($metaFile);
echo "[Test] Cleanup: removed $metaFile\n";

echo "=== Meta info test completed ===\n";
