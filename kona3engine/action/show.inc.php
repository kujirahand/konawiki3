<?php
/** KonaWiki3 show */

function kona3_action_show() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $page_h = htmlspecialchars($page);

  // check login
  kona3show_check_private($page);

  // detect file type
  $wiki_live = kona3show_detect_file($page, $fname, $ext);

  // body
  if ($wiki_live) {
    $txt = @file_get_contents($fname);
    $kona3conf['data_filename'] = $fname;
  } else {
    $txt = kona3show_file_not_found($page, $ext);
  }

  // convert
  $ext = strtolower($ext);
  if ($ext == ".txt") {
    $page_body = konawiki_parser_convert($txt);
  } else if ($ext == ".md") {
    $page_body = kona3show_markdown_convert($txt);
  } else if ($ext == "__dir__") {
    $txt = "* {$page}\n\n#ls"; // ls
    $page_body = konawiki_parser_convert($txt);
  } else if ($ext == '.png' || $ext == '.gif' || $ext == '.jpg' || $ext == '.jpeg') {
    $txt = "#ref({$page})"; // images
    $page_body = konawiki_parser_convert($txt);
  } else if ($ext == '.pdf' || $ext == '.xlsx' || $ext == '.docx' || $ext == '.xls' || $ext == '.doc') {
    $txt = "#ref({$page})"; // pdf 
    $page_body = konawiki_parser_convert($txt);
  } else {
    kona3error($page, "Sorry, System Error."); exit;
  }
  // counter
  $cnt_txt = mb_strlen($txt);

  // header and footer
  $allpage_header = '';
  $allpage_footer = '';
  if (!empty($kona3conf['allpage_header'])) {
    $allpage_header =
      "<div class='allpage_header'>". 
      konawiki_parser_convert(
        $kona3conf['allpage_header']).
      "</div><!-- end of .allpage_hader -->\n";
  }
  if (!empty($kona3conf['allpage_footer'])) {
    $allpage_footer = 
      "<div class='allpage_footer'>".
      konawiki_parser_convert(
        $kona3conf['allpage_footer']).
      "</div><!-- end of .allpage_footer -->\n";
  }
  $page_body = 
    $allpage_header.
    $page_body.
    $allpage_footer;

  // show
  kona3template('show.html', [
    "page_title" => $page,
    "page_body"  => $page_body,
    "cnt_txt"    => $cnt_txt,
    "page_file"  => $fname,
  ]);
}

function kona3show_check_private($page) {
  if (kona3getConf('wiki_private')) {
    if (!kona3isLogin()) {
      $url = kona3getPageURL($page, "login");
      $msg_private = lang('Private Mode');
      $msg_please_login = lang('Please login.');
      kona3error(
        $page, 
        "<div>$msg_private</div><div>&nbsp;</div>".
        "<div><a href='$url'>$msg_please_login</a></div>");
      exit;
    }
  }
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
  // is text file?
  $fname = kona3getWikiFile($page, TRUE, '.txt', FALSE);
  if (file_exists($fname)) {
    $ext = '.txt';
    return TRUE;
  }
  // dir?
  $fname = kona3getWikiFile($page, FALSE);
  if (is_dir($fname)) {
    $ext = '__dir__';
    return TRUE;
  }
  // direct
  $fname = kona3getWikiFile($page,FALSE, '', FALSE);
  if (file_exists($fname)) {
    $ext = '';
    if (preg_match('#(\.[a-zA-Z0-9_]+)$#', $fname, $m)) {
      $ext = $m[1];
    }
    return TRUE;
  }
  return FALSE;
}

function kona3show_markdown_convert($txt) {
  require_once dirname(dirname(__FILE__)).'/vendor/autoload.php';
  $parser = new \cebe\markdown\MarkdownExtra();
  $txt = $parser->parse($txt);
  return $txt;
}
