<?php
/** 指定のパス以下にあるページ一覧を列挙して表示(ページのみ表示)
 * - [書式] #ls(filter)
 * - [引数]
 * -- filter ... フィルタ(ただし拡張子は無視される)
 * -- show_system ... MenuBarなどの隠しファイルも表示
 */

function kona3plugins_ls_execute($args) {
    global $kona3conf;
    // arguments
    $show_system = false;
    $filter = array_shift($args);
    if ($filter == null) {
        $filter = "*";
    }
    foreach ($args as $arg) {
        if ($arg == "show_system") {
            $show_system = true;
        }
    }
    // 標準拡張子を取得
    $default_ext = kona3getConf('def_text_ext', 'txt');
    $filter .= $default_ext;
    // ユーザーに余分なファイルを見せないように配慮
    // 上のフォルダは見せない
    $filter = str_replace(".", "", $filter);
    // .. があれば削除
    $page = $kona3conf['page'];  
    $page = str_replace('..', '', $page);
    if (strpos($page, '//') !== false) {
        return "";
    }
    $fname = kona3getWikiFile($page, false);
    // dir?
    if (is_dir($fname)) {
        $dir = $fname;
    } else {
        $dir = dirname($fname);
    }
    // get all files
    $files = glob($dir."/*");
    sort($files);
    // filter
    if ($filter != null) {
        $res = array();
        foreach ($files as $f) {
            if (fnmatch($filter, basename($f))) {
                $res[] = $f;
            }
        }
        $files = $res;
    }

    $code = "<ul>";
    foreach ($files as $f) {
        $name = kona3getWikiName($f);
        $url = kona3getPageURL($name);
        $name = htmlentities($name, ENT_QUOTES);
        // ディレクトリは除外する
        if (is_dir($f)) {
            continue;
        }
        if (!$show_system) {
            if ($name == "MenuBar" || $name == "GlobalBar" || $name == "SideBar") { continue; }
        }
        $code .= "<li><a href='$url'>$name</a></li>\n";
    }
    $code .= "</ul>\n";
    return $code;
}
