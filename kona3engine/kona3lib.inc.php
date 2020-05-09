<?php
/**
 * konawiki3 main library
 */
require_once 'kona3login.inc.php';

function kona3param($key, $def = NULL) {
  if (isset($_REQUEST[$key])) {
    return $_REQUEST[$key];
  } else {
    return $def;
  }
}

function kona3lib_parseURI() {
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
  kona3getPageTitleLink();
}
function kona3getPageTitleLink() {
  global $kona3conf;

  $page_title = $page = $kona3conf["page"];
  $page_title_ = kona3text2html($page_title);
  $page_url = $kona3conf['page_url'] = kona3getPageURL($page);
  // if page_title has directories
  if (strpos($page_title, '/') >= 0) {
    $titles = explode('/', $page_title);
    $title_a = array();
    $title_links = array();
    foreach ($titles as $title) {
      $title_a[] = $title;
      $name = implode('/', $title_a);
      $name_html = kona3text2html($title);
      $name_link = kona3getPageURL($name);
      $title_links[] = "<a href='$name_link'>$name_html</a>";
    }
    $page_title_a = implode('/', $title_links);
  } else {
    $page_title_a = "<a href='{$page_url}'>{$page_title_}</a>";
  }
  $kona3conf['page_title'] = $page_title;
  $kona3conf['page_title_a'] = $page_title_a;
}

