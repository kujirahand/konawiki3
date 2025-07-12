<?php

/** 最近更新されたページを列挙する
 * - [書式] #recent(count[,title][,filter=xxx])
 * - [引数]
 * -- count ... 件数(省略すると10件)
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
    // select - page_idをユニークにして最新のエントリを取得
    $r = db_get(
        "SELECT ph1.* FROM page_history ph1 " .
            "INNER JOIN ( " .
            "    SELECT page_id, MAX(mtime) as max_mtime " .
            "    FROM page_history " .
            "    WHERE mtime > 0 " .
            "    GROUP BY page_id " .
            "    ORDER BY max_mtime DESC " .
            "    LIMIT ? " .
            ") ph2 ON ph1.page_id = ph2.page_id AND ph1.mtime = ph2.max_mtime " .
            "ORDER BY ph1.mtime DESC",
        [$limit]
    );
    $head = "<h3>" . lang('Recent') . "</h3>";
    if (!$r) {
        return $head . "<p>None</p>";
    }
    $list = "";
    foreach ($r as $v) {
        $page_id = $v["page_id"];
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
        if ($page == "") {
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
