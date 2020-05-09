<?php

function kona3plugins_recent_execute($args) {
  $limit = array_shift($args);
  $limit = intval($limit);
  if ($limit <= 0) { $limit = 10; }
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
    if ($page == "FrontPage" || $page == "MenuBar" ||
      $page == "GlobalBar") {
      continue;
    }
    $url = kona3getPageURL($page);
    $page_h = kona3text2html($page);
    $mtime_h = kona3date($mtime);
    $list .= 
      "<li>".
      "<a href='$url'>$page_h $mtime_h</a>".
      "</li>";
  }
  $list = "<ul class='recent'>$list</ul>";
  return $head.$list;
}


