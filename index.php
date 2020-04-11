<?php
// ----------------------------------------------------
// konawiki3 index
// ----------------------------------------------------
define('KONA3_FILE_CONFIG', 'konawiki3.ini.php');

// Check config file
$file_config = dirname(__FILE__).'/'.KONA3_FILE_CONFIG;
if (!file_exists($file_config)) {
  $url = 'http://kujirahand.com/konawiki3/index.php?install';
  echo "<html><body><h1>";
  echo "<a href='$url'>Please install.</a>";
  echo "</h1></body></html>";
  exit;
}
// Read config
require_once($file_config);

// Include kona3engine/index.inc.php
$engine_index = "kona3engine/index.inc.php";
if (defined("KONA3_DIR_ENGINE")) {
  $engine_index = KONA3_DIR_ENGINE."/index.inc.php";
}
require_once($engine_index);


