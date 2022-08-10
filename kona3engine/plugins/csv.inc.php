<?php
/**
 * - [書式] {{{#csv([noheader][flag=xxx][cell=xxx]) データ }}}
 * - [引数]
 * -- noheader ... 一行目をヘッダとしない
 * -- flag=xxx ... 区切り文字
 * -- cell=xxx ... セルの変換(wiki|text) wikiでwiki記法をパース/デフォルトはwiki
 * -- plain ... 強制セルの変換を行わない
 * -- データ ... CSVデータを指定する
*/
function kona3plugins_csv_execute($params) {
    if (!$params) return "";
    $cellType = "wiki";
    $noheader = FALSE;
    $csv = "";
    $delimiter = ",";
    foreach ($params as $s) {
        if ($s == "noheader") {
            $noheader = TRUE;
            continue;
        }
        if ($s == 'plain') {
            $cellType = 'text';
            continue;
        }
        if (preg_match('#flag\=(.+)#', $s, $m)) {
          $delimiter = $m[1];
          continue;
        }
        if (preg_match('#wiki\=(.+)#', $s, $m)) {
          $cellType = $m[1];
          continue;
        }
        $csv = $s;
        break;
    }
    
    $html = "<table>\n";
    $lines = explode("\n", trim($csv));
    // header
    if ($noheader == FALSE) {
        $line = array_shift($lines);
        $cols = explode($delimiter, $line);
        $html .= "<tr>";
        foreach ($cols as $col) {
            $col = trim($col);
            $col = _cell($col, $cellType);
            $html .= "<th>{$col}</th>";
        }
        $html .= "</tr>\n";
    }
    // csv body
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line) continue;
        $cols = explode($delimiter, $line);
        $html .= "<tr>";
        foreach ($cols as $col) {
            $col = trim($col);
            $col = _cell($col, $cellType);
            $html .= "<td>$col</td>";
        }
        $html .= "</tr>\n";
    }
    $html .= "</table>\n";
    return $html;
}

function _cell($cell, $type) {
  if ($type == 'wiki') {
    $cell = konawiki_parser_convert($cell, FALSE);
    return $cell;
  }
  $cell = kona3text2html($cell);
  return $cell;
}



