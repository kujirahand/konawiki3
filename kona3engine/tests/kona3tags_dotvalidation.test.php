<?php
/**
 * Test for tag name validation (dot character)
 */
require_once __DIR__ . '/test_common.inc.php';
require_once __DIR__ . '/../plugins/tag.inc.php';

echo "=== Tag Name Validation Test (Dot) ===\n";

// テスト用のタグディレクトリをクリーンアップ
$test_tag_dir = KONA3_DIR_DATA . '/.kona3_tag';
if (file_exists($test_tag_dir)) {
    $files = glob($test_tag_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

// グローバル変数の設定
global $kona3conf;
$kona3conf['page'] = 'TestPage';

echo "Test 1: Valid tag names\n";
$result = kona3plugins_tag_execute(['ValidTag']);
test_assert(__LINE__, strpos($result, 'エラー') === false, "ValidTag should be accepted");

$result = kona3plugins_tag_execute(['Valid_Tag']);
test_assert(__LINE__, strpos($result, 'エラー') === false, "Valid_Tag should be accepted");

$result = kona3plugins_tag_execute(['Valid-Tag']);
test_assert(__LINE__, strpos($result, 'エラー') === false, "Valid-Tag should be accepted");

$result = kona3plugins_tag_execute(['日本語タグ']);
test_assert(__LINE__, strpos($result, 'エラー') === false, "Japanese tag should be accepted");

echo "Test 2: Invalid tag names with dot\n";
$result = kona3plugins_tag_execute(['Invalid.Tag']);
test_assert(__LINE__, strpos($result, 'エラー') !== false, "Invalid.Tag should be rejected");
test_assert(__LINE__, strpos($result, 'ドット') !== false, "Error message should mention dot");

$result = kona3plugins_tag_execute(['file.txt']);
test_assert(__LINE__, strpos($result, 'エラー') !== false, "file.txt should be rejected");

$result = kona3plugins_tag_execute(['ver.1.0']);
test_assert(__LINE__, strpos($result, 'エラー') !== false, "ver.1.0 should be rejected");

echo "Test 3: Tag file path generation without dot\n";
$filepath = kona3tags_getFilePath('TestTag');
test_assert(__LINE__, strpos($filepath, '.json') !== false, "File should have .json extension");

// ドットを含むタグ名はURLエンコードされる
$filepath = kona3tags_getFilePath('Test.Tag');
echo "  Filepath for 'Test.Tag': $filepath\n";
test_assert(__LINE__, strpos($filepath, '.json') !== false, "File should still have .json extension");
// ドットは%2Eに変換される
test_assert(__LINE__, strpos($filepath, 'Test%2ETag.json') !== false, "Dot should be URL encoded to %2E");

echo "\n=== Tag Name Validation Test Completed ===\n";
