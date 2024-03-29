<?php
/** 指定のディレクトリ以下のファイルを列挙して一行目をタイトルとして一覧表示
 * - [書式] #chapter(dir)
 * - [引数]
 * -- dir ... ディレクトリ名(アルファベットと数字の名前と「/_-@」のみ許可)
 */
function kona3plugins_chapter_execute($args) {
    global $kona3conf;
    // parametes
    $chapter = array_shift($args);
    if ($chapter == null) { $chapter = ""; }
    // 上位のフォルダを許さない＆アルファベットの名前&「/」のみを許す
    $chapter = preg_replace('#[^a-zA-Z0-9\_\-\@\(\)\/]#', '', $chapter);
    $page = $kona3conf['page'];
    $fname = kona3getWikiFile($page, false);
    $basedir = dirname($fname);
    $ext = '.'.kona3getConf('def_text_ext', 'txt');

    // get files
    $pattern = $basedir.'/'.$chapter.'/*'.$ext;
    $files = glob($pattern);
    sort($files);

    $code = "<ul>";
    foreach ($files as $f) {
        $line = kona3plugin_chapter_read_head($f);
        $name = kona3getWikiName($f);
        $url = kona3getPageURL($name);
        if ($line == '') { $line = $name; }
        $line = htmlentities($line);
        $code .= "<li><a href='$url'>$line</a></li>\n";
    }
    $code .= "</ul>\n";
    return $code;
}

function kona3plugin_chapter_read_head($fname) {
    $result = '';
    $fp = fopen($fname, 'r');
    while (!feof($fp)) {
        $line = trim(fgets($fp));
        if ($line == '') { continue; }
        $result = $line;
        break;
    }
    return trim($result);
}

