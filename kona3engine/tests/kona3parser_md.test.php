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

global $kona3conf;
unset($kona3conf['plugins.image.zoom.init']);
$text = "![alt text](https://example.com/a.png)";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<img src='https://example.com/a.png'") !== false, "Standard Markdown: image is rendered through image plugin");
test_assert(__LINE__, strpos($html, "<div class='memo'>alt text</div>") !== false, "Standard Markdown: image alt is rendered as caption");
test_assert(__LINE__, strpos($html, '<p>') === false, "Standard Markdown: standalone image is not wrapped in a paragraph");
test_assert(__LINE__, strpos($html, '<br/>') === false, "Standard Markdown: image plugin CSS and JavaScript newlines do not become visible breaks");

$text = "before\n\n![alt text](https://example.com/a.png)\n\nafter";
$html = kona3markdown_parser_convert($text, false);

test_eq(__LINE__, substr_count($html, '<p>'), 2, "Standard Markdown: standalone image does not create an empty paragraph between text blocks");
test_assert(__LINE__, strpos($html, '<p>before</p>') !== false, "Standard Markdown: text before standalone image keeps its paragraph");
test_assert(__LINE__, strpos($html, '<p>after</p>') !== false, "Standard Markdown: text after standalone image keeps its paragraph");

$text = "![alt text](https://example.com/a.png)\ndetails (note)";
$html = kona3markdown_parser_convert($text, false);

test_eq(__LINE__, substr_count($html, '<p>'), 1, "Standard Markdown: image followed by text ending in a parenthesis remains a paragraph");
test_assert(__LINE__, strpos($html, '<br/>details (note)</p>') !== false, "Standard Markdown: text after an inline image keeps its line break");

$text = "![xxx](https://n3s.nadesi.com/image.php?t=token&f=778.jpeg)";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<img src='https://n3s.nadesi.com/image.php?t=token&amp;f=778.jpeg'") !== false, "Standard Markdown: extensionless image URL is rendered through image plugin");

$text = "![diagram](diagram.mmd)";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, '<img') === false, "Standard Markdown: local .mmd file is not rendered as an image");
test_assert(__LINE__, strpos($html, "#ref(") !== false, "Standard Markdown: local .mmd file is routed through the ref plugin, not the image plugin");

$text = "```php\necho 1;\n```";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<pre class='code'>echo 1;") !== false, "Standard Markdown: backtick code block is rendered");

$text = "```mermaid\ngraph TD; A-->B;\n```";
$tokens = kona3markdown_parser_parse($text);

test_eq(__LINE__, $tokens[0]['cmd'], 'block', "Markdown Mode: mermaid fence creates a block token");
test_eq(__LINE__, $tokens[0]['params'][0], '#mermaid', "Markdown Mode: mermaid fence targets the mermaid plugin");
test_eq(__LINE__, $tokens[0]['params'][1], 'plugin', "Markdown Mode: mermaid fence is treated as a plugin block");

$html = kona3markdown_parser_render($tokens, false);

test_assert(__LINE__, strpos($html, '<pre class="mermaid">graph TD; A--&gt;B;') !== false, "Markdown Mode: mermaid fence renders a Mermaid diagram");
test_assert(__LINE__, strpos($html, "<pre class='code'>") === false, "Markdown Mode: mermaid fence is not rendered as source code");

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

// 8. <br> tag inside a table cell should break the line
$text = "| A | B |\n| --- | --- |\n| line1<br>line2 | x |";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<td style='text-align:left;'>line1<br/>line2</td>") !== false, "Table cell with <br> should render a line break");

// 9. <br/> and <br /> variants should also work
$text = "| A |\n| --- |\n| a<br/>b<br />c |";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, "<td style='text-align:left;'>a<br/>b<br/>c</td>") !== false, "Table cell should support <br/> and <br /> variants");

// 10. A second row with a different column count should not be misread as a separator row
$text = "| A | B |\n| --- |\n| x | y |";
$html = kona3markdown_parser_convert($text, false);

test_assert(__LINE__, strpos($html, '<thead>') === false, "Column-count mismatch on row 2 must not be treated as a separator");
test_assert(__LINE__, strpos($html, '<td>---</td>') !== false, "Row 2 should remain as ordinary data");
test_assert(__LINE__, strpos($html, '<td>x</td><td>y</td>') !== false, "Row 3 should remain as ordinary data");

// --- ルビ記法 {親文字|ルビ} ---
$html = kona3markdown_parser_convert("{中国語|zhōng guó yǔ}です", false);
test_assert(__LINE__, strpos($html, "<ruby>中国語<rp>(</rp><rt>zhōng guó yǔ</rt><rp>)</rp></ruby>") !== false, "Markdown: ルビ");

