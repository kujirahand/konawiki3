<?php

/** 「S:」や「T:」などの書式の行の文字に色をつけるプラグイン
 * - [書式] {{{#color_talk(引数) ...本文... }}}
 * - [引数]
 * -- S=blue, T=red, ... などの「書式=色」の組み合わせをカンマ区切りで指定
 */

/** CSSカラーとして安全な値か (#fff / #ffffff / 色名 / rgb() / rgba() / hsl() / hsla()) */
function kona3plugins_color_talk_isValidColor($color) {
    if (!is_string($color) || $color === '' || strlen($color) > 64) {
        return false;
    }
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
        return true;
    }
    if (preg_match('/^[a-zA-Z]+$/', $color)) {
        return true; // 色名 (red, blue, ...)
    }
    if (preg_match('/^(rgb|rgba|hsl|hsla)\(\s*[0-9.,%\s\/]+\)$/', $color)) {
        return true;
    }
    return false;
}

function kona3plugins_color_talk_execute($args) {
    $body = array_pop($args); // 末尾の引数は本文
    $color_map = array();
    foreach ($args as $arg) {
        $parts = explode('=', $arg, 2);
        if (count($parts) == 2) {
            $format = trim($parts[0]);
            $color = trim($parts[1]);
            // 色は style 属性へそのまま埋め込むため、CSSの色として
            // 正しい形式のものだけを受け付ける (#324)。
            // ( ' や ; を含む値は属性を抜け出すXSSになる )
            if (!kona3plugins_color_talk_isValidColor($color)) {
                continue;
            }
            $color_map[$format] = $color;
        }
    }
    $lines = explode("\n", $body);
    $result = '';
    foreach ($lines as $line) {
        $line = htmlspecialchars(trim($line), ENT_QUOTES);
        // 行の先頭が「書式:」の形式であれば、その行に対応する色を適用
        if (preg_match('/^-?\s*(\w+):\s*(.+)$/', $line, $matches)) {
            $format = $matches[1];
            $line2 = $matches[2];
            if (isset($color_map[$format])) {
                $color = $color_map[$format];
                $result .= "<li><span style='color: {$color};'><b>{$format}</b> {$line2}</span></li>\n";
                continue;
            }
        }
        if ($line == "") { continue; }
        $result .= "<li>$line</li>\n";
    }
    return "<ul>$result</ul>";
}


