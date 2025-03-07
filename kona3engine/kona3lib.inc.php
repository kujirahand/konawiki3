<?php

/**
 * konawiki3 main library
 */
// --------------------------------------------------------------
require_once __DIR__ . '/konawiki_version.inc.php';
global $kona3conf;
$kona3conf['KONAWIKI_VERSION'] = KONAWIKI_VERSION;
// --------------------------------------------------------------
require_once __DIR__ . '/kona3login.inc.php';
// --------------------------------------------------------------
// param
function kona3param($key, $def = NULL)
{
    if (isset($_REQUEST[$key])) {
        return $_REQUEST[$key];
    } else {
        return $def;
    }
}

function kona3parseURI($uri)
{
    // (ex) /path/index.php?PageName&Action&Status&p1=***&p2=***
    // (ex) /path/index.php?page=PageName&action=Action&status=Status
    global $kona3conf;
    $preAction = empty($_GET['action']) ? FALSE : $_GET['action'];
    $path_args = array();
    list($script_path, $paramStr) = explode('?', $uri . '?');
    $a = explode('&', $paramStr);
    foreach ($a as $p) {
        if (strpos($p, '=') !== false) {
            list($key, $val) = explode('=', $p, 2);
        } else {
            $key = $p;
            $val = "";
        }
        $key = urldecode($key);
        $val = urldecode($val);
        if ($val == "") {
            $path_args[] = $key;
        }
    }
    // check PageName & Action
    array_push($path_args, NULL, NULL, NULL);
    // page
    $page = array_shift($path_args);
    if (isset($path_args['page'])) $page = $path_args['page'];
    if ($page == "") $page = $kona3conf["FrontPage"];
    $action = array_shift($path_args);
    if (isset($path_args['action'])) $action = $path_args['action'];
    if ($action == "") $action = "show";
    $status = array_shift($path_args);
    if (isset($path_args['status'])) $action = $path_args['status'];

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
    // set action go
    if ($preAction) {
        $action = $preAction;
    }
    return [$page, $action, $status, $path_args, $script_path];
}