// ルビ記法のオンオフ (enable_ruby)
global $kona3conf;
$orig_ruby = isset($kona3conf['enable_ruby']) ? $kona3conf['enable_ruby'] : TRUE;
$kona3conf['enable_ruby'] = FALSE;
$html = kona3markdown_parser_convert("{日本語|にほんご}", false);
test_assert(__LINE__, strpos($html, "<ruby>") === false, "Markdown: enable_ruby=false でルビ無効");
$kona3conf['enable_ruby'] = TRUE;
$html = kona3markdown_parser_convert("{日本語|にほんご}", false);
test_assert(__LINE__, strpos($html, "<ruby>") !== false, "Markdown: enable_ruby=true でルビ有効");
$kona3conf['enable_ruby'] = $orig_ruby;

// ルビ記法のエスケープ
$html = kona3markdown_parser_convert("{<b>|<i>}", false);
test_assert(__LINE__, strpos($html, "<ruby>&lt;b&gt;<rp>(</rp><rt>&lt;i&gt;</rt>") !== false, "Markdown: 親文字とルビの両方がエスケープされる");
test_assert(__LINE__, strpos($html, "<b>") === false && strpos($html, "<i>") === false, "Markdown: ルビ内にタグが出力されない");

// --- HTML Comment Tests (<!-- ... -->) #88 ---

// 1. A comment-only page renders nothing (no visible text, no empty paragraph)
$html = kona3markdown_parser_convert("<!-- comment -->", false);
test_eq(__LINE__, $html, "", "Markdown comment: コメントのみのページは何も表示しない");
test_assert(__LINE__, strpos($html, '&lt;!--') === false, "Markdown comment: コメントがエスケープされて表示されることはない");

// 2. Block comment between paragraphs
$html = kona3markdown_parser_convert("before\n\n<!-- comment -->\n\nafter", false);
test_assert(__LINE__, strpos($html, '<p>before</p>') !== false, "Markdown comment: コメント前の段落を表示");
test_assert(__LINE__, strpos($html, '<p>after</p>') !== false, "Markdown comment: コメント後の段落を表示");
test_assert(__LINE__, strpos($html, 'comment') === false, "Markdown comment: ブロックコメントの内容を表示しない");
test_eq(__LINE__, substr_count($html, '<p>'), 2, "Markdown comment: ブロックコメントが空の段落を作らない");

// 3. A comment line without surrounding blank lines does not split the paragraph
$html = kona3markdown_parser_convert("before\n<!-- comment -->\nafter", false);
test_assert(__LINE__, strpos($html, '<p>before<br/>after</p>') !== false, "Markdown comment: 空行なしのコメント行は段落を分けない");

// 4. Inline comment in a paragraph
$html = kona3markdown_parser_convert("Hello <!-- hidden --> world", false);
test_assert(__LINE__, strpos($html, '<p>Hello  world</p>') !== false, "Markdown comment: インラインコメントを除去して前後をつなぐ");
test_assert(__LINE__, strpos($html, 'hidden') === false, "Markdown comment: インラインコメントの内容を表示しない");

// 5. Two comments on one line
$html = kona3markdown_parser_convert("a <!-- x --> b <!-- y --> c", false);
test_assert(__LINE__, strpos($html, '<p>a  b  c</p>') !== false, "Markdown comment: 1行に2つのコメントをどちらも除去");

// 6. Multi-line block comment; markup inside it must not be parsed
$text = "before\n\n<!--\n# NotTitle\n- NotList\n```\ncode\n```\n-->\n\nafter";
$tokens = kona3markdown_parser_parse($text);
$html = kona3markdown_parser_convert($text, false);
test_assert(__LINE__, strpos($html, 'NotTitle') === false && strpos($html, 'NotList') === false, "Markdown comment: 複数行コメントの内容を表示しない");
test_assert(__LINE__, strpos($html, '<h1') === false && strpos($html, '<li>') === false, "Markdown comment: コメント内の見出し・リストは解析しない");
foreach ($tokens as $tok) {
    test_assert(__LINE__, strpos($tok['text'], 'NotTitle') === false, "Markdown comment: コメント内はトークン化しない");
}

// 7. Text after --> on the same line is parsed as usual
$html = kona3markdown_parser_convert("<!-- comment --> trailing", false);
test_assert(__LINE__, strpos($html, '<p>trailing</p>') !== false, "Markdown comment: --> の後ろの本文を表示");
test_assert(__LINE__, strpos($html, 'comment') === false, "Markdown comment: 同一行のコメント内容を表示しない");

