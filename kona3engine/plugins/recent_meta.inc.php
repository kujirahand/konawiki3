<?php

/** メタ情報(data/.meta/*.json)の updated_at を基準に、最近更新されたページを列挙する
 * - DBを使わないため、Web経由でなくgit経由で更新したページも対象になる
 * - メタ情報が無いページは対象外 (git履歴から生成するスクリプトと併用すると便利)
 * - [書式] #recent_meta(count[,title][,filter=xxx])
 * - [引数]
 * -- count ... 件数(省略すると10件)
 * -- title .... ページ名ではなくテキスト一行目を表示する(省略可)
 * -- filter ... 正規表現でフィルタする(省略可)
 * - [例]
 * -- #recent_meta(10)
 */

function kona3plugins_recent_meta_execute($args)
{
    $limit = 10;
    $title = FALSE;
    $filter = '';
    foreach ($args as $arg) {
        $arg = trim($arg);
        if (preg_match('#^\d+$#', $arg)) {
            $limit = intval($arg);
        } else if (preg_match('#count=(\d+)$#', $arg, $m)) {
            $limit = intval($m[1]);
        } else if ($arg == 'title') {
            $title = TRUE;
        } else if (preg_match('#^filter=([^\,\)]+)#', $arg, $m)) {
            $filter = $m[1];
        }
    }
    $head = "<h3>" . lang('Recent') . "</h3>";

    // 更新日時の一覧(キャッシュ済み)を取得して、フィルタする
    $items = [];
    foreach (kona3plugins_recent_meta_index() as list($page, $mtime)) {
        if ($page == "FrontPage" || $page == "MenuBar" || $page == "GlobalBar") {
            continue;
        }
        if ($filter && !preg_match("#$filter#", $page)) {
            continue;
        }
        $items[] = [$page, $mtime];
    }

    $list = "";
    $count = 0;
    foreach ($items as list($page, $mtime)) {
        if ($count >= $limit) {
            break;
        }
        // ページが実在するか確認
        $is_live = kona3show_detect_file($page, $fname, $ext);
        if (!$is_live) {
            continue;
        }
        $url = kona3getPageURL($page);
        $page_h = kona3text2html($page);
        $mtime_h = kona3date($mtime);
        if ($title) {
            $a = explode("\n", trim(file_get_contents($fname)));
            $page_h = htmlspecialchars($a[0], ENT_QUOTES);
            if (mb_strlen($page_h) > 70) {
                $page_h = mb_strimwidth($page_h, 0, 70, "...");
            }
        }
        $list .= "<li><a href='$url'>$page_h $mtime_h</a></li>";
        $count++;
    }
    if ($list === "") {
        return $head . "<li>no recent page</li>";
    }
    return $head . "<ul class='recent'>$list</ul>";
}

/** 全メタ情報から [ページ名, updated_at] の一覧を新しい順に返す
 * - 全JSONを毎回読むと重いので、結果をキャッシュファイルに保存する(有効期間 10分)
 */
function kona3plugins_recent_meta_index()
{
    $ttl = 600;
    $cacheFile = KONA3_DIR_CACHE . '/recent_meta_index.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $cached = json_decode(file_get_contents($cacheFile), TRUE);
        if (is_array($cached)) {
            return $cached;
        }
    }
    $items = [];
    $metaDir = KONA3_DIR_DATA . '/.meta';
    if (is_dir($metaDir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($metaDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }
            $meta = json_decode(file_get_contents($file->getPathname()), TRUE);
            if (!is_array($meta) || empty($meta['page']) || empty($meta['updated_at'])) {
                continue;
            }
            $items[] = [$meta['page'], intval($meta['updated_at'])];
        }
    }
    usort($items, function ($a, $b) {
        return $b[1] <=> $a[1];
    });
    // 書き込みに失敗しても表示は続ける
    @file_put_contents($cacheFile, json_encode($items, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $items;
}
