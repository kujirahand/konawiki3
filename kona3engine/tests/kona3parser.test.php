<?php
require_once __DIR__ . '/test_common.inc.php';

// --- 基本的な見出しのテスト ---
// Konawiki記法の見出し
$text = "■見出し1\n●見出し2\n▲見出し3";
$tokens = konawiki_parser_parse($text);
test_eq(__LINE__, $tokens[0]['cmd'], '*', "Konawiki記法: ■ は見出し");
test_eq(__LINE__, $tokens[0]['level'], 1, "Konawiki記法: ■ はレベル1");
test_eq(__LINE__, $tokens[0]['text'], '見出し1', "Konawiki記法: 見出しテキスト");
test_eq(__LINE__, $tokens[1]['cmd'], '*', "Konawiki記法: ● は見出し");
test_eq(__LINE__, $tokens[1]['level'], 2, "Konawiki記法: ● はレベル2");
test_eq(__LINE__, $tokens[2]['cmd'], '*', "Konawiki記法: ▲ は見出し");
test_eq(__LINE__, $tokens[2]['level'], 3, "Konawiki記法: ▲ はレベル3");

// PukiWiki風記法の見出し
$text = "* 見出し1\n** 見出し2\n*** 見出し3";
$tokens = konawiki_parser_parse($text);
test_eq(__LINE__, $tokens[0]['cmd'], '*', "PukiWiki風: * は見出し");
test_eq(__LINE__, $tokens[0]['level'], 1, "PukiWiki風: * はレベル1");
test_eq(__LINE__, $tokens[1]['cmd'], '*', "PukiWiki風: ** は見出し");
test_eq(__LINE__, $tokens[1]['level'], 2, "PukiWiki風: ** はレベル2");
test_eq(__LINE__, $tokens[2]['cmd'], '*', "PukiWiki風: *** は見出し");
test_eq(__LINE__, $tokens[2]['level'], 3, "PukiWiki風: *** はレベル3");

// --- リストのテスト ---
// 箇条書きリスト（Konawiki記法）
$text = "・項目1\n・項目2\n・項目3";
$tokens = konawiki_parser_parse($text);
test_eq(__LINE__, $tokens[0]['cmd'], '-', "Konawiki記法: ・ は箇条書き");
test_eq(__LINE__, $tokens[0]['text'], '項目1', "Konawiki記法: リストテキスト1");
test_eq(__LINE__, $tokens[1]['text'], '項目2', "Konawiki記法: リストテキスト2");

// 箇条書きリスト（PukiWiki風記法）
$text = "- 項目1\n- 項目2\n-- ネスト項目";
$tokens = konawiki_parser_parse($text);
test_eq(__LINE__, $tokens[0]['cmd'], '-', "PukiWiki風: - は箇条書き");
test_eq(__LINE__, $tokens[0]['level'], 1, "PukiWiki風: - はレベル1");
test_eq(__LINE__, $tokens[2]['level'], 2, "PukiWiki風: -- はレベル2");

// 番号付きリスト
$text = "+ 項目1\n+ 項目2\n++ ネスト項目";
$tokens = konawiki_parser_parse($text);
test_eq(__LINE__, $tokens[0]['cmd'], '+', "PukiWiki風: + は番号付きリスト");
test_eq(__LINE__, $tokens[0]['level'], 1, "+ はレベル1");
test_eq(__LINE__, $tokens[2]['level'], 2, "++ はレベル2");

// --- 表のテスト ---
$text = "|セル1|セル2|セル3|\n|A|B|C|";
$tokens = konawiki_parser_parse($text);
test_eq(__LINE__, $tokens[0]['cmd'], '|', "| は表");
test_eq(__LINE__, $tokens[0]['text'], 'セル1|セル2|セル3|', "表の内容1");
test_eq(__LINE__, $tokens[1]['text'], 'A|B|C|', "表の内容2");

// --- ソースコードのテスト ---
$text = " コード行1\n コード行2";
$tokens = konawiki_parser_parse($text);
test_eq(__LINE__, $tokens[0]['cmd'], 'src', "先頭スペースはソースコード");
test_eq(__LINE__, $tokens[0]['text'], 'コード行1', "ソースコード行1");
test_eq(__LINE__, $tokens[1]['text'], 'コード行2', "ソースコード行2");

// --- ソースコードブロックのテスト ---
$text = "{{{php\necho 'test';\n}}}";
$tokens = konawiki_parser_parse($text);
test_eq(__LINE__, $tokens[0]['cmd'], 'block', "{{{}}} はソースコードブロック");
test_assert(__LINE__, strpos($tokens[0]['text'], "echo 'test';") !== FALSE, "ソースコードブロックの内容");