// 8. Leading whitespace before a block comment
$html = kona3markdown_parser_convert("  <!-- comment -->\ntext", false);
test_assert(__LINE__, strpos($html, '<p>text</p>') !== false, "Markdown comment: 行頭に空白があるコメントも除去");
test_assert(__LINE__, strpos($html, 'comment') === false, "Markdown comment: 空白付きコメントの内容を表示しない");

// 9. Inline comments in headings, lists, quotes and table cells
$html = kona3markdown_parser_convert("# Title <!-- hidden -->", false);
test_assert(__LINE__, strpos($html, '<h1>Title ') !== false && strpos($html, 'hidden') === false, "Markdown comment: 見出し内のコメントを除去");
$html = kona3markdown_parser_convert("- Item <!-- hidden -->", false);
test_assert(__LINE__, strpos($html, '<li>Item </li>') !== false && strpos($html, 'hidden') === false, "Markdown comment: リスト内のコメントを除去");
$html = kona3markdown_parser_convert("> quoted <!-- hidden -->", false);
test_assert(__LINE__, strpos($html, 'quoted') !== false && strpos($html, 'hidden') === false, "Markdown comment: 引用内のコメントを除去");
$html = kona3markdown_parser_convert("| A | B |\n| --- | --- |\n| x <!-- hidden --> | y |", false);
test_assert(__LINE__, strpos($html, "<td style='text-align:left;'>x </td>") !== false && strpos($html, 'hidden') === false, "Markdown comment: 表セル内のコメントを除去");

// 10. Multi-line comment opened in the middle of a paragraph:
// following lines are comment until -->, even if they start with markup,
// and the text after --> on the closing line remains visible.
$html = kona3markdown_parser_convert("Hello <!-- start\n- not a list\n--> world", false);
test_assert(__LINE__, strpos($html, 'not a list') === false, "Markdown comment: 段落途中で始まる複数行コメントの内容を表示しない");
test_assert(__LINE__, strpos($html, '<li>') === false, "Markdown comment: コメント中の行頭記号を解析しない");
test_assert(__LINE__, strpos($html, '<p>Hello <br/>world</p>') !== false, "Markdown comment: --> の後ろの本文を表示する");
$html = kona3markdown_parser_convert("Hello <!-- start\ncontinuation\nend --> world", false);
test_assert(__LINE__, strpos($html, 'continuation') === false && strpos($html, '<p>Hello <br/>world</p>') !== false, "Markdown comment: 段落途中の複数行コメントを除去");

// 11. Comments inside code are literals, not comments
$html = kona3markdown_parser_convert("```\n<!-- comment -->\n```", false);
test_assert(__LINE__, strpos($html, '&lt;!-- comment --&gt;') !== false, "Markdown comment: コードブロック内のコメント記法はそのまま表示");
$html = kona3markdown_parser_convert("`<!-- comment -->` test", false);
test_assert(__LINE__, strpos($html, '<code>&lt;!-- comment --&gt;</code>') !== false, "Markdown comment: インラインコード内のコメント記法はそのまま表示");
// An opener inside an inline code span does not start a multi-line comment
$html = kona3markdown_parser_convert("`<!--` and\nvisible", false);
test_assert(__LINE__, strpos($html, 'visible') !== false, "Markdown comment: コード内の <!-- でコメントを開始しない");

// 12. Escaped comment opener is literal text
$html = kona3markdown_parser_convert("\\<!-- not comment -->", false);
test_assert(__LINE__, strpos($html, '&lt;!-- not comment --&gt;') !== false, "Markdown comment: エスケープした <!-- はコメントにしない");

// 13. Unterminated comments run to the end of the document / text
$html = kona3markdown_parser_convert("before\n\n<!-- unclosed\nrest of doc", false);
test_assert(__LINE__, strpos($html, '<p>before</p>') !== false, "Markdown comment: 未終了コメント前の段落を表示");
test_assert(__LINE__, strpos($html, 'unclosed') === false && strpos($html, 'rest of doc') === false, "Markdown comment: 未終了コメントは文書末尾まで除去");
$html = kona3markdown_parser_convert("Hello <!-- unclosed", false);
test_assert(__LINE__, strpos($html, '<p>Hello </p>') !== false, "Markdown comment: 未終了インラインコメントはテキスト末尾まで除去");

