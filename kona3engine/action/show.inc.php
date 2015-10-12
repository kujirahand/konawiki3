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
    if (kona3isLogin()) {
      $url = kona3getPageURL($page, "edit");
      header("Location: $url");
    } else {
      kona3error($page, "Sorry, Page Not Found.");
    }
    exit;
  }
  // body
  $txt = @file_get_contents($fname);
  $txt = konawiki_parser_convert($txt);

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
    "page_body"  => $txt,
    "wiki_menu"  => $menuhtml
  ));
}
