<?php
require_once __DIR__ . '/test_common.inc.php';

// サイトシェル (#340 方向2): マストヘッド・タブナビ・フッターのサイト情報行

// ---------------------------------------------------------------
// 5.1 関数レベル: kona3navParseHref / kona3extractNavLinks
// ---------------------------------------------------------------
$nav_pages = function ($links) {
    return array_map(function ($l) { return $l['page']; }, $links);
};
$nav_labels = function ($links) {
    return array_map(function ($l) { return $l['label']; }, $links);
};

// 1. 空文字列
test_eq(__LINE__, kona3extractNavLinks(''), [], '空文字列は []');

// 2. 基本の抽出
$links = kona3extractNavLinks('<ul><li><a href="index.php?Docs&amp;show">Docs</a></li><li><a href="index.php?Blog">Blog</a></li></ul>');
test_eq(__LINE__, count($links), 2, '2 件抽出される');
test_eq(__LINE__, $nav_pages($links), ['Docs', 'Blog'], 'page が Docs と Blog');
test_eq(__LINE__, $links[0]['external'], false, 'Docs は内部リンク');
test_eq(__LINE__, $links[1]['external'], false, 'Blog は内部リンク');
test_eq(__LINE__, $links[0]['href'], 'index.php?Docs&show', 'href はエンティティをデコードした値');

// 3. エンコード済みと未エンコードのページ名
$links = kona3extractNavLinks('<a href="index.php?%E6%97%A5%E6%9C%AC&amp;show">A</a><a href="index.php?日本&show">B</a><a href="index.php?A%2FB">C</a>');
test_eq(__LINE__, $nav_pages($links), ['日本', 'A/B'], 'エンコード済み/未エンコードは同じページ名になり、タブは1件にまとまる');

// 4. 外部リンク
$links = kona3extractNavLinks('<a href="https://example.com/">Ex</a><a href="//example.com/">Rel</a>');
test_eq(__LINE__, count($links), 2, '外部リンク 2 件');
foreach ($links as $l) {
    test_eq(__LINE__, $l['external'], true, "external: {$l['href']}");
    test_eq(__LINE__, $l['page'], null, "page は null: {$l['href']}");
}

// 5. 危険なスキームやフラグメントだけのリンクは除外
$links = kona3extractNavLinks(
    '<a href="javascript:alert(1)">a</a><a href="JavaScript:alert(1)">b</a>' .
    '<a href="mailto:x@y">c</a><a href="data:text/html,x">d</a>' .
    '<a href="#top">e</a><a href="">f</a>'
);
test_eq(__LINE__, $links, [], 'javascript:/mailto:/data:/#/空 は除外');
test_eq(__LINE__, kona3navParseHref('tel:123'), null, 'tel: は除外');

// 6. システムアクションへのリンクは除外
$links = kona3extractNavLinks('<a href="index.php?GlobalBar&amp;edit">e</a><a href="index.php?Foo&search">s</a>');
test_eq(__LINE__, $links, [], 'edit / search へのリンクは除外');

// 7. ラベルが空のリンク
$links = kona3extractNavLinks('<a href="index.php?Docs&show"><img src="x.png"></a>');
test_eq(__LINE__, $nav_labels($links), ['Docs'], '内部リンクで空ラベルならページ名をラベルにする');
$links = kona3extractNavLinks('<a href="https://example.com/"><img src="x.png"></a>');
test_eq(__LINE__, $links, [], '外部リンクで空ラベルなら除外');

// 8. ラベルの正規化
$links = kona3extractNavLinks("<a href=\"index.php?Foo\"><b>Foo</b>&amp; Bar\n  Baz</a>");
test_eq(__LINE__, $nav_labels($links), ['Foo& Bar Baz'], 'タグ除去・デコード・空白の圧縮');
$links = kona3extractNavLinks("<a href=\"index.php?Foo\">　Foo　 Bar　</a>");
test_eq(__LINE__, $nav_labels($links), ['Foo Bar'], '全角スペースも圧縮される');

// 9. 重複
$links = kona3extractNavLinks('<a href="index.php?Docs">A</a><a href="index.php?Docs">B</a><a href="index.php?Docs#x">C</a>');
test_eq(__LINE__, $nav_labels($links), ['A'], '同じ href (フラグメント除く) は最初の 1 件だけ');

