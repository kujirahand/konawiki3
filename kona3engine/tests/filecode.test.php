<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/plugins/filecode.inc.php';

echo "=== filecode line number test (issue #265) ===\n";

$h = kona3plugins_filecode_lines("a\nb\nc", 1, false, false);
test_eq(__LINE__, $h,
  "<span class='line' data-n='1'>a</span>".
  "<span class='line' data-n='2'>b</span>".
  "<span class='line' data-n='3'>c</span>", "lines 1-3");

$h = kona3plugins_filecode_lines("…省略…\nb\nc\n…省略…", 5, true, true);
test_eq(__LINE__, $h,
  "<span class='line' data-n=''>…省略…</span>".
  "<span class='line' data-n='5'>b</span>".
  "<span class='line' data-n='6'>c</span>".
  "<span class='line' data-n=''>…省略…</span>", "lines with omit");

// 行をまたぐタグは行ごとに閉じて開き直す
$h = kona3plugins_filecode_lines("<span class='x'>a\nb</span>c", 1, false, false);
test_eq(__LINE__, $h,
  "<span class='line' data-n='1'><span class='x'>a</span></span>".
  "<span class='line' data-n='2'><span class='x'>b</span>c</span>", "split span");

// PHPのハイライトは外側のpre/codeを除き、改行を保持する
$h = kona3plugins_filecode_php_highlight("<?php\n\$a=1;\necho \$a;");
test_eq(__LINE__, strpos($h, '<pre') === false && strpos($h, '<code') === false, true, "no pre/code");
test_eq(__LINE__, substr_count($h, "\n"), 2, "php keeps newlines");

// Python/JavaScriptのハイライト
test_eq(__LINE__, kona3plugins_filecode_lang('a/b.py'), 'py', "lang py");
test_eq(__LINE__, kona3plugins_filecode_lang('a/b.JS'), 'js', "lang js");
test_eq(__LINE__, kona3plugins_filecode_lang('a/b.txt'), '', "lang other");

$h = kona3plugins_filecode_tokenize("def f(x):\n  return 'a<b' # c", 'py');
test_eq(__LINE__, strpos($h, "<span class='tok-k'>def</span> <span class='tok-f'>f</span>") !== false, true, "py keyword/func");
test_eq(__LINE__, strpos($h, "<span class='tok-s'>&#039;a&lt;b&#039;</span>") !== false, true, "py string escaped");
test_eq(__LINE__, strpos($h, "<span class='tok-c'># c</span>") !== false, true, "py comment");

$h = kona3plugins_filecode_tokenize("// x\nconst a = 0x1F + `t\nu`;\nconsole.log(a)", 'js');
test_eq(__LINE__, strpos($h, "<span class='tok-c'>// x</span>") !== false, true, "js comment");
test_eq(__LINE__, strpos($h, "<span class='tok-k'>const</span>") !== false, true, "js keyword");
test_eq(__LINE__, strpos($h, "<span class='tok-n'>0x1F</span>") !== false, true, "js number");
test_eq(__LINE__, strpos($h, "<span class='tok-s'>`t\nu`</span>") !== false, true, "js multiline template");
test_eq(__LINE__, strpos($h, "<span class='tok-f'>log</span>") !== false, true, "js func call");

// 日本語を含んでも壊れない
$h = kona3plugins_filecode_tokenize("print('こんにちは') # 日本語", 'py');
test_eq(__LINE__, strpos($h, "こんにちは") !== false && strpos($h, "日本語") !== false, true, "multibyte");

// JSの正規表現リテラルは文字列色、除算はそのまま
$h = kona3plugins_filecode_tokenize("const r = /ab+c\\/[/]/gi;\nconst d = a / b / c;\nreturn /x/.test(s);", 'js');
test_eq(__LINE__, strpos($h, "<span class='tok-s'>/ab+c\\/[/]/gi</span>") !== false, true, "js regex literal");
test_eq(__LINE__, strpos($h, "a / b / c;") !== false, true, "js division stays plain");
test_eq(__LINE__, strpos($h, "<span class='tok-s'>/x/</span>") !== false, true, "js regex after return");

// ネストが崩れた閉じタグでもスタックが壊れない
$h = kona3plugins_filecode_lines("<b><i>a\nb</b>c\nd", 1, false, false);
test_eq(__LINE__, strpos($h, "data-n='3'><i>d</i>") !== false, true, "mismatched close tag keeps <i> open");
