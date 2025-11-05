<?php
require_once __DIR__ . '/test_common.inc.php';

// --- プラグインシステムのテスト ---

// --- kona3getPName() のテスト ---
// プラグイン名のサニタイズ
$clean = kona3getPName("test_plugin");
test_eq(__LINE__, $clean, "test_plugin", "プラグイン名: アンダースコア含む");

$clean = kona3getPName("test-plugin");
test_eq(__LINE__, $clean, "test-plugin", "プラグイン名: ハイフン含む");

$clean = kona3getPName("test123");
test_eq(__LINE__, $clean, "test123", "プラグイン名: 数字含む");

// 不正な文字を含む
$clean = kona3getPName("test/plugin");
test_eq(__LINE__, $clean, "testplugin", "プラグイン名: スラッシュは削除");

$clean = kona3getPName("test.plugin");
test_eq(__LINE__, $clean, "testplugin", "プラグイン名: ドットは削除");

$clean = kona3getPName("test<script>");
test_eq(__LINE__, $clean, "testscript", "プラグイン名: タグは削除");

$clean = kona3getPName("../../../etc/passwd");
test_eq(__LINE__, strpos($clean, '..') === FALSE && strpos($clean, '/') === FALSE, true, "プラグイン名: パストラバーサル対策");

// --- kona3getPluginName() のテスト ---

// 通常のプラグイン名
$name = kona3getPluginName("test");
test_eq(__LINE__, $name, "test", "プラグイン名の取得");

// スラッシュを含む不正なプラグイン名
$name = kona3getPluginName("test/evil");
test_assert(__LINE__, strpos($name, '/') === FALSE, "プラグイン名: スラッシュは削除される");

// ドットを含む不正なプラグイン名
$name = kona3getPluginName("test.evil");
test_assert(__LINE__, strpos($name, '.') === FALSE, "プラグイン名: ドットは削除される");

// --- エイリアスのテスト ---
global $kona3conf;

// エイリアスを設定
$original_alias = isset($kona3conf['plugin_alias']) ? $kona3conf['plugin_alias'] : [];
$kona3conf['plugin_alias'] = [
    'short' => 'long_plugin_name',
    'alias1' => 'real_plugin1',
];

$name = kona3getPluginName("short");
test_eq(__LINE__, $name, urlencode("long_plugin_name"), "プラグインエイリアス: short → long_plugin_name");

$name = kona3getPluginName("alias1");
test_eq(__LINE__, $name, urlencode("real_plugin1"), "プラグインエイリアス: alias1 → real_plugin1");

// エイリアスがない場合はそのまま
$name = kona3getPluginName("no_alias");
test_eq(__LINE__, $name, "no_alias", "プラグインエイリアス: エイリアスがない場合");

// エイリアスを元に戻す
$kona3conf['plugin_alias'] = $original_alias;

// --- kona3getPluginPathInfo() のテスト ---

$info = kona3getPluginPathInfo("test");
test_assert(__LINE__, isset($info['file']), "プラグイン情報: fileキーが存在");
test_assert(__LINE__, isset($info['func']), "プラグイン情報: funcキーが存在");
test_assert(__LINE__, isset($info['init']), "プラグイン情報: initキーが存在");
test_assert(__LINE__, isset($info['disallow']), "プラグイン情報: disallowキーが存在");

// ファイルパスにプラグインディレクトリが含まれる
test_assert(__LINE__, strpos($info['file'], 'plugins') !== FALSE, "プラグイン情報: ファイルパスにpluginsが含まれる");
test_assert(__LINE__, strpos($info['file'], '.inc.php') !== FALSE, "プラグイン情報: ファイルパスに.inc.phpが含まれる");

// 関数名の形式チェック
test_assert(__LINE__, strpos($info['func'], 'kona3plugins_') === 0, "プラグイン情報: 関数名はkona3plugins_で始まる");
test_assert(__LINE__, strpos($info['func'], '_execute') !== FALSE, "プラグイン情報: 関数名は_executeを含む");

// 初期化関数名の形式チェック
test_assert(__LINE__, strpos($info['init'], 'kona3plugins_') === 0, "プラグイン情報: 初期化関数名はkona3plugins_で始まる");
test_assert(__LINE__, strpos($info['init'], '_init') !== FALSE, "プラグイン情報: 初期化関数名は_initを含む");

// --- プラグインの無効化テスト ---

// 無効化リストを設定
$original_disallow = isset($kona3conf['plugin.disallow']) ? $kona3conf['plugin.disallow'] : [];
$kona3conf['plugin.disallow'] = [
    'evil_plugin' => true,
    'dangerous' => true,
];

$info = kona3getPluginPathInfo("evil_plugin");
test_eq(__LINE__, $info['disallow'], true, "プラグイン無効化: evil_pluginは無効");
test_eq(__LINE__, $info['file'], '', "プラグイン無効化: ファイルパスは空");

$info = kona3getPluginPathInfo("dangerous");
test_eq(__LINE__, $info['disallow'], true, "プラグイン無効化: dangerousは無効");

