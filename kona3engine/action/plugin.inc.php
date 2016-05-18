<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';
include_once dirname(dirname(__FILE__)).'/kona3parser.inc.php';

function err404($msg) {
  echo "404 $msg\n";
  exit;
}

function kona3_action_plugin() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $name = isset($_GET['name']) ? $_GET['name'] : '';
  if ($name == '') {
    header("location: index.php");
    exit;
  }
  if (!preg_match('#^[a-zA-Z0-9]+$#', $name)) {
    err404("Invalid plugin name");
  }
  // pinfo
  $pinfo = konawiki_parser_getPlugin($name);
  if (empty($pinfo['file']) || !file_exists($pinfo['file'])) {
    err404("Invalid plugin name");
  }
  // execute
  include_once($pinfo['file']);
  $func_name = "kona3plugins_{$name}_action";
  if (!function_exists($func_name)) {
    err404("Plugin function not found");
  }
  @call_user_func($func_name);
}



