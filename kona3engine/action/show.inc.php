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
  if (!file_exists($fname)) {
    // mark down
    $fname = kona3getWikiFile($page, TRUE, '.md');
    if (!file_exists($fname)) {
      if (kona3isLogin()) {
        $url = kona3getPageURL($page, "edit");
        header("Location: $url");
      } else {
        kona3error($page, "Sorry, Page Not Found.");
      }
      exit;
    }
  }
  // body
  $txt = @file_get_contents($fname);
  $cnt_txt = mb_strlen($txt);
  $ext = "txt";
  if (preg_match('#\.(md|txt)$#', $fname, $m)) {
    $ext = $m[1];
  }
  // convert
  if ($ext == "txt") {
    $txt = konawiki_parser_convert($txt);
  } else if ($ext == "md") {
    $txt = _markdown_convert($txt);
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
    "page_body"  => $txt,
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




