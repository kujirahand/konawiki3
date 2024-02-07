<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';
include_once dirname(dirname(__FILE__)).'/kona3parser.inc.php';

function err404($msg) {
  echo "<h1><span style='color:red'>404</span>\n$msg\n</h1>";
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
  // disallow ?
  if ($pinfo['disallow']) {
    err404('Plugin Disallow');
  }
  // execute
  include_once($pinfo['file']);
  $func_name = "kona3plugins_{$name}_action";
  if (!function_exists($func_name)) {
    err404("Plugin function not found: The plugin should have [{$func_name}].");
  }
  @call_user_func($func_name);
}


