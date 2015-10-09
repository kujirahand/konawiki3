<?php
/** KonaWiki3 show */

include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';
include_once dirname(dirname(__FILE__)).'/kona3parser.inc.php';

function kona3_action_show() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $page_h = htmlspecialchars($page);
  $fname = kona3getWikiFile($page);
  if (!file_exists($fname)) {
    kona3error("404 page not found", "Sorry, page not found");
    exit;
  }
  // body
  $txt = @file_get_contents($fname);
  $txt = konawiki_parser_convert($txt);
  // ---
  $menufile = @kona3getWikiFile("MenuBar");
  $menu = file_get_contents($menufile);
  $menuhtml = konawiki_parser_convert($menu);
  //
  kona3template('show', array(
    "page_title" => kona3text2html($page),
    "page_body"  => $txt,
    "wiki_menu"  => $menuhtml
  ));
}
