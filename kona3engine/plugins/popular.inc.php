<?php

/** 人気のあるページを列挙する
 * - [書式] #popular(count)
 * - [引数]
 * -- count ... 件数(省略可)
 */

function kona3plugins_popular_execute($args) {
  global $kona3conf;
  
  $page = $kona3conf['page'];
  $limit = array_shift($args);
  $limit = intval($limit);
  if ($limit <= 0) { $limit = 10; }

  // can i use popular?
  if (!db_table_exists("counter")) {
    return "<h3>".lang('Popular')."</h3><p>---</p>";
  }

  $head = "<h3>".lang('Popular')."</h3>";
  $r = db_get(
    "SELECT * FROM counter ".
    "WHERE mtime > 0 ".
    "ORDER BY value DESC LIMIT ?",[$limit]);
  if (!$r) {
    return $head."<p>None</p>";
  }
  $list = "";
  foreach ($r as $v) {
    $page_id = $v['page_id'];
    $page = kona3db_getPageNameById($page_id);
    // FrontPage/MenuBar を除外
    if ($page == $kona3conf['FrontPage'] || $page == "MenuBar" ||
      $page == "GlobalBar" || $page == "SideBar" || $page == '') {
      continue;
    }
    $mtime = $v["mtime"];
    $url = kona3getPageURL($page);
    $page_h = kona3text2html($page);
    $value = $v["value"];
    $list .= 
      "<li>".
      "<a href='$url'>$page_h ".
      "<span class='popular_info'>($value)</span></a>".
      "</li>";
  }
  $list = "<ul class='recent'>$list</ul>";
  return $head.$list;
}


