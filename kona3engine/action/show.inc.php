<?php
/** KonaWiki3 show */

function kona3_action_show() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $page_h = htmlspecialchars($page);

  // check login
  if ($kona3conf["wiki.private"]) {
    if (!kona3isLogin()) {
      $url = kona3getPageURL($page, "login");
      $msg_private = lang('Private Mode');
      $msg_please_login = lang('Please login.');
      kona3error(
        $page, 
        "$msg_private <a href='$url'>$msg_please_login.</a>");
      exit;
    }
  }

  // detect file type
  $wiki_live = kona3show_detect_file($page, $fname, $ext);

  // body
  if ($wiki_live) {
    $txt = @file_get_contents($fname);
  } else {
    $txt = kona3show_file_not_found($page, $ext);
  }

  // convert
  $cnt_txt = mb_strlen($txt);
  if ($ext == ".txt") {
    $page_body = konawiki_parser_convert($txt);
  } else if ($ext == ".md") {
    $page_body = kona3show_markdown_convert($txt);
  } else {
    kona3error($page, "Sorry, System Error."); exit;
  }

  // show
  kona3template('show.html', [
    "page_title" => $page,
    "page_body"  => $page_body,
    "cnt_txt"    => $cnt_txt,
    "page_file"  => $fname,
  ]);
}

function kona3show_file_not_found($page, &$ext) {
  $updir = dirname($page);
  $PageNotFound = lang('Page Not Found.');
  $txt = "* {$page}\n{$PageNotFound}\n";
  $txt .= "*** ls\n\n".
         "#ls()\n";
  // directory?
  if ($ext == '__dir__') {
    $txt .= "*** Directory: $page\n\n#ls()\n";
  }
  else if ($ext == '') {
    $txt .= "not found.\n".$txt;
  }
  $ext = ".txt";
  return $txt;
}

function kona3show_detect_file($page, &$fname, &$ext) {
  // check file types
  $ext_list = ['.txt', '.md', '.png', '.jpg', '.jpeg'];
  foreach ($ext_list as $ext) {
    // encode uri
    $fname = kona3getWikiFile($page, TRUE, $ext, TRUE);
    if (file_exists($fname)) return TRUE;
    // no encode uri
    $fname = kona3getWikiFile($page, TRUE, $ext, FALSE);
    if (file_exists($fname)) return TRUE;
  }

  // is dir?
  $ext = '';
  $fname = kona3getWikiFile($page, FALSE);
  if (is_dir($fname)) {
    $ext = '__dir__';
    return FALSE;
  }

  // make link
  $fname = kona3getWikiFile($page, TRUE);
  if (preg_match('#\.([a-z0-9]+)$#', $fname, $m)) {
    $ext = $m[1];
  } else {
    $ext = '';
  }
  return FALSE;
}

function kona3show_markdown_convert($txt) {
  require_once dirname(dirname(__FILE__)).'/vendor/autoload.php';
  $parser = new \cebe\markdown\MarkdownExtra();
  $txt = $parser->parse($txt);
  return $txt;
}
