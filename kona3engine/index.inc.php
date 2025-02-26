<?php

/**
 * KonaWiki3 kona3engine/index.inc.php
 */

// konawiki version
require_once 'konawiki_version.inc.php';
// load template engine
require_once 'php_fw_simple/fw_template_engine.lib.php';
require_once 'php_fw_simple/fw_database.lib.php';
require_once 'php_fw_simple/fw_etc.lib.php';
// load base library
require_once 'kona3conf.inc.php';
require_once 'kona3lib.inc.php';
require_once 'kona3parser.inc.php';
require_once 'kona3db.inc.php';

function kona3index_main()
{
    global $kona3conf;
    $kona3conf = [];

    // load conf
    $kona3conf_notExists = kona3index_loadConf();
    kona3conf_init();

    // start session
    $session_name = isset($kona3conf['session_name']) ? $kona3conf['session_name'] : 'kona3session';
    session_start(['name' => $session_name]);


    // parse url
    kona3lib_parseURI();
    // execute
    if ($kona3conf_notExists) {
        if (!in_array($kona3conf['action'], ['resource', 'skin', 'logout'])) {
            $_GET['action'] = $kona3conf['action'] = 'admin';
        }
    }
    if (php_sapi_name() !== 'cli') {
        // check security
        kona3lib_checkSecurity();
        // execute
        kona3lib_execute();
    }
}

function kona3index_loadConf()
{
    global $kona3conf;
    $kona3conf_notExists = true;

    // check directories
    if (!defined('KONA3_DIR_PRIVATE')) {
        define('KONA3_DIR_PRIVATE', dirname(__DIR__) . '/private');
    }
    if (!defined('KONA3_DIR_ENGINE')) {
        define('KONA3_DIR_ENGINE', __DIR__);
    }

    // Load Config data
    $file_kona3conf_json = KONA3_DIR_PRIVATE . '/kona3conf.json.php';
    if (file_exists($file_kona3conf_json)) {
        // load config file
        include_once KONA3_DIR_ENGINE . '/jsonphp.lib.php';
        $conf = jsonphp_load($file_kona3conf_json, []);
        foreach ($conf as $k => $v) { // overwrite config
            $kona3conf[$k] = $v;
        }
        $kona3conf_notExists = false;
    } else {
        // --- no config file ---
        // set directories
        if (!defined('KONA3_DIR_INDEX')) {
            define('KONA3_DIR_INDEX', dirname(__DIR__));
        }
        if (!defined('KONA3_DIR_ENGINE')) {
            define('KONA3_DIR_ENGINE',  __DIR__);
        }
        // check template engine
        $template_engine_lib = __DIR__ . '/php_fw_simple/fw_template_engine.lib.php';
    }
    return $kona3conf_notExists;
}

// main
kona3index_main();