// 10. 属性の書き方の違い
$links = kona3extractNavLinks(
    "<a href='index.php?P1'>1</a><a href=index.php?P2>2</a>" .
    '<a class="x" href="index.php?P3">3</a><A HREF="index.php?P4">4</A><a name="noref">5</a>'
);
test_eq(__LINE__, $nav_pages($links), ['P1', 'P2', 'P3', 'P4'], '単一引用符・引用符なし・class 先行・大文字で抽出でき、href なしはスキップ');

// 11. 入れ子のリスト
$links = kona3extractNavLinks('<ul><li><a href="index.php?A">A</a><ul><li><a href="index.php?A/B">AB</a></li></ul></li><li><a href="index.php?C">C</a></li></ul>');
test_eq(__LINE__, $nav_pages($links), ['A', 'A/B', 'C'], '入れ子のリストは文書順に平坦化');

// 12. その他の相対 URL
$links = kona3extractNavLinks('<a href="foo.html">Foo</a>');
test_eq(__LINE__, count($links), 1, 'foo.html は残る');
test_assert(__LINE__, array_key_exists('page', $links[0]) && $links[0]['page'] === null, 'foo.html の page は null');
test_eq(__LINE__, $links[0]['external'] ?? null, false, 'foo.html は内部扱い');

// 12b. Copilot レビュー対応: 制御文字でスキーム判定をすり抜ける href は除外する
test_eq(__LINE__, kona3extractNavLinks('<a href="java&#9;script:alert(1)">x</a>'), [], 'タブ入りの javascript: は除外');
test_eq(__LINE__, kona3extractNavLinks("<a href=\"java\nscript:alert(1)\">x</a>"), [], '改行入りの javascript: は除外');
test_eq(__LINE__, kona3extractNavLinks("<a href=\"\x01javascript:alert(1)\">x</a>"), [], '制御文字始まりの javascript: は除外');
test_eq(__LINE__, kona3extractNavLinks('<a href="&#x6A;avascript:alert(1)">x</a>'), [], 'エンティティ経由の javascript: は除外');
test_eq(__LINE__, kona3extractNavLinks('<a href="vbscript:msgbox(1)">x</a>'), [], 'vbscript: は除外');
test_eq(__LINE__, kona3extractNavLinks('<a href="1foo:bar">x</a>'), [], 'スキーム風の相対 URL は除外');
// data-href を href と誤認しない
$links = kona3extractNavLinks('<a data-href="index.php?Fake" href="index.php?Real">x</a>');
test_eq(__LINE__, $nav_pages($links), ['Real'], 'data-href は拾わない');
// 末尾の & (空の第2セグメント) は show 扱い
$links = kona3extractNavLinks('<a href="index.php?Docs&amp;">x</a>');
test_eq(__LINE__, $nav_pages($links), ['Docs'], '?Docs& は Docs として扱う');
// ?Docs と ?Docs&show は同じページとして重複排除
$links = kona3extractNavLinks('<a href="index.php?Docs">a</a><a href="index.php?Docs&amp;show">b</a>');
test_eq(__LINE__, count($links), 1, '?Docs と ?Docs&show は 1 件にまとまる');
// url.index が index.php 以外でもページ名を取る
global $kona3conf;
$saved_index = $kona3conf['url.index'] ?? null;
$kona3conf['url.index'] = 'wiki.php';
$links = kona3extractNavLinks('<a href="wiki.php?Docs&amp;show">x</a>');
test_eq(__LINE__, $nav_pages($links), ['Docs'], 'url.index=wiki.php でもページ名を取る');
if ($saved_index === null) { unset($kona3conf['url.index']); } else { $kona3conf['url.index'] = $saved_index; }

