<?php

/**
 * KonaWiki3 - kona3conf.inc.php
 */

include_once __DIR__ . '/kona3lib.inc.php';
include_once __DIR__ . '/php_fw_simple/fw_database.lib.php';
include_once __DIR__ . '/kona3db.inc.php';

// --------------------
// Initialize config
// --------------------
function kona3conf_init()
{
    global $kona3conf;
    if (empty($kona3conf)) {
        $kona3conf = [];
    }
    kona3conf_setPHPConf();
    kona3conf_setWikiConf($kona3conf);
    kona3conf_checkDirs();
    kona3conf_setURI();
    kona3conf_setHeaderFooter();
    kona3conf_setTemplateEngine();
    kona3conf_setDatabase();
}

function kona3conf_setPHPConf()
{
    // Charset Setting
    mb_internal_encoding("UTF-8");
    mb_detect_encoding("UTF-8,SJIS,EUC-JP,JIS,ASCII");
    // Web interface
    if (php_sapi_name() !== 'cli') {
        ini_set('default_charset', 'UTF-8');
        header("Content-Type: text/html; charset=UTF-8");
    }
}

function kona3conf_setWikiConf(&$kona3conf)
{
    // default settings
    $defaultConf = [
        'KONAWIKI_VERSION' => KONAWIKI_VERSION,
        'wiki_title' => 'KonaWiki3',
        'session_name' => 'kona3session',
        'admin_email' => 'admin@example.com',
        'wiki_private' => TRUE,
        'lang' => 'ja',
        'skin' => 'def',
        'def_text_ext' => 'txt',
        'allow_add_user' => FALSE,
        'allpage_header' => '',
        'allpage_footer' => '',
        'analytics' => '',
        'FrontPage' => 'FrontPage',
        'plugin_disallow' => 'html,htmlshow,filelist',
        'git_enabled' => FALSE,
        'git_branch' => 'main',
        'git_remote_repository' => 'origin',
        'noanchor' => FALSE,
        'enc_pagename' => FALSE,
        'show_data_dir' => FALSE,
        'show_counter' => FALSE,
        'para_enabled_br' => TRUE,
        'path_max_mkdir' => 3,
        'chmod_mkdir' => '0777',
        'max_edit_size' => '3',
        'max_search' => '10',
        'max_search_level' => '2',
        'use_pdf_out' => FALSE,
        'image_pattern' => '(png|jpg|jpeg|gif|bmp|ico|svg|webp)',
        'data_dir_allow_pattern' => '(csv|json|xml|doc|docx|xls|xlsx|ppt|pptx|pdf|zip|gz|bz2|wav|mid|mp3|mp4|ogg)',
        'allow_upload' => FALSE,
        'allow_upload_ext' => 'txt;md;pdf;csv;wav;mid;mp3;mp4;ogg;zip;gz;bz2;jpg;jpeg;png;gif;webp;svg;xml;json;ini;doc;docx;xls;xlsx;ppt;pptx',
        'upload_max_file_size' => 1024 * 1024 * 5,
        'bbs_admin_password' => 'BBS#admin!PassWord!!',
        'openai_apikey' => '',
        'openai_apikey_model' => 'gpt-4o-mini',
        'openai_api_basic_instruction' => 'You are helpful AI assitant.',
        'discord_webhook_url' => '',
        'plugin_alias' => [
            "画像" => "ref",
            "なでしこ" => "nako3",
        ],
        'mermaid_cli' => '',
    ];
    kona3conf_setDefault($kona3conf, $defaultConf);

    // -------------------------------------------
    // plugin diallow
    // -------------------------------------------
    $plugin_disallow_kv = [];
    $plugin_disallow_a = explode(",", $kona3conf['plugin_disallow']);
    foreach ($plugin_disallow_a as $name) {
        $plugin_disallow_kv[$name] = TRUE;
    }
    $kona3conf["plugin.disallow"] = $plugin_disallow_kv;
}

function check_conf(&$conf, $key, $def)
{
    if (!isset($conf[$key])) {
        $conf[$key] = $def;
    }
}

function kona3conf_setDefault(&$kona3conf, $defaultValues)
{
    foreach ($defaultValues as $key => $value) {
        if (!isset($kona3conf[$key])) {
            $kona3conf[$key] = $value;
        }
    }
}

function kona3conf_checkDirs()
{
    // Directories
    if (!defined('KONA3_DIR_DATA')) {
        define('KONA3_DIR_DATA', dirname(__DIR__) . '/data');
    }
    if (!defined('KONA3_DIR_PRIVATE')) {
        define('KONA3_DIR_PRIVATE', dirname(__DIR__) . '/private');
    }
    if (!defined('KONA3_DIR_SKIN')) {
        define('KONA3_DIR_SKIN', dirname(__DIR__) . '/skin');
    }
    if (!defined('KONA3_DIR_CACHE')) {
        define('KONA3_DIR_CACHE', dirname(__DIR__) . '/cache');
    }
    if (!defined('KONA3_PAGE_ID_JSON')) {
        define("KONA3_PAGE_ID_JSON", KONA3_DIR_DATA . "/.kona3_page_id.json");
    }
}

