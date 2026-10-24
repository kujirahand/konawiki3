<?php
require_once __DIR__ . '/test_common.inc.php';

// ダークモードのまま印刷したときに、背景が残らないことを確認する (#288)
// 印刷用(@media print)で背景を消しているセレクタに、ダーク用の !important で背景を付けるルールがあるなら、
// 印刷用にも body.dark-theme 付きの !important で背景を消すルールが必要

// @media print { ... } の中身と、それ以外を分ける
function skin_print_split($css)
{
    $print = '';
    $rest = $css;
    while (preg_match('#@media\s+print\s*\{#', $rest, $m, PREG_OFFSET_CAPTURE)) {
        $start = $m[0][1];
        $i = $start + strlen($m[0][0]);
        $depth = 1;
        while ($depth > 0 && $i < strlen($rest)) {
            if ($rest[$i] === '{') $depth++;
            if ($rest[$i] === '}') $depth--;
            $i++;
        }
        $print .= substr($rest, $start + strlen($m[0][0]), $i - 1 - ($start + strlen($m[0][0])));
        $rest = substr($rest, 0, $start) . substr($rest, $i);
    }
    return [$print, $rest];
}

// 背景を指定しているルールのセレクタ(カンマ区切りを分解)
function skin_print_bgSelectors($css, $requireImportant)
{
    $res = [];
    preg_match_all('#([^{}]+)\{([^{}]*)\}#', $css, $rules, PREG_SET_ORDER);
    foreach ($rules as $rule) {
        if (!preg_match('#background(-color)?\s*:[^;]*' . ($requireImportant ? '!important' : '') . '#', $rule[2])) {
            continue;
        }
        foreach (explode(',', $rule[1]) as $part) {
            $res[] = preg_replace('#\s+#', ' ', trim($part));
        }
    }
    return $res;
}

foreach (['def', 'aqua', 'nako3', 'single'] as $skin) {
    $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(KONA3_DIR_SKIN . "/$skin/kona3.css"));
    if ($skin !== 'def') {
        // 派生スキンは def の上に重なるので、def のダーク用ルールも対象にする
        $def = preg_replace('#/\*.*?\*/#s', '', file_get_contents(KONA3_DIR_SKIN . '/def/kona3.css'));
        list($def_print, $def_rest) = skin_print_split($def);
    } else {
        $def_print = $def_rest = '';
    }
    list($print, $rest) = skin_print_split($css);
    $dark_bg = skin_print_bgSelectors($rest . $def_rest, true);
    $print_bg = skin_print_bgSelectors($print . $def_print, false);
    $print_dark_bg = skin_print_bgSelectors($print . $def_print, true);
    foreach ($print_bg as $sel) {
        if (strpos($sel, 'body.dark-theme') === 0) {
            continue;
        }
        $dark_sel = "body.dark-theme $sel";
        if (!in_array($dark_sel, $dark_bg)) {
            continue; // ダーク用の !important がなければ、通常の印刷ルールで消える
        }
        test_assert(__LINE__, in_array($dark_sel, $print_dark_bg), "$skin: print resets background of '$dark_sel' with !important");
    }
}

// ダークモードでも、表の上端の罫線を表示すること
// darkmode.css の変数化(#294)で、ダーク用に表の罫線を消す指定はなくなった。
// そのため、通常の(ライト用の)ルールの border-top がダークでもそのまま効く
$css = file_get_contents(KONA3_DIR_SKIN . '/def/kona3.css');
test_assert(__LINE__, preg_match('#\#kona3_main table tr:first-child > th,\s*\#kona3_main table tr:first-child > td\s*\{\s*border-top:#', $css) === 1, "def: table keeps the top border rule");
test_assert(__LINE__, strpos($css, 'body.dark-theme #kona3_main table') === false, "def: no dark table override (darkmode.css is variable-based)");
