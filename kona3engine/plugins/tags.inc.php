<?php
/**
 * #tags(tag,sort=(mtime|page_id,limit=30))
 */
function kona3plugins_tags_execute($args) {
  $limit = 30;
  $sort = 'mtime';
  $tag = '';
  // check params
  foreach ($args as $arg) {
    if ($arg == 'sort=mtime') { $sort = 'mtime'; continue; }
    if ($arg == 'sort=page_id') { $sort = 'page_id'; continue; }
    if (preg_match('/^limit=(\d+)/', $arg, $m)) {
      $limit = $m[1];
      continue;
    }
    $tag = $arg;
  }
  // get
  $r = db_get(
    'SELECT * FROM tags WHERE tag=? ORDER BY '.$sort.' LIMIT ?', 
    [$tag, $limit]);
  $code = "";
  if ($r) {
    $code .= "<ul>";
    $code .= "<li>".htmlspecialchars($tag)."</li>\n";
    foreach ($r as $t) {
      $page = kona3db_getPageNameById($t['page_id']);
      $page_h = htmlspecialchars($page);
      $url = kona3getPageURL($page);
      $code .= "<li><a href='$url'>{$page_h}</a></li>\n";
    }
    $code .= "</ul>\n";
  }
  return $code;
}
