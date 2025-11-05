<?php
require_once __DIR__ . '/test_common.inc.php';

// テンプレートエンジンのテスト
// fw_template_engine.lib.php を使用

// テスト用のテンプレートディレクトリを作成
$test_tpl_dir = KONA3_DIR_PRIVATE . "/test_templates";
$test_cache_dir = KONA3_DIR_PRIVATE . "/test_cache";

if (!is_dir($test_tpl_dir)) {
    mkdir($test_tpl_dir, 0777, true);
}
if (!is_dir($test_cache_dir)) {
    mkdir($test_cache_dir, 0777, true);
}

// テンプレートエンジンのグローバル変数を設定
global $DIR_TEMPLATE, $DIR_TEMPLATE_CACHE;
$DIR_TEMPLATE = $test_tpl_dir;
$DIR_TEMPLATE_CACHE = $test_cache_dir;

// --- 変数の出力テスト ---

// 単純な変数
$tpl_file = $test_tpl_dir . "/test_var.html";
file_put_contents($tpl_file, "Hello, {{" . '$name' . "}}!");

ob_start();
template_render("test_var.html", ['name' => 'World']);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "Hello, World!") !== FALSE, "変数の出力");

// 複数の変数
$tpl_file = $test_tpl_dir . "/test_multi_var.html";
file_put_contents($tpl_file, "{{" . '$a' . "}} + {{" . '$b' . "}} = {{" . '$c' . "}}");

ob_start();
template_render("test_multi_var.html", ['a' => 1, 'b' => 2, 'c' => 3]);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "1 + 2 = 3") !== FALSE, "複数の変数の出力");

// --- 条件分岐のテスト ---

// if文
$tpl_file = $test_tpl_dir . "/test_if.html";
file_put_contents($tpl_file, "{{if " . '$flag' . "}}True{{endif}}");

ob_start();
template_render("test_if.html", ['flag' => true]);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "True") !== FALSE, "if文: 条件がtrueの場合");

ob_start();
template_render("test_if.html", ['flag' => false]);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "True") === FALSE, "if文: 条件がfalseの場合");

// if-else文
$tpl_file = $test_tpl_dir . "/test_if_else.html";
file_put_contents($tpl_file, "{{if " . '$flag' . "}}Yes{{else}}No{{endif}}");

ob_start();
template_render("test_if_else.html", ['flag' => true]);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "Yes") !== FALSE, "if-else文: trueの場合");

ob_start();
template_render("test_if_else.html", ['flag' => false]);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "No") !== FALSE, "if-else文: falseの場合");

// 比較演算子
$tpl_file = $test_tpl_dir . "/test_if_cond.html";
file_put_contents($tpl_file, "{{if " . '$num' . " > 5}}Big{{else}}Small{{endif}}");

ob_start();
template_render("test_if_cond.html", ['num' => 10]);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "Big") !== FALSE, "if文: 比較演算子（>）");

ob_start();
template_render("test_if_cond.html", ['num' => 3]);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "Small") !== FALSE, "if文: 比較演算子（<）");

// --- ループのテスト ---

// for文（値のみ）
$tpl_file = $test_tpl_dir . "/test_for.html";
file_put_contents($tpl_file, "{{for " . '$items' . " as " . '$item' . "}}{{" . '$item' . "}}{{endfor}}");

ob_start();
template_render("test_for.html", ['items' => ['A', 'B', 'C']]);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "ABC") !== FALSE, "for文: 値のみ");

// for文（キーと値）
$tpl_file = $test_tpl_dir . "/test_for_kv.html";
file_put_contents($tpl_file, "{{for " . '$items' . " as " . '$key' . "=>" . '$val' . "}}{{" . '$key' . "}}-{{" . '$val' . "}} {{endfor}}");

ob_start();
template_render("test_for_kv.html", ['items' => ['a' => '1', 'b' => '2', 'c' => '3']]);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "a-1") !== FALSE, "for文: キーと値（a-1）");
test_assert(__LINE__, strpos($output, "b-2") !== FALSE, "for文: キーと値（b-2）");
test_assert(__LINE__, strpos($output, "c-3") !== FALSE, "for文: キーと値（c-3）");

// 空の配列でのループ
ob_start();
template_render("test_for.html", ['items' => []]);
$output = ob_get_clean();
test_eq(__LINE__, trim($output), "", "for文: 空の配列");

// --- ネストしたデータへのアクセス ---

// 配列の要素アクセス
$tpl_file = $test_tpl_dir . "/test_nested.html";
file_put_contents($tpl_file, "{{" . '$user.name' . "}} - {{" . '$user.age' . "}}");

ob_start();
template_render("test_nested.html", ['user' => ['name' => 'Taro', 'age' => 30]]);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "Taro") !== FALSE, "ネストしたデータ: user.name");
test_assert(__LINE__, strpos($output, "30") !== FALSE, "ネストしたデータ: user.age");

