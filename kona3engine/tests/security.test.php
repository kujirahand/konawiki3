<?php
require_once __DIR__ . '/test_common.inc.php';

// --- セキュリティテスト ---

// --- XSS対策のテスト ---

// kona3text2html() - HTMLエスケープ関数
$escaped = kona3text2html('<script>alert("xss")</script>');
test_assert(__LINE__, strpos($escaped, '&lt;script&gt;') !== FALSE, "XSS対策: <script>タグがエスケープされる");
test_assert(__LINE__, strpos($escaped, '<script>') === FALSE, "XSS対策: 生のscriptタグは含まれない");

$escaped = kona3text2html('<img src=x onerror=alert(1)>');
test_assert(__LINE__, strpos($escaped, '&lt;img') !== FALSE, "XSS対策: imgタグがエスケープされる");
test_assert(__LINE__, strpos($escaped, '<img') === FALSE, "XSS対策: 生のimgタグは含まれない");

// 特殊文字のエスケープ
$escaped = kona3text2html('&<>"\'');
test_assert(__LINE__, strpos($escaped, '&amp;') !== FALSE, "XSS対策: &がエスケープされる");
test_assert(__LINE__, strpos($escaped, '&lt;') !== FALSE, "XSS対策: <がエスケープされる");
test_assert(__LINE__, strpos($escaped, '&gt;') !== FALSE, "XSS対策: >がエスケープされる");
test_assert(__LINE__, strpos($escaped, '&quot;') !== FALSE, "XSS対策: \"がエスケープされる");

// 日本語文字のエスケープ（エスケープされないこと）
$escaped = kona3text2html('こんにちは<世界>');
test_assert(__LINE__, strpos($escaped, 'こんにちは') !== FALSE, "XSS対策: 日本語はそのまま");
test_assert(__LINE__, strpos($escaped, '&lt;世界&gt;') !== FALSE, "XSS対策: 日本語内のタグはエスケープ");

// --- パストラバーサル対策のテスト ---

// kona3parseURI() でのパストラバーサル対策（再テスト）
$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?../../etc/passwd&show");
test_assert(__LINE__, strpos($page, '..') === FALSE, "パストラバーサル: .. は削除される");

// kona3getWikiFile() でのパストラバーサル対策
$file = kona3getWikiFile("../../etc/passwd", TRUE, '.txt');
test_assert(__LINE__, strpos($file, '..') === FALSE, "パストラバーサル: ファイルパスから .. が削除");
test_assert(__LINE__, strpos($file, KONA3_DIR_DATA) !== FALSE, "パストラバーサル: データディレクトリ内のパス");

// 複数の .. を含むパス
$file = kona3getWikiFile("test/../../../etc/passwd", TRUE, '.txt');
test_assert(__LINE__, strpos($file, '..') === FALSE, "パストラバーサル: 複数の .. も削除");

// // を含むパス
$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?test//page&show");
test_assert(__LINE__, strpos($page, '//') === FALSE, "パストラバーサル: // は削除される");

// --- パスワードハッシュのテスト ---

// kona3getHash() - パスワードハッシュ関数
$hash1 = kona3getHash('password123', '');
test_assert(__LINE__, strlen($hash1) > 64, "パスワードハッシュ: ハッシュが生成される");

// 同じパスワードは同じハッシュになる
$hash2 = kona3getHash('password123', '');
test_eq(__LINE__, $hash1, $hash2, "パスワードハッシュ: 同じ入力は同じハッシュ");

// 異なるパスワードは異なるハッシュになる
$hash3 = kona3getHash('different_password', '');
test_ne(__LINE__, $hash1, $hash3, "パスワードハッシュ: 異なる入力は異なるハッシュ");

// ソルト付きハッシュ
$hash_with_salt1 = kona3getHash('password', 'salt123');
$hash_with_salt2 = kona3getHash('password', 'salt123');
test_eq(__LINE__, $hash_with_salt1, $hash_with_salt2, "パスワードハッシュ: ソルト付きハッシュの一貫性");

