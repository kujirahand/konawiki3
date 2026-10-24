<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/jsonphp.lib.php';
require_once KONA3_DIR_ENGINE . '/kona3login.inc.php';

// 一時テストファイル
$tmp_file = KONA3_DIR_PRIVATE . '/test_jsonphp_load_' . time() . '_' . uniqid() . '.json.php';
@unlink($tmp_file);

// --- 1. 存在しないファイル: 非strict / strict どちらでも既定値を返し、例外を投げない ---
$res_non_strict = jsonphp_load($tmp_file, ['default' => 1], false, false);
test_eq(__LINE__, $res_non_strict, ['default' => 1], "存在しないファイル: 非strictで既定値を返す");

$res_strict = null;
$threw = false;
try {
  $res_strict = jsonphp_load($tmp_file, ['default' => 2], false, true);
} catch (JsonphpLoadException $e) {
  $threw = true;
}
test_assert(__LINE__, !$threw, "存在しないファイル: strictで例外を投げない");
test_eq(__LINE__, $res_strict, ['default' => 2], "存在しないファイル: strictで既定値を返す");

// --- 2. 正常なファイル: jsonphp_save() して jsonphp_load()（strict含む）で読み戻せる ---
$sample_data = ['hello' => 'world', 'num' => 42, 'list' => [1, 2, 3]];
jsonphp_save($tmp_file, $sample_data);
test_assert(__LINE__, file_exists($tmp_file), "正常なファイル: ファイルが保存される");

$loaded_normal = jsonphp_load($tmp_file, [], false, false);
test_eq(__LINE__, $loaded_normal['hello'], 'world', "正常なファイル: 非strictで読み戻せる");
test_eq(__LINE__, $loaded_normal['num'], 42, "正常なファイル: 数値も保持される");

$loaded_strict = null;
$threw = false;
try {
  $loaded_strict = jsonphp_load($tmp_file, [], false, true);
} catch (JsonphpLoadException $e) {
  $threw = true;
}
test_assert(__LINE__, !$threw, "正常なファイル: strictで例外を投げない");
test_eq(__LINE__, $loaded_strict['hello'], 'world', "正常なファイル: strictで正しく読み込める");
@unlink($tmp_file);

// --- 3. ヘッダーを壊したファイル: 非strict では既定値を返す、strict では JsonphpLoadException を投げる ---
$bad_head_data = "CORRUPTED_HEADER\n{\"hello\":\"world\"}\n_____END_OF_JSONPHP__;exit;\n";
file_put_contents($tmp_file, $bad_head_data);

$res_bad_head = jsonphp_load($tmp_file, ['fallback' => true], false, false);
test_eq(__LINE__, $res_bad_head, ['fallback' => true], "ヘッダー破損: 非strictで既定値を返す");

$threw = false;
try {
  jsonphp_load($tmp_file, ['fallback' => true], false, true);
} catch (JsonphpLoadException $e) {
  $threw = true;
}
test_assert(__LINE__, $threw, "ヘッダー破損: strictでJsonphpLoadExceptionを投げる");
@unlink($tmp_file);

// --- 4. JSON部分を壊したファイル: strict では例外を投げる ---
$bad_json_data = JSONPHP_HEAD . "{invalid_json: true" . JSONPHP_FOOT;
file_put_contents($tmp_file, $bad_json_data);

$res_bad_json = jsonphp_load($tmp_file, ['fallback' => true], false, false);
test_eq(__LINE__, $res_bad_json, ['fallback' => true], "JSON破損: 非strictで既定値を返す");

$threw = false;
try {
  jsonphp_load($tmp_file, [], false, true);
} catch (JsonphpLoadException $e) {
  $threw = true;
}
test_assert(__LINE__, $threw, "JSON破損: strictでJsonphpLoadExceptionを投げる");
@unlink($tmp_file);

// --- 5. 空ファイル（0バイト、存在する）: strict では例外を投げる ---
file_put_contents($tmp_file, "");

$res_empty = jsonphp_load($tmp_file, ['fallback' => true], false, false);
test_eq(__LINE__, $res_empty, ['fallback' => true], "空ファイル(0バイト): 非strictで既定値を返す");

$threw = false;
try {
  jsonphp_load($tmp_file, [], false, true);
} catch (JsonphpLoadException $e) {
  $threw = true;
}
test_assert(__LINE__, $threw, "空ファイル(0バイト): strictでJsonphpLoadExceptionを投げる");
@unlink($tmp_file);

// --- 6. kona3createAdminUser() の回帰テスト ---
// 管理者ファイルを作成した後にそのファイルを壊し、
// kona3createAdminUser('new@example.com', 'pw') を呼ぶと例外が投げられ、
// ファイルの内容が壊れたまま（＝上書きされず元のバイト列のまま）保持されること。
// 終わったら管理者ファイルは削除して後片付けする（setup_admin.test.php と干渉しないように）。
$adminFile = kona3getAdminUserFile();
@unlink($adminFile);

// 初期管理者を作成
kona3createAdminUser('original@example.com', 'original-password');
test_assert(__LINE__, file_exists($adminFile), "回帰テスト: 管理者ファイル作成成功");

// 管理者ファイルを破損させる
$corrupted_content = "INVALID_HEADER_CONTENT\n{\"enc:key\":\"xxxx\"}\n";
file_put_contents($adminFile, $corrupted_content);

$threw = false;
try {
  kona3createAdminUser('new@example.com', 'new-password');
} catch (JsonphpLoadException $e) {
  $threw = true;
}
test_assert(__LINE__, $threw, "回帰テスト: 管理者ファイル破損時にkona3createAdminUserがJsonphpLoadExceptionを投げる");

// ファイルの内容が上書きされず元のバイト列のまま保持されていること
$current_content = file_get_contents($adminFile);
test_eq(__LINE__, $current_content, $corrupted_content, "回帰テスト: 管理者ファイルが上書きされず保持されている");

// 後片付け
@unlink($adminFile);
@unlink($tmp_file);