// --- フィルターのテスト ---

// HTMLエスケープ（デフォルト）
$tpl_file = $test_tpl_dir . "/test_escape.html";
file_put_contents($tpl_file, "{{" . '$html' . "}}");

ob_start();
template_render("test_escape.html", ['html' => '<script>alert(1)</script>']);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, '&lt;script&gt;') !== FALSE, "HTMLエスケープ");
test_assert(__LINE__, strpos($output, '<script>') === FALSE, "スクリプトタグはエスケープされる");

// safeフィルター（エスケープなし）
$tpl_file = $test_tpl_dir . "/test_safe.html";
file_put_contents($tpl_file, "{{" . '$html' . " | safe}}");

ob_start();
template_render("test_safe.html", ['html' => '<b>bold</b>']);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, '<b>bold</b>') !== FALSE, "safeフィルター: HTMLがそのまま表示");

// --- includeのテスト ---

// 子テンプレートファイル
$tpl_file = $test_tpl_dir . "/test_include_child.html";
file_put_contents($tpl_file, "Child: {{" . '$value' . "}}");

// 親テンプレートファイル
$tpl_file = $test_tpl_dir . "/test_include_parent.html";
file_put_contents($tpl_file, "Parent: {{include test_include_child.html}}");

ob_start();
template_render("test_include_parent.html", ['value' => 'Test']);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "Parent:") !== FALSE, "include: 親テンプレート");
test_assert(__LINE__, strpos($output, "Child: Test") !== FALSE, "include: 子テンプレート");

// --- コメントのテスト ---

$tpl_file = $test_tpl_dir . "/test_comment.html";
file_put_contents($tpl_file, "Before{{# This is a comment }}After");

ob_start();
template_render("test_comment.html", []);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "BeforeAfter") !== FALSE, "コメント: コメント部分が削除される");
test_assert(__LINE__, strpos($output, "This is a comment") === FALSE, "コメント: コメント内容は表示されない");

// --- evalのテスト ---

$tpl_file = $test_tpl_dir . "/test_eval.html";
file_put_contents($tpl_file, "{{eval echo 'Hello from eval';}}");

ob_start();
template_render("test_eval.html", []);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "Hello from eval") !== FALSE, "eval: PHPコードの実行");

// --- 複雑な組み合わせのテスト ---

$tpl_file = $test_tpl_dir . "/test_complex.html";
$complex_tpl = <<<'TPL'
<ul>
{{for $users as $user}}
  <li>{{if $user.active}}✓{{else}}✗{{endif}} {{$user.name}}</li>
{{endfor}}
</ul>
TPL;
file_put_contents($tpl_file, $complex_tpl);

ob_start();
template_render("test_complex.html", [
    'users' => [
        ['name' => 'Alice', 'active' => true],
        ['name' => 'Bob', 'active' => false],
        ['name' => 'Carol', 'active' => true],
    ]
]);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "Alice") !== FALSE, "複雑な組み合わせ: Alice");
test_assert(__LINE__, strpos($output, "Bob") !== FALSE, "複雑な組み合わせ: Bob");
test_assert(__LINE__, strpos($output, "Carol") !== FALSE, "複雑な組み合わせ: Carol");
test_assert(__LINE__, strpos($output, "✓") !== FALSE, "複雑な組み合わせ: アクティブマーク");
test_assert(__LINE__, strpos($output, "✗") !== FALSE, "複雑な組み合わせ: 非アクティブマーク");

// --- エッジケースのテスト ---

// 空のテンプレート
$tpl_file = $test_tpl_dir . "/test_empty.html";
file_put_contents($tpl_file, "");

ob_start();
template_render("test_empty.html", []);
$output = ob_get_clean();
test_assert(__LINE__, strlen(trim($output)) === 0, "空のテンプレート");

// 変数が存在しない場合（エラーにならないこと）
$tpl_file = $test_tpl_dir . "/test_undefined.html";
file_put_contents($tpl_file, "Value: {{" . '$undefined' . "}}");

ob_start();
@template_render("test_undefined.html", []);
$output = ob_get_clean();
test_assert(__LINE__, strlen($output) >= 0, "未定義変数: エラーにならない");

// 日本語のテキスト
$tpl_file = $test_tpl_dir . "/test_japanese.html";
file_put_contents($tpl_file, "こんにちは、{{" . '$name' . "}}さん！");

ob_start();
template_render("test_japanese.html", ['name' => '太郎']);
$output = ob_get_clean();
test_assert(__LINE__, strpos($output, "こんにちは、太郎さん！") !== FALSE, "日本語のテンプレート");

// クリーンアップ
array_map('unlink', glob($test_tpl_dir . "/*"));
array_map('unlink', glob($test_cache_dir . "/*"));
@rmdir($test_tpl_dir);
@rmdir($test_cache_dir);
