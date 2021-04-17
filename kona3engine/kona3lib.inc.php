<?php
/**
 * konawiki3 main library
 */
require_once __DIR__.'/kona3login.inc.php';

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
  $params = $_GET; // default params
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
  
  // Check invalid page name like /../../..
  $page = str_replace('..', '', $page);
  $page = str_replace('//', '', $page);
  $page = preg_replace('#^/#', '', $page);
  // Check invalid action
	if (!preg_match('#^[a-zA-Z0-9_]+$#', $action)) {
		$action = '__INVALID__';
	}
  // Check invalid status
  if ($status != '') {
    if (!preg_match('#^[a-zA-Z0-9_]*$#', $status)) {
      $status = '__INVALID__';
    }
  }
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
    $action_html = htmlspecialchars($action);
    kona3error($page, "Invalid Action `$action_html`");
    exit;
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
  $af = KONA3_DIR_ENGINE."/$dir/$pname.inc.php";
  return $af;
}

// pname(Plugin name) is only alhpabet and number and %_-
function kona3getPName($pname) {
  // sanitize path
  $pname = preg_replace("/([^a-zA-Z0-9\_\-\%]+)/", "", $pname);
  return $pname;
}

// wikiname to filename
function kona3getWikiFile($wikiname, $autoExt = true, $ext = '.txt', $force_encode = FALSE) {
  global $kona3conf;
  $path_data = KONA3_DIR_DATA;
  
  // encode
  if (empty($kona3conf['enc_pagename'])) {
    $kona3conf['enc_pagename'] = FALSE;
  }
  $encode = $kona3conf['enc_pagename'];
  if ($force_encode) { $encode = $force_encode; }
  
  // check path traversal (1/2)
  $wikiname = str_replace('..', '', $wikiname);
  
  // make path
  $wikiname = kona3getRelativePath($wikiname);
  $paths = explode("/", $wikiname);
  $rpath = array();
  foreach ($paths as $p) {
    $enc = $p;
    if ($encode) $enc = urlencode($p);
    // remove path travasal (2/2)
    $enc = str_replace('..', '', $enc);
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


// relative path from KONA3_DIR_DATA
function kona3getRelativePath($wikiname) {
  global $kona3conf;
  $path_data = KONA3_DIR_DATA;
  
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
  $title = htmlspecialchars($title);
  $err = "<div class='error_box'>".
    "<h3 class='error'>$title</h3>".
    "<div class='error pad'>$msg</div>".
    "</div>";
  kona3template("message.html", array(
    'page_body'  => $err,
  ));
  exit;
}
function kona3showMessage($title, $msg, $tpl = '') {
  $title = htmlspecialchars($title);
  $body = "<div>".
    "<h3>$title</h3>".
    "<div class='pad'>$msg</div>".
    "</div>";
  if ($tpl == '') {
    $tpl = 'message.html';
  }
  kona3template($tpl, array(
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
  if (empty($kona3conf['FrontPage'])) {
    $kona3conf['FrontPage'] = 'FrontPage';
  }
  $kona3conf['FrontPage_url'] =
    kona3getPageURL($kona3conf['FrontPage']);
  
  // data file 
  if (empty($kona3conf['page'])) {
    $kona3conf['page'] = 'FrontPage';
  }
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
  if (empty($kona3conf["url.index"])) {
    $kona3conf["url.index"] = 'index.php';
  }
  $url_index = $kona3conf["url.index"];
  $url = "{$url_index}?{$page_}";
  
  // FrontPageならオプションを削る
  if ($page == $kona3conf['FrontPage'] && $action == "show" && $stat == "" && $paramStr == '') {
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
  // remove dir travasal
  $file = str_replace('..', '', $file);
  $path_resource = $kona3conf['path_resource'];
  $path = "{$path_resource}/$file";
  if ($use_mtime && file_exists($path)) {
    $mtime = filemtime($path);
    return kona3getPageURL($file, "resource", "", "m=$mtime");
  }
  return kona3getPageURL($file, "resource");
}

function kona3getSkinURL($file, $use_mtime = FALSE) {
  global $kona3conf;
  $skin = $kona3conf['skin'];
  // check invalid skin pattern
  if (preg_match('#[^a-zA-Z0-9_\-]#', $skin)) {
    $skin = 'def';
  }
  $path_skin = KONA3_DIR_SKIN;
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
  $path_data = KONA3_DIR_DATA.'/';
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
  if ($kona3conf["wiki_private"]) $opt .= "(private)";
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
  $FrontPage_label = '🏠 '.$kona3conf['FrontPage'];
  //
  $list = array();
  if (!kona3isLogin()) {
    if ($type == "bar") {
      $list[] = array(lang('Search'), $search_uri);
      $list[] = array(lang('Login'), $login_uri);
    } else {
      $list[] = array($FrontPage_label, $FrontPage_uri);
      $list[] = array(lang('Search'), $search_uri);
    }
  } else {
    $loginInfo = kona3getLoginInfo();
    $user = $loginInfo['user'];
    $userpage_uri = kona3getPageURL($user, 'user');
    //
    if (kona3isAdmin()) {
      $list[] = array("👋🙋 $user", $userpage_uri);
    } else {
      $list[] = array("🙋 $user", $userpage_uri);
    }
    $list[] = array(lang('Edit'), $edit_uri);
    $list[] = array(lang('New'), $new_uri);
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

// localize
$lang_data = null;
function lang($msg, $def = null) {
  global $lang_data;
  global $kona3conf;
  // Load message data
  if (!$lang_data) {
    $lang_data = [];
    if (!empty($kona3conf['lang'])) {
      // language should be [a-z]+
      $lang = $kona3conf['lang'];
      if (!preg_match('#^[a-z]+$#', $lang)) {
        $lang = 'en';
      }
      // check locale file
      $langfile = KONA3_DIR_ENGINE."/lang/$lang.inc.php";
      if (file_exists($langfile)) {
        @include_once($langfile); // $lang_data
      } else {
      }
    }
  }
  // 値を取得
  if (isset($lang_data[$msg])) {
    return $lang_data[$msg];
  }
  // def ?
  return isset($def) ? $def : $msg;
}

// get page body hash
function kona3getPageHash($body) {
  return hash("sha256", $body);
}


// for template
function t_url($v) {
  echo kona3getPageURL($v);
}
function t_edit_url($v) {
  echo kona3getPageURL($v, 'edit');
}


function kona3date($value, $mode='easy') {
  // for time=0
	if ($value === 0) return "@";
  // to_int
	if (is_int($value)) {
		$target = konawiki_date($value);
	} else {
		$target = $value;
	}
  $now = time();
  // ちょっとだけの目安表示
	if ($mode == 'easy') {
    $sa = $now - $value;
    if ($sa < 3600) { // 1h
      return "<span class='date new'>1h</span>";
    } else if ($sa < 3600 * 6) {
      return "<span class='date new'>6h</span>";
    } else if ($sa < 3600 * 12) {
      $today = lang('Today');
      return "<span class='date new'>$today</span>";
    }
    $s = "";
    $y_now = date("Y", $now);
    $y     = date("Y", $value);
    if ($y_now == $y) {
      $dfe = lang('date_format_e', 'm-d');
      $s = date($dfe, $value);
    } else {
      $df = lang('date_format', 'Y-m-d');
      $s = date($df, $value);
    }
    return "<span class='date'>$s</span>";
	}
	//
  // しっかりと日付を表示
  //
	$opt = "";
	$new_limit = time() - (3600 * 24) /* hour */;
  if ($value > $new_limit) {
    $opt = " <span class='new'>New!</span>";
  }
  $fmt = konawiki_private("data_format", 'Y-m-d');
  $s = date($fmt, $value);
	//
	return "<span class='date'>{$s}</span>{$opt}";
}

function kona3datetime($value)
{
	$fmt1 = konawiki_private('date_format', 'Y-m-d');
	$fmt2 = konawiki_private('time_format', 'H:i:s');
	return date("{$fmt1} {$fmt2}", $value);
}

function kona3_getPluginInfo($plugin_name, $key, $def = FALSE) {
  global $kona3conf;
	if (isset($kona3conf["plugins"][$plugin_name][$key])) {
		return $kona3conf["plugins"][$plugin_name][$key];
	}
	return $def;
}

function kona3_setPluginInfo($plugin_name, $key, $value) {
  global $kona3conf;
  if (!isset($kona3conf['plugins'])) {
    $kona3conf['plugins'] = [];
  }
  if (!isset($kona3conf['plugins'][$plugin_name])) {
    $kona3conf['plugins'][$plugin_name] = [];
  }
	$kona3conf["plugins"][$plugin_name][$key] = $value;
}

function kona3_getEditToken($key = 'default', $update = TRUE) {
  global $kona3conf;
  $sname = "konawiki3_edit_token_$key";
  if ($update == FALSE) {
    if (isset($_SESSION[$sname])) {
      $kona3conf['edit_token'] = $_SESSION[$sname];
      return $kona3conf['edit_token'];
    }
  }
  if (!isset($kona3conf['edit_token'])) {
    $t = $kona3conf['edit_token'] = bin2hex(random_bytes(32));
    $_SESSION[$sname] = $t;
  }
  return $kona3conf['edit_token'];
}

function kona3_checkEditToken($key = 'default') {
  $sname = "konawiki3_edit_token_$key";
  $ses = isset($_SESSION[$sname]) ? $_SESSION[$sname] : '';
  $get = isset($_REQUEST['edit_token']) ? $_REQUEST['edit_token'] : '';
  if ($ses != '' && $ses == $get) {
    return TRUE;
  }
  return FALSE;
}

function kona3getConf($key, $def = '') {
  global $kona3conf;
  if (isset($kona3conf[$key])) {
    return $kona3conf[$key];
  }
  return $def;
}

function kona3getShortcutLink() {
  // get url
  $host = $_SERVER['HTTP_HOST'];
  $scriptname = dirname($_SERVER['SCRIPT_NAME']);
  $scheme = $_SERVER['REQUEST_SCHEME'];
  $base_url = "{$scheme}://{$host}{$scriptname}/go.php";
  // get shortcut
  // get page_id
  $page = $_GET['page'];
  $page_id = kona3db_getPageId($page);
  if ($page_id == 0) { return '<!-- no shortcut -->'; }
  $url = "{$base_url}?{$page_id}";
  // get real url
  $real_url = kona3getPageURL($page);
  $page_h = htmlspecialchars($page, ENT_QUOTES);
  return "".
    "<a href=\"$real_url\">$page_h</a><br>".
    "<a href=\"$url\">$url</a><br>";
}



