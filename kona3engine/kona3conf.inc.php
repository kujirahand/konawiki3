<?php

/**
 * KonaWiki3 - kona3conf.inc.php
 */

// Charset Setting
mb_internal_encoding("UTF-8");
mb_detect_encoding("UTF-8,SJIS,EUC-JP,JIS,ASCII");
// Web interface
if (php_sapi_name() !== 'cli') {
    ini_set('default_charset', 'UTF-8');
    header("Content-Type: text/html; charset=UTF-8");
}

include_once __DIR__ . '/kona3lib.inc.php';
include_once __DIR__ . '/php_fw_simple/fw_database.lib.php';
include_once __DIR__ . '/kona3db.inc.php';

// --------------------
// Initialize config
// --------------------
function kona3conf_init(&$conf)
{
    check_conf($conf, 'wiki_title', 'KonaWiki3');
    check_conf($conf, 'session_name', 'kona3session');
    check_conf($conf, 'admin_email', 'admin@example.com');
    check_conf($conf, 'wiki_private', TRUE);
    check_conf($conf, 'lang', 'ja');
    check_conf($conf, 'skin', 'def');
    check_conf($conf, 'def_text_ext', 'txt');
    check_conf($conf, 'allow_add_user', FALSE);
    check_conf($conf, 'allpage_header', '');
    check_conf($conf, 'allpage_footer', '');
    check_conf($conf, 'analytics', '');
    check_conf($conf, 'FrontPage', 'FrontPage');
    check_conf($conf, 'plugin_disallow', 'html,htmlshow,filelist');
    check_conf($conf, 'git_enabled', FALSE);
    check_conf($conf, 'git_branch', 'main');
    check_conf($conf, 'git_remote_repository', 'origin');
    check_conf($conf, 'noanchor', FALSE);
    check_conf($conf, 'enc_pagename', FALSE);
    check_conf($conf, 'show_data_dir', FALSE);
    check_conf($conf, 'show_counter', FALSE);
    check_conf($conf, 'para_enabled_br', TRUE);
    check_conf($conf, 'path_max_mkdir', 3);
    check_conf($conf, 'chmod_mkdir', '0777');
    check_conf($conf, 'max_edit_size', '3');
    check_conf($conf, 'max_search', '10');
    check_conf($conf, 'max_search_level', '2');
    check_conf($conf, 'use_pdf_out', FALSE);
    check_conf($conf, 'image_pattern', '(png|jpg|jpeg|gif|bmp|ico|svg|webp)');
    check_conf($conf, 'data_dir_allow_pattern', '(csv|json|xml|doc|docx|xls|xlsx|ppt|pptx|pdf|zip|gz|bz2|wav|mid|mp3|mp4|ogg)');
    check_conf($conf, 'allow_upload', FALSE);
    check_conf($conf, 'allow_upload_ext', 'txt;md;pdf;csv;wav;mid;mp3;mp4;ogg;zip;gz;bz2;jpg;jpeg;png;gif;webp;svg;xml;json;ini;doc;docx;xls;xlsx;ppt;pptx');
    check_conf($conf, 'upload_max_file_size', 1024 * 1024 * 5);
    check_conf($conf, 'bbs_admin_password', 'BBS#admin!PassWord!!');
    check_conf($conf, 'openai_apikey', '');
    check_conf($conf, 'openai_apikey_model', 'gpt-4o-mini');
    check_conf($conf, 'openai_api_basic_instruction', 'You are helpful AI assitant.');
    check_conf($conf, 'discord_webhook_url', '');
    // plugin alias
    check_conf($conf, 'plugin_alias', [
        "画像" => "ref",
        "なでしこ" => "nako3",
    ]);
}

function check_conf(&$conf, $key, $def)
{
    if (!isset($conf[$key])) {
        $conf[$key] = $def;
    }
}

function kona3conf_gen()
{
    global $kona3conf;

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
    check_conf($kona3conf, 'url.index', "{$scheme}://{$host}{$script}");
    check_conf($kona3conf, 'url.data', './data');
    check_conf($kona3conf, 'url.pub', './pub');
    check_conf($kona3conf, 'path_resource', KONA3_DIR_ENGINE . '/resource');
    check_conf($kona3conf, 'scriptname', 'index.php');

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
    if ($kona3conf['analytics'] != '') {
        $kona3conf['header.tags'][] = $kona3conf['analytics'];
    }
    // plugin diallow
    $pd = array();
    $a = explode(",", $kona3conf['plugin_disallow']);
    foreach ($a as $name) {
        $pd[$name] = TRUE;
    }
    $kona3conf["plugin.disallow"] = $pd;

    // check
    $url_data = $kona3conf["url.data"];
    if (substr($url_data, strlen($url_data) - 1, 1) == '/') {
        $kona3conf["url.data"] = substr($url_data, 0, strlen($url_data) - 1);
    }

    // Template engine
    global $DIR_TEMPLATE;
    global $DIR_TEMPLATE_CACHE;
    global $FW_TEMPLATE_PARAMS;
    global $FW_ADMIN_EMAIL;
    $DIR_TEMPLATE = KONA3_DIR_ENGINE . '/template';
    $DIR_TEMPLATE_CACHE = KONA3_DIR_CACHE;
    $FW_ADMIN_EMAIL = $kona3conf['admin_email'];
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
    // autologin database
    database_set(
        KONA3_DIR_PRIVATE . '/autologin.sqlite',
        $DIR_TEMPLATE . '/autologin.sql',
        'autologin'
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
