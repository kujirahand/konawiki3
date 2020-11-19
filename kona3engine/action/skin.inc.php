<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';

function kona3_action_skin() {
  global $kona3conf;
  $fname = $kona3conf["page"];

  // check filename
  if (!preg_match('#^[a-zA-Z0-9\_\%][a-zA-Z0-9\_\.\-\%]+$#', $fname)) {
    header('HTTP/1.0 404 Not Found');
    echo "File not found.\n";
    exit;
  }
  
  // get extension
  $ext = "";
  if (preg_match('/(\.\w+?)$/', $fname, $m)) {
    $ext = $m[1];
  }
  $skin = $kona3conf['skin'];
  $skin_dir = KONA3_DIR_SKIN;
  $res_dir = $kona3conf['path_resource'];
  
  // check skin dir
  $path = "$skin_dir/$skin/$fname";
  if (!file_exists($path)) {
    $path = "$skin_dir/def/$fname";
    if (!file_exists($path)) {
      $path = "$res_dir/$fname";
      if (!file_exists($path)) {
        header('HTTP/1.0 404 Not Found');
        echo "File not found.";
        exit;
      }
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



