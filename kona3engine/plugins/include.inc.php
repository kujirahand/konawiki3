<?php

/** 複数WIKIファイルを取り込んで表示する
 * - [書式] #include(pagename_list)
 * - [引数]
 * -- pagename_list ... inlucde other pages
 * - [利用例] {{{#include flie1 \n file2 \n file3 ... }}}
 */

include_once dirname(dirname(__FILE__)) . '/kona3parser.inc.php';
include_once dirname(dirname(__FILE__)) . '/kona3parser_md.inc.php';

function kona3plugins_include_execute($args) {
  $files_str = implode("\n", $args);
  $files = explode("\n", $files_str);
  $txtlist = array();

  foreach($files as $name) {
    $name = str_replace('..', '', $name); // 上のフォルダを許さない
    $name = trim($name);
    if ($name == "") continue;
    $html = "";
    $b = kona3plugins_include_file($name, $html);
    if ($b) {
      $txtlist[] = $html;
    } else {
      $name_esc = htmlspecialchars($name);
      $txtlist[] = "<div class='kona3plugins_include_error'>Error: $name_esc is not found.</div>";
    }
  }

  return implode("\n", $txtlist);
}

/** #include / #file の入れ子の深さ上限 (自己・相互参照による無限再帰を防ぐ, #324) */
if (!defined('KONA3_INCLUDE_MAX_DEPTH')) {
  define('KONA3_INCLUDE_MAX_DEPTH', 5);
}

/** $fname の実パスが data ディレクトリ配下にあるか (シンボリックリンク経由の外部読み取りを防ぐ, #324) */
function kona3plugins_include_inDataDir($fname) {
  $real = realpath($fname);
  $data = realpath(KONA3_DIR_DATA);
  if ($real === false || $data === false) return false;
  $data = rtrim($data, '/\\');
  return $real === $data || strpos($real, $data . DIRECTORY_SEPARATOR) === 0;
}

function kona3plugins_include_file($name, &$html) {
  // 実際に存在するファイルを解決する (メタデータのmode → def_text_ext の優先順)
  $fname = koan3getWikiFileText($name);
  if (file_exists($fname)) {
    if (!kona3plugins_include_inDataDir($fname)) {
      return false;
    }
    // 入れ子が深すぎる場合は変換しない (循環参照によるメモリ枯渇を防ぐ)
    global $kona3_include_depth;
    if (!isset($kona3_include_depth)) $kona3_include_depth = 0;
    if ($kona3_include_depth >= KONA3_INCLUDE_MAX_DEPTH) {
      $html = "<div class='kona3plugins_include_error'>Error: include depth limit exceeded.</div>";
      return true;
    }
    $txt = file_get_contents($fname);
    $kona3_include_depth++;
    try {
      if (preg_match('/\.md$/', $fname)) {
        $html = kona3plugins_include_markdown_convert($txt);
      } else {
        $html = konawiki_parser_convert($txt);
      }
    } finally {
      $kona3_include_depth--;
    }
    return true;
  }
  return false;
}

function kona3plugins_include_markdown_convert($txt) {
  return kona3markdown_parser_convert($txt);
}