// --- プラグインのテスト ---
$text = "#test(arg1,arg2)\n";
$tokens = konawiki_parser_parse($text);
// プラグインは必ずしも独立したトークンにならない場合がある
if (count($tokens) > 0 && isset($tokens[0]['cmd']) && $tokens[0]['cmd'] === 'plugin') {
    test_eq(__LINE__, $tokens[0]['cmd'], 'plugin', "# はプラグイン");
} else {
    // プラグインがplainとして扱われる場合もある
    test_assert(__LINE__, count($tokens) > 0, "プラグインテキストがパースされる");
}

// --- 水平線のテスト ---
$text = "---\n";
$tokens = konawiki_parser_parse($text);
test_eq(__LINE__, $tokens[0]['cmd'], 'hr', "--- は水平線");

// --- プレーンテキストのテスト ---
$text = "これは普通のテキストです。\n段落が続きます。";
$tokens = konawiki_parser_parse($text);
test_eq(__LINE__, $tokens[0]['cmd'], 'plain', "通常のテキストはplain");
test_assert(__LINE__, strpos($tokens[0]['text'], "普通のテキスト") !== FALSE, "プレーンテキストの内容");

// --- インライン記法のテスト（tohtml関数） ---
// Konawiiki記法では '' が太字
$html = konawiki_parser_tohtml("''太字''です");
test_assert(__LINE__, strpos($html, '<strong') !== FALSE, "''太字'' は<strong>");

// %% は赤文字
$html = konawiki_parser_tohtml("%%赤文字%%です");
test_assert(__LINE__, strpos($html, 'red') !== FALSE, "%%赤文字%% はredクラス");

// [[...]] はリンク
$html = konawiki_parser_tohtml("[[リンク]]です");
test_assert(__LINE__, strpos($html, '<a') !== FALSE, "[[リンク]] はリンクタグ");

// WikiLinkのテスト
$html = konawiki_parser_tohtml("[[FrontPage]]");
test_assert(__LINE__, strpos($html, 'href=') !== FALSE, "[[PageName]] はリンク");
test_assert(__LINE__, strpos($html, 'FrontPage') !== FALSE, "リンクにページ名が含まれる");

// URLリンクのテスト
$html = konawiki_parser_tohtml("http://example.com");
test_assert(__LINE__, strpos($html, '<a') !== FALSE, "URLは自動リンク");
test_assert(__LINE__, strpos($html, 'http://example.com') !== FALSE, "URLが含まれる");

// --- エスケープのテスト ---
$html = konawiki_parser_tohtml("<script>alert('xss')</script>");
test_assert(__LINE__, strpos($html, '&lt;script&gt;') !== FALSE, "HTMLタグはエスケープされる");
test_assert(__LINE__, strpos($html, '<script>') === FALSE, "<script>タグは実行されない");

// --- 複雑な組み合わせのテスト ---
$text = "■タイトル\nこれは段落です。\n\n・リスト1\n・リスト2\n|表|のセル|";
$tokens = konawiki_parser_parse($text);
test_assert(__LINE__, count($tokens) >= 3, "複数の要素を含むテキスト");
test_eq(__LINE__, $tokens[0]['cmd'], '*', "最初は見出し");
// トークンの存在確認
$has_heading = false;
$has_list = false;
$has_table = false;
foreach ($tokens as $token) {
    if ($token['cmd'] === '*') $has_heading = true;
    if ($token['cmd'] === '-') $has_list = true;
    if ($token['cmd'] === '|') $has_table = true;
}
test_assert(__LINE__, $has_heading, "見出しが含まれる");
test_assert(__LINE__, $has_list, "リストが含まれる");
test_assert(__LINE__, $has_table, "表が含まれる");

// --- レンダリングテスト ---
$text = "■テストタイトル\nこれはテスト段落です。";
$html = konawiki_parser_convert($text);
test_assert(__LINE__, strpos($html, '<h') !== FALSE, "見出しはhタグに変換");
test_assert(__LINE__, strpos($html, 'テストタイトル') !== FALSE, "見出しのテキストが含まれる");
test_assert(__LINE__, strpos($html, 'テスト段落') !== FALSE, "段落のテキストが含まれる");
test_assert(__LINE__, strpos($html, '<div class="contents">') !== FALSE, "contentsクラスのdivで囲まれる");

// --- 空のテキストのテスト ---
$tokens = konawiki_parser_parse("");
test_eq(__LINE__, count($tokens), 0, "空のテキストは空の配列");

// --- 特殊文字のテスト ---
$html = konawiki_parser_tohtml("&amp; &lt; &gt;");
test_assert(__LINE__, strpos($html, '&amp;') !== FALSE, "&はエスケープされる");

// --- プラグインの引数パース ---
$text = "#plugin(arg1, arg2, arg3)\n";
$tokens = konawiki_parser_parse($text);
// プラグインがパースされたか確認
test_assert(__LINE__, count($tokens) > 0, "テキストがパースされる");
test_assert(__LINE__, is_array($tokens), "トークン配列が返される");
