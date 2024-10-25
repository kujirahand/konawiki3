<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';
include_once __DIR__.'/show.inc.php';

function kona3_action_data() {
  $fileNotFound = "<html><body><h1>404 File not found.</h1><a href='index.php'>-&gt; index</a></body></html>\n\n";
  $fname = kona3getConf('page');
  // check login
  if (kona3show_check_private($fname)) {
    return;
  } 
  // check filename
  if (!preg_match('#^[a-zA-Z0-9\_\%][a-zA-Z0-9\_\.\-\%\/]+$#', $fname)) {
    header('HTTP/1.0 404 Not Found');
    echo $fileNotFound;
    exit;
  }
  // remove ".."
  $fname = str_replace('..', '', $fname);
  // check data dir
  $fullpath = KONA3_DIR_DATA."/$fname";
  if (!file_exists($fullpath)) {
    header('HTTP/1.0 404 Not Found');
    echo $fileNotFound;
    exit;
  }
  // image
  $image_type = kona3getConf("image_pattern", "(jpg|jpeg|png|gif|ico|svg|webp)");
  $pattern = "#\.($image_type)$#";
  if (preg_match($pattern, $fname, $m)) {
    $ctype = "image/".$m[1];
    header("Content-Type: $ctype");
    header("Content-Length: ".filesize($fullpath));
    echo file_get_contents($fullpath);
    exit;
  }
  // data_dir_allow_pattern
  $allow_pattern = kona3getConf("data_dir_allow_pattern", "(csv|json|xml|doc|docx|xls|xlsx|ppt|pptx|pdf|zip|gz|bz2|wav|mid|mp3|mp4|ogg)");
  $pattern = "#\.($allow_pattern)$#";
  if (!preg_match($pattern, $fname)) {
    header('HTTP/1.0 404 Not Found');
    echo $fileNotFound;
    exit;
  }
  // output contents
  $mime = mime_content_type($fullpath);
  header("Content-Type: $mime");
  echo file_get_contents($fullpath);
}