// ---------------------------------------------------------------
// 5.1 関数レベル: kona3markActiveNavLinks
// ---------------------------------------------------------------
$active_labels = function ($links) {
    $r = [];
    foreach ($links as $l) {
        if ($l['active']) $r[] = $l['label'];
    }
    return $r;
};
$tabs = kona3extractNavLinks(
    '<a href="index.php?FrontPage">FrontPage</a><a href="index.php?Docs">Docs</a>' .
    '<a href="index.php?Blog">Blog</a><a href="https://example.com/Docs">Ext</a>'
);
// 13-15, 17
test_eq(__LINE__, $active_labels(kona3markActiveNavLinks($tabs, 'Docs')), ['Docs'], 'Docs で Docs だけ active');
test_eq(__LINE__, $active_labels(kona3markActiveNavLinks($tabs, 'Docs/API')), ['Docs'], 'Docs/API で Docs が active');
test_eq(__LINE__, $active_labels(kona3markActiveNavLinks($tabs, 'DocsX')), [], 'DocsX で Docs は active にならない');
test_eq(__LINE__, $active_labels(kona3markActiveNavLinks($tabs, 'docs')), [], '大文字小文字は区別する');
$marked = kona3markActiveNavLinks($tabs, 'Docs');
test_eq(__LINE__, $marked[3]['active'], false, '外部リンクは active にならない');
// 16. より深い一致を優先
$tabs2 = kona3extractNavLinks('<a href="index.php?Docs">Docs</a><a href="index.php?Docs/API">API</a>');
test_eq(__LINE__, $active_labels(kona3markActiveNavLinks($tabs2, 'Docs/API/v1')), ['API'], 'Docs/API/v1 で Docs/API だけ active');
// 18. FrontPage
test_eq(__LINE__, $active_labels(kona3markActiveNavLinks($tabs, 'Other')), [], 'Other で FrontPage は active にならない');
test_eq(__LINE__, $active_labels(kona3markActiveNavLinks($tabs, 'FrontPage')), ['FrontPage'], 'FrontPage で FrontPage が active');
// 19. page が null のリンクは URL で比較する
$null_tab = [['label' => 'Docs', 'href' => kona3getPageURL('Docs'), 'page' => null, 'external' => false]];
test_eq(__LINE__, $active_labels(kona3markActiveNavLinks($null_tab, 'Docs')), ['Docs'], 'page が null でも URL 一致で active');
test_eq(__LINE__, $active_labels(kona3markActiveNavLinks($null_tab, 'Blog')), [], 'page が null で URL 不一致なら active にならない');
// 19b. 静的モードの URL でも active 判定できる
global $kona3conf;
$saved_static = $kona3conf['static_mode'] ?? null;
$kona3conf['static_mode'] = true;
$static_tab = [['label' => 'Docs', 'href' => kona3getPageURL('Docs'), 'page' => null, 'external' => false]];
test_eq(__LINE__, $active_labels(kona3markActiveNavLinks($static_tab, 'Docs')), ['Docs'], '静的URLでも一致すれば active');
if ($saved_static === null) { unset($kona3conf['static_mode']); } else { $kona3conf['static_mode'] = $saved_static; }

// ---------------------------------------------------------------
// 描画・GlobalBar 系の準備
// ---------------------------------------------------------------
global $kona3conf;
$backup = $kona3conf; // prepare が書き込む分も含め、丸ごと復元する
$kona3conf['def_text_ext'] = 'txt'; // GlobalBar.txt (KonaNotation) で検証する
$globalbar_file = koan3getWikiFileText('GlobalBar');
$kona3conf = $backup;
$globalbar_backup = file_exists($globalbar_file) ? file_get_contents($globalbar_file) : null;

$warnings = [];
$set_handler = function () use (&$warnings) {
    set_error_handler(function ($errno, $errstr, $errfile = '', $errline = 0) use (&$warnings) {
        $warnings[] = "$errstr ($errfile:$errline)";
        return true;
    }, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING | E_DEPRECATED);
};

// $page を現在ページにして header と footer を描画する
$render = function ($page, $opts = []) use (&$backup, $set_handler) {
    global $kona3conf;
    $kona3conf = $backup;
    $kona3conf['def_text_ext'] = 'txt';
    $kona3conf['page'] = $page;
    $kona3conf['static_mode'] = !empty($opts['static_mode']);
    if (isset($opts['wiki_title'])) {
        $kona3conf['wiki_title'] = $opts['wiki_title'];
    }
    $res = ['header' => '', 'footer' => '', 'front_url' => '', 'search_url' => ''];
    // フッターの kona3getShortcutLink() が $_GET['page'] を参照する (実リクエスト相当にする)
    $get_page_backup = $_GET['page'] ?? null;
    $_GET['page'] = $page;
    $set_handler();
    try {
        kona3getSiteNavLinks(true);
        $params = ['page_title' => $page, 'page_title_a' => htmlspecialchars($page), 'robots' => ''];
        ob_start();
        kona3template('parts_header.html', $params);
        $res['header'] = ob_get_clean();
        ob_start();
        kona3template('parts_footer.html', $params);
        $res['footer'] = ob_get_clean();
        $res['front_url'] = $kona3conf['FrontPage_url'];
        $res['search_url'] = kona3getPageURL($page, 'search');
    } finally {
        restore_error_handler();
        $kona3conf = $backup;
        if ($get_page_backup === null) {
            unset($_GET['page']);
        } else {
            $_GET['page'] = $get_page_backup;
        }
    }
    return $res;
};

