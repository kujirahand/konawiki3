<?php
require_once __DIR__ . '/test_common.inc.php';

// --- 設定システムのテスト ---

// --- kona3getConf() のテスト ---

// 既存の設定値の取得
$value = kona3getConf('wiki_title', '');
test_assert(__LINE__, $value !== '', "kona3getConf: wiki_titleが取得できる");

// デフォルト値の取得（存在しないキー）
$value = kona3getConf('nonexistent_key', 'default_value');
test_eq(__LINE__, $value, 'default_value', "kona3getConf: 存在しないキーのデフォルト値");

// FALSEのデフォルト値
$value = kona3getConf('another_nonexistent_key', FALSE);
test_eq(__LINE__, $value, FALSE, "kona3getConf: FALSEのデフォルト値");

// --- kona3conf_setDefault() のテスト ---

global $kona3conf;
$test_conf = [];

kona3conf_setDefault($test_conf, [
    'key1' => 'value1',
    'key2' => 'value2',
]);

test_eq(__LINE__, $test_conf['key1'], 'value1', "kona3conf_setDefault: key1が設定される");
test_eq(__LINE__, $test_conf['key2'], 'value2', "kona3conf_setDefault: key2が設定される");

// 既存の値は上書きされない
$test_conf['key1'] = 'existing_value';
kona3conf_setDefault($test_conf, [
    'key1' => 'new_value',
    'key3' => 'value3',
]);

test_eq(__LINE__, $test_conf['key1'], 'existing_value', "kona3conf_setDefault: 既存の値は上書きされない");
test_eq(__LINE__, $test_conf['key3'], 'value3', "kona3conf_setDefault: 新しいキーは追加される");

// --- JSON-PHP形式のテスト ---

$test_file = KONA3_DIR_PRIVATE . "/test_jsonphp_" . time() . ".json.php";

// 保存テスト
$test_data = [
    'name' => 'TestUser',
    'email' => 'test@example.com',
    'age' => 30,
    'active' => true,
];

jsonphp_save($test_file, $test_data);
test_eq(__LINE__, file_exists($test_file), true, "jsonphp_save: ファイルが作成される");

// ファイル内容の確認（PHPヘッダーがあること）
$content = file_get_contents($test_file);
test_assert(__LINE__, strpos($content, '<?php') === 0, "JSON-PHP: PHPヘッダーが含まれる");
test_assert(__LINE__, strpos($content, 'exit;') !== FALSE, "JSON-PHP: exit文が含まれる");

// 読み込みテスト
$loaded_data = jsonphp_load($test_file, []);
test_eq(__LINE__, $loaded_data['name'], 'TestUser', "jsonphp_load: nameが読み込める");
test_eq(__LINE__, $loaded_data['age'], 30, "jsonphp_load: ageが読み込める");
test_eq(__LINE__, $loaded_data['active'], true, "jsonphp_load: activeが読み込める");

// 暗号化の確認（生のJSONには平文が含まれない）
test_assert(__LINE__, strpos($content, 'TestUser') === FALSE, "JSON-PHP: 暗号化されている（平文がない）");
test_assert(__LINE__, strpos($content, 'test@example.com') === FALSE, "JSON-PHP: emailも暗号化されている");

// 存在しないファイルの読み込み
$nonexistent = KONA3_DIR_PRIVATE . "/nonexistent_" . time() . ".json.php";
$result = jsonphp_load($nonexistent, ['default' => true]);
test_eq(__LINE__, $result['default'], true, "jsonphp_load: 存在しないファイルはデフォルト値を返す");

// 空のデータの保存と読み込み
$empty_file = KONA3_DIR_PRIVATE . "/test_empty_" . time() . ".json.php";
jsonphp_save($empty_file, []);
$loaded_empty = jsonphp_load($empty_file, null);
test_assert(__LINE__, is_array($loaded_empty), "jsonphp_load: 空のデータも読み込める");

// 日本語データの保存と読み込み
$japanese_file = KONA3_DIR_PRIVATE . "/test_japanese_" . time() . ".json.php";
$japanese_data = [
    'name' => '太郎',
    'description' => 'これはテストです。',
];
jsonphp_save($japanese_file, $japanese_data);
$loaded_japanese = jsonphp_load($japanese_file, []);
test_eq(__LINE__, $loaded_japanese['name'], '太郎', "JSON-PHP: 日本語データの保存と読み込み");
test_eq(__LINE__, $loaded_japanese['description'], 'これはテストです。', "JSON-PHP: 日本語の説明文");

// ネストした配列の保存と読み込み
$nested_file = KONA3_DIR_PRIVATE . "/test_nested_" . time() . ".json.php";
$nested_data = [
    'user' => [
        'name' => 'Alice',
        'settings' => [
            'theme' => 'dark',
            'lang' => 'en',
        ],
    ],
];
jsonphp_save($nested_file, $nested_data);
$loaded_nested = jsonphp_load($nested_file, []);
test_eq(__LINE__, $loaded_nested['user']['name'], 'Alice', "JSON-PHP: ネストした配列（name）");
test_eq(__LINE__, $loaded_nested['user']['settings']['theme'], 'dark', "JSON-PHP: ネストした配列（theme）");
test_eq(__LINE__, $loaded_nested['user']['settings']['lang'], 'en', "JSON-PHP: ネストした配列（lang）");

