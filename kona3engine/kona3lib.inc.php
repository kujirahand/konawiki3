<?php
/**
 * konawiki3 main library
 */
require_once 'kona3login.inc.php';

if (get_magic_quotes_gpc()){ un_magic_quotes(); }

function kona3param($key, $def = NULL) {
  if (isset($_REQUEST[$key])) {
    return $_REQUEST[$key];
  } else {
    return $def;
  }
}

function kona3parseURI() {
  // (ex) /path/index.php?PageName&Action&Status&p1=***&p2=***
  // (ex) /path/index.php?page=PageName&action=Action&status=Status
  global $kona3conf;
  $uri = $_SERVER["REQUEST_URI"];
  $params = array();
  $path_args = array();
  list($script_path, $paramStr) = explode('?', $uri.'?');
  $a = explode('&', $paramStr);
  foreach ($a as $p) {
    if (strpos($p, '=') !== false) {
      list($key, $val) = explode('=', $p, 2);
    } else {
      $key = $p; $val = "";
    }
    $key = urldecode($key);
    $val = urldecode($val);
    $params[$key] = $val;
    if ($val == "") {
      $path_args[] = $key;
    }
  }
  // check PageName & Action
  array_push($path_args, NULL, NULL, NULL);
  // page
  $page = array_shift( $path_args );
  if (isset($params['page'])) $page = $params['page'];
  if ($page == "") $page = $kona3conf["FrontPage"];
  $action = array_shift( $path_args );
  if (isset($params['action'])) $action = $params['action'];
  if ($action == "") $action = "show";
  $status = array_shift( $path_args );
  if (isset($params['status'])) $action = $params['status'];
  // set to conf
  $kona3conf['page']   = $_GET['page']   = $page;
  $kona3conf['action'] = $_GET['action'] = $action;
  $kona3conf['status'] = $_GET['status'] = $status;
  //
  $script = $kona3conf['scriptname'] = basename($_SERVER['SCRIPT_NAME']);
  $script_dir = preg_replace("#/{$script}$#", "", $script_path);
  $kona3conf['baseurl'] = sprintf(
    "%s://%s%s",
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
    $_SERVER['HTTP_HOST'],
    $script_dir
  );
}

// execute
function kona3execute() {
  global $kona3conf;
  $action = $kona3conf["action"];
  $actionFile = kona3getEngineFName("action", $action);
  $actionFunc = "kona3_action_$action";
  $page = $kona3conf['page'];
  if (!file_exists($actionFile)) {
    kona3error($page, "Invalid Action `$action`"); exit;
  }
  include_once($actionFile);
  if (!function_exists($actionFunc)) {
    kona3error($page, "[System Error] Action not found."); exit;
  }
  call_user_func($actionFunc);
}

// get file path from kona3engine dir
function kona3getEngineFName($dir, $pname) {
  global $kona3conf;
  $pname = kona3getPName($pname);
  $af = $kona3conf["path.engine"]."/$dir/$pname.inc.php";
  return $af;
}

// pname(Plugin name) is only alhpabet and number and %_-
function kona3getPName($pname) {
  $pname = preg_replace("/([a-zA-Z0-9\_\-\%]+)/", "$1", $pname);
  return $pname;
}

// wikiname to filename
function kona3getWikiFile($wikiname, $autoExt = true, $ext = '.txt') {
  global $kona3conf;
  $path_data = $kona3conf["path.data"];
  
  // make path
  $wikiname = kona3getRelativePath($wikiname);
  $paths = explode("/", $wikiname);
  $rpath = array();
  foreach ($paths as $p) {
    $rpath[] = urlencode($p);
  }
  $res = $path_data . "/" . implode("/", $rpath);
  if ($autoExt) $res .= $ext;
  return $res;
}

// wikiname to url
function kona3getWikiUrl($wikiname) {
  global $kona3conf;
  $path_url = $kona3conf["url.data"];
  $wikiname = kona3getRelativePath($wikiname);
  $paths = explode("/", $wikiname);
  $rpath = array();
  foreach ($paths as $p) {
    $rpath[] = urlencode($p);
  }
  return $path_url . "/" . implode("/", $rpath);
}

// relative path from path.data
function kona3getRelativePath($wikiname) {
  global $kona3conf;
  $path_data = $kona3conf["path.data"];
  
  // check "file:/path/to/file"
  if (substr($wikiname, 0, 5) == 'file:') {
    $wikiname = substr($wikiname, 5);
    $len = strlen($path_data);
    if (substr($wikiname, 0, $len) === $path_data) {
      $wikiname = substr($wikiname, $len);
      if (substr($wikiname, 0, 1) == '/') {
        $wikiname = substr($wikiname, 1);
      }
    }
  }

  return $wikiname;
}


