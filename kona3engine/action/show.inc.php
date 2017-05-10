<?php
/** KonaWiki3 show */

include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';
include_once dirname(dirname(__FILE__)).'/kona3parser.inc.php';

function kona3_action_show() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $page_h = htmlspecialchars($page);

  // check login
  if ($kona3conf["wiki.private"]) {
    if (!kona3isLogin()) {
      $url = kona3getPageURL($page, "login");
      kona3error($page, "Private mode. <a href='$url'>Please login.</a>"); exit;
    }
  }

  // detect file type
  $wiki_live = kona3show_detect_file($page, $fname, $ext);

  // body
  if ($wiki_live) {
    $txt = @file_get_contents($fname);
  } else {
    $updir = dirname($page);
    $txt = "*** ls\n\n".
           "#ls()\n";
    // image
    if ($ext === 'png' || $ext === 'jpg' || $ext === 'jpeg' || $ext == 'gif') {
      $txt = "#ref($page,width=400,*$page)\n".$txt;
    }
    // directory?
    else if ($ext == '__dir__') {
      $txt = "*** Directory: $page\n\n#ls()\n";
    }
    else if ($ext == '') {
      $txt = "not found.\n".$txt;
    }
    else {
      $txt = "*** file: [$ext] $page\n#ref($page)\n".$txt;
    }
    $ext = "txt";
  }

  // convert
  $cnt_txt = mb_strlen($txt);
  if ($ext == "txt") {
    $page_body = konawiki_parser_convert($txt);
  } else if ($ext == "md") {
    $page_body = kona3show_markdown_convert($txt);
  } else {
    kona3error($page, "Sorry, System Error."); exit;
  }

  // every page
  if (!empty($kona3conf['allpage.footer'])) {
    $footer = konawiki_parser_convert($kona3conf['allpage.footer']);
    $page_body .= "<hr>".$footer;
  }

  // show
  kona3template('show', array(
    "page_title" => kona3text2html($page),
    "page_body"  => $page_body,
    "cnt_txt"    => $cnt_txt,
  ));
}

function kona3show_detect_file($page, &$fname, &$ext) {

  // wiki file (text)
  $ext = 'txt';
  $fname = kona3getWikiFile($page, $ext);
  if (file_exists($fname)) return true;

  // markdown
  $ext = 'md';
  $fname = kona3getWikiFile($page, $ext);
  if (file_exists($fname)) return true;

  // is dir?
  $ext = '';
  $fname = kona3getWikiFile($page, false);
  if (is_dir($fname)) {
    $ext = '__dir__';
    return false;
  }

  // make link
  $fname = kona3getWikiFile($page, false);
  if (preg_match('#\.([a-z0-9]+)$#', $fname, $m)) {
    $ext = $m[1];
  } else {
    $ext = '';
  }
  return false;
}

function kona3show_markdown_convert($txt) {
  require_once dirname(dirname(__FILE__)).'/vendor/autoload.php';
  $parser = new \cebe\markdown\GithubMarkdown();
  $txt = $parser->parse($txt);
  return $txt;
}
