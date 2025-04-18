<?php

/** 最近更新されたページを列挙する
 * - [書式] #recent(count[,title][,filter=xxx])
 * - [引数]
 * -- count ... 件数(省略可)
 * -- title .... ページ名ではなくテキスト一行目を表示する(省略可)
 * -- filter ... 正規表現でフィルタする(省略可)
 * - [例]
 * -- #recent(10)
 */

function kona3plugins_recent_execute($args)
{
    // get args
    $limit = 10;
    $title = FALSE;
    $filter = '';
    foreach ($args as $arg) {
        $arg = trim($arg);
        if (preg_match('#^\d+$#', $arg)) {
            $limit = intval($arg);
            continue;
        }
        if (preg_match('#count=(\d+)$#', $arg, $m)) {
            $limit = intval($m[1]);
            continue;
        }
        if ($arg == 'title') {
            $title = TRUE;
            continue;
        }
        if (preg_match('#^filter=([^\,\)]+)#', $arg, $m)) {
            $filter = $m[1];
            continue;
        }
    }
    // select
    $r = db_get(
        "SELECT * FROM page_history " .
            "WHERE mtime > 0 " .
            "ORDER BY mtime DESC " .
            "LIMIT ?",
        [$limit]
    );
    $head = "<h3>" . lang('Recent') . "</h3>";
    if (!$r) {
        return $head . "<p>None</p>";
    }
    $uniq_ids = [];
    $list = "";
    foreach ($r as $v) {
        $page_id = $v["page_id"];
        if (isset($uniq_ids[$page_id])) {
            continue;
        }
        $uniq_ids[$page_id] = TRUE;
        $page = kona3db_getPageNameById($page_id);
        if ($filter) {
            if (!preg_match("#$filter#", $page)) {
                continue;
            }
        }
        $mtime = $v["mtime"];
        if ($page == "FrontPage" || $page == "MenuBar" || $page == "GlobalBar") {
            continue;
        }
        $url = kona3getPageURL($page);
        $page_h = kona3text2html($page);
        $mtime_h = kona3date($mtime);
        if ($title) {
            $is_live = kona3show_detect_file($page, $fname, $ext);
            if (!$is_live) {
                continue;
            } // page not exists
            // print_r([$is_live, $fname, $ext]);
            $txt = trim(file_get_contents($fname));
            $a = explode("\n", $txt);
            $page_h = htmlspecialchars($a[0], ENT_QUOTES);
            // trim
            if (mb_strlen($page_h) > 70) {
                $page_h = mb_strimwidth($page_h, 0, 70, "...");
            }
        }
        $list .=
            "<li>" .
            "<a href='$url'>$page_h $mtime_h</a>" .
            "</li>";
    }
    if ($list === "") {
        return $head . "<li>no recent page</li>";
    }
    $list = "<ul class='recent'>$list</ul>";
    return $head . $list;
}