function kona3conf_setURI()
{
    global $kona3conf;

    // robots
    if ($kona3conf['wiki_private']) {
        $kona3conf["robots"] = 'noindex';
    } else {
        $kona3conf["robots"] = ''; // or 'index,follow' (default is '')
    }

    // path
    $baseurl = ".";

    // URL
    $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';

    // go.php?
    if (basename($script) == 'go.php') {
        $script = dirname($script) . '/index.php';
    }

    // set default
    $urlValues = [
        'url.index' => "{$scheme}://{$host}{$script}",
        'url.data' => './data',
        'url.pub' => './pub',
        'path_resource' => KONA3_DIR_ENGINE . '/resource',
        'scriptname' => 'index.php',
    ];
    kona3conf_setDefault($kona3conf, $urlValues);

    // check
    $url_data = $kona3conf["url.data"];
    if (substr($url_data, strlen($url_data) - 1, 1) == '/') {
        $kona3conf["url.data"] = substr($url_data, 0, strlen($url_data) - 1);
    }
}

function kona3conf_setHeaderFooter()
{
    global $kona3conf;

    // javascript files
    check_conf($kona3conf, "header.tags", []); // additional header
    check_conf($kona3conf, 'js', [
        kona3getResourceURL('ajax_qq.js'),
        kona3getSkinURL('drawer.js', TRUE),
    ]);

    // css files
    check_conf($kona3conf, "css", [
        kona3getResourceURL('pure-min.css'),
        kona3getResourceURL('grids-responsive-min.css'),
        kona3getResourceURL('kona3def.css', TRUE), // default style
        kona3getSkinURL('kona3.css', TRUE), // skin style
        kona3getSkinURL('drawer.css', TRUE),
    ]);

    // analytics
    if ($kona3conf['analytics'] != '') {
        $kona3conf['header.tags'][] = $kona3conf['analytics'];
    }
}

function kona3conf_setTemplateEngine()
{
    global $kona3conf;

    // Template engine
    global $DIR_TEMPLATE;
    global $DIR_TEMPLATE_CACHE;
    global $FW_TEMPLATE_PARAMS;
    global $FW_ADMIN_EMAIL;
    $DIR_TEMPLATE = KONA3_DIR_ENGINE . '/template';
    $DIR_TEMPLATE_CACHE = KONA3_DIR_CACHE;
    $FW_ADMIN_EMAIL = $kona3conf['admin_email'];
}

function kona3conf_setDatabase()
{
    $DIR_TEMPLATE = KONA3_DIR_ENGINE . '/template';

    // Database library
    $file_info_sqlite = KONA3_DIR_PRIVATE . '/info.sqlite';
    $old_file_info_sqlite = KONA3_DIR_DATA . '/.info.sqlite';
    if (!file_exists($file_info_sqlite)) { // rename old file
        // 古いDBファイルがあれば、新しいパスに移動する
        if (file_exists($old_file_info_sqlite)) {
            // ファイルを異動する前にpage_idを移行する
            kona3_conf_read_old_page_ids($old_file_info_sqlite);
            @kona3db_getPageId(kona3getConf("FrontPage"), FALSE);
            // ファイルを移動する
            @rename($old_file_info_sqlite, $file_info_sqlite);
        }
    }
    // main database
    database_set(
        $file_info_sqlite, // DB file path
        $DIR_TEMPLATE . '/info.sql', // SQL file path
        'main' // Name of DB
    );
    // subdb database
    database_set(
        KONA3_DIR_PRIVATE . '/subdb.sqlite',
        $DIR_TEMPLATE . '/subdb.sql',
        'subdb'
    );
    try {
        database_get('main');
    } catch (Exception $e) {
        echo "<pre><h1>DB ERROR</h1>\n";
        echo "[FILE] $file_info_sqlite\n";
        echo "<p><a href='https://github.com/kujirahand/konawiki3'>Please setup konawiki3.</a></p>";
        if (!is_dir(KONA3_DIR_PRIVATE)) {
            echo "<p>Please make `private` dir.</p>";
        }
        throw $e;
    }
}

// (旧v.3.3.11以前のDB対応) データベースファイルがあればそれを読み込む
function kona3_conf_read_old_page_ids($old_db_path)
{
    $kona3pageIds = [];
    // v3.3.11 以前のバージョンのデータベース
    if (file_exists($old_db_path)) {
        // load
        $pdo = new PDO("sqlite:$old_db_path");
        $r = $pdo->query("SELECT * FROM pages"); // 自動的にJSONに移行
        $kona3pageIds = [];
        foreach ($r as $v) {
            // ファイルの存在チェック
            $file = koan3getWikiFileText($v['name']);
            if (file_exists($file)) {
                // echo $v['name'] . " => " . $v['page_id'] . "\n";
                $kona3pageIds[$v['name']] = $v['page_id'];
            }
        }
        $pdo = null;
        // save
        kona3lock_save(KONA3_PAGE_ID_JSON, json_encode($kona3pageIds, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    return $kona3pageIds;
}
