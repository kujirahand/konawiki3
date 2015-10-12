<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';

function kona3_action_skin() {
  global $kona3conf;
  $fname = $kona3conf["page"];
  
  // get extension
  $ext = "";
  if (preg_match('/(\.\w+?)$/', $fname, $m)) {
    $ext = $m[1];
  }
  $skin = KONA3_WIKI_SKIN;
  $skin_dir = KONA3_DIR_SKIN;
  
  // check skin dir
  $path = "$skin_dir/$skin/$fname";
  if (!file_exists($path)) {
    $path = "$skin_dir/def/$fname";
    if (!file_exists($path)) {
      header('HTTP/1.0 404 Not Found');
      exit;
    }
  }
  // output
  $ext = strtolower($ext);
  $ctype = "text/plain";
  if ($ext == ".css") $ctype = "text/css";
  else if ($ext == ".js") $ctype = "text/javascript";
  //
  header("Content-Type: $ctype");
  //
  $s = file_get_contents($path);
  echo $s;
}



