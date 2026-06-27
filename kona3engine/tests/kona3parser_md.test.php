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

// --- Standard Markdown Syntax Tests ---
$text = "# Heading 1\n## Heading 2\n### Heading 3";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, '<h1>Heading 1') !== false, "Standard Markdown: # becomes h1");
test_assert(__LINE__, strpos($html, '<h2>Heading 2') !== false, "Standard Markdown: ## becomes h2");
test_assert(__LINE__, strpos($html, '<h3>Heading 3') !== false, "Standard Markdown: ### becomes h3");

$text = "- Item 1\n  - Item 2\n* Item 3";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, '<ul>') !== false, "Standard Markdown: unordered list creates ul");
test_assert(__LINE__, strpos($html, '<li>Item 1') !== false, "Standard Markdown: hyphen list item is rendered");
test_assert(__LINE__, strpos($html, '<li> Item 2') !== false, "Standard Markdown: indented list item is rendered");
test_assert(__LINE__, strpos($html, '<li>Item 3') !== false, "Standard Markdown: asterisk list item is rendered");

$text = "+ One\n+ Two";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, '<ol>') !== false, "Markdown Mode: plus list creates ol");
test_assert(__LINE__, strpos($html, '<li>One</li>') !== false, "Markdown Mode: ordered item 1 is rendered");
test_assert(__LINE__, strpos($html, '<li>Two</li>') !== false, "Markdown Mode: ordered item 2 is rendered");

$text = "1. One\n2. Two";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, '<ul>') !== false, "Markdown Mode: numeric list is parsed as a list");
test_assert(__LINE__, strpos($html, '<li>1. One</li>') !== false, "Markdown Mode: numeric list item 1 is preserved");
test_assert(__LINE__, strpos($html, '<li>2. Two</li>') !== false, "Markdown Mode: numeric list item 2 is preserved");

$text = "> quoted\n> next";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<div class='resmark'>") !== false, "Standard Markdown: quote creates resmark block");
test_assert(__LINE__, strpos($html, 'quoted<br/>') !== false, "Standard Markdown: first quote line is rendered");
test_assert(__LINE__, strpos($html, 'next<br/>') !== false, "Standard Markdown: second quote line is rendered");

$text = "-----";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, '<hr>') !== false, "Standard Markdown: hyphen rule creates hr");

$text = "line~\nnext";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, '<p>line<br/>next</p>') !== false, "Markdown Mode: trailing tilde creates br");

$text = "**bold** and ~~gone~~ and [link](https://example.com) and `code`";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<strong class='strong1'>bold</strong>") !== false, "Standard Markdown: **bold** becomes strong1");
test_assert(__LINE__, strpos($html, '<del>gone</del>') !== false, "Standard Markdown: ~~gone~~ becomes del");
test_assert(__LINE__, strpos($html, "<a href='https://example.com'>link</a>") !== false, "Standard Markdown: link is rendered");
test_assert(__LINE__, strpos($html, "<span class='code'><code>code</code></span>") !== false, "Standard Markdown: inline code is rendered");

$text = "![alt text](https://example.com/a.png)";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<img src='https://example.com/a.png'") !== false, "Standard Markdown: image is rendered through ref plugin");
test_assert(__LINE__, strpos($html, "<div class='memo'>alt text</div>") !== false, "Standard Markdown: image alt is rendered as caption");

$text = "```php\necho 1;\n```";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<pre class='code'>echo 1;") !== false, "Standard Markdown: backtick code block is rendered");

$text = "~~~js\nlet x = 1;\n~~~";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<pre class='code'>let x = 1;") !== false, "Standard Markdown: tilde code block is rendered");

$text = ":::html\n<div>x</div>\n:::";
$tokens = kona3markdown_parser_parse($text);

test_eq(__LINE__, $tokens[0]['cmd'], 'block', "Markdown Mode: ::: creates a block token");
test_eq(__LINE__, $tokens[0]['params'][1], 'plugin', "Markdown Mode: ::: block is treated as plugin block");
test_assert(__LINE__, strpos($tokens[0]['text'], '<div>x</div>') !== false, "Markdown Mode: ::: block keeps body text");

$text = "::::html\n<div>x</div>\n::::";
$tokens = kona3markdown_parser_parse($text);

