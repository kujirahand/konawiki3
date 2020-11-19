<?php
// ----------------------------------------------------
// konawiki3 - index.php
// ----------------------------------------------------
define('KONA3_FILE_CONFIG', 'konawiki3.ini.php');

// Read config file
$file_config = __DIR__.'/'.KONA3_FILE_CONFIG;
if (!file_exists($file_config)) {
  $file_kona3setup = __DIR__ . '/kona3admin/kona3setup.inc.php';
  if (file_exists($file_kona3setup)) {
    require_once $file_kona3setup;
    konawiki3_setup($file_config); exit;
  } else {
    $help_url = 'https://kujirahand.com/konawiki3/index.php?install';
    echo "<!DOCTYPE html><html><body><h1><a href='$help_url'>Please Setup.</a></h1></body></html>";
    exit;
  }
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