// execute
function kona3lib_execute() {
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
function kona3getWikiFile($wikiname, $autoExt = true, $ext = '.txt', $force_encode = FALSE) {
  global $kona3conf;
  $path_data = $kona3conf["path.data"];
  
  // encode
  $encode = $kona3conf['enc.pagename'];
  if ($force_encode) { $encode = $force_encode; }
  
  // make path
  $wikiname = kona3getRelativePath($wikiname);
  $paths = explode("/", $wikiname);
  $rpath = array();
  foreach ($paths as $p) {
    $enc = $p;
    if ($encode) $enc = urlencode($p);
    $rpath[] = $enc;
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
  $base = $path_url . ($path_url != '') ? '/' : '';
  return $base . implode("/", $rpath);
}

// get wiki data
function kona3getWikiPage($wikiname, $def = '') {
  require_once dirname(__FILE__).'/kona3parser.inc.php';
  $file = kona3getWikiFile($wikiname);
  if (file_exists($file)) {
    $text = @file_get_contents($file);
    $html = konawiki_parser_convert($text);
    return $html; 
  }
  return $def;
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
  $err = "<div class='error_box'>".
    "<h3 class='error'>$title</h3>".
    "<div class='error pad'>$msg</div>".
    "</div>";
  kona3template("message.html", array(
    'page_body'  => $err,
  ));
  exit;
}
function kona3showMessage($title, $msg) {
  $body = "<div>".
    "<h3>$title</h3>".
    "<div class='pad'>$msg</div>".
    "</div>";
  kona3template("message.html", array(
    'page_body'  => $body,
  ));
  exit;
}

function kona3template_prepare($name, $params) {
  global $kona3conf;
  // header tags
  $head_tags = "";
  if (isset($kona3conf['header.tags'])) {
    foreach($kona3conf['header.tags'] as $tag) {
      $head_tags .= $tag."\n";
    }
  }
  $kona3conf['head_tags'] = $head_tags;
  
  // css tags
  $css = "";
  if (isset($kona3conf['css'])) {
    $csslist = $kona3conf['css'];
    $csslist = array_unique($csslist);
    foreach($csslist as $c) {
      $css .= "<link rel=\"stylesheet\" type=\"text/css\"\n".
              " href=\"{$c}\">\n";
    }
  }
  $kona3conf['css_tags'] = $css;
  
  // js tags
  $js = "";
  if (isset($kona3conf['js'])) {
    $jslist = $kona3conf['js'];
    $jslist = array_unique($jslist);
    foreach($jslist as $j) {
      $js .= "<script type=\"text/javascript\"\n".
             " src=\"$j\"></script>\n";
    }
  }
  $kona3conf['js_tags'] = $js;

  // FrontPage URL
  $kona3conf['FrontPage_url'] =
    kona3getPageURL($kona3conf['FrontPage']);
  
  // data file 
  if (empty($kona3conf['data_filename'])) {
    $kona3conf['data_filename'] = 
      kona3getWikiFile($kona3conf['page']);
  }
}

// Show template
function kona3template($name, $params) {
  global $kona3conf, $FW_TEMPLATE_PARAMS;
  kona3template_prepare($name, $params);
  $FW_TEMPLATE_PARAMS = $kona3conf;
  template_render($name, $params);
}

function kona3getPage() {
  global $kona3conf;
  $page = $kona3conf["page"];
  return $page;
}

function kona3getURLParams($params) {
  $a = [];
  foreach ($params as $k => $v) {
    $a[] = urlencode($k)."=".urlencode($v);
  }
  return implode("&", $a);
}

function kona3getPageURL($page = "", $action = "", $stat = "", $paramStr = "") {
  global $kona3conf;
  if ($page == "") $page = $kona3conf["page"];
  $page_ = urlencode($page);
  if ($action == "") $action = "show";
  $action_ = urlencode($action);
  $stat_ = urlencode($stat);
  
  // 基本URLを構築
  $url_index = $kona3conf["url.index"];
  $url = "{$url_index}?{$page_}";
  
  // FrontPageならオプションを削る
  if ($page == KONA3_WIKI_FRONTPAGE && $action == "show" && $stat == "" && $paramStr == '') {
    return $url_index;
  }
  
  // パラメータを追加
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

function kona3getResourceURL($file, $use_mtime = FALSE) {
  global $kona3conf;
  $path_resource = KONA3_DIR_RESOURCE;
  $path = "{$path_resource}/$file";
  if ($use_mtime && file_exists($path)) {
    $mtime = filemtime($path);
    return kona3getPageURL($file, "resource", "", "m=$mtime");
  }
  return kona3getPageURL($file, "resource");
}

function kona3getSkinURL($file, $use_mtime = FALSE) {
  global $kona3conf;
  $skin = KONA3_WIKI_SKIN;
  $path_skin = $kona3conf['path.skin'];
  $path = "{$path_skin}/{$skin}/{$file}";
  // skinディレクトリにファイルがなければresourceを探す
  if (!file_exists($path)) {
    return kona3getResourceURL($file, $use_mtime);
  }
  // mtimeをつけて出力?
  if ($use_mtime && file_exists($path)) {
    $mtime = filemtime($path);
    return kona3getPageURL($file, "skin", "", "m=$mtime");
  }
  return kona3getPageURL($file, "skin");
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
  global $kona3conf;
  $href = "https://kujirahand.com/konawiki3/";
  $ver  = KONA3_SYSTEM_VERSION;
  $opt = "";
  if ($kona3conf["wiki.private"]) $opt .= "(private)";
  return
    "<span class='konawiki3copyright'>".
    "<a href=\"$href\">Konawiki3 v.{$ver} {$opt}</a>".
    "</span>";
}

function kona3getCtrlMenuArray($type) {
  global $kona3conf;
  $page = $kona3conf['page'];
  //
  $new_uri = kona3getPageURL($page, 'new');
  $edit_uri = kona3getPageURL($page, 'edit');
  $login_uri = kona3getPageURL($page, 'login');
  $logout_uri = kona3getPageURL($page, 'logout');
  $search_uri = kona3getPageURL($page, 'search');
  $email_logs_uri = kona3getPageURL($page, 'emailLogs');
  $FrontPage_uri = kona3getPageURL($kona3conf['FrontPage']);
  $users_uri = kona3getPageURL($page, 'users');
  //
  $list = array();
  //
  if (!kona3isLogin()) {
    $list[] = array(lang('Search'), $search_uri);
    if ($type == "bar") {
      $list[] = array(lang('Login'), $login_uri);
    }
  } else {
    $loginInfo = kona3getLoginInfo();
    $user = $loginInfo['user'];
    $list[] = array("🙋 $user", $FrontPage_uri);
    $list[] = array(lang('Edit'), $edit_uri);
    $list[] = array(lang('New'), $new_uri);
    if (kona3isAdmin()) {
      $list[] = array(lang('Email Logs'), $email_logs_uri);
      $list[] = array(lang('Users List'), $users_uri);
    }
    $list[] = array(lang('Search'), $search_uri);
    $list[] = array(lang('Logout'), $logout_uri);
  }
  return $list;
}

function kona3getCtrlMenu($type='bar') {
  $list = kona3getCtrlMenuArray($type);
  // render
  if ($type == 'bar') {
    $ha = array();
    foreach ($list as $it) {
      $label = $it[0];
      $href  = $it[1];
      if ($href != "-") {
        $ha[] = "<a class='pure-button' href=\"$href\">$label</a>";
      } else {
        $ha[] = " - ";
      }
    }
    return implode(" ", $ha);
  }
  if ($type == 'list') {
    $ha = array();
    foreach ($list as $it) {
      $label = $it[0];
      $href  = $it[1];
      if ($href != "-") {
        $ha[] = "<li><a href=\"$href\">$label</a></li>";
      } else {
        $ha[] = "<li></li>";
      }
    }
    return '<ul>'.implode("", $ha).'</ul>';
  }
  return '[ctrl_menu-error-no-type--]';
}

// THIS METHOD FOR PLUGINS ONLY
$kona3db = null;
function kona3getDB() {
  global $kona3db;
  global $kona3conf;
  if (!is_null($kona3db)) return $kona3db;
  $kona3db = new PDO($kona3conf['dsn']);
  $kona3db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  return $kona3db;
}

// localize
$lang_data = null;
function lang($msg) {
  global $lang_data;
  // メッセージデータを読み込み
  if (!$lang_data) {
    $lang = KONA3_LANG;
    $langfile = KONA3_DIR_LANG."/$lang.inc.php";
    @include_once($langfile); // $lang_data
  }
  // 値を取得
  if (isset($lang_data[$msg])) {
    return $lang_data[$msg];
  }
  return $msg;
}

// get page body hash
function kona3getPageHash($body) {
  return hash("sha256", $body);
}





