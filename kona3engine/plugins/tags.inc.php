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
      $limit = intval($m[1]);
      continue;
    }
    if ($tag == '') { $tag = $arg; }
  }
  return kona3plugins_tags_getTags($tag, $sort, $limit);
}

function kona3plugins_tags_getTags($tag, $sort = 'mtime', $limit = 30) {
  // get
  $r = db_get(
    'SELECT * FROM tags WHERE tag=? ORDER BY '.$sort.' LIMIT ?', 
    [$tag, $limit]);
  $code = "";
  if ($r) {
    $code .= "Tag: ".htmlspecialchars($tag)."</li>\n";
    $code .= "<ul>";
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

function kona3plugins_tags_action() {
  $tag = kona3param('tag', '');
  $tag_h = htmlspecialchars($tag);
  $code = kona3plugins_tags_getTags($tag, 'mtime', 300);
  kona3showMessage("Tag: $tag_h", $code, 'white.html');
}
