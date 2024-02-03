<?php

/** 最近更新されたページを列挙する
 * - [書式] #recent(count)
 * - [引数]
 * -- count ... 件数(省略可)
 * -- title .... テキスト一行目を表示する(省略可)
 */

function kona3plugins_recent_execute($args) {
  // get args
  $limit = 10;
  $title = FALSE;
  foreach ($args as $arg) {
    $arg = trim($arg);
    if (preg_match('#^\d+$#', $arg)) {
      $limit = intval($arg);
    }
    if (preg_match('#count=(\d+)$#', $arg, $m)) {
      $limit = intval($m[1]);
    }
    if ($arg == 'title') {
      $title = TRUE;
    }
  }
  // select
  $r = db_get(
    "SELECT * FROM pages ".
    "ORDER BY mtime DESC ".
    "LIMIT ?",
    [$limit]);
  $head = "<h3>".lang('Recent')."</h3>";
  if (!$r) {
    return $head."<p>None</p>";
  }
  $list = "";
  foreach ($r as $v) {
    $page = $v["name"];
    $mtime = $v["mtime"];
    if ($page == "FrontPage" || $page == "MenuBar" || $page == "GlobalBar") {
      continue;
    }
    $url = kona3getPageURL($page);
    $page_h = kona3text2html($page);
    $mtime_h = kona3date($mtime);
    if ($title) {
      $is_live = kona3show_detect_file($page, $fname, $ext);
      if (!$is_live) { continue; } // page not exists
      // print_r([$is_live, $fname, $ext]);
      $txt = trim(file_get_contents($fname));
      $a = explode("\n", $txt);
      $page_h = $page_h . " - " . htmlspecialchars($a[0], ENT_QUOTES);
      // trim
      if (mb_strlen($page_h) > 50) {
        $page_h = mb_strimwidth($page_h, 0, 50, "...");
      }
    }
    $list .= 
      "<li>".
      "<a href='$url'>$page_h $mtime_h</a>".
      "</li>";
  }
  $list = "<ul class='recent'>$list</ul>";
  return $head.$list;
}


