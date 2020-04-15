<?php

/**
 * [USAGE] #include(pagename_list)
 * - inlucde other pages
 * - (ex) {{{#include(LF)name1(LF)name2(LF)name3(LF)}}}
 */

include_once dirname(dirname(__FILE__)).'/kona3parser.inc.php';

function kona3plugins_include_execute($args) {
  global $kona3conf;
  $files_str = array_shift($args);
  $files = explode("\n", $files_str);
  $txtlist = array();

  foreach($files as $name) {
    $name = trim($name);
    if ($name == "") continue;
    $b = kona3plugins_include_file($name, $html);
    $txtlist[] = $html;
  }

  return implode("\n", $txtlist);
}

function kona3plugins_include_file($name, &$html) {
  // dir?
  $fname = kona3getWikiFile($name, false);
  if (is_dir($fname)) return false;
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
  // else
  $m_edit = lang('Edit');
  $url = kona3getPageURL($name, 'edit');
  $name_html = htmlspecialchars($name);
  $html = "<a href=\"$url\" class=\"pure-button\">$m_edit: $name_html</a>";
  return false;
}

function kona3plugins_include_markdown_convert($txt) {
  require_once dirname(dirname(__FILE__)).'/vendor/autoload.php';
  $parser = new \cebe\markdown\GithubMarkdown();
  $txt = $parser->parse($txt);
  return $txt;
}


