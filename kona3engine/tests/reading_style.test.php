<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/kona3parser.inc.php';
require_once dirname(__DIR__) . '/kona3parser_md.inc.php';

// 読書体験 (#340 方向3): 本文の組版・行長・印刷が docs/default_skin.md の方針どおりか確認する

$root_dir = dirname(dirname(__DIR__));
$css_file = $root_dir . '/skin/def/kona3.css';
$css = preg_replace('#/\*.*?\*/#s', '', file_get_contents($css_file));

// 指定した @media ブロックの中身と、それ以外に分ける(波括弧の深さを数える)
function reading_split_media($css, $pattern)
{
    $inner = '';
    $rest = $css;
    while (preg_match($pattern, $rest, $m, PREG_OFFSET_CAPTURE)) {
        $start = $m[0][1];
        $body_start = $start + strlen($m[0][0]);
        $i = $body_start;
        $depth = 1;
        while ($depth > 0 && $i < strlen($rest)) {
            if ($rest[$i] === '{') $depth++;
            if ($rest[$i] === '}') $depth--;
            $i++;
        }
        $inner .= substr($rest, $body_start, $i - 1 - $body_start);
        $rest = substr($rest, 0, $start) . substr($rest, $i);
    }
    return [$inner, $rest];
}

// @media print { ... } の中身と、それ以外に分ける
function reading_split_print($css)
{
    return reading_split_media($css, '#@media\s+print\s*\{#');
}

// セレクタリストを分解し、空白を正規化する
function reading_selectors($selector)
{
    return array_map(function ($s) {
        return preg_replace('#\s+#', ' ', trim($s));
    }, explode(',', $selector));
}

// 全ルールを [セレクタの配列, 宣言] で返す
function reading_rules($css)
{
    $res = [];
    preg_match_all('#([^{}]+)\{([^{}]*)\}#', $css, $rules, PREG_SET_ORDER);
    foreach ($rules as $rule) {
        $res[] = [reading_selectors($rule[1]), $rule[2]];
    }
    return $res;
}

// 指定したセレクタを含むルールの宣言を連結して返す
function reading_decls($css, $selector)
{
    $decls = '';
    foreach (reading_rules($css) as $rule) {
        if (in_array($selector, $rule[0])) {
            $decls .= $rule[1] . ';';
        }
    }
    return $decls;
}

// 最初の :root { ... } の中身
function reading_root_block($css)
{
    return preg_match('#:root\s*\{([^}]*)\}#', $css, $m) ? $m[1] : '';
}

// 宣言の中に「プロパティ: 値」があるか
function reading_has($decls, $prop, $value)
{
    return preg_match('#(^|[;{\s])' . preg_quote($prop, '#') . '\s*:\s*' . preg_quote($value, '#') . '\s*(;|$)#', $decls) === 1;
}

list($print, $rest) = reading_split_print($css);
list($sp, $rest_main) = reading_split_media($rest, '#@media\s+screen\s+and\s+\(max-width:\s*480px\)\s*\{#');

// ---------- A. トークン ----------
$tokens = [
    '--k3-measure' => '40em',
    '--k3-line-height-body' => '1.85',
    '--k3-line-height-tight' => '1.35',
    '--k3-line-height-code' => '1.6',
    '--k3-fs-h1' => '1.75em',
    '--k3-fs-h2' => '1.45em',
    '--k3-fs-h3' => '1.2em',
    '--k3-fs-h4' => '1.05em',
    '--k3-fs-small' => '0.875em',
    '--k3-fs-code' => '0.9em',
];
$root = reading_root_block($css);
foreach ($tokens as $name => $value) {
    test_assert(__LINE__, reading_has($root, $name, $value), "first :root defines $name: $value");
}
test_assert(__LINE__, preg_match('#@media\s+screen\s*\{\s*body\.dark-theme\s*\{([^}]*)\}#', $css, $m) === 1, "dark variable block exists");
$dark = $m[1] ?? '';
foreach (array_keys($tokens) as $name) {
    test_assert(__LINE__, strpos($dark, $name . ':') === false && strpos($dark, $name . ' :') === false, "dark theme does not redefine $name");
}

