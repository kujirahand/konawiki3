<?php
/**
 * KonaWiki3 kona3engine/index.inc.php
 */
// konawiki version
require_once __DIR__.'/konawiki_version.inc.php';
// global
global $kona3conf;
$kona3conf = [];
$kona3conf_notExists = true;
// session
session_start();
// Load Config data
$file_kona3conf_json = KONA3_DIR_PRIVATE.'/kona3conf.json.php';
if (file_exists($file_kona3conf_json)) {
    include_once KONA3_DIR_ENGINE.'/jsonphp.lib.php';
    $kona3conf = jsonphp_load($file_kona3conf_json, []);
    $kona3conf_notExists = false;
} else {
    // set directories
    if (!defined('KONA3_DIR_INDEX')) { define('KONA3_DIR_INDEX', dirname(__DIR__)); }
    if (!defined('KONA3_DIR_ENGINE')) { define('KONA3_DIR_ENGINE',  __DIR__); }
    // check template engine
    $template_engine_lib = __DIR__ . '/fw_simple/fw_template_engine.lib.php';
    if (!file_exists($template_engine_lib)) {
        echo "<p><a href='./script/setup-template.php'>Please install Template Engine.</a></p>\n";
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
require_once __DIR__.'/fw_simple/fw_template_engine.lib.php';
require_once __DIR__.'/fw_simple/fw_database.lib.php';
require_once __DIR__.'/fw_simple/fw_etc.lib.php';
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
if ($kona3conf_notExists) {
    if ($kona3conf['action'] != 'resource') {
        $_GET['action'] = $kona3conf['action'] = 'admin'; 
    }
}
// execute
kona3lib_execute();



