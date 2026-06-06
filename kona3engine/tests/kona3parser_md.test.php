<?php
require_once __DIR__ . '/test_common.inc.php';

// Test Japanese headers and list markers in Markdown parser (kona3parser_md.inc.php)
require_once dirname(__DIR__) . '/kona3parser_md.inc.php';

// --- Header Tests ---
$text = "■見出し1\n●見出し2\n▲見出し3";
$tokens = kona3markdown_parser_parse($text);

test_eq(__LINE__, $tokens[0]['cmd'], '*', "Markdown Mode: ■ is parsed as header");
test_eq(__LINE__, $tokens[0]['level'], 1, "Markdown Mode: ■ header level is 1");
test_eq(__LINE__, $tokens[0]['text'], '見出し1', "Markdown Mode: ■ header text matches");

test_eq(__LINE__, $tokens[1]['cmd'], '*', "Markdown Mode: ● is parsed as header");
test_eq(__LINE__, $tokens[1]['level'], 2, "Markdown Mode: ● header level is 2");
test_eq(__LINE__, $tokens[1]['text'], '見出し2', "Markdown Mode: ● header text matches");

test_eq(__LINE__, $tokens[2]['cmd'], '*', "Markdown Mode: ▲ is parsed as header");
test_eq(__LINE__, $tokens[2]['level'], 3, "Markdown Mode: ▲ header level is 3");
test_eq(__LINE__, $tokens[2]['text'], '見出し3', "Markdown Mode: ▲ header text matches");

// --- List Tests ---
$text = "・項目1\n・・項目2\n・・・項目3";
$tokens = kona3markdown_parser_parse($text);

test_eq(__LINE__, $tokens[0]['cmd'], '-', "Markdown Mode: ・ is parsed as list");
test_eq(__LINE__, $tokens[0]['level'], 1, "Markdown Mode: ・ list level is 1");
test_eq(__LINE__, $tokens[0]['text'], '項目1', "Markdown Mode: ・ list text matches");

test_eq(__LINE__, $tokens[1]['cmd'], '-', "Markdown Mode: ・・ is parsed as list");
test_eq(__LINE__, $tokens[1]['level'], 2, "Markdown Mode: ・・ list level is 2");
test_eq(__LINE__, $tokens[1]['text'], '項目2', "Markdown Mode: ・・ list text matches");

test_eq(__LINE__, $tokens[2]['cmd'], '-', "Markdown Mode: ・・・ is parsed as list");
test_eq(__LINE__, $tokens[2]['level'], 3, "Markdown Mode: ・・・ list level is 3");
test_eq(__LINE__, $tokens[2]['text'], '項目3', "Markdown Mode: ・・・ list text matches");

// --- HTML Rendering Tests ---
$text = "■テスト見出し\n・テストリスト";
$html = kona3markdown_parser_convert($text);

test_assert(__LINE__, strpos($html, '<h1') !== false, "Markdown Mode: ■ translates to <h1> tag");
test_assert(__LINE__, strpos($html, '<li>テストリスト') !== false, "Markdown Mode: ・ translates to <li> tag");
