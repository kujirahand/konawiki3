<?php

/** 訪問カウンターを表示する
 * - [書式] #counter
 * - [引数] なし
 */

function kona3plugins_counter_execute($args)
{
    global $kona3conf;
    $page = $kona3conf['page'];
    $file = kona3getWikiFile($page);
    if (file_exists($file)) {
        $page_id = kona3db_getPageId($page, TRUE);
    } else {
        $page_id = kona3db_getPageId($page, FALSE);
    }
    if ($page_id == 0) {
        return "-";
    }

    // check table
    if (!db_table_exists('counter', 'subdb')) {
        return '<span style="color:red;">Counter table not found</span>';
    }

    // 同時アクセスでも数え落ちや一意制約エラーが起きないよう、
    // 行の作成はINSERT OR IGNORE、加算はSQL側(value=value+1)で行う
    // また、DBが混み合っていてもページ表示は続けられるようにする
    try {
        list($value, $mvalue) = kona3plugins_counter_countUp($page_id);
    } catch (Exception $e) {
        return "<div class='counter'>-</div>";
    }
    $m_this = lang('Monthly');
    $html = "$value" . "<span class='coutner_month'>({$m_this}{$mvalue})</span>";
    if (kona3isLogin()) {
        $url = kona3getPageURL($page, 'counter');
        $html = "<a href='{$url}'>{$html}</a>";
    }
    return
        "<div class='counter'>" .
        $html .
        "</div>";
}

// カウンターを1つ増やして [合計, 今月] を返す
function kona3plugins_counter_countUp($page_id)
{
    // === total counter ===
    subdb_exec(
        "INSERT OR IGNORE INTO counter " .
            "(page_id, value, mtime) VALUES (?,0,?)",
        [$page_id, time()]
    );
    subdb_exec(
        "UPDATE counter SET value=value+1, mtime=? " .
            "WHERE page_id=?",
        [time(), $page_id]
    );
    $r = subdb_get1(
        "SELECT value FROM counter WHERE page_id=?",
        [$page_id]
    );
    $value = isset($r['value']) ? intval($r['value']) : 1;
    // === monthly counter ===
    $year  = intval(date('Y'));
    $month = intval(date('n'));
    subdb_exec(
        "INSERT OR IGNORE INTO counter_month " .
            "(page_id, year, month, value, mtime) " .
            "VALUES(?,?,?,0,?)",
        [$page_id, $year, $month, time()]
    );
    subdb_exec(
        "UPDATE counter_month SET value=value+1, mtime=? " .
            "WHERE (page_id=?)AND(year=?)AND(month=?)",
        [time(), $page_id, $year, $month]
    );
    $r = subdb_get1(
        "SELECT value FROM counter_month " .
            "WHERE (page_id=?)AND(year=?)AND(month=?) LIMIT 1",
        [$page_id, $year, $month]
    );
    $mvalue = isset($r['value']) ? intval($r['value']) : 1;
    return [$value, $mvalue];
}
