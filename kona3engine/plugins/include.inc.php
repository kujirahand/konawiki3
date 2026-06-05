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

function kona3plugins_include_file($name, &$html) {
  // text
  $fname = kona3getWikiFile($name, true, '.txt');
  if (file_exists($fname)) {
    $txt = file_get_contents($fname);
    $html = konawiki_parser_convert($txt);
    return true;  
  }
  // md
  $fname = kona3getWikiFile($name, true, '.md');
  if (file_exists($fname)) {
    $txt = file_get_contents($fname);
    $html = kona3plugins_include_markdown_convert($txt);
    return true;
  }
  return false;
}

function kona3plugins_include_markdown_convert($txt) {
  return kona3markdown_parser_convert($txt);
}
