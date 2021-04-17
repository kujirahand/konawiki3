<?php
/** KonaWiki3 go */

function kona3_action_go() {
  global $kona3conf;
  $page_id = intval($kona3conf["page"]);
  $page = kona3db_getPageNameById($page_id);
  if ($page == '') {
    header("HTTP/1.1 404 Not Found");
    echo "<h3>Page Not found ...</h3>";
    echo "<p>page_id=$page_id</p>";
    exit;
  }
  $url = kona3getPageURL($page, 'show');
  header("location: $url");
  echo "<a href='$url'>JUMP</a>";
  exit;
}