// show error page
function kona3error($title, $msg) {
  $err = "<div class='error'>$msg</div>";
  kona3template("message", array(
    'page_title' => kona3text2html($title),
    'page_body'  => $err,
  ));
  exit;
}
function kona3showMessage($title, $msg) {
  $body = "<div class='message'>$msg</div>";
  kona3template("message", array(
    'page_title' => kona3text2html($title),
    'page_body'  => $body,
  ));
  exit;
}

function kona3template($name, $params) {
  global $kona3conf;
  extract($params);
  $file = $kona3conf['path.engine']."/template/{$name}.tpl.php";
  include($file);
}

// for magic_quotes_gpc
function un_magic_quotes() {
  if (get_magic_quotes_gpc()){
    $_GET       = array_map("strip_text_slashes",$_GET);
    $_POST      = array_map("strip_text_slashes",$_POST);
    $_COOKIE    = array_map("strip_text_slashes",$_COOKIE);
  }
}
function strip_text_slashes($arg) {
  if(!is_array($arg)){
    $arg = stripslashes($arg);
  }elseif(is_array($arg)){
    $arg = array_map("strip_text_slashes",$arg);
  }
  return $arg;
}

function kona3getPage() {
  global $kona3conf;
  $page = $kona3conf["page"];
  return $page;
}

function kona3getPageURL($page = "", $action = "", $stat = "", $paramStr = "") {
  global $kona3conf;
  if ($page == "") $page = $kona3conf["page"];
  $page_ = urlencode($page);
  if ($action == "") $action = "show";
  $action_ = urlencode($action);
  $stat_ = urlencode($stat);
  //
  $base = $kona3conf["baseurl"];
  if (substr($base, strlen($base) - 1, 1) == "/") {
    $base = substr($base, 0, strlen($base) - 1);
  }
  $script = $kona3conf["scriptname"];
  if ($page == KONA3_WIKI_FRONTPAGE && $action == "show") {
    $url = "{$base}/";
    $action = "";
  } else {
    $url = "{$base}/{$script}?{$page_}";
  }
  //
  if ($action != "") {
    $url .= "&".$action_;
  }
  if ($stat != "") {
    $url .= "&".$stat_;
  }
  if ($paramStr != "") {
    $url .= "&".$paramStr;
  }
  return $url;
}

function kona3text2html($text) {
  return htmlentities($text, ENT_QUOTES, 'UTF-8');
}

// filename to wikiname
function kona3getWikiName($filename) {
  global $kona3conf;
  $path_data = $kona3conf["path.data"].'/';
  $f = str_replace($path_data, "", $filename);
  if (preg_match('#(.+)\.(txt|md)$#', $f, $m)) {
    $f = $m[1];
  }
  return $f;
}

function kona3getSysInfo() {
  $href = "http://kujirahand.com/konawiki/";
  $ver  = KONA3_SYSTEM_VERSION;
  return "<a href=\"$href\">Konawiki3 v.{$ver}</a>";
}

function kona3getMenu() {
  global $kona3conf;
  $page = $kona3conf['page'];
  //
  $new_uri = kona3getPageURL($page, 'new');
  $edit_uri = kona3getPageURL($page, 'edit');
  $login_uri = kona3getPageURL($page, 'login');
  $logout_uri = kona3getPageURL($page, 'logout');
  $search_uri = kona3getPageURL($page, 'search');
  //
  $list = array();
  //
  if (!kona3isLogin()) {
    $list[] = array('login', $login_uri);
    $list[] = array('-','-');
  } else {
    $list[] = array('logout', $logout_uri);
    $list[] = array('-','-');
    $list[] = array('new', $new_uri);
    $list[] = array('edit', $edit_uri);
  }
  $list[] = array('search', $search_uri);

  // make link
  $ha = array();
  foreach ($list as $it) {
    $label = $it[0];
    $href  = $it[1];
    if ($href != "-") {
      $ha[] = "[<a href=\"$href\">$label</a>]";
    } else {
      $ha[] = " - ";
    }
  }
  return implode(" ", $ha);
}

$kona3db = null;
function kona3getDB() {
  global $kona3db;
  global $kona3conf;
  if (!is_null($kona3db)) return $kona3db;
  $kona3db = new PDO($kona3conf['dsn']);
  $kona3db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  return $kona3db;
}

function kona3use_jquery() {
  global $kona3conf;
  $kona3conf['js'][] = "index.php?jquery-3.1.0.min.js&resource";
}