$info = kona3getPluginPathInfo("safe_plugin");
test_eq(__LINE__, $info['disallow'], false, "プラグイン無効化: safe_pluginは有効");
test_ne(__LINE__, $info['file'], '', "プラグイン無効化: safe_pluginのファイルパスは空でない");

// 無効化リストを元に戻す
$kona3conf['plugin.disallow'] = $original_disallow;

// --- 実際のプラグインファイルの存在確認 ---

// br プラグイン（存在するプラグイン）
$info = kona3getPluginPathInfo("br");
test_eq(__LINE__, file_exists($info['file']), true, "実在するプラグイン: brプラグインのファイルが存在");

// code プラグイン（存在するプラグイン）
$info = kona3getPluginPathInfo("code");
test_eq(__LINE__, file_exists($info['file']), true, "実在するプラグイン: codeプラグインのファイルが存在");

// 存在しないプラグイン
$info = kona3getPluginPathInfo("nonexistent_plugin_xyz");
test_eq(__LINE__, file_exists($info['file']), false, "存在しないプラグイン: ファイルが存在しない");

// --- プラグイン関数の呼び出しテスト ---

// br プラグインを読み込んで実行
$info = kona3getPluginPathInfo("br");
if (file_exists($info['file'])) {
    include_once($info['file']);
    $func = $info['func'];
    if (function_exists($func)) {
        $result = call_user_func($func, []);
        test_assert(__LINE__, is_string($result), "プラグイン実行: brプラグインが文字列を返す");
        test_assert(__LINE__, strpos($result, '<br') !== FALSE, "プラグイン実行: brプラグインが<br>タグを返す");
    } else {
        test_assert(__LINE__, true, "brプラグインの関数が存在しないためスキップ");
    }
} else {
    test_assert(__LINE__, true, "brプラグインが存在しないためスキップ");
}

// --- kona3_getPluginInfo / kona3_setPluginInfo のテスト ---

// プラグイン情報の設定と取得
kona3_setPluginInfo("test_plugin", "version", "1.0.0");
$version = kona3_getPluginInfo("test_plugin", "version");
test_eq(__LINE__, $version, "1.0.0", "プラグイン情報: 設定と取得");

kona3_setPluginInfo("test_plugin", "author", "TestAuthor");
$author = kona3_getPluginInfo("test_plugin", "author");
test_eq(__LINE__, $author, "TestAuthor", "プラグイン情報: author");

// デフォルト値
$undefined = kona3_getPluginInfo("test_plugin", "undefined_key", "default_value");
test_eq(__LINE__, $undefined, "default_value", "プラグイン情報: デフォルト値");

// 存在しない情報
$noinfo = kona3_getPluginInfo("nonexistent", "key", FALSE);
test_eq(__LINE__, $noinfo, FALSE, "プラグイン情報: 存在しない情報はFALSE");

// --- セキュリティテスト ---

// パストラバーサル攻撃の防止
$info = kona3getPluginPathInfo("../../etc/passwd");
test_assert(__LINE__, strpos($info['file'], '..') === FALSE, "セキュリティ: パストラバーサル対策（..が含まれない）");
test_assert(__LINE__, strpos($info['file'], 'plugins') !== FALSE, "セキュリティ: pluginsディレクトリ内のパス");

// ディレクトリトラバーサル（スラッシュ）
$name = kona3getPluginName("../admin");
test_assert(__LINE__, strpos($name, '/') === FALSE, "セキュリティ: スラッシュは削除される");
test_assert(__LINE__, strpos($name, '..') === FALSE, "セキュリティ: .. は削除される");

// ドットによる拡張子偽装の防止
$name = kona3getPluginName("evil.php");
test_assert(__LINE__, strpos($name, '.php') === FALSE, "セキュリティ: .phpは削除される");

// --- エッジケースのテスト ---

// 空のプラグイン名
$name = kona3getPluginName("");
test_eq(__LINE__, $name, "", "エッジケース: 空のプラグイン名");

// 非常に長いプラグイン名
$long_name = str_repeat("a", 1000);
$name = kona3getPluginName($long_name);
test_assert(__LINE__, strlen($name) > 0, "エッジケース: 長いプラグイン名");

// 日本語のプラグイン名
$name = kona3getPluginName("テスト");
test_assert(__LINE__, strlen($name) > 0, "エッジケース: 日本語プラグイン名");
test_assert(__LINE__, $name !== "テスト", "エッジケース: 日本語はURLエンコードされる");

// 特殊文字を含むプラグイン名
$name = kona3getPluginName("test!@#$%^&*()");
test_assert(__LINE__, preg_match('/^[a-zA-Z0-9_\-\%]*$/', $name), "エッジケース: 特殊文字は削除またはエンコードされる");

// --- プラグインエイリアスの連鎖テスト ---
$kona3conf['plugin_alias'] = [
    'a' => 'b',
    // 'b' => 'c', // 連鎖エイリアスはサポートされていない想定
];

$name = kona3getPluginName("a");
test_eq(__LINE__, $name, urlencode("b"), "プラグインエイリアス: aはbにマップされる");

// エイリアスをクリア
$kona3conf['plugin_alias'] = $original_alias;
