<?php
/**
 * KonaWiki3 - kona3conf.inc.php
 */

// Charset Setting
mb_internal_encoding("UTF-8");
mb_detect_encoding("UTF-8,SJIS,EUC-JP,JIS,ASCII");
ini_set('default_charset', 'UTF-8');
header("Content-Type: text/html; charset=UTF-8");

include_once __DIR__.'/kona3lib.inc.php';
include_once __DIR__.'/php_fw_simple/fw_database.lib.php';
include_once __DIR__.'/kona3db.inc.php';

// --------------------
// Initialize config
// --------------------
function kona3conf_init(&$conf) {
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

function check_conf(&$conf, $key, $def) {
  if (!isset($conf[$key])) {
    $conf[$key] = $def;
  }
}

function kona3conf_gen() {
  global $kona3conf; 
  
  if (!defined('KONA3_DIR_SKIN')) {
    define('KONA3_DIR_SKIN', dirname(__DIR__).'/skin');
  }
  if (!defined('KONA3_DIR_CACHE')) {
    define('KONA3_DIR_CACHE', dirname(__DIR__).'/cache');
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
  $host = $_SERVER['HTTP_HOST'];
  $script = $_SERVER['SCRIPT_NAME'];
  // go.php?
  if (basename($script) == 'go.php') {
    $script = dirname($script).'/index.php';
  }
  // set default
  check_conf($kona3conf, 'url.index', "{$scheme}://{$host}{$script}");
  check_conf($kona3conf, 'url.data', './data');
  check_conf($kona3conf, 'url.pub', './pub');
  check_conf($kona3conf, 'path_resource', KONA3_DIR_ENGINE.'/resource');
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
    kona3getResourceURL('kona3def.css'), // default style
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
  $DIR_TEMPLATE = KONA3_DIR_ENGINE.'/template';
  $DIR_TEMPLATE_CACHE = KONA3_DIR_CACHE;
  $FW_ADMIN_EMAIL = $kona3conf['admin_email'];

  // Database library
  $file_info_sqlite = KONA3_DIR_DATA.'/.info.sqlite';
  if (!file_exists($file_info_sqlite)) {
    $old_info_sqlite = KONA3_DIR_PRIVATE.'/info.sqlite';
    if (file_exists($old_info_sqlite)) {
      @rename($old_info_sqlite, $file_info_sqlite);
    }
  }
  database_set(
    $file_info_sqlite,
    $DIR_TEMPLATE.'/info.sql',
    'main'
  );
  database_set(
    KONA3_DIR_PRIVATE.'/autologin.sqlite',
    $DIR_TEMPLATE.'/autologin.sql',
    'autologin'
  );
  $_dbMain = database_get();
}
