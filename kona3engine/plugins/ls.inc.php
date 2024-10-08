<?php
/** 指定のパス以下にあるページ一覧を列挙して表示(ページのみ表示)
 * - [書式] #ls(filter)
 * - [引数]
 * -- filter ... フィルタ(ただし拡張子は無視される)
 * -- show_system ... MenuBarなどの隠しファイルも表示
 * -- show_dir ... ディレクトリがあればそれも表示
 * -- reverse ... 逆順に表示
 * -- sort_by_time ... 日付順に表示
 */

function kona3plugins_ls_execute($args) {
    global $kona3conf;
    // arguments
    $show_system = false;
    $show_dir = false;
    $reverse = false;
    $sort_by_time = false;
    // 引数を取得
    $filter = array_shift($args); // 先頭の引数をフィルタとする
    if ($filter == null) {
        $filter = "*";
    }
    foreach ($args as $arg) {
        if ($arg == "show_system") {
            $show_system = true;
            continue;
        }
        if ($arg == "show_dir") {
            $show_dir = true;
            continue;
        }
        if ($arg == "reverse") {
            $reverse = true;
            continue;
        }
        if ($arg == "sort_by_time") {
            $sort_by_time = true;
            continue;
        }
    }
    // 標準拡張子を取得
    $default_ext = kona3getConf('def_text_ext', 'txt');
    $filter_ext = $filter.$default_ext;
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
    if ($sort_by_time) {
        // 日付順にソート
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
    } else {
        sort($files);
    }
    if ($reverse) {
        $files = array_reverse($files);
    }

    $code = "<ul>";
    foreach ($files as $f) {
        $name = kona3getWikiName($f);
        $url = kona3getPageURL($name);
        $name = htmlentities($name, ENT_QUOTES);
        // ディレクトリならリンクを表示
        if ($show_dir) { // ただしオプションがあれば
            if (is_dir($f)) {
                $code .= "<li>&lt;<a href='$url'>$name/</a>&gt;</li>\n";
                continue;
            }
        }
        // ファイルならフィルタでマッチさせる
        if (fnmatch($filter_ext, basename($f))) {
            if (!$show_system) {
                if ($name == "MenuBar" || $name == "GlobalBar" || $name == "SideBar") {
                    continue;
                }
            }
            $code .= "<li><a href='$url'>$name</a></li>\n";
        }
    }
    $code .= "</ul>\n";
    return $code;
}
