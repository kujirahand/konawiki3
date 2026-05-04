<?php

/** 「S:」や「T:」などの書式の行の文字に色をつけるプラグイン
 * - [書式] {{{#color_talk(引数) ...本文... }}}
 * - [引数]
 * -- S=blue, T=red, ... などの「書式=色」の組み合わせをカンマ区切りで指定
 */

function kona3plugins_color_talk_execute($args) {
    $body = array_pop($args); // 末尾の引数は本文
    $color_map = array();
    foreach ($args as $arg) {
        $parts = explode('=', $arg, 2);
        if (count($parts) == 2) {
            $format = trim($parts[0]);
            $color = trim($parts[1]);
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
                $result .= "<li><span style='color: {$color};'>{$line2}</span></li>\n";
                continue;
            }
        }
        if ($line == "") { continue; }
        $result .= "<li>$line</li>\n";
    }
    return "<ul>$result</ul>";
}


