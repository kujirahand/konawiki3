<?php
require_once __DIR__ . '/test_common.inc.php';

// Test Japanese headers and list markers in Markdown parser (kona3parser_md.inc.php)
require_once dirname(__DIR__) . '/kona3parser_md.inc.php';

// --- Constant Definition Tests ---
test_assert(__LINE__, defined('KONA3_MD_H1_MARK'), "KONA3_MD_H1_MARK is defined");
test_eq(__LINE__, KONA3_MD_H1_MARK, '■', "KONA3_MD_H1_MARK defaults to ■");
test_assert(__LINE__, defined('KONA3_MD_H2_MARK'), "KONA3_MD_H2_MARK is defined");
test_eq(__LINE__, KONA3_MD_H2_MARK, '●', "KONA3_MD_H2_MARK defaults to ●");
test_assert(__LINE__, defined('KONA3_MD_H3_MARK'), "KONA3_MD_H3_MARK is defined");
test_eq(__LINE__, KONA3_MD_H3_MARK, '▲', "KONA3_MD_H3_MARK defaults to ▲");
test_assert(__LINE__, defined('KONA3_MD_UL_MARK'), "KONA3_MD_UL_MARK is defined");
test_eq(__LINE__, KONA3_MD_UL_MARK, '・', "KONA3_MD_UL_MARK defaults to ・");

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

// --- Security Rendering Tests ---
$text = "\\<script\\>alert(1)\\</script\\>";
$html = kona3markdown_parser_convert($text, false);
test_assert(__LINE__, strpos($html, '<script>') === false, "Escaped angle brackets must not create script tags");
test_assert(__LINE__, strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, "Escaped angle brackets are rendered as text");

$text = "[<img src=x onerror=alert(1)>](https://example.com)";
$html = kona3markdown_parser_convert($text, false);
test_assert(__LINE__, strpos($html, '<img src=x') === false, "Markdown link labels must escape HTML tags");
test_assert(__LINE__, strpos($html, '&lt;img src=x onerror=alert(1)&gt;') !== false, "Markdown link labels keep escaped text");

$text = "https://example.com/?a=1&b=<x>";
$html = kona3markdown_parser_convert($text, false);
test_assert(__LINE__, strpos($html, '>https://example.com/?a=1&amp;b=') !== false, "Auto-link display text escapes ampersands");

$text = "[safe](HTTPS://example.com/?a=1&b=2)";
$html = kona3markdown_parser_convert($text, false);
test_assert(__LINE__, strpos($html, "href='HTTPS://example.com/?a=1&amp;b=2'") !== false, "URL scheme checks are case-insensitive for HTTPS");

$text = "[bad](javascript:alert(1))";
$html = kona3markdown_parser_convert($text, false);
test_assert(__LINE__, strpos($html, 'javascript:') === false, "Markdown links must reject javascript scheme");
test_assert(__LINE__, strpos($html, 'WIKI_LINK_ERROR') !== false, "Rejected schemes become link errors");

$text = "![bad](javascript:alert(1))";
$html = kona3markdown_parser_convert($text, false);
test_assert(__LINE__, strpos($html, 'javascript:') === false, "Markdown images must not output javascript scheme");

// --- Inline Code and Underscore Emphasis Tests ---
global $kona3conf;
$orig_emphasis = isset($kona3conf['md_underscore_emphasis']) ? $kona3conf['md_underscore_emphasis'] : false;

// 1. Test when md_underscore_emphasis is enabled (true)
$kona3conf['md_underscore_emphasis'] = true;

// Double underscore inside inline code should not trigger emphasis
$text = "`__file__` and `__test__` code";
$html = kona3markdown_parser_convert($text);
test_assert(__LINE__, strpos($html, '<strong') === false, "Inside backticks, __ should not be styled as strong");
test_assert(__LINE__, strpos($html, '<code>__file__</code>') !== false, "Inside backticks, __file__ should be displayed literally");

// __emphasis__ should render as strong2
$text = "This is __bold__ text";
$html = kona3markdown_parser_convert($text);
test_assert(__LINE__, strpos($html, "<strong class='strong2'>bold</strong>") !== false, "Enabled: __bold__ becomes strong2");

// 2. Test when md_underscore_emphasis is disabled (false)
$kona3conf['md_underscore_emphasis'] = false;

// Double underscore inside inline code should not trigger emphasis
$text = "`__file__` and `__test__` code";
$html = kona3markdown_parser_convert($text);
test_assert(__LINE__, strpos($html, '<strong') === false, "Inside backticks, __ should not be styled as strong");

// __emphasis__ should NOT render as strong
$text = "This is __bold__ text";
$html = kona3markdown_parser_convert($text);
test_assert(__LINE__, strpos($html, '<strong') === false, "Disabled: __bold__ should not become strong tag");
test_assert(__LINE__, strpos($html, '__bold__') !== false, "Disabled: __bold__ should be literal");

// Restore config
$kona3conf['md_underscore_emphasis'] = $orig_emphasis;
