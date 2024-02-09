<?php
/** 指定のパス以下にあるファイル一覧を列挙して表示
 * - [書式] #ls(filter)
 * - [引数]
 * -- filter ... フィルタ
 */

function kona3plugins_ls_execute($args) {
    global $kona3conf;
    // arguments
    $filter = array_shift($args);
    if ($filter == null) {
        $default_ext = $kona3conf['def_text_ext'];
        $filter = "*.{$default_ext}";
    }
    //
    $page = $kona3conf['page'];  
    // .. があれば削除
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
            if (fnmatch($filter, basename($f))) $res[] = $f;
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
        $code .= "<li><a href='$url'>$name</a></li>\n";
    }
    $code .= "</ul>\n";
    return $code;
}
