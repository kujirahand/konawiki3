<?php

/** KonaWiki3 pagelist (#314): ページ一覧をツリービューで表示する
 *
 * - 設定 `show_page_list` が有効なときだけ表示できる
 * - 非公開Wiki (wiki_private) ではログインが必要 (show と同じ扱い)
 * - ハンバーガーメニューへの項目追加は kona3getCtrlMenuArray() 側で行う
 */

function kona3_action_pagelist()
{
    global $kona3conf;
    $page = $kona3conf["page"];

    // 静的HTML出力モードでは一覧を提供しない (直接アクセスも拒否)
    if (!empty($kona3conf['static_mode'])) {
        kona3error(lang('Page List'), lang('Page List is not available in static mode.'));
        return;
    }
    // 設定で許可されていない場合は表示しない
    if (!kona3getConf('show_page_list')) {
        kona3error(lang('Page List'), lang('Page List is disabled.'));
        return;
    }
    // 非公開Wikiではログインが必要
    if (kona3getConf('wiki_private') && !kona3isLogin()) {
        header('HTTP/1.0 403 Forbidden');
        $url = kona3getPageURL($page, "login");
        $msg_private = lang('Private Mode');
        $msg_please_login = lang('Please login.');
        kona3error(
            $page,
            "<div>$msg_private</div><div>&nbsp;</div>" .
                "<div><a href='$url'>$msg_please_login</a></div>"
        );
        return;
    }

    $pages = kona3getAllPages();
    $tree_html = kona3renderPageTree(kona3buildPageTree($pages));
    $kona3conf["robots"] = "noindex";
    kona3template('pagelist.html', array(
        "page_title" => $page,
        "title" => lang('Page List'),
        "tree_html" => $tree_html,
        "page_count" => count($pages),
    ));
}