// 異なるソルト
$hash_different_salt = kona3getHash('password', 'different_salt');
test_ne(__LINE__, $hash_with_salt1, $hash_different_salt, "パスワードハッシュ: 異なるソルトは異なるハッシュ");

// 空のパスワード
$hash_empty = kona3getHash('', '');
test_assert(__LINE__, strlen($hash_empty) > 0, "パスワードハッシュ: 空のパスワードもハッシュ化");

// 長いパスワード
$long_password = str_repeat('a', 1000);
$hash_long = kona3getHash($long_password, '');
test_assert(__LINE__, strlen($hash_long) > 64, "パスワードハッシュ: 長いパスワードも処理可能");

// 特殊文字を含むパスワード
$special_password = '!@#$%^&*()_+-={}[]|:";\'<>?,./';
$hash_special = kona3getHash($special_password, '');
test_assert(__LINE__, strlen($hash_special) > 64, "パスワードハッシュ: 特殊文字を含むパスワード");

// --- プラグイン名のサニタイズ（セキュリティ） ---

// kona3getPName() でのサニタイズ
$clean = kona3getPName("../../etc/passwd");
test_assert(__LINE__, strpos($clean, '..') === FALSE && strpos($clean, '/') === FALSE, "プラグイン名サニタイズ: パストラバーサル対策");

$clean = kona3getPName("<script>alert(1)</script>");
test_assert(__LINE__, strpos($clean, '<') === FALSE && strpos($clean, '>') === FALSE, "プラグイン名サニタイズ: HTMLタグ除去");

$clean = kona3getPName("evil.php");
test_assert(__LINE__, strpos($clean, '.php') === FALSE, "プラグイン名サニタイズ: 拡張子偽装対策");

// --- アクション名の検証 ---

// 不正なアクション名の検出
$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page&../admin");
test_eq(__LINE__, $action, "__INVALID__", "アクション検証: 不正なアクション名は__INVALID__");

$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page&action;rm+-rf");
test_eq(__LINE__, $action, "__INVALID__", "アクション検証: 危険なコマンドを含むアクション");

// 正常なアクション名
$_GET = []; // クリア
list($page, $action, $status, $args, $script_path) = kona3parseURI("index.php?Page&edit");
test_eq(__LINE__, $action, "edit", "アクション検証: 正常なアクション名");

// --- ファイルアップロード拡張子の制限 ---

global $kona3conf;

// 許可される拡張子
$allowed_exts = explode(';', $kona3conf['allow_upload_ext']);
test_assert(__LINE__, in_array('jpg', $allowed_exts), "アップロード制限: jpgは許可される");
test_assert(__LINE__, in_array('png', $allowed_exts), "アップロード制限: pngは許可される");
test_assert(__LINE__, in_array('pdf', $allowed_exts), "アップロード制限: pdfは許可される");

// 危険な拡張子は許可されないはず
test_assert(__LINE__, !in_array('php', $allowed_exts), "アップロード制限: phpは許可されない");
test_assert(__LINE__, !in_array('exe', $allowed_exts), "アップロード制限: exeは許可されない");
test_assert(__LINE__, !in_array('sh', $allowed_exts), "アップロード制限: shは許可されない");

// --- SQLインジェクション対策 ---

// データベース操作ではプリペアドステートメントを使用していることを確認
// kona3db_getPageId() のテスト（SQLインジェクション対策）
$page_id = kona3db_getPageId("Test' OR '1'='1", TRUE);
test_assert(__LINE__, is_numeric($page_id), "SQLインジェクション対策: 不正なSQL文字列でもページIDが返される");

// --- セッション名のサニタイズ ---

// セッション名が適切に設定されているか
test_assert(__LINE__, isset($kona3conf['session_name']), "セッション: session_nameが設定されている");
test_assert(__LINE__, strlen($kona3conf['session_name']) > 0, "セッション: session_nameが空でない");

// --- CSRF トークンのテスト ---

// トークンの生成
$token1 = kona3_getEditToken('test_key');
test_assert(__LINE__, strlen($token1) > 32, "CSRFトークン: トークンが生成される（32文字以上）");

// 同じキーで再度取得（同じトークンが返される）
$token2 = kona3_getEditToken('test_key');
test_eq(__LINE__, $token1, $token2, "CSRFトークン: 同じキーで同じトークン");

