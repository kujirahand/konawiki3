<?php
// ----------------------------------------------------
// konawiki3 index
// ----------------------------------------------------

// when you use config file
$config_file = dirname(__FILE__).'/konawiki3.ini.php';
if (file_exists($config_file)) {
  require_once($config_file);
} else {
  // set config
  define("KONA3_WIKI_TITLE", "Konawiki3");
  define("KONA3_WIKI_USERS", "kona3:pass3,kona2:pass2"); # admin users
  define("KONA3_WIKI_PRIVATE", true); # true or false
  define("KONA3_DIR_ENGINE", dirname(__FILE__).'/kona3engine');
  define("KONA3_DIR_DATA", dirname(__FILE__).'/data');
  define("KONA3_DIR_PRIVATE", dirname(__FILE__).'/private');
  define("KONA3_DSN", "sqlite:".KONA3_DIR_PRIVATE."/data.sqlite");
  define("KONA3_ALLPAGE_FOOTER", "#comment");
  define("KONA3_WIKI_SKIN", "def");
}

// Include kona3engine/index.inc.php
$engine_index = "kona3engine/index.inc.php";
if (defined("KONA3_DIR_ENGINE")) {
  $engine_index = KONA3_DIR_ENGINE."/index.inc.php";
}
require_once($engine_index);

