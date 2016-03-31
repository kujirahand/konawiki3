<?php
/** KonaWiki3 show */

include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';
include_once dirname(dirname(__FILE__)).'/kona3parser.inc.php';

function kona3_action_show() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $page_h = htmlspecialchars($page);
  // wiki file (text)
  $fname = kona3getWikiFile($page);
  // wiki file eixsits?
  $ext = "txt";
  $wiki_live = file_exists($fname);
  if (!$wiki_live) {
    // mark down
    $fname = kona3getWikiFile($page, TRUE, '.md');
    $wiki_live = file_exists($fname);
    if ($wiki_live) {
      $ext = "md";
    } else {
      $fname = kona3getWikiFile($page, FALSE);
      $wiki_live = file_exists($fname);
      if (preg_match('#\.([a-z]+)$#', $fname, $m)) {
        $ext = $m[1];
      }
    }
  }
  // body
  if ($wiki_live) {
    $txt = @file_get_contents($fname);
  } else {
    $updir = dirname($page);
    $txt = "*** $page\n\n".
           "Not Found\n\n". 
           "#ls()\n";
  }
  // convert
  $cnt_txt = mb_strlen($txt);
  if ($ext == "txt") {
    $page_body = konawiki_parser_convert($txt);
  } else if ($ext == "md") {
    $page_body = _markdown_convert($txt);
  } else {
    kona3error($page, "Sorry, System Error."); exit;
  }

  // menu
  $menufile = kona3getWikiFile("MenuBar");
  if (file_exists($menufile)) {
    $menu = @file_get_contents($menufile);
    $menuhtml = konawiki_parser_convert($menu);
  } else {
    $menuhtml = "";
  }

  // show
  kona3template('show', array(
    "page_title" => kona3text2html($page),
    "page_body"  => $page_body,
    "cnt_txt"    => $cnt_txt,
    "wiki_menu"  => $menuhtml
  ));
}

function _markdown_convert($txt) {
  require_once dirname(dirname(__FILE__)).'/vendor/autoload.php';
  $parser = new \cebe\markdown\GithubMarkdown();
  $txt = $parser->parse($txt);
  return $txt;
}




