<?php
/**
 * Test for kona3tags.inc.php
 */
require_once __DIR__ . '/test_common.inc.php';

// タグシステムのテスト
echo "=== Tag System Test ===\n";

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

// 1. タグの追加テスト
echo "Test 1: Add tag to page\n";
kona3tags_addPageTag('TestPage1', 'PHP');
kona3tags_addPageTag('TestPage2', 'PHP');
kona3tags_addPageTag('TestPage1', 'プログラミング');

// 2. タグの読み込みテスト
echo "Test 2: Load tags\n";
$pages = kona3tags_getPages('PHP');
test_eq(__LINE__, 2, count($pages), "PHP tag should have 2 pages");

// 3. ページのタグ一覧取得テスト
echo "Test 3: Get page tags\n";
$tags = kona3tags_getPageTags('TestPage1');
test_eq(__LINE__, 2, count($tags), "TestPage1 should have 2 tags");

// 4. タグの削除テスト
echo "Test 4: Remove tag from page\n";
kona3tags_removePageTag('TestPage1', 'PHP');
$pages = kona3tags_getPages('PHP');
test_eq(__LINE__, 1, count($pages), "PHP tag should have 1 page after removal");

// 5. ページの全タグクリアテスト
echo "Test 5: Clear all tags from page\n";
kona3tags_clearPageTags('TestPage1');
$tags = kona3tags_getPageTags('TestPage1');
test_eq(__LINE__, 0, count($tags), "TestPage1 should have no tags");

// 6. 全タグ一覧取得テスト
echo "Test 6: Get all tags\n";
kona3tags_addPageTag('TestPage3', 'JavaScript');
$all_tags = kona3tags_getAllTags();
test_assert(__LINE__, in_array('PHP', $all_tags), "PHP should be in all tags");
test_assert(__LINE__, in_array('JavaScript', $all_tags), "JavaScript should be in all tags");

// 7. ソートテスト
echo "Test 7: Sort test\n";
kona3tags_addPageTag('PageA', 'TestTag');
sleep(1);
kona3tags_addPageTag('PageB', 'TestTag');
$pages = kona3tags_getPages('TestTag', 'mtime', 10);
test_eq(__LINE__, 'PageB', $pages[0]['page'], "PageB should be first (sorted by mtime desc)");

$pages = kona3tags_getPages('TestTag', 'page', 10);
test_eq(__LINE__, 'PageA', $pages[0]['page'], "PageA should be first (sorted by page)");

echo "\n=== All tests completed ===\n";
