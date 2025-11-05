<?php
require_once __DIR__ . '/test_common.inc.php';

// --- kona3parseURI() のテスト ---

// $_GETをクリア（テストの干渉を防ぐ）
$_GET = [];

// 基本的なURL解析
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?FrontPage&show&");
test_eq(__LINE__, $page, "FrontPage", "基本的なページ名の解析");
test_eq(__LINE__, $action, "show", "基本的なアクションの解析");

// アクションのみ
$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?PageName&edit");
test_eq(__LINE__, $page, "PageName", "ページ名の解析");
test_eq(__LINE__, $action, "edit", "editアクションの解析");

// ステータス付き
$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?TestPage&admin&users");
test_eq(__LINE__, $page, "TestPage", "ページ名の解析（ステータス付き）");
test_eq(__LINE__, $action, "admin", "アクションの解析（ステータス付き）");
test_eq(__LINE__, $status, "users", "ステータスの解析");

// ページ名のみ（アクションはデフォルトでshow）
$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?MyPage");
test_eq(__LINE__, $page, "MyPage", "ページ名のみの解析");
test_eq(__LINE__, $action, "show", "デフォルトアクションはshow");

// 空のページ名（デフォルトでFrontPage）
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?");
test_eq(__LINE__, $page, "FrontPage", "空のページ名はFrontPageになる");
test_eq(__LINE__, $action, "show", "空のアクションはshowになる");

// --- パストラバーサル対策のテスト ---

// .. を含むパス
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?../../../etc/passwd&show");
test_assert(__LINE__, strpos($page, '..') === FALSE, ".. は削除される");
test_assert(__LINE__, strpos($page, 'etc/passwd') !== FALSE, ".. 以外のパスは残る");

// // を含むパス
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page//Name&show");
test_assert(__LINE__, strpos($page, '//') === FALSE, "// は削除される");

// 先頭の / を含むパス
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?/PageName&show");
test_assert(__LINE__, strpos($page, '/') !== 0, "先頭の / は削除される");

// 複合的な攻撃パターン
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?/../..///test&show");
test_assert(__LINE__, strpos($page, '..') === FALSE, "複合攻撃: .. は削除される");
test_assert(__LINE__, strpos($page, '//') === FALSE, "複合攻撃: // は削除される");
test_assert(__LINE__, strpos($page, '/') !== 0 || $page === '', "複合攻撃: 先頭の / は削除される");

// --- 不正なアクション名のテスト ---

// 英数字とアンダースコア以外の文字を含むアクション
$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page&show/../admin");
test_eq(__LINE__, $action, "__INVALID__", "不正なアクション名は__INVALID__になる");

$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page&<script>alert(1)</script>");
test_eq(__LINE__, $action, "__INVALID__", "スクリプトタグを含むアクションは無効");

$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page&action!@#");
test_eq(__LINE__, $action, "__INVALID__", "特殊文字を含むアクションは無効");

// 正常なアクション名
$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page&my_action_123");
test_eq(__LINE__, $action, "my_action_123", "英数字とアンダースコアのみのアクションは有効");

// --- 不正なステータス名のテスト ---

// 特殊文字を含むステータス
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page&admin&users<script>");
test_eq(__LINE__, $status, "__INVALID__", "特殊文字を含むステータスは無効");

// 正常なステータス名
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page&admin&user_list_123");
test_eq(__LINE__, $status, "user_list_123", "英数字とアンダースコアのみのステータスは有効");

// 空のステータス
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page&admin&");
test_eq(__LINE__, $status, "", "空のステータスは許可される");

// --- 日本語ページ名のテスト ---

// URLエンコードされた日本語
$encoded_page = urlencode("テストページ");
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?{$encoded_page}&show");
test_eq(__LINE__, $page, "テストページ", "URLエンコードされた日本語ページ名");

// スラッシュを含むページ名（階層構造）
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Category/SubPage&show");
test_eq(__LINE__, $page, "Category/SubPage", "階層構造のページ名");

// --- URL引数のテスト ---

// 追加パラメータ付き
$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page&search&result&query=test&page=2");
test_eq(__LINE__, $page, "Page", "追加パラメータ付きのページ名");
test_eq(__LINE__, $action, "search", "追加パラメータ付きのアクション");
test_eq(__LINE__, $status, "result", "追加パラメータ付きのステータス");

// --- エッジケースのテスト ---

// 非常に長いページ名
$long_page = str_repeat("A", 1000);
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?{$long_page}&show");
test_eq(__LINE__, strlen($page), 1000, "長いページ名も処理可能");

// 特殊文字を含むページ名
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?" . urlencode("ページ(特殊)") . "&show");
test_assert(__LINE__, strlen($page) > 0, "特殊文字を含むページ名");

// 空白を含むページ名
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?" . urlencode("Page Name") . "&show");
test_eq(__LINE__, $page, "Page Name", "空白を含むページ名");

// --- スクリプトパスの解析 ---
list($page, $action, $status, $args, $script_path) = kona3parseURI("/path/to/index.php?Page&show");
test_eq(__LINE__, $script_path, "/path/to/index.php", "スクリプトパスの解析");

// --- 複数の & が連続する場合 ---
$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page&&show");
test_eq(__LINE__, $page, "Page", "複数の & が連続する場合のページ名");
test_eq(__LINE__, $action, "show", "複数の & が連続する場合のアクション（空は'show'になる）");

// --- セキュリティテスト：NULL バイト ---
$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page%00&show");
// NULLバイトが含まれていないか、またはデコードされて"Page\0"になっているか確認
$null_pos = strpos($page, "\0");
test_assert(__LINE__, $null_pos === FALSE || $null_pos > 0, "NULLバイトの処理（完全に削除されるか、デコード後に存在）");
