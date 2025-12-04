<?php
/**
 * メタ情報ファイルの確認テスト
 */
require_once __DIR__ . '/test_common.inc.php';
require_once __DIR__ . '/../action/edit.inc.php';

echo "=== Meta File Content Verification Test ===\n";

// テスト用のページ名
$test_page = 'MetaTagTest';
$filepath = koan3getWikiFileText($test_page);

// 古いファイルを削除
if (file_exists($filepath)) {
    unlink($filepath);
}
$metaFile = kona3db_getPageMetaFile($test_page);
if (file_exists($metaFile)) {
    unlink($metaFile);
}

echo "Test 1: Save page with tags\n";

// タグを含むコンテンツを直接保存
$edit_txt = "■テストページ\n\nタグ付きページです。";
$tags_str = "テスト/サンプル/確認";
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
if ($tags_str !== '') {
    $tagArray = array_map('trim', explode('/', $tags_str));
    $meta['tags'] = $tagArray;
} else {
    $meta['tags'] = [];
}
echo "  Saving meta with tags: " . json_encode($meta['tags'], JSON_UNESCAPED_UNICODE) . "\n";
$result = kona3db_savePageMeta($test_page, $meta);
echo "  Save result: " . ($result ? "SUCCESS" : "FAILED") . "\n";

// タグファイルシステムに保存
foreach ($oldTags as $oldTag) {
    if (!in_array($oldTag, $meta['tags'])) {
        kona3tags_removePageTag($test_page, $oldTag);
    }
}
foreach ($meta['tags'] as $tag) {
    kona3tags_addPageTag($test_page, $tag);
}

echo "\nTest 2: Verify meta file exists and content\n";
$metaFile = kona3db_getPageMetaFile($test_page);
echo "  Meta file path: $metaFile\n";
echo "  File exists: " . (file_exists($metaFile) ? "YES" : "NO") . "\n";

if (file_exists($metaFile)) {
    $content = file_get_contents($metaFile);
    echo "  File content:\n";
    echo "---START---\n";
    echo $content;
    echo "\n---END---\n";
    
    // JSONとしてパース
    $parsed = json_decode($content, true);
    if ($parsed !== null) {
        echo "  JSON parsed successfully\n";
        echo "  Has 'tags' key: " . (isset($parsed['tags']) ? "YES" : "NO") . "\n";
        if (isset($parsed['tags'])) {
            echo "  Tags content: " . json_encode($parsed['tags'], JSON_UNESCAPED_UNICODE) . "\n";
            echo "  Number of tags: " . count($parsed['tags']) . "\n";
        }
    } else {
        echo "  JSON parse FAILED: " . json_last_error_msg() . "\n";
    }
}

echo "\nTest 3: Load meta and verify\n";
$meta_loaded = kona3db_loadPageMeta($test_page);
echo "  Meta loaded: " . ($meta_loaded !== null ? "YES" : "NO") . "\n";
if ($meta_loaded !== null) {
    echo "  Has 'tags' key: " . (isset($meta_loaded['tags']) ? "YES" : "NO") . "\n";
    if (isset($meta_loaded['tags'])) {
        echo "  Tags: " . json_encode($meta_loaded['tags'], JSON_UNESCAPED_UNICODE) . "\n";
        test_eq(__LINE__, 3, count($meta_loaded['tags']), "Should have 3 tags");
        test_assert(__LINE__, in_array('テスト', $meta_loaded['tags']), "Should contain 'テスト'");
        test_assert(__LINE__, in_array('サンプル', $meta_loaded['tags']), "Should contain 'サンプル'");
        test_assert(__LINE__, in_array('確認', $meta_loaded['tags']), "Should contain '確認'");
    }
}

// クリーンアップ
if (file_exists($filepath)) {
    unlink($filepath);
}
if (file_exists($metaFile)) {
    unlink($metaFile);
}

echo "\n=== Meta File Content Verification Test Completed ===\n";
