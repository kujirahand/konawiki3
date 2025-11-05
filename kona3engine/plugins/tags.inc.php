<?php
/** タグ一覧を表示する
 * - [書式] #tags(tag,sort=(mtime|page),limit=30)
 * - [引数]
 * -- tag ... タグ名
 * -- sort=xxx ... ソート方法(mtime|page)
 * -- limit=xxx ... 表示件数
 */
function kona3plugins_tags_execute($args) {
  $limit = 30;
  $sort = 'mtime';
  $tag = '';
  // check params
  foreach ($args as $arg) {
    if ($arg == 'sort=mtime') { $sort = 'mtime'; continue; }
    if ($arg == 'sort=page') { $sort = 'page'; continue; }
    if ($arg == 'sort=page_id') { $sort = 'page'; continue; } // 互換性のため
    if (preg_match('/^limit=(\d+)/', $arg, $m)) {
      $limit = intval($m[1]);
      continue;
    }
    if ($tag == '') { $tag = $arg; }
  }
  return kona3plugins_tags_getTags($tag, $sort, $limit);
}

function kona3plugins_tags_getTags($tag, $sort = 'mtime', $limit = 30) {
  // 新しいファイルベースのタグシステムから取得
  $pages = kona3tags_getPages($tag, $sort, $limit);
  
  $code = "";
  if ($pages && count($pages) > 0) {
    $tag_h = htmlspecialchars($tag);
    $code .= "<div class='kona3-tags-list'>\n";
    $code .= "<h3>🏷️ Tag: {$tag_h}</h3>\n";
    $code .= "<ul>";
    foreach ($pages as $p) {
      $page = $p['page'];
      $page_h = htmlspecialchars($page);
      $url = kona3getPageURL($page);
      $code .= "<li><a href='$url'>{$page_h}</a></li>\n";
    }
    $code .= "</ul>\n";
    $code .= "</div>\n";
  } else {
    $tag_h = htmlspecialchars($tag);
    $code = "<div class='kona3-tags-list'><p>タグ「{$tag_h}」が設定されているページはありません。</p></div>";
  }
  return $code;
}

function kona3plugins_tags_action() {
  $tag = kona3param('tag', '');
  $tag_h = htmlspecialchars($tag);
  $code = kona3plugins_tags_getTags($tag, 'mtime', 300);
  kona3showMessage("Tag: $tag_h", $code, 'white.html');
}
