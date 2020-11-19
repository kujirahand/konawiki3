<?php
/**
 * KonaWiki3 kona3engine/index.inc.php
 */
define("KONA3_SYSTEM_VERSION", "3.2.0");
// global
global $kona3conf;
$kona3conf = array();
// session
session_start();
/*
// check old version
if (!defined('KONA3_DIR_INDEX')) {
  echo '<a href="https://kujirahand.com/konawiki3/index.php?update_3_2">Please update index.php.</a>'; exit;
}
 */
// Load Config data
$file_kona3conf_json = KONA3_DIR_PRIVATE.'/kona3conf.json.php';
if (file_exists($file_kona3conf_json)) {
  include_once KONA3_DIR_ENGINE.'/jsonphp.lib.php';
  $kona3conf = jsonphp_load($file_kona3conf_json, []);
} else {
  if (!defined('KONA3_DIR_ADMIN')) {
    define('KONA3_DIR_INDEX', dirname(__DIR__));
    define('KONA3_DIR_ADMIN', dirname(__DIR__).'/kona3admin');
  }
  $setup_php = KONA3_DIR_ADMIN.'/kona3setup.inc.php';
  if (file_exists($setup_php)) {
    require $setup_php;
    konawiki3_setup(); exit;
  } else {
    kona3_setup_help($setup_php.'/You do not have setup script.');
    exit;
  }
}


// --------------------
// include library
// --------------------
// base library
require_once __DIR__.'/kona3conf.inc.php';
require_once __DIR__.'/kona3lib.inc.php';
// template engine
require_once __DIR__.'/fw_template_engine.lib.php';
require_once __DIR__.'/fw_database.lib.php';
require_once __DIR__.'/fw_etc.lib.php';
// library
require_once __DIR__.'/kona3parser.inc.php';
require_once __DIR__.'/kona3db.inc.php';

// --------------------
// main
// --------------------
// load conf
kona3conf_init($kona3conf);
kona3conf_gen();
// parse url
kona3lib_parseURI();
// execute
kona3lib_execute();



