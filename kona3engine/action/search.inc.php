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

  $res= [];
  if ($am == "search") {
    $result = array();
    $path_data = $kona3conf["path.data"];
    kona3search($key, $result, $path_data);
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
    "result" => $res,
    "action" => $action,
  ));
}

function kona3search($key, &$result, $dir) {
  global $kona3conf;
  global $_search_exclude;
  if ($key == "") return;
  $flist = glob($dir.'/*');
  foreach ($flist as $f) {
    if ($f == "." || $f == "..") continue;
    if (is_dir($f)) {
      $dirname = basename($f);
      if (array_search($dirname, $_search_exclude)) continue;
      kona3search($key, $result, $f);
      continue;
    }
    if (preg_match('/\.(md|txt)$/', $f)) {
      $txt = @file_get_contents($f);
      if (strpos($txt, $key) !== FALSE) {
        $result[] = $f;
        continue;
      }
    }
  }
}







