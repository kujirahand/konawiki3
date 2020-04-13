<?php
/**
 * KonaWiki3
 */
define("KONA3_SYSTEM_VERSION", "3.0.2");

// charset
mb_internal_encoding("UTF-8");
mb_detect_encoding("UTF-8,SJIS,EUC-JP,JIS,ASCII");
ini_set('default_charset', 'UTF-8');
header("Content-Type: text/html; charset=UTF-8");

// session
session_start();

// global
global $kona3conf;
$kona3conf = array();

// --------------------
// main
// --------------------
// include library
$path_engine = dirname(__FILE__); // this directory is engine dir
require_once $path_engine . '/kona3lib.inc.php';

// load conf
setDefConfig();
// parse url
kona3parseURI();
// execute
kona3execute();

// --------------------
// Initialize config
// --------------------
function setDefConfig() {
  global $kona3conf;

  // global setting
  defC("KONA3_WIKI_TITLE",     "Konwwiki3");
  defC("KONA3_WIKI_FRONTPAGE", "FrontPage");
  defC("KONA3_WIKI_PRIVATE",   false);
  defC("KONA3_WIKI_USERS",     "kona3:pass3,kona2:pass2");
  defC("KONA3_WIKI_SKIN",      "def");
  defC("KONA3_LANG",           "ja");
  // global dir
  defC("KONA3_DIR_PUBLIC",     dirname(dirname(__FILE__)));
  defC("KONA3_DIR_ENGINE",     dirname(__FILE__));
  defC("KONA3_DIR_DATA",       KONA3_DIR_PUBLIC."/data");
  defC("KONA3_DIR_PRIVATE",    KONA3_DIR_PUBLIC."/private");
  defC("KONA3_DIR_ATTACH",     KONA3_DIR_PUBLIC."/attach");
  defC("KONA3_DIR_SKIN",       KONA3_DIR_PUBLIC."/skin");
  defC("KONA3_DIR_PUB",        KONA3_DIR_PUBLIC."/pub");
  defC("KONA3_DIR_CACHE",      KONA3_DIR_PUBLIC."/cache");
  defC("KONA3_DIR_TEMPLATE",   KONA3_DIR_ENGINE."/template");
  defC("KONA3_DIR_RESOURCE",   KONA3_DIR_ENGINE."/resource");
  defC("KONA3_DIR_LANG",       KONA3_DIR_ENGINE."/lang");
  // git
  defC("KONA3_GIT_ENABLED", false);
  defC("KONA3_GIT_BRANCH", "master");
  defC("KONA3_GIT_REMOTE_REPOSITORY", "origin");
  // uri
  defC("KONA3_URI_ATTACH",     "./attach");
  defC("KONA3_URI_DATA",       "./data");
  defC("KONA3_URI_PUB",        "./pub");
  // option
  defC("KONA3_DSN",            "sqlite:".KONA3_DIR_PRIVATE."/data.sqlite");
  defC("KONA3_ALLPAGE_FOOTER", "");
  defC("KONA3_PLUGIN_DISALLOW", ""); // delimitter=","
  defC("KONA3_ENC_PAGENAME", FALSE);
  defC("KONA3_PARTS_COUNTCHAR", true);
  defC("KONA3_NOANCHOR", false);
  // files
  defC("KONA3_FILES_JS", ''); // (ex) a.js, b.js, c.js
  defC("KONA3_FILES_CSS", ''); // (ex) a.css, b.css c.css

  // global setting
  $kona3conf["title"]          = KONA3_WIKI_TITLE;
  $kona3conf["wiki.private"]   = KONA3_WIKI_PRIVATE;
  $kona3conf["FrontPage"]      = KONA3_WIKI_FRONTPAGE;
  $kona3conf["allpage.footer"] = KONA3_ALLPAGE_FOOTER;

  // users
  $users = array();
  $users_a = explode(",", KONA3_WIKI_USERS);
  foreach ($users_a as $r) {
    $ra = explode(":", trim($r), 2);
    if (count($ra) == 2) {
      $users[$ra[0]] = $ra[1];
    }
  }
  $kona3conf["users"] = $users;
  
  // path
  $base    = dirname(__FILE__);
  $baseurl = ".";
  $kona3conf["path.pub"]      = KONA3_DIR_PUBLIC;
  $kona3conf["path.engine"]   = KONA3_DIR_ENGINE;
  $kona3conf["path.data"]     = KONA3_DIR_DATA;
  $kona3conf["path.attach"]   = KONA3_DIR_ATTACH;
  $kona3conf["path.cache"]    = KONA3_DIR_CACHE;
  // URL
  $scheme = $_SERVER['REQUEST_SCHEME'];
  $host = $_SERVER['HTTP_HOST'];
  $script = $_SERVER['SCRIPT_NAME'];
  $kona3conf["url.index"]   = "{$scheme}://{$host}{$script}";
  $kona3conf["url.attach"]  = KONA3_URI_ATTACH;
  $kona3conf["url.data"]    = KONA3_URI_DATA;
  $kona3conf["url.pub"]     = KONA3_URI_PUB;
  $kona3conf["path.skin"]   = KONA3_DIR_SKIN;
  $kona3conf["path.max.mkdir"] = 3; // max level dir under path.data (disallow = 0)
  $kona3conf["scriptname"] = 'index.php';
  $kona3conf["para_enabled_br"] = true;

  // git
  $kona3conf["git.enabled"] = KONA3_GIT_ENABLED;

  if ($kona3conf["git.enabled"]) {
      $kona3conf["git.branch"] = KONA3_GIT_BRANCH;
      $kona3conf["git.remote_repository"] = KONA3_GIT_REMOTE_REPOSITORY;
  }

  // options
  $kona3conf["noanchor"] = KONA3_NOANCHOR;
  $kona3conf["header.tags"] = array(); // additional header
  $kona3conf["dsn"] = KONA3_DSN;
  $kona3conf["enc.pagename"] = KONA3_ENC_PAGENAME;
  // javascript files
  $kona3conf["js"] = array(
    kona3getResourceURL('jquery-3.4.1.min.js'),
  );
  if (KONA3_FILES_JS != '') {
    $files = explode(',', KONA3_FILES_JS);
    foreach ($files as $f) {
      $kona3conf["js"][] = trim($f);
    }
  }
  // css files
  $kona3conf["css"] = array(
    kona3getResourceURL('pure-min.css'),
    kona3getResourceURL('grids-responsive-min.css'),
    kona3getSkinURL('drawer.css', TRUE),
    kona3getSkinURL('kona3.css', TRUE),
  );
  if (KONA3_FILES_CSS != '') {
    $files = explode(',', KONA3_FILES_CSS);
    foreach ($files as $f) {
      $kona3conf["css"][] = trim($f);
    }
  }
  // plugin diallow
  $pd = array();
  $a = explode(",", KONA3_PLUGIN_DISALLOW);
  foreach ($a as $name) {
    $pd[$name] = TRUE;
  }
  $kona3conf["plugin.disallow"] = $pd;

  // check
  $url_data = $kona3conf["url.data"];
  if (substr($url_data, strlen($url_data) - 1, 1) == '/') {
    $kona3conf["url.data"] = substr($url_data, 0, strlen($url_data) - 1);
  }
}

// get value from array with default value
function getA($a, $key, $def = null) {
  return (isset($a[$key])) ? $a[$key] : $def;
}
// define Constant value
function defC($key, $def = null) {
  if (!defined($key)) {
    define($key, $def);
  }
}
