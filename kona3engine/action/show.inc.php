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
  $txt = file_get_contents($fname);
  $charCount = mb_strlen($txt);
  $byteCount = strlen($txt);
  $txt = "{$page_h} --- {$charCount}字/{$byteCount}B\n\n".$txt;
  // link wiki origin text flie
  // $txt .= "\n------\n";
  $urlwiki = kona3getWikiUrl($page).'.txt';
  // $txt .= "{{{#html\norigin: <a href='$urlwiki'>text</a>\n}}}\n";
  // $txt .= "{{{#html\n<input value='$fname'>\n}}}\n\n";
  $txt .= "\n------\n";
  $txt .= "[[FrontPage]]\n";
  $txt = konawiki_parser_convert($txt);
  // ---
  $menufile = kona3getWikiFile("MenuBar");
  $menu = @file_get_contents($menufile);
  $menuhtml = konawiki_parser_convert($menu);
  //
  kona3template('show', array(
    "title" => kona3text2html($page),
    "body"  => $txt,
    "menu"  => $menuhtml
  ));
}


