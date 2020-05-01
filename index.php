<?php
// ----------------------------------------------------
// konawiki3 - index.php
// ----------------------------------------------------
define('KONA3_FILE_CONFIG', 'konawiki3.ini.php');

// Read config file
$file_config = dirname(__FILE__).'/'.KONA3_FILE_CONFIG;
if (!file_exists($file_config)) {
  echo "<h1><a href='http://kujirahand.com/konawiki3/index.php?install'>Please set...</a></h1>"; exit;
}
require_once($file_config);

// Include kona3engine/index.inc.php
if (defined("KONA3_DIR_ENGINE")) {
  $engine_index = KONA3_DIR_ENGINE."/index.inc.php";
} else {
  $engine_index = dirname(__FILE__)."/kona3engine/index.inc.php";
}
if (!file_exists($engine_index)) {
  echo "<h1>Sorry, engine not exists...</h1>"; exit;
}
require_once($engine_index);