// 14. Empty comment and comment with extra dashes
$html = kona3markdown_parser_convert("a<!---->b", false);
test_assert(__LINE__, strpos($html, '<p>ab</p>') !== false, "Markdown comment: 空コメント <!----> を除去");
$html = kona3markdown_parser_convert("<!--- dashed --->\ntext", false);
test_assert(__LINE__, strpos($html, '<p>text</p>') !== false && strpos($html, 'dashed') === false, "Markdown comment: ハイフン付きコメントを除去");

// 15. A comment between list items does not split the list
$html = kona3markdown_parser_convert("- one\n<!-- comment -->\n- two", false);
test_eq(__LINE__, substr_count($html, '<ul>'), 1, "Markdown comment: リスト項目間のコメントはリストを分けない");
test_assert(__LINE__, strpos($html, '<li>one</li>') !== false && strpos($html, '<li>two</li>') !== false, "Markdown comment: コメント前後のリスト項目を表示");

// --- #311: follow-up fixes for the comment handling of #307 ---

// 16. Code spans with multiple backticks: the opener inside is literal,
// and the rest of the line / document must not disappear
$html = kona3markdown_parser_convert("``code`` test", false);
test_assert(__LINE__, strpos($html, '<code>code</code>') !== false, "Markdown comment(#311): 複数バッククォートのコードスパンを表示");
$html = kona3markdown_parser_convert("コード ``<!--`` の使い方です。\nこの行も表示されるべきテキストです。", false);
test_assert(__LINE__, strpos($html, '<code>&lt;!--</code>') !== false, "Markdown comment(#311): ``<!--`` をコードとして表示");
test_assert(__LINE__, strpos($html, 'の使い方です') !== false && strpos($html, 'この行も表示されるべきテキストです') !== false, "Markdown comment(#311): ``<!--`` の後続テキストと次の行が消失しない");
$html = kona3markdown_parser_convert("``code <!-- x`` after\nnext line", false);
test_assert(__LINE__, strpos($html, '<code>code &lt;!-- x</code>') !== false && strpos($html, 'after') !== false && strpos($html, 'next line') !== false, "Markdown comment(#311): 複数バッククォートのコードスパンの内容と後続を表示");

// 17. The abrupt closings <!--> and <!---> are closed comments everywhere,
// also in the middle of a paragraph
$html = kona3markdown_parser_convert("段落の途中 <!--> ここからテキスト\n次の行", false);
test_assert(__LINE__, strpos($html, 'ここからテキスト') !== false && strpos($html, '次の行') !== false, "Markdown comment(#311): 段落途中の <!--> で後続が消失しない");
$html = kona3markdown_parser_convert("段落の途中 <!---> ここからテキスト\n次の行", false);
test_assert(__LINE__, strpos($html, 'ここからテキスト') !== false && strpos($html, '次の行') !== false, "Markdown comment(#311): 段落途中の <!---> で後続が消失しない");

// 18. A multi-line comment can start at the end of a heading, list,
// quote or table line; continuation lines are hidden until -->
$html = kona3markdown_parser_convert("# 見出し <!-- コメント開始\n非表示にしたい行\n-->\n本文", false);
test_assert(__LINE__, strpos($html, '<h1>見出し ') !== false, "Markdown comment(#311): 見出しを表示");
test_assert(__LINE__, strpos($html, '非表示にしたい行') === false && strpos($html, '-&gt;') === false, "Markdown comment(#311): 見出しから始まる複数行コメントの後続行を表示しない");
test_assert(__LINE__, strpos($html, '<p>本文</p>') !== false, "Markdown comment(#311): コメント後の本文を表示");
$html = kona3markdown_parser_convert("- Item <!-- start\ncontinuation\n--> end", false);
test_assert(__LINE__, strpos($html, '<li>Item </li>') !== false && strpos($html, 'continuation') === false && strpos($html, 'end') !== false, "Markdown comment(#311): リストから始まる複数行コメントの後続行を表示しない");
$html = kona3markdown_parser_convert("> quoted <!-- start\ncontinuation\n--> end", false);
test_assert(__LINE__, strpos($html, 'quoted') !== false && strpos($html, 'continuation') === false && strpos($html, 'end') !== false, "Markdown comment(#311): 引用から始まる複数行コメントの後続行を表示しない");
$html = kona3markdown_parser_convert("| A |\n| --- |\n| x <!-- start |\ncontinuation\n--> end", false);
test_assert(__LINE__, strpos($html, "<td style='text-align:left;'>x </td>") !== false && strpos($html, 'continuation') === false && strpos($html, '<p>end</p>') !== false, "Markdown comment(#311): 表行から始まる複数行コメントの後続行を表示しない");
// A closed comment at the end of such a line does not start comment mode
$html = kona3markdown_parser_convert("- Item <!-- x --> more\n- second", false);
test_assert(__LINE__, strpos($html, '<li>Item  more</li>') !== false && strpos($html, '<li>second</li>') !== false, "Markdown comment(#311): 同行で閉じるコメントは複数行モードにしない");

