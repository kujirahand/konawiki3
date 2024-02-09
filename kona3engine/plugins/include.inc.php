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
  global $kona3conf;
  $files_str = array_shift($args);
  $files = explode("\n", $files_str);
  $txtlist = array();

  foreach($files as $name) {
    $name = str_replace('..', '', $name); // 上のフォルダを許さない
    $name = trim($name);
    if ($name == "") continue;
    $b = kona3plugins_include_file($name, $html);
    if ($b) {
      $txtlist[] = $html;
    }
  }

  return implode("\n", $txtlist);
}

function kona3plugins_include_file($name, &$html) {
  // text
  $fname = kona3getWikiFile($name, 'txt');
  if (file_exists($fname)) {
    $txt = file_get_contents($fname);
    $html = konawiki_parser_convert($txt);
    return true;  
  }
  // md
  $fname = kona3getWikiFile($name, 'md');
  if (file_exists($fname)) {
    $txt = file_get_contents($fname);
    $html = kona3plugins_include_markdown_convert($txt);
    return true;
  }
  // dir?
  $fname = kona3getWikiFile($name, false);
  if (is_dir($fname)) return false;
  // else
  $m_edit = lang('Edit');
  $url = kona3getPageURL($name, 'edit');
  $name_html = htmlspecialchars($name);
  $html = "<a href=\"$url\" class=\"pure-button\">$m_edit: $name_html</a>";
  return false;
}

function kona3plugins_include_markdown_convert($txt) {
  return kona3markdown_parser_convert($txt);
}