// ---------- B. 行間と利用者設定 ----------
test_assert(__LINE__, reading_has(reading_decls($rest, '#kona3_layout_show'), 'line-height', 'var(--k3-line-height-body)'), "#kona3_layout_show uses --k3-line-height-body");
$body_lh_rules = [];
foreach (reading_rules($rest) as $rule) {
    if (strpos($rule[1], 'var(--k3-line-height-body)') !== false) {
        $body_lh_rules[] = implode(', ', $rule[0]);
    }
}
test_eq(__LINE__, $body_lh_rules, ['#kona3_layout_show'], "only #kona3_layout_show refers to --k3-line-height-body");
foreach (reading_rules($css) as $rule) {
    $target = false;
    foreach ($rule[0] as $sel) {
        if ($sel === '#kona3_main' || $sel === '#kona3_main p' || strpos($sel, '.contents') !== false) {
            $target = true;
        }
    }
    if ($target) {
        test_assert(__LINE__, preg_match('#(^|[;\s])line-height\s*:#', $rule[1]) === 0, "no line-height on body text: " . implode(', ', $rule[0]));
    }
}
test_assert(__LINE__, preg_match('#line-height\s*:\s*1\.75\b#', $css) === 0, "literal line-height: 1.75 is removed");
$inject = '#kona3_layout_show, #wikibody, .edit_preview_body { line-height: ';
test_assert(__LINE__, strpos(file_get_contents(dirname(__DIR__) . '/template/parts_header.html'), $inject) !== false, "parts_header.html injects user line-height into #kona3_layout_show (and the edit preview)");
test_assert(__LINE__, strpos(file_get_contents(dirname(__DIR__) . '/resource/drawer.js'), $inject) !== false, "drawer.js injects user line-height into #kona3_layout_show (and the edit preview)");

// ---------- C. 見出し ----------
$h_all = reading_decls($rest, '#kona3_main h6');
test_assert(__LINE__, reading_has($h_all, 'line-height', 'var(--k3-line-height-tight)'), "headings use --k3-line-height-tight");
test_assert(__LINE__, reading_has($h_all, 'word-break', 'auto-phrase'), "headings use word-break: auto-phrase");
test_assert(__LINE__, reading_has($h_all, 'text-spacing-trim', 'trim-start'), "headings use text-spacing-trim: trim-start");
foreach ([1, 2, 3, 4] as $n) {
    test_assert(__LINE__, reading_has(reading_decls($rest_main, "#kona3_main h$n"), 'font-size', "var(--k3-fs-h$n)"), "h$n uses --k3-fs-h$n");
}
$h56 = '';
foreach (reading_rules($rest_main) as $rule) {
    if ($rule[0] === ['#kona3_main h5', '#kona3_main h6']) {
        $h56 .= $rule[1];
    }
}
test_assert(__LINE__, reading_has($h56, 'font-size', '1em'), "h5, h6 rule (separated from h4) uses font-size: 1em");
$margin_start = function ($sel) use ($rest_main) {
    return preg_match('#(^|[;\s])margin\s*:\s*(var\(--k3-space-\d\))#', reading_decls($rest_main, $sel), $m) ? $m[2] : '';
};
test_eq(__LINE__, $margin_start('#kona3_main h1'), 'var(--k3-space-6)', "h1 top margin is space-6");
test_eq(__LINE__, $margin_start('#kona3_main h2'), 'var(--k3-space-6)', "h2 top margin is space-6");
test_eq(__LINE__, $margin_start('#kona3_main h3'), 'var(--k3-space-5)', "h3 top margin is space-5");
test_eq(__LINE__, $margin_start('#kona3_main h4'), 'var(--k3-space-5)', "h4 top margin is space-5");
foreach ([1, 2, 3] as $n) {
    test_assert(__LINE__, reading_has(reading_decls($rest, "#kona3_main .contents > h$n:first-child"), 'margin-top', '0'), "first h$n in .contents has no top margin");
}
test_assert(__LINE__, $sp !== '', "max-width: 480px block exists");
test_assert(__LINE__, reading_has(reading_decls($sp, '#kona3_main h1'), 'font-size', '1.5em'), "sp: h1 font-size 1.5em");
test_assert(__LINE__, reading_has(reading_decls($sp, '#kona3_main h2'), 'font-size', '1.3em'), "sp: h2 font-size 1.3em");
test_assert(__LINE__, reading_has(reading_decls($sp, '#kona3_main h2'), 'margin-top', 'var(--k3-space-5)'), "sp: h2 margin-top space-5");

