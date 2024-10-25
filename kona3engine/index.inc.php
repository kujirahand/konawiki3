<?php
/**
 * KonaWiki3 kona3engine/index.inc.php
 */
// konawiki version
require_once __DIR__.'/konawiki_version.inc.php';
// global
global $kona3conf;
if (!isset($kona3conf)) { $kona3conf = []; }
$kona3conf_notExists = true;
// Load Config data
$file_kona3conf_json = KONA3_DIR_PRIVATE.'/kona3conf.json.php';
if (file_exists($file_kona3conf_json)) {
    // load config file
    include_once KONA3_DIR_ENGINE.'/jsonphp.lib.php';
    $conf = jsonphp_load($file_kona3conf_json, []);
    foreach ($conf as $k => $v) { // overwrite config
        $kona3conf[$k] = $v;
    }
    $kona3conf_notExists = false;
} else {
    // --- no config file ---
    // set directories
    if (!defined('KONA3_DIR_INDEX')) { define('KONA3_DIR_INDEX', dirname(__DIR__)); }
    if (!defined('KONA3_DIR_ENGINE')) { define('KONA3_DIR_ENGINE',  __DIR__); }
    // check template engine
    $template_engine_lib = __DIR__ . '/php_fw_simple/fw_template_engine.lib.php';
}
// session
$wiki_title = isset($kona3conf['wiki_title']) ? $kona3conf['wiki_title'] : 'KonaWiki3';
$session_name = isset($kona3conf['session_name']) ? $kona3conf['session_name'] : 'kona3session';
session_start(['name' => $session_name]);

// --------------------
// include library
// --------------------
// base library
require_once __DIR__.'/kona3conf.inc.php';
require_once __DIR__.'/kona3lib.inc.php';
// template engine
require_once __DIR__.'/php_fw_simple/fw_template_engine.lib.php';
require_once __DIR__.'/php_fw_simple/fw_database.lib.php';
require_once __DIR__.'/php_fw_simple/fw_etc.lib.php';
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
    if (!in_array($kona3conf['action'], ['resource', 'skin', 'logout'])) {
        $_GET['action'] = $kona3conf['action'] = 'admin'; 
    }
}
// check security
kona3lib_checkSecurity();
// execute
kona3lib_execute();



