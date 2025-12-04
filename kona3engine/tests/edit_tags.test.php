<?php
/**
 * Test for edit action tag saving
 * edit.inc.php でタグが正しく保存されることを確認
 */
require_once __DIR__ . '/test_common.inc.php';
require_once __DIR__ . '/../action/edit.inc.php';

echo "=== Edit Action Tag Saving Test ===\n";

// テスト用のページ名
$test_page = 'EditTagTest';
$filepath = koan3getWikiFileText($test_page);

// 古いファイルを削除
if (file_exists($filepath)) {
    unlink($filepath);
}

// タグディレクトリをクリーンアップ
$test_tag_dir = KONA3_DIR_DATA . '/.kona3_tag';
if (file_exists($test_tag_dir)) {
    $files = glob($test_tag_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

echo "Test 1: Save page with tags\n";

// タグを含むコンテンツを直接保存（edit.inc.phpの処理をシミュレート）
$edit_txt = "■テストページ\n\nタグ付きページです。";
$tags_str = "tag1/tag2/tag3";
$user_id = 0;

// ファイルに保存
kona3lock_save($filepath, $edit_txt);

// データベースに保存
kona3db_writePage($test_page, $edit_txt, $user_id, $tags_str);

// メタ情報を保存
$meta = kona3db_loadPageMeta($test_page);
if ($meta === null) {
    $meta = [];
}
$oldTags = isset($meta['tags']) ? $meta['tags'] : [];
$tagArray = array_map('trim', explode('/', $tags_str));
$meta['tags'] = $tagArray;
kona3db_savePageMeta($test_page, $meta);

// タグファイルシステムに保存
foreach ($oldTags as $oldTag) {
    if (!in_array($oldTag, $meta['tags'])) {
        kona3tags_removePageTag($test_page, $oldTag);
    }
}
foreach ($meta['tags'] as $tag) {
    kona3tags_addPageTag($test_page, $tag);
}

echo "Test 2: Verify meta info\n";
$meta_loaded = kona3db_loadPageMeta($test_page);
test_assert(__LINE__, $meta_loaded !== null, "Meta info should exist");
test_assert(__LINE__, isset($meta_loaded['tags']), "Meta info should have tags");
test_eq(__LINE__, 3, count($meta_loaded['tags']), "Meta info should have 3 tags");
test_assert(__LINE__, in_array('tag1', $meta_loaded['tags']), "Meta info should contain tag1");
test_assert(__LINE__, in_array('tag2', $meta_loaded['tags']), "Meta info should contain tag2");
test_assert(__LINE__, in_array('tag3', $meta_loaded['tags']), "Meta info should contain tag3");

echo "Test 3: Verify tag files\n";
$pages1 = kona3tags_getPages('tag1');
$pages2 = kona3tags_getPages('tag2');
$pages3 = kona3tags_getPages('tag3');

test_eq(__LINE__, 1, count($pages1), "tag1 should have 1 page");
test_eq(__LINE__, 1, count($pages2), "tag2 should have 1 page");
test_eq(__LINE__, 1, count($pages3), "tag3 should have 1 page");

test_eq(__LINE__, $test_page, $pages1[0]['page'], "tag1 should contain test page");
test_eq(__LINE__, $test_page, $pages2[0]['page'], "tag2 should contain test page");
test_eq(__LINE__, $test_page, $pages3[0]['page'], "tag3 should contain test page");

echo "Test 4: Update tags (remove tag2, add tag4)\n";
$new_tags_str = "tag1/tag3/tag4";
$oldTags = $meta_loaded['tags'];
$tagArray = array_map('trim', explode('/', $new_tags_str));
$meta_loaded['tags'] = $tagArray;
kona3db_savePageMeta($test_page, $meta_loaded);

// タグファイルシステムに保存
foreach ($oldTags as $oldTag) {
    if (!in_array($oldTag, $meta_loaded['tags'])) {
        kona3tags_removePageTag($test_page, $oldTag);
    }
}
foreach ($meta_loaded['tags'] as $tag) {
    kona3tags_addPageTag($test_page, $tag);
}

echo "Test 5: Verify updated tags\n";
$pages1 = kona3tags_getPages('tag1');
$pages2 = kona3tags_getPages('tag2');
$pages3 = kona3tags_getPages('tag3');
$pages4 = kona3tags_getPages('tag4');

test_eq(__LINE__, 1, count($pages1), "tag1 should still have 1 page");
test_eq(__LINE__, 0, count($pages2), "tag2 should have 0 pages (removed)");
test_eq(__LINE__, 1, count($pages3), "tag3 should still have 1 page");
test_eq(__LINE__, 1, count($pages4), "tag4 should have 1 page (newly added)");

echo "Test 6: Clear all tags\n";
kona3tags_clearPageTags($test_page);

$pages1 = kona3tags_getPages('tag1');
$pages3 = kona3tags_getPages('tag3');
$pages4 = kona3tags_getPages('tag4');

test_eq(__LINE__, 0, count($pages1), "tag1 should have 0 pages (cleared)");
test_eq(__LINE__, 0, count($pages3), "tag3 should have 0 pages (cleared)");
test_eq(__LINE__, 0, count($pages4), "tag4 should have 0 pages (cleared)");

// クリーンアップ
if (file_exists($filepath)) {
    unlink($filepath);
}
$metaFile = kona3db_getPageMetaFile($test_page);
if (file_exists($metaFile)) {
    unlink($metaFile);
}

echo "\n=== Edit Action Tag Saving Test Completed ===\n";