try {
    // ---------------------------------------------------------------
    // GlobalBar が無い場合
    // ---------------------------------------------------------------
    @unlink($globalbar_file);
    $kona3conf['def_text_ext'] = 'txt';
    test_eq(__LINE__, kona3getSiteNavLinks(true), [], 'GlobalBar が無ければリンクは 0 件');
    // 20. 0 件なら空文字列
    test_eq(__LINE__, kona3getSiteNavHTML('tabs', 'Docs'), '', '0 件なら tabs は空');
    test_eq(__LINE__, kona3getSiteNavHTML('footer', 'Docs'), '', '0 件なら footer は空');
    $kona3conf = $backup;

    // 27, 29. GlobalBar なしの描画
    $r = $render('Docs');
    test_assert(__LINE__, strpos($r['header'], 'kona3_sitenav') === false, 'GlobalBar が無ければタブ列は出力されない');
    test_assert(__LINE__, strpos($r['header'], 'id="kona3_masthead"') !== false, 'GlobalBar が無くてもマストヘッドは出る');
    test_assert(__LINE__, strpos($r['header'], 'id="kona3_header_title"') !== false, 'GlobalBar が無くてもパンくずは出る');
    test_assert(__LINE__, strpos($r['footer'], 'class="kona3-footer-site"') !== false, 'GlobalBar が無くてもフッターのサイト行は出る');
    test_assert(__LINE__, strpos($r['footer'], 'class="kona3-footer-sitename"') !== false, 'GlobalBar が無くてもサイト名は出る');
    test_assert(__LINE__, strpos($r['footer'], 'kona3-footer-nav') === false, 'GlobalBar が無ければ kona3-footer-nav は出ない');

    // ---------------------------------------------------------------
    // GlobalBar がある場合
    // ---------------------------------------------------------------
    @mkdir(dirname($globalbar_file), 0777, true);
    file_put_contents($globalbar_file,
        "- [[FrontPage]]\n" .
        "- [[Docs]]\n" .
        "- [[Blog]]\n" .
        "- [[Example:https://example.com/]]\n"
    );
    $kona3conf['def_text_ext'] = 'txt';
    $links = kona3getSiteNavLinks(true);
    test_eq(__LINE__, $nav_labels($links), ['FrontPage', 'Docs', 'Blog', 'Example'], 'GlobalBar からリンクが抽出される');

    // 22. aria-current は tabs に 1 回、footer に 0 回
    $kona3conf['def_text_ext'] = 'txt';
    $tabs_html = kona3getSiteNavHTML('tabs', 'Docs');
    $footer_html = kona3getSiteNavHTML('footer', 'Docs');
    test_eq(__LINE__, substr_count($tabs_html, 'aria-current="page"'), 1, 'tabs の aria-current はちょうど 1 回');
    test_eq(__LINE__, substr_count($footer_html, 'aria-current'), 0, 'footer には aria-current が無い');
    test_eq(__LINE__, substr_count($footer_html, 'is-active'), 0, 'footer には is-active が無い');
    test_assert(__LINE__, strpos($tabs_html, 'class="is-external"') !== false, '外部リンクに is-external が付く');
    test_eq(__LINE__, kona3getSiteNavHTML('unknown', 'Docs'), '', '未知の variant は空文字列');
    $kona3conf = $backup;

    // 21. ラベルと href のエスケープ
    $evil = kona3extractNavLinks('<a href="https://example.com/?q=&quot;x">&lt;script&gt;x&lt;/script&gt;</a>');
    test_eq(__LINE__, $nav_labels($evil), ['<script>x</script>'], 'ラベルは平文で保持される');
    test_eq(__LINE__, $evil[0]['href'] ?? '', 'https://example.com/?q="x', 'href はデコード済みで保持される');
    file_put_contents($globalbar_file, "- [[<script>x</script>:https://example.com/?q=\"x]]\n");
    $kona3conf['def_text_ext'] = 'txt';
    kona3getSiteNavLinks(true);
    $html = kona3getSiteNavHTML('tabs', 'Other') . kona3getSiteNavHTML('footer', 'Other');
    test_assert(__LINE__, $html !== '', 'エスケープ検証用のリンクが出力される');
    test_assert(__LINE__, strpos($html, '<script>') === false, 'ラベルの <script> はエスケープされる');
    test_assert(__LINE__, strpos($html, '&lt;script&gt;x&lt;/script&gt;') !== false, 'ラベルがエスケープ済みで出力される');
    test_assert(__LINE__, strpos($html, '?q="x') === false && strpos($html, 'href="https://example.com/?q=&quot;x"') !== false, 'href の " がエスケープされる');
    $kona3conf = $backup;

    // GlobalBar を描画用の内容に戻す
    file_put_contents($globalbar_file,
        "- [[FrontPage]]\n" .
        "- [[Docs]]\n" .
        "- [[Blog]]\n" .
        "- [[Example:https://example.com/]]\n"
    );

    // 23-25, 28. ヘッダー描画
    $r = $render('Docs');
    $h = $r['header'];
    test_assert(__LINE__, strpos($h, 'id="kona3_masthead"') !== false, 'マストヘッドが出力される');
    test_eq(__LINE__, preg_match('/<img class="kona3-brand-logo" src="[^"]*konawiki3-logo\.png[^"]*" width="512" height="512" alt="">/', $h), 1, 'ロゴ img が出力される');
    test_assert(__LINE__, strpos($h, '<a class="kona3-brand" href="' . htmlspecialchars($r['front_url'], ENT_QUOTES, 'UTF-8') . '">') !== false, 'brand の href が FrontPage_url');
    test_assert(__LINE__, strpos($h, 'id="kona3_sitenav"') !== false, 'タブ列が出力される');
    $p_nav = strpos($h, 'id="kona3_sitenav"');
    $p_nav_end = ($p_nav === false) ? false : strpos($h, '</nav>', $p_nav);
    $nav = ($p_nav_end === false) ? '' : substr($h, $p_nav, $p_nav_end - $p_nav);
    foreach (['FrontPage', 'Docs', 'Blog', 'Example'] as $label) {
        test_assert(__LINE__, strpos($nav, ">{$label}</a></li>") !== false, "タブ {$label} が出力される");
    }
    $p_mast = strpos($h, 'kona3_masthead');
    $p_nav = strpos($h, 'kona3_sitenav');
    $p_title = strpos($h, 'kona3_header_title');
    test_assert(__LINE__, $p_mast !== false && $p_nav !== false && $p_title !== false && $p_mast < $p_nav && $p_nav < $p_title,
        'マストヘッド → タブ列 → パンくず の順');
    test_assert(__LINE__, strpos($h, 'class="pagename"') !== false, '.pagename が残っている');
    test_eq(__LINE__, substr_count($h, 'aria-current="page"'), 1, 'aria-current は 1 回');
    test_eq(__LINE__, preg_match('/<li class="is-active"><a href="[^"]*" aria-current="page">Docs<\/a><\/li>/', $h), 1, '現在ページ Docs のタブに aria-current');
    // 28. ドロワーの GlobalBar は従来どおり
    $p_menu = strpos($h, 'class="menuitems"');
    $p_menu_end = ($p_menu === false) ? false : strpos($h, '</nav>', $p_menu);
    $menu = ($p_menu_end === false) ? '' : substr($h, $p_menu, $p_menu_end - $p_menu);
    foreach (['Docs', 'Blog', 'Example'] as $label) {
        test_assert(__LINE__, strpos($menu, ">{$label}</a>") !== false, "ドロワーに GlobalBar の {$label} が残る");
    }
    // 26. 検索リンク (static_mode = false)
    $search_href = htmlspecialchars($r['search_url'], ENT_QUOTES, 'UTF-8');
    test_assert(__LINE__, strpos($h, '<a class="kona3-masthead-search" href="' . $search_href . '">') !== false,
        '検索リンクの href がエスケープ済みの search URL');

    // 29. フッター
    $f = $r['footer'];
    test_assert(__LINE__, strpos($f, 'class="kona3-footer-site"') !== false, 'フッターのサイト行が出力される');
    test_assert(__LINE__, strpos($f, '<a class="kona3-footer-sitename" href="' . htmlspecialchars($r['front_url'], ENT_QUOTES, 'UTF-8') . '">') !== false, 'フッターのサイト名リンク');
    test_assert(__LINE__, strpos($f, 'class="kona3-footer-nav"') !== false, 'フッターにリンク群が出る');
    foreach (['FrontPage', 'Docs', 'Blog', 'Example'] as $label) {
        test_assert(__LINE__, strpos($f, ">{$label}</a></li>") !== false, "フッターに {$label} が出る");
    }
    test_eq(__LINE__, substr_count($f, 'class="footer_menu"'), 2, '既存の .footer_menu は 2 つとも残る');
    test_eq(__LINE__, substr_count($f, 'aria-current'), 0, 'フッターに aria-current は無い');
    $p_site = strpos($f, 'kona3-footer-site');
    $p_menu = strpos($f, 'footer_menu');
    test_assert(__LINE__, $p_site !== false && $p_menu !== false && $p_site < $p_menu, 'サイト行は .footer_menu の前');

    // 26. static_mode = true なら検索リンクを出さない
    $r = $render('Docs', ['static_mode' => true]);
    test_assert(__LINE__, strpos($r['header'], 'kona3-masthead-search') === false, 'static_mode では検索リンクを出さない');
    test_assert(__LINE__, strpos($r['header'], 'id="kona3_masthead"') !== false, 'static_mode でもマストヘッドは出る');

    // 30. wiki_title のエスケープ
    $r = $render('Docs', ['wiki_title' => 'W<b>&"']);
    test_eq(__LINE__, preg_match('/<span class="kona3-brand-name">([^<]*)<\/span>/', $r['header'], $m), 1, 'マストヘッドにサイト名がある');
    test_assert(__LINE__, isset($m[1]) && strpos($m[1], 'W&lt;b&gt;&amp;') === 0 && strpos($m[1], '"') === false, 'マストヘッドのサイト名がエスケープされる: ' . ($m[1] ?? ''));
    test_eq(__LINE__, preg_match('/<a class="kona3-footer-sitename" href="[^"]*">([^<]*)<\/a>/', $r['footer'], $m), 1, 'フッターにサイト名がある');
    test_assert(__LINE__, isset($m[1]) && strpos($m[1], 'W&lt;b&gt;&amp;') === 0 && strpos($m[1], '"') === false, 'フッターのサイト名がエスケープされる: ' . ($m[1] ?? ''));
    test_assert(__LINE__, strpos($r['header'] . $r['footer'], 'W<b>') === false, '生の <b> が出力されない');
} finally {
    // GlobalBar を必ず元に戻す (test_main は全テストを 1 プロセスで実行するため)
    if ($globalbar_backup === null) {
        @unlink($globalbar_file);
    } else {
        file_put_contents($globalbar_file, $globalbar_backup);
    }
    $kona3conf = $backup;
    kona3getSiteNavLinks(true); // static キャッシュも捨てる
}

// 31. 描画中の Notice / Warning は 0 件
test_eq(__LINE__, count($warnings), 0, '描画中に Notice/Warning が出ない: ' . implode(' / ', $warnings));

// ---------------------------------------------------------------
// 5.3 CSS の静的検査
// ---------------------------------------------------------------
$css = file_get_contents(dirname(__DIR__) . '/resource/kona3def.css');
test_assert(__LINE__, strpos($css, '#kona3_sitenav') !== false, 'kona3def.css に #kona3_sitenav');
test_assert(__LINE__, strpos($css, '.kona3-masthead-inner') !== false, 'kona3def.css に .kona3-masthead-inner');
test_assert(__LINE__, strpos($css, '.kona3-footer-site') !== false, 'kona3def.css に .kona3-footer-site');
$has_print_nav = false;
if (preg_match_all('/@media print\s*\{((?:[^{}]|\{[^{}]*\})*)\}/', $css, $pm)) {
    foreach ($pm[1] as $block) {
        if (strpos($block, '#kona3_sitenav') !== false) $has_print_nav = true;
    }
}
test_assert(__LINE__, $has_print_nav, '@media print で #kona3_sitenav を隠す');
