<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';

function kona3_action_resource() {
  global $kona3conf;

  $fname = $kona3conf["page"];
  if (!preg_match('#^[a-zA-Z0-9\_\%][a-zA-Z0-9\_\.\%\-]+$#', $fname)) {
    header('HTTP/1.0 404 Not Found');
    echo "[error] FILENAME ERROR\n"; exit;
  }

  // get extension
  $ext = "";
  if (preg_match('/(\.\w+?)$/', $fname, $m)) {
    $ext = $m[1];
  }
  $file = $kona3conf['path.engine'].'/resource/'.$fname;
  // check skin dir
  if (!file_exists($file)) {
    header('HTTP/1.0 404 Not Found');
    echo "FILE NOT FOUND";
    exit;
  }

  // output
  $ext = strtolower($ext);
  $ctype = "text/plain";
  if ($ext == ".css") $ctype = "text/css";
  else if ($ext == ".js") $ctype = "text/javascript";
  //
  header("Content-Type: $ctype");
  //
  $s = @file_get_contents($file);
  echo $s;
}