test_eq(__LINE__, $tokens[0]['params'][0], '#html', "Markdown Mode: :::: plugin name should not keep an extra colon");
test_assert(__LINE__, strpos($tokens[0]['text'], '<div>x</div>') !== false, "Markdown Mode: :::: block keeps body text");
test_assert(__LINE__, strpos($tokens[0]['text'], "\n:") === false, "Markdown Mode: :::: closing marker should not leak into body text");

$text = ":::::html\nbefore\n:::\nafter\n:::::";
$tokens = kona3markdown_parser_parse($text);

test_eq(__LINE__, $tokens[0]['params'][0], '#html', "Markdown Mode: ::::: plugin name should not keep extra colons");
test_assert(__LINE__, strpos($tokens[0]['text'], "before\n:::\nafter\n") !== false, "Markdown Mode: shorter ::: inside ::::: block should remain body text");

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

// --- Table Support Tests ---
// 1. Standard GFM Table with alignment
$text = "| Header 1 | Header 2 | Header 3 |\n|:---|:---:|---:|\n| Cell 1 | Cell 2 | Cell 3 |";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, '<table class=\'grid\'') !== false, "Table tag should be generated");
test_assert(__LINE__, strpos($html, "<thead><tr><th style='text-align:left;'>Header 1</th><th style='text-align:center;'>Header 2</th><th style='text-align:right;'>Header 3</th></tr></thead>") !== false, "Header with style alignments should be generated");
test_assert(__LINE__, strpos($html, "<td style='text-align:left;'>Cell 1</td>") !== false, "Cell 1 aligned left");
test_assert(__LINE__, strpos($html, "<td style='text-align:center;'>Cell 2</td>") !== false, "Cell 2 aligned center");
test_assert(__LINE__, strpos($html, "<td style='text-align:right;'>Cell 3</td>") !== false, "Cell 3 aligned right");

// 2. Simple Table without separator
$text = "| Cell 1 | Cell 2 |\n| Cell 3 | Cell 4 |";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, '<thead>') === false, "Simple table should not have thead");
test_assert(__LINE__, strpos($html, '<td>Cell 1</td>') !== false, "Simple cell 1");
test_assert(__LINE__, strpos($html, '<td>Cell 4</td>') !== false, "Simple cell 4");

// 3. GFM table without leading/trailing pipes
$text = "Header 1 | Header 2 | Header 3\n:--- | :---: | ---:\nCell 1 | Cell 2 | Cell 3";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<thead><tr><th style='text-align:left;'>Header 1</th><th style='text-align:center;'>Header 2</th><th style='text-align:right;'>Header 3</th></tr></thead>") !== false, "GFM table without edge pipes should have a header");
test_assert(__LINE__, strpos($html, "<td style='text-align:right;'>Cell 3</td>") !== false, "GFM table without edge pipes should keep alignment");

// 4. Escaped pipe in table cell
$text = "| Name | Character |\n| --- | --- |\n| Pipe | \\| |";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<td style='text-align:left;'>|</td>") !== false, "Escaped pipe should remain in one cell");
test_assert(__LINE__, strpos($html, "<td style='text-align:left;'></td><td") === false, "Escaped pipe should not create an extra cell");

// 5. A normal paragraph containing a pipe is not a table
$text = "This is A | B in a sentence.";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<table") === false, "Plain text with a pipe should not become a table");
test_assert(__LINE__, strpos($html, "This is A | B in a sentence.") !== false, "Plain text with a pipe should remain a paragraph");

// 6. Table cells with inline markup
$text = "| Command | Description |\n| --- | --- |\n| `git status` | List **new** files |";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<span class='code'><code>git status</code></span>") !== false, "Table cell should render inline code");
test_assert(__LINE__, strpos($html, "<strong class='strong1'>new</strong>") !== false, "Table cell should render strong markup");

// 7. Empty cells and uneven rows
$text = "| A | B |\n| --- | --- |\n|  | x |\n| y | |\n| z | extra | value |";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<td style='text-align:left;'></td><td style='text-align:left;'>x</td>") !== false, "Table should keep an empty leading cell");
test_assert(__LINE__, strpos($html, "<td style='text-align:left;'>y</td><td style='text-align:left;'></td>") !== false, "Table should keep an empty trailing cell");
test_assert(__LINE__, strpos($html, "<td>value</td>") !== false, "Table should render an extra uneven cell without alignment");