// ---------- D. measure ----------
$measure_rules = [];
foreach (reading_rules($css) as $rule) {
    if (strpos($rule[1], 'var(--k3-measure)') !== false) {
        $measure_rules[] = $rule;
    }
}
test_eq(__LINE__, count($measure_rules), 1, "only one rule refers to --k3-measure");
$measure_sels = $measure_rules[0][0] ?? [];
test_assert(__LINE__, reading_has($measure_rules[0][1] ?? '', 'max-width', 'var(--k3-measure)'), "measure is applied as max-width");
foreach (['p', 'ul', 'ol', 'dl', 'blockquote', '.resmark'] as $el) {
    test_assert(__LINE__, in_array("#kona3_main > .contents > $el", $measure_sels), "measure applies to #kona3_main > .contents > $el");
}
foreach ($measure_sels as $sel) {
    test_assert(__LINE__, $sel !== '#kona3_main' && $sel !== '#kona3_main > .contents', "measure is not applied to the whole column: $sel");
    test_assert(__LINE__, preg_match('#\b(table|pre|h[1-6])\b#', $sel) === 0, "measure is not applied to table/pre/headings: $sel");
}
foreach (['show.html', 'print.html'] as $tpl) {
    $html = file_get_contents(dirname(__DIR__) . "/template/$tpl");
    test_assert(__LINE__, preg_match('#<div id="kona3_main">.*\{\{\$page_body \| safe\}\}.*</div><!-- \#kona3_main -->#s', $html) === 1, "$tpl: page_body is inside #kona3_main");
}
test_assert(__LINE__, strpos(konawiki_parser_convert("abc"), '<div class="contents">') === 0, "konawiki parser wraps body with div.contents");
test_assert(__LINE__, strpos(kona3markdown_parser_convert("abc"), '<div class="contents">') === 0, "markdown parser wraps body with div.contents");
// measure の前提: 段落とリストが .contents の直下に出ること (直下でないと max-width が効かない)
$kona_html = konawiki_parser_convert("段落のテキストです。\n\n・項目1\n・項目2");
test_assert(__LINE__, strpos($kona_html, "<div class=\"contents\">\n<p>") === 0, "konawiki parser: p is a direct child of .contents");
test_assert(__LINE__, preg_match('/\n<ul>\n<li>/', $kona_html) === 1, "konawiki parser: ul is a direct child of .contents");
$md_html = kona3markdown_parser_convert("段落のテキストです。\n\n- 項目1\n- 項目2");
test_assert(__LINE__, strpos($md_html, "<div class=\"contents\">\n<p>") === 0, "markdown parser: p is a direct child of .contents");
test_assert(__LINE__, preg_match('/\n<ul>\n<li>/', $md_html) === 1, "markdown parser: ul is a direct child of .contents");
foreach (glob(dirname(__DIR__) . '/template/*.html') as $tpl) {
    $name = basename($tpl);
    if ($name === 'show.html' || $name === 'print.html') {
        continue;
    }
    test_assert(__LINE__, strpos(file_get_contents($tpl), 'id="kona3_main"') === false, "$name does not have #kona3_main");
}

// ---------- E. 和文組版 ----------
$main = reading_decls($rest, '#kona3_main');
test_assert(__LINE__, reading_has($main, 'line-break', 'strict'), "#kona3_main uses line-break: strict");
test_assert(__LINE__, reading_has($main, 'text-autospace', 'normal'), "#kona3_main uses text-autospace: normal");
foreach (['pre', 'code', 'span.code', 'textarea'] as $sel) {
    test_assert(__LINE__, reading_has(reading_decls($rest, $sel), 'text-autospace', 'no-autospace'), "$sel uses text-autospace: no-autospace");
}