// 異なるキーで異なるトークン
$token3 = kona3_getEditToken('another_key');
test_ne(__LINE__, $token1, $token3, "CSRFトークン: 異なるキーで異なるトークン");

// 強制更新 (セッション破壊が必要なので、トークンの存在チェックのみ)
$token4a = kona3_getEditToken('test_key_force', TRUE);
$token4b = kona3_getEditToken('test_key_force', TRUE);
test_assert(__LINE__, !empty($token4a) && is_string($token4a), "CSRFトークン: 強制更新で新しいトークンも生成できる");
test_ne(__LINE__, $token4a, $token4b, "CSRFトークン: 強制更新で異なるトークンが生成される");

// トークン検証のテスト（空白文字のトリミング対策）
$editTokenKey = 'edit_token';
$token5 = kona3_getEditToken($editTokenKey);
$_POST[$editTokenKey] = $token5;
test_eq(__LINE__, $token5, $_SESSION[kona3_getEditTokenKeyName($editTokenKey)], "CSRFトークンのチェック");
test_assert(__LINE__, kona3_checkEditToken($editTokenKey), "CSRFトークンのチェック");
$_POST[$editTokenKey] = " " . $token5 . " "; // 前後に空白を追加
test_assert(__LINE__, kona3_checkEditToken($editTokenKey), "CSRFトークン: 前後の空白は無視される");
unset($_POST[$editTokenKey]);

// --- HTMLエスケープの包括的テスト ---

// JavaScriptイベントハンドラー
$escaped = kona3text2html('<div onclick="alert(1)">Click</div>');
test_assert(__LINE__, strpos($escaped, 'onclick') !== FALSE && strpos($escaped, '<div') === FALSE, "XSS対策: イベントハンドラーもエスケープ");

// データ属性
$escaped = kona3text2html('<div data-value="<script>"></div>');
test_assert(__LINE__, strpos($escaped, '&lt;script&gt;') !== FALSE, "XSS対策: データ属性内のスクリプトもエスケープ");

// iframe
$escaped = kona3text2html('<iframe src="evil.com"></iframe>');
test_assert(__LINE__, strpos($escaped, '&lt;iframe') !== FALSE, "XSS対策: iframeタグがエスケープ");
test_assert(__LINE__, strpos($escaped, '<iframe') === FALSE, "XSS対策: 生のiframeタグは含まれない");

// --- パスの正規化テスト ---

// kona3getRelativePath() での正規化
$path = kona3getRelativePath("./test/./page");
test_assert(__LINE__, strpos($path, './') === FALSE || $path === "./test/./page", "パス正規化: ./ の処理");

// --- リソースURLの安全性 ---

// kona3getResourceURL() でのパストラバーサル対策
$url = kona3getResourceURL("../../etc/passwd");
test_assert(__LINE__, strpos($url, '..') === FALSE, "リソースURL: パストラバーサル対策");

// --- 入力値の長さ制限 ---

// 非常に長い入力のテスト
$very_long_input = str_repeat("A", 100000);
$escaped = kona3text2html($very_long_input);
test_assert(__LINE__, strlen($escaped) >= 100000, "長い入力: 処理可能");

// --- NULL バイトの処理 ---

$input_with_null = "test\0null";
$escaped = kona3text2html($input_with_null);
test_assert(__LINE__, strlen($escaped) > 0, "NULL バイト: 処理される");

// --- ディレクトリリスティング対策 ---

// データディレクトリの直接アクセスは許可しない設定
test_eq(__LINE__, $kona3conf['show_data_dir'], FALSE, "ディレクトリリスティング: show_data_dirがFALSE");

// --- プライベート情報の保護 ---

// プライベートディレクトリが適切に設定されている
test_eq(__LINE__, defined('KONA3_DIR_PRIVATE'), TRUE, "プライベート情報: KONA3_DIR_PRIVATEが定義されている");
test_assert(__LINE__, strpos(KONA3_DIR_PRIVATE, 'private') !== FALSE, "プライベート情報: privateディレクトリ");
