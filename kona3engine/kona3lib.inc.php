<?php
/**
 * konawiki3 main library
 */
if (get_magic_quotes_gpc()){ un_magic_quotes(); }

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
  if (!file_exists($actionFile)) {
    kona3error("Invalid Action", "Invalid Action"); exit;
  }
  include_once($actionFile);
  if (!function_exists($actionFunc)) {
    kona3error("System Error", "Action(function) not found."); exit;
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
function kona3getWikiFile($wikiname, $autoExt = true) {
  global $kona3conf;
  $path_data = $kona3conf["path.data"];
  $paths = explode("/", $wikiname);
  $rpath = array();
  foreach ($paths as $p) {
    $rpath[] = urlencode($p);
  }
  $res = $path_data . "/" . implode("/", $rpath);
  if ($autoExt) $res .= ".txt";
  return $res;
}

// wikiname to url
function kona3getWikiUrl($wikiname) {
  global $kona3conf;
  $path_url = $kona3conf["url.data"];
  $paths = explode("/", $wikiname);
  $rpath = array();
  foreach ($paths as $p) {
    $rpath[] = urlencode($p);
  }
  return $path_url . "/" . implode("/", $rpath);
}

// show error page
function kona3error($title, $msg) {
  echo "<h1>".kona3text2html($title)."</h1>";
  echo "<div>".kona3text2html($msg)."</div>";
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

function kona3getPageURL($page = "", $action = "", $stat = "", $paramStr = "") {
  global $kona3conf;
  if ($page == "") $page = $kona3conf["page"];
  $page_ = urlencode($page);
  if ($action == "") $action = $kona3conf["action"];
  if ($action == "show") $action = "";
  $action_ = urlencode($action);
  if ($stat == "") $stat = isset($kona3conf["stat"]) ? $kona3conf["stat"] : "";
  $stat_ = urlencode($stat);
  //
  $base = $kona3conf["baseurl"];
  if (substr($base, strlen($base) - 1, 1) == "/") {
    $base = substr($base, 0, strlen($base) - 1);
  }
  $script = $kona3conf["scriptname"];
  if ($page == KONA3_WIKI_FRONTPAGE) {
    $url = "{$base}/";
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
  $f = preg_replace("#\.txt$#", "", $f);
  return $f;
}
