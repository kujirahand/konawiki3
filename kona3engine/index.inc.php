<?php
/**
 * KonaWiki3
 */
define("KONA3_SYSTEM_VERSION", "0.1");

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
// load conf
setDefConfig();
// include library
$path_engine = dirname(__FILE__); // this directory is engine dir
require_once $path_engine . '/kona3lib.inc.php';

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
  defC("KONA3_WIKI_PRIVATE",   true);
  defC("KONA3_WIKI_USERS",     "kona3:pass3,kona2:pass2");
  defC("KONA3_WIKI_SKIN",      "def");
    // global dir
  defC("KONA3_DIR_PUBLIC",     dirname(dirname(__FILE__)));
  defC("KONA3_DIR_ENGINE",     dirname(__FILE__));
  defC("KONA3_DIR_DATA",       KONA3_DIR_PUBLIC."/data");
  defC("KONA3_DIR_ATTACH",     KONA3_DIR_PUBLIC."/attach");
  defC("KONA3_DIR_SKIN",       KONA3_DIR_PUBLIC."/skin");
  defC("KONA3_DIR_PUB",        KONA3_DIR_PUBLIC."/pub");
  defC("KONA3_DIR_CACHE",      KONA3_DIR_PUBLIC."/cache");
  //
  defC("KONA3_URI_ATTACH",     "./attach");
  defC("KONA3_URI_DATA",       "./data");
  defC("KONA3_URI_PUB",        "./pub");

  // global setting
  $kona3conf["title"]       = KONA3_WIKI_TITLE;
  $kona3conf["privateMode"] = KONA3_WIKI_PRIVATE;
  $kona3conf["FrontPage"]   = KONA3_WIKI_FRONTPAGE;
  $kona3conf["language"]    = 'ja';

  // users
  $users = array();
  $users_a = explode(",", KONA3_WIKI_USERS);
  foreach ($users_a as $r) {
    $ra = explode(":", trim($r)."::");
    $users[$ra[0]] = $ra[1];
  }
  $kona3conf["users"] = $users;

  // path
  $base    = dirname(__FILE__);
  $baseurl = ".";
  $kona3conf["path.pub"]    = KONA3_DIR_PUBLIC;
  $kona3conf["path.engine"] = KONA3_DIR_ENGINE;
  $kona3conf["path.data"]   = KONA3_DIR_DATA;
  $kona3conf["path.attach"] = KONA3_DIR_ATTACH;
  $kona3conf["path.cache"]  = KONA3_DIR_CACHE;
  $kona3conf["url.attach"]  = KONA3_URI_ATTACH;
  $kona3conf["url.data"]    = KONA3_URI_DATA;
  $kona3conf["url.pub"]     = KONA3_URI_PUB;

  // options
  defC("KONA3_PARTS_COUNTCHAR", true);
  defC("KONA3_NOANCHOR", false);
  $kona3conf["noanchor"] = KONA3_NOANCHOR;
  $kona3conf["js"] = array(); // javascript files
  $kona3conf["header.tags"] = array(); // additional header 

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
