<?php
require_once __DIR__ . '/test_common.inc.php';

// --- データベース層のテスト ---

// グローバル変数の初期化
global $kona3pageIds, $kona3pageIdCache;
$kona3pageIds = NULL;
$kona3pageIdCache = NULL;

// --- ページIDの管理テスト ---

// 新しいページIDの作成（テストページ1）
$test_page_name = "TestPageDB_" . time() . "_1";
kona3db_getPageId($test_page_name, TRUE); // ページを作成
$new_page_id = kona3db_getPageId($test_page_name, FALSE); // IDを取得
test_assert(__LINE__, is_numeric($new_page_id) && $new_page_id > 0, "ページID作成: 新しいページIDが作成される");

// 作成したページIDが一貫している
$same_page_id = kona3db_getPageId($test_page_name, FALSE);
test_eq(__LINE__, $new_page_id, $same_page_id, "ページID一貫性: 同じページは同じID");

// ページIDからページ名を取得（新規作成したページ）
$retrieved_name = kona3db_getPageNameById($new_page_id);
test_eq(__LINE__, $retrieved_name, $test_page_name, "ページ名取得: 新規作成ページの名前");

// 存在しないページID
$page_name = kona3db_getPageNameById(999999999, "default");
test_eq(__LINE__, $page_name, "default", "ページ名取得: 存在しないIDはデフォルト値");

// 複数のページIDを連続して作成
$page_ids = [];
for ($i = 0; $i < 5; $i++) {
    $page_name = "TestPage_Multi_" . time() . "_" . $i;
    usleep(100); // 時刻の衝突を避ける
    kona3db_getPageId($page_name, TRUE); // ページを作成
    $page_ids[] = kona3db_getPageId($page_name, FALSE); // IDを取得
}

// すべてのIDが異なることを確認
$unique_ids = array_unique($page_ids);
test_eq(__LINE__, count($unique_ids), 5, "ページID: 連続作成で異なるIDが割り当てられる");

// IDが昇順であることを確認
$sorted_ids = $page_ids;
sort($sorted_ids);
test_eq(__LINE__, $page_ids, $sorted_ids, "ページID: IDが昇順で割り当てられる");

// --- ページハッシュ関数のテスト ---

$text = "テストコンテンツです。";
$hash1 = kona3getPageHash($text);
test_assert(__LINE__, !empty($hash1) && is_string($hash1), "ハッシュ生成: 文字列が返される");

// 同じテキストは同じハッシュ
$hash2 = kona3getPageHash($text);
test_eq(__LINE__, $hash1, $hash2, "ハッシュ生成: 同じテキストは同じハッシュ");

// 異なるテキストは異なるハッシュ
$hash3 = kona3getPageHash("別のテキスト");
test_ne(__LINE__, $hash1, $hash3, "ハッシュ生成: 異なるテキストは異なるハッシュ");

// 空文字列のハッシュ
$hash_empty = kona3getPageHash("");
test_assert(__LINE__, !empty($hash_empty) && is_string($hash_empty), "ハッシュ生成: 空文字列でもハッシュが生成される");

// --- kona3getRelativePath のテスト ---

// FrontPageの相対パスを取得
$rel_path = kona3getRelativePath("FrontPage");
test_assert(__LINE__, is_string($rel_path), "相対パス取得: FrontPage");

// file:プレフィックス付きパス
$full_path = "file:" . KONA3_DIR_DATA . "/TestPage.txt";
$rel_path2 = kona3getRelativePath($full_path);
test_assert(__LINE__, is_string($rel_path2), "相対パス取得: file:プレフィックス");

// 通常のWiki名
$rel_path3 = kona3getRelativePath("Test/SubPage");
test_assert(__LINE__, is_string($rel_path3), "相対パス取得: サブページ");

// --- エッジケースのテスト ---

// 日本語ページ名
$japanese_page = "日本語ページ_" . time();
kona3db_getPageId($japanese_page, TRUE);
$japanese_page_id = kona3db_getPageId($japanese_page, FALSE);
test_assert(__LINE__, $japanese_page_id > 0, "エッジケース: 日本語ページ名");

// キャッシュをクリア
$kona3pageIdCache = NULL;
$retrieved_japanese = kona3db_getPageNameById($japanese_page_id);
test_eq(__LINE__, $retrieved_japanese, $japanese_page, "エッジケース: 日本語ページ名の取得");

// 特殊文字を含むページ名
$special_page = "Special!@#$%^&*()_" . time();
kona3db_getPageId($special_page, TRUE);
$special_page_id = kona3db_getPageId($special_page, FALSE);
test_assert(__LINE__, $special_page_id > 0, "エッジケース: 特殊文字を含むページ名");

// 非常に長いページ名
$long_page = str_repeat("A", 200) . "_" . time();
kona3db_getPageId($long_page, TRUE);
$long_page_id = kona3db_getPageId($long_page, FALSE);
test_assert(__LINE__, $long_page_id > 0, "エッジケース: 長いページ名");

// --- ハッシュ関数の追加テスト ---

// 長いテキスト
$long_text = str_repeat("これは長い本文です。\n", 1000);
$long_hash = kona3getPageHash($long_text);
test_assert(__LINE__, !empty($long_hash) && is_string($long_hash), "ハッシュ生成: 長いテキスト");

// 特殊文字を含むテキスト
$special_text = "特殊文字: <script>alert('XSS')</script> & 改行\nタブ\t引用\"'";
$special_hash = kona3getPageHash($special_text);
test_assert(__LINE__, !empty($special_hash) && is_string($special_hash), "ハッシュ生成: 特殊文字を含むテキスト");

// ハッシュの長さの確認（SHA-256は64文字）
test_eq(__LINE__, strlen($hash1), 64, "ハッシュ生成: SHA-256の長さ(64文字)");