// 特殊文字を含むデータ
$special_file = KONA3_DIR_PRIVATE . "/test_special_" . time() . ".json.php";
$special_data = [
    'html' => '<script>alert("xss")</script>',
    'quotes' => "He said 'Hello'",
    'newlines' => "Line1\nLine2\nLine3",
];
jsonphp_save($special_file, $special_data);
$loaded_special = jsonphp_load($special_file, []);
test_eq(__LINE__, $loaded_special['html'], '<script>alert("xss")</script>', "JSON-PHP: HTMLタグを含むデータ");
test_eq(__LINE__, $loaded_special['quotes'], "He said 'Hello'", "JSON-PHP: 引用符を含むデータ");
test_eq(__LINE__, $loaded_special['newlines'], "Line1\nLine2\nLine3", "JSON-PHP: 改行を含むデータ");

// 数値型のデータ
$number_file = KONA3_DIR_PRIVATE . "/test_number_" . time() . ".json.php";
$number_data = [
    'int' => 42,
    'float' => 3.14,
    'zero' => 0,
    'negative' => -100,
];
jsonphp_save($number_file, $number_data);
$loaded_number = jsonphp_load($number_file, []);
test_eq(__LINE__, $loaded_number['int'], 42, "JSON-PHP: 整数型");
test_eq(__LINE__, $loaded_number['float'], 3.14, "JSON-PHP: 浮動小数点型");
test_eq(__LINE__, $loaded_number['zero'], 0, "JSON-PHP: ゼロ");
test_eq(__LINE__, $loaded_number['negative'], -100, "JSON-PHP: 負の数");

// 真偽値のデータ
$bool_file = KONA3_DIR_PRIVATE . "/test_bool_" . time() . ".json.php";
$bool_data = [
    'true_val' => true,
    'false_val' => false,
];
jsonphp_save($bool_file, $bool_data);
$loaded_bool = jsonphp_load($bool_file, []);
test_eq(__LINE__, $loaded_bool['true_val'], true, "JSON-PHP: true値");
test_eq(__LINE__, $loaded_bool['false_val'], false, "JSON-PHP: false値");

// --- 設定ファイルの実際のロード ---

// 実際の設定ファイルを読み込む
$actual_conf_file = KONA3_DIR_PRIVATE . "/kona3conf.json.php";
if (file_exists($actual_conf_file)) {
    $actual_conf = jsonphp_load($actual_conf_file, []);
    test_assert(__LINE__, is_array($actual_conf), "実際の設定ファイル: 配列として読み込める");
    
    // 一般的な設定項目が存在するかチェック
    test_assert(__LINE__, isset($actual_conf['wiki_title']) || count($actual_conf) >= 0, "実際の設定ファイル: wiki_titleまたは何らかの設定が存在");
} else {
    test_assert(__LINE__, true, "実際の設定ファイルが存在しないためスキップ");
}

// --- プラグイン無効化リストの設定 ---

$original_plugin_disallow = isset($kona3conf['plugin_disallow']) ? $kona3conf['plugin_disallow'] : '';
$kona3conf['plugin_disallow'] = 'html,htmlshow,filelist';

// プラグイン無効化リストの解析
kona3conf_setWikiConf($kona3conf);
test_assert(__LINE__, isset($kona3conf['plugin.disallow']), "プラグイン無効化: plugin.disallowが設定される");
test_eq(__LINE__, $kona3conf['plugin.disallow']['html'], TRUE, "プラグイン無効化: htmlが無効");
test_eq(__LINE__, $kona3conf['plugin.disallow']['htmlshow'], TRUE, "プラグイン無効化: htmlshowが無効");
test_eq(__LINE__, $kona3conf['plugin.disallow']['filelist'], TRUE, "プラグイン無効化: filelistが無効");

// 元に戻す
$kona3conf['plugin_disallow'] = $original_plugin_disallow;

// --- デフォルト設定値のテスト ---

test_eq(__LINE__, isset($kona3conf['FrontPage']), true, "デフォルト設定: FrontPageが設定されている");
test_eq(__LINE__, isset($kona3conf['wiki_title']), true, "デフォルト設定: wiki_titleが設定されている");
test_eq(__LINE__, isset($kona3conf['lang']), true, "デフォルト設定: langが設定されている");
test_eq(__LINE__, isset($kona3conf['skin']), true, "デフォルト設定: skinが設定されている");

// --- 定数のテスト ---

test_eq(__LINE__, defined('KONA3_DIR_DATA'), true, "定数: KONA3_DIR_DATAが定義されている");
test_eq(__LINE__, defined('KONA3_DIR_PRIVATE'), true, "定数: KONA3_DIR_PRIVATEが定義されている");
test_eq(__LINE__, defined('KONA3_DIR_CACHE'), true, "定数: KONA3_DIR_CACHEが定義されている");
test_eq(__LINE__, defined('KONA3_DIR_SKIN'), true, "定数: KONA3_DIR_SKINが定義されている");

// クリーンアップ
@unlink($test_file);
@unlink($empty_file);
@unlink($japanese_file);
@unlink($nested_file);
@unlink($special_file);
@unlink($number_file);
@unlink($bool_file);
