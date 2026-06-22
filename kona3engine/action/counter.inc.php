<?php

require_once dirname(__DIR__) . '/kona3lib.inc.php';

function kona3_action_counter()
{
    global $kona3conf;
    
    // ログインチェック
    if (!kona3isLogin()) {
        kona3error(
            lang('Login Required'),
            lang('You must login to view counter history.')
        );
        exit;
    }

    $page = $kona3conf['page'];
    $page_id = kona3db_getPageId($page, FALSE);
    
    if ($page_id == 0) {
        kona3error(
            lang('Error'),
            lang('Page not found.')
        );
        exit;
    }

    // 累計アクセス数
    $total = 0;
    $r = subdb_get1("SELECT * FROM counter WHERE page_id=?", [$page_id]);
    if ($r) {
        $total = $r['value'];
    }

    // 月ごとのアクセス数
    $list = subdb_get(
        "SELECT * FROM counter_month WHERE page_id=? ORDER BY year DESC, month DESC",
        [$page_id]
    );

    // テンプレートの呼び出し
    kona3template('counter.html', [
        'page' => $page,
        'total' => $total,
        'list' => $list,
    ]);
}