// 19. A backslash at the end of an inline code span is a literal, so a
// following multi-line comment is detected correctly
$html = kona3markdown_parser_convert("`C:\\` <!-- start\ncontinuation\n--> end", false);
test_assert(__LINE__, strpos($html, '<code>C:\\</code>') !== false, "Markdown comment(#311): 末尾バックスラッシュのコードスパンを表示");
test_assert(__LINE__, strpos($html, 'continuation') === false && strpos($html, '-&gt;') === false, "Markdown comment(#311): コードスパン直後の複数行コメントの後続行を表示しない");
test_assert(__LINE__, strpos($html, 'end') !== false, "Markdown comment(#311): コメント後のテキストを表示");

// --- PR #312 review: the renderer must close a code span only at a run of
// exactly the same length as its opener, like the comment scanner does ---

// 20. A longer backtick run inside a code span is literal content
$html = kona3markdown_parser_convert("``foo ``` bar``", false);
test_assert(__LINE__, strpos($html, '<code>foo ``` bar</code>') !== false, "Markdown comment(#312): 長いランを含むコードスパンを1つに表示");
test_eq(__LINE__, substr_count($html, "<span class='code'>"), 1, "Markdown comment(#312): コードスパンが断片化しない");
// Even with a single backtick and a comment opener after the longer run,
// nothing may be treated as a comment or disappear
$html = kona3markdown_parser_convert("``a ``` b` <!-- x`` c\nd", false);
test_assert(__LINE__, strpos($html, '<code>a ``` b` &lt;!-- x</code>') !== false, "Markdown comment(#312): コードスパン内の <!-- をリテラル表示");
test_assert(__LINE__, strpos($html, 'c<br/>d') !== false, "Markdown comment(#312): コードスパン後のテキストと次の行が消失しない");
test_eq(__LINE__, substr_count($html, "<span class='code'>"), 1, "Markdown comment(#312): コードスパンは1つだけになる");
// A shorter run inside a single-backtick span does not close it either
$html = kona3markdown_parser_convert("`a `` b`", false);
test_assert(__LINE__, strpos($html, '<code>a `` b</code>') !== false, "Markdown comment(#312): 単一バッククォート内の二連バッククォートはリテラル");
// Boundary cases: a blank-only span keeps its spaces, and a span may
// contain single backticks when opened by a longer run
$html = kona3markdown_parser_convert("``   `` end", false);
test_assert(__LINE__, strpos($html, '<code>   </code>') !== false, "Markdown comment(#312): 空白のみのコードスパンは空白を保持");
$html = kona3markdown_parser_convert("``foo ` bar`` end", false);
test_assert(__LINE__, strpos($html, '<code>foo ` bar</code>') !== false, "Markdown comment(#312): 二連バッククォートで単一バッククォートを含むコードを表示");

// 21. Cases confirmed by the PR #312+#315 series review:
// a multi-line comment starting at the end of a numbered list item
// (its token cmd is "-", so it is covered by the token check), and
// an unterminated code span whose <!-- is not a comment.
$html = kona3markdown_parser_convert("1. First <!-- comment\nhidden\n--> visible", false);
test_assert(__LINE__, strpos($html, '<li>1. First </li>') !== false && strpos($html, 'hidden') === false && strpos($html, 'visible') !== false, "Markdown comment(#312+#315): 番号付きリストから始まる複数行コメントの後続行を表示しない");
$html = kona3markdown_parser_convert("1. Item <!-- start\ncontinuation\n-->\nend", false);
test_assert(__LINE__, strpos($html, 'continuation') === false && strpos($html, '<p>end</p>') !== false, "Markdown comment(#312+#315): 番号付きリストの複数行コメントで --> 単独行も正しく処理");
$html = kona3markdown_parser_convert("- Item `code <!-- not a comment\nstill visible line", false);
test_assert(__LINE__, strpos($html, '<code>code &lt;!-- not a comment</code>') !== false, "Markdown comment(#312+#315): 未終了コードスパン内の <!-- はコメントにしない");
test_assert(__LINE__, strpos($html, 'still visible line') !== false, "Markdown comment(#312+#315): 未終了コードスパンの次の行を表示する");
