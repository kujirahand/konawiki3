<?php
/**
 * KonaWiki3 kona3engine/index.inc.php
 */
define("KONA3_SYSTEM_VERSION", "3.1.3");
// global
global $kona3conf;
$kona3conf = array();
// session
session_start();

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
kona3conf_setDefConfig();
// parse url
kona3lib_parseURI();
// execute
kona3lib_execute();



