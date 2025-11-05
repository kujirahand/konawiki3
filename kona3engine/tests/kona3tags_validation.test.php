<?php
/**
 * Test for tags validation
 */
require_once __DIR__ . '/test_common.inc.php';
require_once __DIR__ . '/../plugins/tags.inc.php';

echo "=== Tags Validation Test ===\n";

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

// テスト用のページを作成
$test_page1 = 'TagValidationTest1';
$test_page2 = 'TagValidationTest2';
$test_page3 = 'TagValidationTest3';

// ページ1: タグを含むページ
$body1 = "■テストページ1\n\nこのページにはタグがあります。\n\n#tag(ValidTag)\n#tag(TestTag)";
$filepath1 = koan3getWikiFileText($test_page1);
file_put_contents($filepath1, $body1);
kona3db_writePage($test_page1, $body1, 0);

// ページ2: タグを含むページ
$body2 = "■テストページ2\n\n別のページ\n\n#tag(ValidTag)";
$filepath2 = koan3getWikiFileText($test_page2);
file_put_contents($filepath2, $body2);
kona3db_writePage($test_page2, $body2, 0);

// ページ3: タグを含まないページ（手動でタグだけ追加）
$body3 = "■テストページ3\n\nタグプラグインを使わないページ";
$filepath3 = koan3getWikiFileText($test_page3);
file_put_contents($filepath3, $body3);
kona3db_writePage($test_page3, $body3, 0);

echo "Test 1: Add tags to pages\n";
kona3tags_addPageTag($test_page1, 'ValidTag');
kona3tags_addPageTag($test_page1, 'TestTag');
kona3tags_addPageTag($test_page2, 'ValidTag');
kona3tags_addPageTag($test_page3, 'ValidTag'); // このページには実際にタグプラグインがない

// タグの状態を確認
$pages = kona3tags_getPages('ValidTag');
test_eq(__LINE__, 3, count($pages), "ValidTag should have 3 pages initially");

echo "Test 2: Check tag validation in #tags plugin\n";
// #tagsプラグインを実行してバリデーションを実行
$html = kona3plugins_tags_getTags('ValidTag', 'mtime', 30);

// バリデーション後、タグプラグインを含まないページは除去されているはず
$pages = kona3tags_getPages('ValidTag');
test_eq(__LINE__, 2, count($pages), "ValidTag should have 2 pages after validation");

// テストページが含まれているか確認
$page_names = array_map(function($p) { return $p['page']; }, $pages);
test_assert(__LINE__, in_array($test_page1, $page_names), "TagValidationTest1 should remain");
test_assert(__LINE__, in_array($test_page2, $page_names), "TagValidationTest2 should remain");
test_assert(__LINE__, !in_array($test_page3, $page_names), "TagValidationTest3 should be removed");

echo "Test 3: Verify individual tag check function\n";
test_assert(__LINE__, kona3plugins_tags_hasTagInPage($test_page1, 'ValidTag'), "Test page 1 has ValidTag");
test_assert(__LINE__, kona3plugins_tags_hasTagInPage($test_page1, 'TestTag'), "Test page 1 has TestTag");
test_assert(__LINE__, kona3plugins_tags_hasTagInPage($test_page2, 'ValidTag'), "Test page 2 has ValidTag");
test_assert(__LINE__, !kona3plugins_tags_hasTagInPage($test_page3, 'ValidTag'), "Test page 3 doesn't have ValidTag");
test_assert(__LINE__, !kona3plugins_tags_hasTagInPage($test_page1, 'NonExistentTag'), "Test page 1 doesn't have NonExistentTag");

echo "\n=== Tags Validation Test Completed ===\n";