function kona3lib_parseURI()
{
    global $kona3conf;
    $uri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
    $script_name = isset($_SERVER["SCRIPT_NAME"]) ? $_SERVER["SCRIPT_NAME"] : "";
    $host = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : "localhost";
    list($page, $action, $status, $_path_args, $script_path) = kona3parseURI($uri);
    // set to conf
    $kona3conf['page']   = $_GET['page']   = $page;
    $kona3conf['action'] = $_GET['action'] = $action;
    $kona3conf['status'] = $_GET['status'] = $status;
    //
    $script = $kona3conf['scriptname'] = basename($script_name);
    $script_dir = preg_replace("#/{$script}$#", "", $script_path);
    $kona3conf['baseurl'] = sprintf(
        "%s://%s%s",
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
        $host,
        $script_dir
    );
    kona3getPageTitleLink();
}
function kona3getPageTitleLink()
{
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
function kona3lib_execute()
{
    global $kona3conf;
    $action = $kona3conf["action"];
    $actionFile = kona3getEngineFName("action", $action);
    $actionFunc = "kona3_action_{$action}";
    $page = $kona3conf['page'];
    if (!file_exists($actionFile)) {
        $action_html = htmlspecialchars($action);
        kona3error($page, "Invalid Action `$action_html`");
        exit;
    }
    include_once($actionFile);
    if (!function_exists($actionFunc)) {
        kona3error($page, "[System Error] Action not found.");
        exit;
    }
    call_user_func($actionFunc);
}

// get file path from kona3engine dir
function kona3getEngineFName($dir, $pname)
{
    global $kona3conf;
    $pname = kona3getPName($pname);
    $af = KONA3_DIR_ENGINE . "/$dir/$pname.inc.php";
    return $af;
}

// get config data
function kona3getConf($key, $def = FALSE)
{
    global $kona3conf;
    if (empty($kona3conf[$key])) {
        return $def;
    }
    return $kona3conf[$key];
}

// pname(Plugin name) is only alhpabet and number and %_-
function kona3getPName($pname)
{
    // sanitize path
    $pname = preg_replace("/([^a-zA-Z0-9\_\-\%]+)/", "", $pname);
    return $pname;
}

// get ext from file path
function kona3getFileExt($path)
{
    if (preg_match('#\.([a-zA-Z0-9_]+)$#', $path, $m)) {
        return $m[1];
    }
    return '';
}

// wikiname to filename (for text)
function koan3getWikiFileText($wikiname)
{
    // (1) デフォルトのテキストファイルの存在を確認する
    $default_ext = kona3getConf('def_text_ext', 'txt');
    $path = kona3getWikiFile($wikiname, TRUE, ".$default_ext");
    if (file_exists($path)) {
        return $path; // 存在するのでそのまま返す
    }
    // (2) デフォルトのテキストファイルが存在しない時の処理
    if ($default_ext == 'txt') {
        // .mdファイルがあるか確認する
        $path_md = kona3getWikiFile($wikiname, TRUE, '.md');
        if (file_exists($path_md)) {
            return $path_md;
        }
    } else {
        // .txtファイルがあるか確認する
        $path_txt = kona3getWikiFile($wikiname, TRUE, '.txt');
        if (file_exists($path_txt)) {
            return $path_txt;
        }
    }
    // (3) どちらも存在しないので、デフォルトのテキストファイルを返す
    return $path;
}

// wikiname to filename
function kona3getWikiFile($wikiname, $autoExt = true, $ext = '.txt', $force_encode = FALSE)
{
    global $kona3conf;
    $path_data = KONA3_DIR_DATA;

    // encode
    if (empty($kona3conf['enc_pagename'])) {
        $kona3conf['enc_pagename'] = FALSE;
    }
    $encode = $kona3conf['enc_pagename'];
    if ($force_encode) {
        $encode = $force_encode;
    }

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
function kona3getWikiUrl($wikiname)
{
    global $kona3conf;
    $path_url = $kona3conf["url.data"];
    $file_path = kona3getRelativePath($wikiname);
    $paths = explode("/", $file_path);
    $rpath = array();
    foreach ($paths as $p) {
        $rpath[] = urlencode($p);
    }
    $base = $path_url . (($path_url != '') ? '/' : '');
    $result = $base . implode("/", $rpath);
    return $result;
}

// get wiki data
function kona3getWikiPage($wikiname, $def = '')
{
    $ext = '.' . kona3getConf('def_text_ext', 'txt');
    $file = kona3getWikiFile($wikiname, TRUE, $ext);
    if (file_exists($file)) {
        $text = @file_get_contents($file);
        if ($ext == '.txt') {
            require_once __DIR__ . '/kona3parser.inc.php';
            $html = konawiki_parser_convert($text);
            return $html;
        } else {
            require_once __DIR__ . '/kona3parser_md.inc.php';
            $html = kona3markdown_parser_convert($text);
            return $html;
        }
    }
    return $def;
}

// relative path from KONA3_DIR_DATA
function kona3getRelativePath($wikiname)
{
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

    // check /path/to/data_dir/wikiname => /wikiname
    if (substr($wikiname, 0, strlen($path_data)) === $path_data) {
        $wikiname = substr($wikiname, strlen($path_data));
    }

    return $wikiname;
}


// show error page
function kona3error($title, $msg)
{
    $title = htmlspecialchars($title);
    $err = "<div class='error_box'>" .
        "<h3 class='error'>$title</h3>" .
        "<div class='error pad'>$msg</div>" .
        "</div>";
    kona3template("message.html", array(
        'page_body'  => $err,
    ));
    exit;
}
function kona3showMessage($title, $msg, $tpl = '')
{
    $title = htmlspecialchars($title);
    $body = "<div>" .
        "<h3>$title</h3>" .
        "<div class='pad'>$msg</div>" .
        "</div>";
    if ($tpl == '') {
        $tpl = 'message.html';
    }
    kona3template($tpl, array(
        'page_body'  => $body,
    ));
    exit;
}

function kona3template_prepare($name, $params)
{
    global $kona3conf;
    // header tags
    $head_tags = "";
    if (isset($kona3conf['header.tags'])) {
        foreach ($kona3conf['header.tags'] as $tag) {
            $head_tags .= $tag . "\n";
        }
    }
    $kona3conf['head_tags'] = $head_tags;

    // css tags
    $css = "";
    if (isset($kona3conf['css'])) {
        $csslist = $kona3conf['css'];
        $csslist = array_unique($csslist);
        foreach ($csslist as $c) {
            $css .= "<link rel=\"stylesheet\" type=\"text/css\"\n" .
                " href=\"{$c}\">\n";
        }
    }
    $kona3conf['css_tags'] = $css;

    // js tags
    $js = "";
    if (isset($kona3conf['js'])) {
        $jslist = $kona3conf['js'];
        $jslist = array_unique($jslist);
        foreach ($jslist as $j) {
            $js .= "<script type=\"text/javascript\"\n" .
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
function kona3template($name, $params)
{
    global $kona3conf, $FW_TEMPLATE_PARAMS;
    kona3template_prepare($name, $params);
    $FW_TEMPLATE_PARAMS = $kona3conf;
    template_render($name, $params);
}

function kona3getPage()
{
    global $kona3conf;
    $page = $kona3conf["page"];
    return $page;
}

function kona3getURLParams($params)
{
    $a = [];
    foreach ($params as $k => $v) {
        $a[] = urlencode($k) . "=" . urlencode($v);
    }
    return implode("&", $a);
}

function kona3getPageURL($page = "", $action = "", $stat = "", $paramStr = "")
{
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
        $url .= "&" . $action_;
    }
    if ($stat != "") {
        $url .= "&" . $stat_;
    }
    if ($paramStr != "") {
        $url .= "&" . $paramStr;
    }
    return $url;
}

function kona3getResourceURL($file, $use_mtime = FALSE)
{
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

function kona3getSkinURL($file, $use_mtime = FALSE)
{
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
        return kona3getPageURL($file, "skin", "", "sm=$mtime");
    }
    return kona3getPageURL($file, "skin");
}

function kona3text2html($text)
{
    return htmlentities($text, ENT_QUOTES, 'UTF-8');
}

// filename to wikiname
function kona3getWikiName($filename)
{
    global $kona3conf;
    $path_data = KONA3_DIR_DATA . '/';
    $f = str_replace($path_data, "", $filename);
    if (preg_match('#(.+)\.(txt|md)$#', $f, $m)) {
        $f = $m[1];
    }
    // decode「%xx%xx」format
    $f = urldecode($f);
    return $f;
}

function kona3getSysInfo()
{
    global $kona3conf;
    $href = "https://kujirahand.com/konawiki3/";
    $ver  = KONAWIKI_VERSION;
    $opt = "";
    if ($kona3conf["wiki_private"]) $opt .= "(private)";
    return
        "<span class='konawiki3copyright'>" .
        "<a href=\"$href\">Konawiki3 v.{$ver} {$opt}</a>" .
        "</span>";
}

function kona3getCtrlMenuArray($type)
{
    global $kona3conf;
    $page = $kona3conf['page'];
    //
    $new_uri = kona3getPageURL($page, 'new');
    $edit_uri = kona3getPageURL($page, 'edit', '');
    $login_uri = kona3getPageURL($page, 'login');
    $logout_uri = kona3getPageURL($page, 'logout');
    $search_uri = kona3getPageURL($page, 'search');
    $pdf_uri = kona3getPageURL($page, 'pdf');
    $attach_uri = kona3getPageURL($page, 'attach');
    $FrontPage_uri = kona3getPageURL($kona3conf['FrontPage']);
    // $email_logs_uri = kona3getPageURL($page, 'emailLogs');
    // $users_uri = kona3getPageURL($page, 'users');
    //
    $FrontPage_label = '🏠 ' . $kona3conf['FrontPage'];
    //
    $list = array();
    if (!kona3isLogin()) {
        if ($type == "bar") {
            $list[] = array(lang('Search'), $search_uri);
            $list[] = array(lang('Login'), $login_uri);
        } else {
            $list[] = array($FrontPage_label, $FrontPage_uri);
            $list[] = array(lang('Search'), $search_uri);
            $list[] = array(lang('Login'), $login_uri);
        }
    } else {
        $loginInfo = kona3getLoginInfo();
        $user = $loginInfo['user'];
        $userpage_uri = kona3getPageURL($user, 'user');
        //
        if (kona3isAdmin()) {
            $list[] = array("👑 $user", $userpage_uri);
        } else {
            $list[] = array("🙋 $user", $userpage_uri);
        }
        $list[] = array(lang('Edit'), $edit_uri);
        $list[] = array(lang('New'), $new_uri);
        $list[] = array(lang('Search'), $search_uri);
        if (kona3getConf('allow_upload')) {
            $list[] = array(lang('Attach'), $attach_uri);
        }
        if (kona3getConf('use_pdf_out')) {
            $list[] = array(lang('PDF'), $pdf_uri);
        }
        $list[] = array(lang('Logout'), $logout_uri);
    }
    return $list;
}

function kona3getCtrlMenu($type = 'bar')
{
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
        return '<ul>' . implode("", $ha) . '</ul>';
    }
    return '[ctrl_menu-error-no-type--]';
}

// localize
$lang_data = null;
function lang($msg, $def = null)
{
    global $lang_data;
    // Load message data
    if (!$lang_data) {
        $lang_data = [];
        $lang = kona3getLangCode();
        $langfile = KONA3_DIR_ENGINE . "/lang/$lang.inc.php";
        if (!file_exists($langfile)) {
            $langfile = KONA3_DIR_ENGINE . "/lang/en.inc.php"; // default lang
        }
        @include_once($langfile); // $lang_data
    }
    // get message data
    if (isset($lang_data[$msg])) {
        return $lang_data[$msg];
    }
    // default value
    return isset($def) ? $def : $msg;
}

function kona3getLangCode()
{
    // check code
    $lang = kona3getConf('lang', 'en');
    // language should be [a-z]+
    if (!preg_match('#^[a-z]+$#', $lang)) {
        $lang = 'en';
    } else {
        global $kona3conf;
        $kona3conf['lang'] = 'en';
    }
    return $lang;
}

// get page body hash
function kona3getPageHash($body)
{
    return hash("sha256", $body);
}


// for template
function t_url($v)
{
    echo kona3getPageURL($v);
}
function t_edit_url($v)
{
    echo kona3getPageURL($v, 'edit');
}


function kona3date($value, $mode = 'easy')
{
    // for time=0
    if ($value === 0) return "@";
    // to_int
    if (is_int($value)) {
        $target = date(lang('date_format', 'Y-m-d'), $value);
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
    $fmt = kona3getConf("data_format", 'Y-m-d');
    $s = date($fmt, $value);
    //
    return "<span class='date'>{$s}</span>{$opt}";
}

function kona3datetime($value)
{
    $fmt1 = kona3getConf('date_format', 'Y-m-d');
    $fmt2 = kona3getConf('time_format', 'H:i:s');
    return date("{$fmt1} {$fmt2}", $value);
}

// plugin info
function kona3_getPluginInfo($plugin_name, $key, $def = FALSE)
{
    global $kona3conf;
    if (isset($kona3conf["plugins"][$plugin_name][$key])) {
        return $kona3conf["plugins"][$plugin_name][$key];
    }
    return $def;
}

function kona3_setPluginInfo($plugin_name, $key, $value)
{
    global $kona3conf;
    if (!isset($kona3conf['plugins'])) {
        $kona3conf['plugins'] = [];
    }
    if (!isset($kona3conf['plugins'][$plugin_name])) {
        $kona3conf['plugins'][$plugin_name] = [];
    }
    $kona3conf["plugins"][$plugin_name][$key] = $value;
}

// get plugin name
function kona3getPluginName($pname)
{
    // check alias
    $alias = kona3getConf("plugin_alias", []);
    if (!empty($alias[$pname])) {
        $pname = $alias[$pname];
    }
    // Sanitize path
    $pname = str_replace('/', '', $pname);
    $pname = str_replace('.', '', $pname);

    // 日本語ファイル名のプラグインはurlencodeした名前にする
    $pname = urlencode($pname);
    return $pname;
}

// plugin path info
function kona3getPluginPathInfo($pname)
{
    $uname = kona3getPluginName($pname);

    // path
    $path  = KONA3_DIR_ENGINE . "/plugins/$uname.inc.php";
    $func  = str_replace("%", "_", $uname);

    // check disabled
    $disallow = FALSE;
    $pd = kona3getConf('plugin.disallow', []);
    if (isset($pd[$pname]) && $pd[$pname]) {
        $path = '';
        $disallow = TRUE;
    }
    return [
        "file" => $path,
        "init" => "kona3plugins_{$func}_init",
        "func" => "kona3plugins_{$func}_execute",
        "disallow" => $disallow,
    ];
}

// -----------------------------------------------
// CSRF token functions
// -----------------------------------------------
function kona3_getEditTokenKeyName($key)
{
    return "konawiki3_edit_token::{$key}";
}

function kona3_getEditTokenForceUpdate($key = 'default')
{
    $sname = kona3_getEditTokenKeyName($key);
    $sname_time = "{$sname}.time";
    // generate new token
    if (empty($_SESSION[$sname])) {
        $token = bin2hex(random_bytes(32));
        $_SESSION[$sname] = $token;
        $_SESSION[$sname_time] = time();
    }
    return $_SESSION[$sname];
}

function kona3_getEditToken($key = 'default', $update = FALSE)
{
    $sname = kona3_getEditTokenKeyName($key);
    $sname_time = "{$sname}.time";
    $limit = 60 * 60 * 24; // 1day

    // force update?
    if ($update) {
        return kona3_getEditTokenForceUpdate($key);
    }

    // check empty
    if (empty($_SESSION[$sname])) {
        return kona3_getEditTokenForceUpdate($key);
    }

    // check expire
    $time = isset($_SESSION[$sname_time]) ? $_SESSION[$sname_time] : time();
    if (time() - $time >  $limit) {
        return kona3_getEditTokenForceUpdate($key);
    }

    return $_SESSION[$sname];
}

function kona3_checkEditToken($key = 'default')
{
    $sname = kona3_getEditTokenKeyName($key);
    $ses = isset($_SESSION[$sname]) ? $_SESSION[$sname] : '';
    $get = isset($_POST['edit_token']) ? trim($_POST['edit_token']) : '';
    if ($ses == '') {
        return FALSE;
    }
    return ($ses === $get);
}

function kona3_getEditTokenForm($page = 'default', $action = 'edit', $label = 'Edit', $style = '')
{
    // style
    if ($style !== '') {
        $style = "style='$style'";
    }
    // get token
    $edit_token = kona3_getEditToken($page);
    $url = kona3getPageURL($page, $action);
    // html
    $html = implode("\n", [
        "<form method='POST' action='$url'>",
        "<input type='hidden' name='edit_token' value='$edit_token'>",
        "<input type='submit' class='pure-button pure-button-primary' value='$label' $style>",
        "</form>",
    ]);
    return $html;
}

// -----------------------------------------------

function kona3getShortcutLink()
{
    // get url
    $host = empty($_SERVER['HTTP_HOST']) ? "" : $_SERVER['HTTP_HOST'];
    $scriptname = dirname($_SERVER['SCRIPT_NAME']);
    // check last path
    if (substr($scriptname, strlen($scriptname) - 1, 1) == '/') {
        $scriptname = substr(0, strlen($scriptname) - 1);
    }
    $scheme = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on')) ? 'https' : 'http';
    $base_url = "{$scheme}://{$host}{$scriptname}/go.php";
    // get shortcut
    // get page_id
    $file_not_found = kona3getConf("page_not_found", FALSE);
    if ($file_not_found) {
        return '<!-- no shortcut -->';
    } else {
        $page = $_GET['page'];
        $page_id = kona3db_getPageId($page);
    }
    if ($page_id == 0) {
        return '<!-- no shortcut -->';
    }
    $url = "{$base_url}?{$page_id}";
    // get real url
    $real_url = kona3getPageURL($page);
    $page_h = htmlspecialchars($page, ENT_QUOTES);
    return "" .
        "<a href=\"$real_url\">$page_h</a>&nbsp;" .
        "(<a href=\"$url\">{$page_id}</a>)";
}



function kona3lib_send_email($to, $subject, $email_body)
{
    $admin_email = kona3getConf('admin_email', '');
    if ($admin_email == '' || $admin_email == 'admin@example.com') {
        return;
    }
    $headers = "From: $admin_email";
    @mb_send_mail($to, $subject, $email_body, $headers);
    //
    // 送信したことを記録する
    $db = database_get();
    $stmt = $db->prepare(
        'INSERT INTO email_logs (mailto, body, title, ctime) ' .
            'VALUES(?, ?,?,?)'
    );
    $stmt->execute([$to, $email_body, $subject, time()]);
}

function kona3lib_send_email_to_admin($subject, $email_body)
{
    $admin_email = kona3getConf('admin_email', '');
    kona3lib_send_email($admin_email, $subject, $email_body);
}

function kona3jump($url, $msg = '')
{
    header("Location: $url");
    $msg = ($msg == '') ? "Jump to <a href='$url'>$url</a>" : $msg;
    kona3showMessage('Jump', $msg);
}

function kona3path_join($path, $file)
{
    if (substr($path, -1) == '/') {
        return $path . $file;
    } else {
        return $path . '/' . $file;
    }
}

function kona3postDiscordWebhook($page, $msg = '')
{
    $discord_webhook_url = kona3getConf('discord_webhook_url', '');
    if ($discord_webhook_url == '') {
        return;
    }
    // Webhook
    $wikiTitle = kona3getConf('wiki_title', 'konawiki3');
    // MenuやFrontPageは除外
    if (preg_match('#^(Menu|FrontPage|GlobalBar|SideBar)$#', $page)) {
        return;
    }
    // check interval
    $last_times = json_decode(kona3db_getMetaStr('discord_webhook_last_times', '{}'), TRUE);
    // 3時間以内のエントリのみ残す
    $remain = [];
    $limit = time() - 60 * 60 * 3;
    // ~~~~~~~~~ $limit -----$val------ $now
    foreach ($last_times as $key => $val) {
        if ($limit < $val) {
            $remain[$key] = $val;
        }
    }
    $last_times = $remain;
    // check interval
    $last_t = isset($last_times[$page]) ? $last_times[$page] : 0;
    if ($last_t == 0) { // new post
        $last_times[$page] = time();
        kona3db_setMetaStr('discord_webhook_last_times', json_encode($last_times));
    } else {
        return;
    }

    // page link
    $app_url = kona3getPageURL($page);
    //メッセージの内容を定義
    $contents = sprintf(lang('The page "%s" has been updated.'), $page) . "\n$app_url";
    $message = array(
        'username' => $wikiTitle,
        'content'  => $contents
    );
    $message_json = json_encode($message);
    // curlを利用してポスト(非同期)
    $curl_command = sprintf(
        'curl -X POST %s -H "Content-Type: application/json; charset=utf-8" -d %s --insecure > /dev/null 2>&1 &',
        escapeshellarg($discord_webhook_url),
        escapeshellarg($message_json)
    );
    @exec($curl_command);
    /*
    // PHP code
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $discord_webhook_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    $_resp = curl_exec($ch);
    curl_close($ch);
    */
}

function kona3lib_checkSecurity()
{
    // check security version
    $security_check_file = KONA3_DIR_PRIVATE . '/.security_check.v3_3_12.php';
    if (file_exists($security_check_file)) {
        return;
    }
    // check private/.htaccess
    $htaccessBody = <<< EOS
# Deny all access
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
  Order Allow,Deny
  Deny from all
</IfModule>
EOS;
    // cache dir
    $cache_htaccess = KONA3_DIR_PRIVATE . '/.htaccess';
    file_put_contents($cache_htaccess, $htaccessBody);
    // private dir
    $private_htaccess = KONA3_DIR_PRIVATE . '/.htaccess';
    file_put_contents($private_htaccess, $htaccessBody);
    // body dir
    $body_htaccess = KONA3_DIR_DATA . '/.htaccess';
    file_put_contents($body_htaccess, $htaccessBody);
    file_put_contents($security_check_file, "ok");
}

// file lock save
function kona3lock_save($path, $contents, $retry = 3, $usleep = 100000)
{
    for ($try = 0; $try < $retry; $try++) {
        $fp = fopen($path, 'c+');
        if (!$fp) {
            return FALSE;
        }
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, $contents);
            flock($fp, LOCK_UN);
            fclose($fp);
            return TRUE;
        }
        fclose($fp);
        usleep($usleep);
    }
    return FALSE;
}

// file lock load
function kona3lock_load($path, $retry = 3, $usleep = 100000)
{
    // ファイルかどうかチェック
    if (is_file($path) === FALSE) {
        return FALSE;
    }
    for ($try = 0; $try < $retry; $try++) {
        $fp = fopen($path, 'r');
        if (!$fp) {
            return FALSE;
        }
        if (flock($fp, LOCK_SH)) {
            $size = filesize($path);
            if ($size > 0) {
                $contents = fread($fp, $size);
            } else {
                $contents = '';
            }
            flock($fp, LOCK_UN);
            fclose($fp);
            return $contents;
        }
        fclose($fp);
        usleep($usleep);
    }
    return FALSE;
}