// ---------- F. リンク・表・コード・引用・小さい文字 ----------
foreach (['#kona3_main .contents a:link', '#kona3_main .contents a:visited'] as $sel) {
    $d = reading_decls($rest, $sel);
    test_assert(__LINE__, reading_has($d, 'text-decoration', 'underline'), "$sel is underlined");
    test_assert(__LINE__, reading_has($d, 'text-decoration-thickness', '1px'), "$sel underline thickness 1px");
    test_assert(__LINE__, reading_has($d, 'text-underline-offset', '0.2em'), "$sel underline offset 0.2em");
}
foreach (['#kona3_main .contents a.anchor_super:link', '#kona3_main .contents a.pure-button:link', '#kona3_main .contents a:has(> img):link'] as $sel) {
    test_assert(__LINE__, reading_has(reading_decls($rest, $sel), 'text-decoration', 'none'), "$sel has no underline");
}
test_assert(__LINE__, reading_has(reading_decls($rest, '#kona3_main a:link'), 'text-decoration', 'none'), "#kona3_main a:link (outside .contents) has no underline");
$table = reading_decls($rest, '#kona3_main table');
test_assert(__LINE__, reading_has($table, 'font-variant-numeric', 'tabular-nums'), "table uses tabular-nums");
test_assert(__LINE__, reading_has($table, 'line-height', 'var(--k3-line-height-tight)'), "table uses --k3-line-height-tight");
$pre = reading_decls($rest, 'pre.code');
test_assert(__LINE__, reading_has($pre, 'font-size', 'var(--k3-fs-code)'), "pre uses --k3-fs-code");
test_assert(__LINE__, reading_has($pre, 'line-height', 'var(--k3-line-height-code)'), "pre uses --k3-line-height-code");
test_assert(__LINE__, reading_has(reading_decls($rest, 'span.code'), 'font-size', 'var(--k3-fs-code)'), "span.code uses --k3-fs-code");
test_assert(__LINE__, preg_match('#font-size\s*:\s*0\.875em#', $css) === 0, "literal font-size: 0.875em is removed");
$quote = reading_decls($rest, '#kona3_main blockquote');
test_assert(__LINE__, reading_has($quote, 'font-style', 'normal'), "blockquote is not italic");
test_assert(__LINE__, reading_has($quote, 'color', 'var(--k3-color-text-muted)'), "blockquote uses muted text color");
test_assert(__LINE__, preg_match('#(^|[;\s])border-left\s*:#', $quote) === 1, "blockquote has a left border");
test_assert(__LINE__, reading_decls($rest, '#kona3_main .resmark') === reading_decls($rest, '#kona3_main blockquote'), ".resmark shares the blockquote rule");
foreach (['#kona3_main .date', '#kona3_main .imagecaption', '.memo'] as $sel) {
    test_assert(__LINE__, reading_has(reading_decls($rest, $sel), 'font-size', 'var(--k3-fs-small)'), "$sel uses --k3-fs-small");
}

// ---------- G. 印刷 ----------
test_assert(__LINE__, preg_match('#@page\s*\{\s*margin\s*:\s*20mm\s*;?\s*\}#', $rest) === 1, "top-level @page { margin: 20mm }");
test_assert(__LINE__, strpos($print, '@page') === false, "@page is not nested in @media print");
test_assert(__LINE__, reading_has(reading_decls($print, ':root'), '--k3-measure', 'none'), "print: :root { --k3-measure: none }");
test_assert(__LINE__, reading_has(reading_decls($print, 'body'), 'font-size', '10.5pt'), "print: body font-size 10.5pt");
$pmain = reading_decls($print, '#kona3_main');
test_assert(__LINE__, reading_has($pmain, 'orphans', '3'), "print: #kona3_main orphans: 3");
test_assert(__LINE__, reading_has($pmain, 'widows', '3'), "print: #kona3_main widows: 3");
foreach ([1, 2, 3, 4, 5, 6] as $n) {
    test_assert(__LINE__, reading_has(reading_decls($print, "#kona3_main h$n"), 'break-after', 'avoid'), "print: h$n break-after: avoid");
}
test_assert(__LINE__, reading_has(reading_decls($print, '#kona3_main table tr'), 'break-inside', 'avoid'), "print: table tr break-inside: avoid");
test_assert(__LINE__, reading_has(reading_decls($print, '#kona3_main table thead'), 'display', 'table-header-group'), "print: thead repeats on each page");
test_assert(__LINE__, preg_match('#break-inside#', reading_decls($print, '#kona3_main table')) === 0, "print: table itself has no break-inside (avoid large blanks)");
$ppre = reading_decls($print, 'pre.code');
test_assert(__LINE__, reading_has($ppre, 'break-inside', 'avoid'), "print: pre break-inside: avoid");
test_assert(__LINE__, reading_has($ppre, 'overflow', 'visible'), "print: pre overflow: visible");
test_assert(__LINE__, reading_has($ppre, 'white-space', 'pre-wrap'), "print: pre white-space: pre-wrap");
$pfc = reading_decls($print, '.filecode .filecode-body > pre.code');
test_assert(__LINE__, reading_has($pfc, 'white-space', 'pre-wrap'), "print: filecode pre white-space: pre-wrap (overrides kona3def.css by same specificity)");
test_assert(__LINE__, reading_has($pfc, 'width', 'auto'), "print: filecode pre width: auto");
$bg_rules = [];
foreach (reading_rules($print) as $rule) {
    if (preg_match('#(^|[;\s])background(-color)?\s*:#', $rule[1])) {
        $bg_rules[] = implode(', ', $rule[0]);
    }
}
test_eq(__LINE__, $bg_rules, ['#kona3_main table th, pre, pre.code, span.code, .column'], "print: no new background rules");

// ---------- H. 全体の健全性 ----------
test_eq(__LINE__, substr_count($css, '{'), substr_count($css, '}'), "braces are balanced");
