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
  
  // 各ページの内容をチェックして、タグが実際に埋め込まれているか確認
  $valid_pages = [];
  foreach ($pages as $p) {
    $page = $p['page'];
    if (kona3plugins_tags_hasTagInPage($page, $tag)) {
      $valid_pages[] = $p;
    } else {
      // タグが見つからない場合は削除
      kona3tags_removePageTag($page, $tag);
    }
  }
  
  $code = "";
  if ($valid_pages && count($valid_pages) > 0) {
    $tag_h = htmlspecialchars($tag);
    $code .= "<div class='kona3-tags-list'>\n";
    $code .= "<h3>🏷️ Tag: {$tag_h}</h3>\n";
    $code .= "<ul>";
    foreach ($valid_pages as $p) {
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

/**
 * ページ内に指定したタグが埋め込まれているかチェックする
 * @param string $page ページ名
 * @param string $tag タグ名
 * @return bool タグが見つかった場合true
 */
function kona3plugins_tags_hasTagInPage($page, $tag) {
  // ページファイルを取得
  $filepath = koan3getWikiFileText($page);
  if (!file_exists($filepath)) {
    return false;
  }
  
  // ページの内容を読み込む
  $body = file_get_contents($filepath);
  if (empty($body)) {
    return false;
  }
  
  // #tag(TAG) の形式でタグが埋め込まれているかチェック
  // エスケープされた正規表現でチェック
  $tag_escaped = preg_quote($tag, '/');
  $pattern = '/#tag\s*\(\s*' . $tag_escaped . '\s*\)/i';
  
  return preg_match($pattern, $body) > 0;
}

function kona3plugins_tags_action() {
  $tag = kona3param('tag', '');
  $tag_h = htmlspecialchars($tag);
  $code = kona3plugins_tags_getTags($tag, 'mtime', 300);
  kona3showMessage("Tag: $tag_h", $code, 'white.html');
}
