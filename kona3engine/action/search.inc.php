<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';
// $kona3conf['search.exclude'] に検索除外対象ディレクトリを記述可能
global $_search_exclude;
$_search_exclude = empty($kona3conf['search.exclude']) 
    ? array('vendor', 'node_modules') 
    : $kona3konf['search.exclude'];


function kona3_action_search() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $action = kona3getPageURL($page, "search");  
  $am   = kona3param('a_mode', '');
  $key  = kona3param('a_key', '');
  $a_body = kona3param('a_body', '');
  $search_body = ($a_body == 'on');

  // キーが短すぎるなら検索しない
  if ($am == 'search' && mb_strlen($key) < 2) {
    $url = kona3getPageURL($page, 'search');
    kona3error('検索できません', 
      "2文字以上のキーワードを入力してください。".
      "<a href='$url'>→やり直す</a>");
    return;
  }

  $res= [];
  if ($am == "search") {
    $result = array();
    $path_data = KONA3_DIR_DATA;
    kona3search($key, $result, $path_data, $search_body);
    foreach ($result as $f) {
      $path = str_replace("$path_data/", "", $f);
      $path = preg_replace('/\.(txt|md)$/', '', $path);
      $url = kona3getPageURL($path);
      $res[] = ["url" => $url, "name" => $path];
    }
  }
  $kona3conf["robots"] = "noindex";
  // show
  kona3template('search.html', array(
    "page_title" => $page,
    "key" => $key,
    "a_body" => ($search_body) ? 'checked' : '',
    "result" => $res,
    "action" => $action,
  ));
}

function kona3search($key, &$result, $dir, $search_body, $level = 0) {
  global $kona3conf;
  global $_search_exclude;
  if ($key == "") return;

  // 再帰の深さを確認
  $max_search_level = kona3getConf('max_search_level', 3);
  if ($level >= $max_search_level) return FALSE;
  // 最大検索結果表示数を超えた？
  $max_search = kona3getConf('max_search', 30);
  if ($max_search <= count($result)) return TRUE;
 
  $flist = glob($dir.'/*');
  $path_len = strlen(KONA3_DIR_DATA);
  // check filename
  foreach ($flist as $f) {
    if ($f == "." || $f == "..") continue;
    if (!preg_match('/\.(md|txt)$/', $f)) continue;
    $ff = substr($f, $path_len);
    if (strpos($ff, $key) !== FALSE) {
      $result[] = $f;
      if ($max_search <= count($result)) return TRUE;
      continue;
    }
  }
  // check text file and sub folder
  foreach ($flist as $f) {
    if ($f == "." || $f == "..") continue;
    if (is_dir($f)) {
      $dirname = basename($f);
      if (array_search($dirname, $_search_exclude)) continue;
      kona3search($key, $result, $f, $search_body, $level + 1);
      continue;
    }
    if (!$search_body) continue;
    if (preg_match('/\.(md|txt)$/', $f)) {
      // check contents
      $txt = @file_get_contents($f);
      if (strpos($txt, $key) !== FALSE) {
        $result[] = $f;
        if ($max_search <= count($result)) return TRUE;
        continue;
      }
    }
  }
}







