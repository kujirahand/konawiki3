<?php
require_once __DIR__ . '/test_common.inc.php';

// issue #314: ページ一覧 (ツリービュー) のテスト
global $kona3conf;
$kona3conf['page'] = 'FrontPage';
$kona3conf['FrontPage'] = 'FrontPage';
$kona3conf['static_mode'] = FALSE;

// --- 設定項目 ---
$flat_items = kona3conf_getFlatConfigItems();
test_assert(__LINE__, isset($flat_items['show_page_list']), "pagelist: 設定項目 show_page_list がある");
test_eq(__LINE__, $flat_items['show_page_list']['type'], 'bool', "pagelist: show_page_list は bool");
test_eq(__LINE__, $flat_items['show_page_list']['default'], FALSE, "pagelist: show_page_list の既定は FALSE");
foreach (['ja', 'en'] as $lc) {
    $kona3conf['lang'] = $lc;
    $texts = kona3conf_loadConfigTexts();
    test_assert(__LINE__, !empty($texts['items']['show_page_list']['desc']), "pagelist: 設定説明 ($lc) がある");
}

// --- kona3getAllPages(): サンドボックスにテスト用ページを作る ---
$base = KONA3_DIR_DATA . '/__pagelist_test';
@mkdir($base . '/Guide/Sub', 0777, TRUE);
file_put_contents($base . '/Top.txt', 'top');
file_put_contents($base . '/Guide/Install.md', 'install');
file_put_contents($base . '/Guide/Usage.txt', 'usage');
file_put_contents($base . '/Guide/Sub/Deep.txt', 'deep');
file_put_contents($base . '/Dup.txt', 'dup txt');
file_put_contents($base . '/Dup.md', 'dup md');
file_put_contents($base . '/MenuBar.txt', 'system page');
file_put_contents($base . '/.hidden.txt', 'hidden');
file_put_contents($base . '/image.png', 'not a page');

$all = kona3getAllPages(FALSE, TRUE); // キャッシュに左右されず実走査する
$mine = array_values(array_filter($all, function ($n) {
    return strpos($n, '__pagelist_test') === 0;
}));
sort($mine, SORT_STRING);
$expected = [
    '__pagelist_test/Dup',
    '__pagelist_test/Guide/Install',
    '__pagelist_test/Guide/Sub/Deep',
    '__pagelist_test/Guide/Usage',
    '__pagelist_test/Top',
];
test_eq(__LINE__, $mine, $expected, "pagelist: getAllPages は .txt/.md を再帰列挙し重複・システム・隠し・非ページを除く");
test_eq(__LINE__, $all, array_values(array_unique($all)), "pagelist: getAllPages に重複がない");

$with_system = kona3getAllPages(TRUE, TRUE);
test_assert(__LINE__, in_array('__pagelist_test/MenuBar', $with_system, TRUE), "pagelist: includeSystem=TRUE で MenuBar も含む");

// 後片付け
foreach (['Top.txt', 'Dup.txt', 'Dup.md', 'MenuBar.txt', '.hidden.txt', 'image.png'] as $f) {
    @unlink($base . '/' . $f);
}
@unlink($base . '/Guide/Install.md');
@unlink($base . '/Guide/Usage.txt');
@unlink($base . '/Guide/Sub/Deep.txt');
@rmdir($base . '/Guide/Sub');
@rmdir($base . '/Guide');
@rmdir($base);
// 削除したページ名がキャッシュに残らないよう再走査してキャッシュを実状態に合わせる
kona3getAllPages(FALSE, TRUE);
kona3getAllPages(TRUE, TRUE);

// --- kona3buildPageTree() ---
$tree = kona3buildPageTree(['B/Page', 'A', 'B/Apple', 'Top', 'B']);
test_eq(__LINE__, array_keys($tree), ['B', 'A', 'Top'], "pagelist: ツリーはフォルダ優先・名前順");
test_eq(__LINE__, $tree['B']['is_page'], TRUE, "pagelist: フォルダと同名のページは is_page");
test_eq(__LINE__, $tree['A']['is_page'], TRUE, "pagelist: 葉ページは is_page");
test_eq(__LINE__, array_keys($tree['B']['children']), ['Apple', 'Page'], "pagelist: 子ノードも名前順");
test_eq(__LINE__, $tree['B']['children']['Page']['path'], 'B/Page', "pagelist: ノードの path はページ名");

// --- kona3renderPageTree() ---
$html = kona3renderPageTree(kona3buildPageTree(['Guide/Install', 'Guide', 'Top']));
test_assert(__LINE__, strpos($html, '<details>') !== FALSE, "pagelist: フォルダは details で折りたたみ可能");
test_assert(__LINE__, strpos($html, 'Guide/Install') !== FALSE || strpos($html, 'Install') !== FALSE, "pagelist: 子ページが描画される");
$install_url = htmlspecialchars(kona3getPageURL('Guide/Install'), ENT_QUOTES, 'UTF-8');
test_assert(__LINE__, strpos($html, 'href="' . $install_url . '"') !== FALSE, "pagelist: ページへのリンクがある");
test_assert(__LINE__, strpos($html, 'data-path="Guide/Install"') !== FALSE, "pagelist: 絞り込み用 data-path がある");

$evil = kona3renderPageTree(kona3buildPageTree(['Evil/<b>Bold</b>']));
test_assert(__LINE__, strpos($evil, '<b>Bold</b>') === FALSE, "pagelist: ページ名はエスケープされる (XSS)");
test_assert(__LINE__, strpos($evil, '&lt;b&gt;') !== FALSE, "pagelist: エスケープ済みの名前が描画される");

// フォルダ自体がページの場合、名前は開閉専用でリンクは分離される (agyレビュー指摘1)
$folder_html = kona3renderPageTree(kona3buildPageTree(['Guide', 'Guide/Install']));
test_assert(__LINE__, strpos($folder_html, 'class="pt-open"') !== FALSE, "pagelist: フォルダ=ページでは分離した開くリンクがある");
test_assert(__LINE__, strpos($folder_html, '>Guide</a>') === FALSE, "pagelist: フォルダ名自体はリンクにしない (開閉と競合させない)");
test_assert(__LINE__, strpos($folder_html, '>🔗</a>') !== FALSE, "pagelist: 開くリンクはアイコンで表示される");

// 境界値: 空の一覧、日本語ページ名、数字だけのページ名 (agyレビュー指摘3)
test_eq(__LINE__, kona3buildPageTree([]), [], "pagelist: 空の一覧からは空のツリー");
$empty_html = kona3renderPageTree([]);
test_assert(__LINE__, strpos($empty_html, '<li') === FALSE, "pagelist: 空のツリーは項目なしで描画される");
$edge = kona3buildPageTree(['0', '日本語/ページ', '日本語']);
test_assert(__LINE__, isset($edge['0']), "pagelist: 数字だけのページ名 '0' もツリーに入る");
test_eq(__LINE__, $edge['日本語']['is_page'], TRUE, "pagelist: 日本語フォルダのページ判定");
test_eq(__LINE__, $edge['日本語']['children']['ページ']['path'], '日本語/ページ', "pagelist: 日本語ページのパス");
$edge_html = kona3renderPageTree($edge);
test_assert(__LINE__, strpos($edge_html, '日本語') !== FALSE, "pagelist: 日本語ページ名が描画される");

// --- ハンバーガーメニュー (kona3getCtrlMenuArray) ---
@session_start();
if (function_exists('kona3logout')) {
    kona3logout();
}
$has_pagelist = function ($menu) {
    foreach ($menu as $it) {
        if (strpos($it[1], 'pagelist') !== FALSE) {
            return TRUE;
        }
    }
    return FALSE;
};
$kona3conf['show_page_list'] = FALSE;
test_eq(__LINE__, $has_pagelist(kona3getCtrlMenuArray('list')), FALSE, "pagelist: 設定OFFでは未ログインのメニューに出ない");
$kona3conf['show_page_list'] = TRUE;
test_eq(__LINE__, $has_pagelist(kona3getCtrlMenuArray('list')), TRUE, "pagelist: 設定ONで未ログインのメニューに出る");

kona3login('test@example.com', 'test@example.com', 'normal', 1);
$kona3conf['show_page_list'] = FALSE;
test_eq(__LINE__, $has_pagelist(kona3getCtrlMenuArray('list')), FALSE, "pagelist: 設定OFFではログイン後メニューに出ない");
$kona3conf['show_page_list'] = TRUE;
test_eq(__LINE__, $has_pagelist(kona3getCtrlMenuArray('list')), TRUE, "pagelist: 設定ONでログイン後メニューに出る");
kona3logout();
$kona3conf['show_page_list'] = FALSE;

// --- アクションとテンプレートの存在 ---
$action_file = KONA3_DIR_ENGINE . '/action/pagelist.inc.php';
test_assert(__LINE__, file_exists($action_file), "pagelist: アクションファイルがある");
$action_src = file_get_contents($action_file);
test_assert(__LINE__, strpos($action_src, 'show_page_list') !== FALSE, "pagelist: アクションは設定を確認する");
test_assert(__LINE__, strpos($action_src, 'wiki_private') !== FALSE, "pagelist: アクションは非公開Wikiを確認する");
$tpl_file = KONA3_DIR_ENGINE . '/template/pagelist.html';
test_assert(__LINE__, file_exists($tpl_file), "pagelist: テンプレートがある");
$tpl_src = file_get_contents($tpl_file);
test_assert(__LINE__, strpos($tpl_src, 'kona3-pagetree') !== FALSE, "pagelist: テンプレートにツリーがある");
test_assert(__LINE__, strpos($tpl_src, 'pagelist_filter') !== FALSE, "pagelist: テンプレートに絞り込み入力がある");

// --- 静的モードでは pagelist アクションへの直接アクセスを拒否する ---
require_once $action_file;
$kona3conf['static_mode'] = TRUE;
$kona3conf['show_page_list'] = TRUE;
$static_blocked = FALSE;
try {
    kona3_action_pagelist();
} catch (Kona3StaticException $e) {
    $static_blocked = TRUE;
}
$kona3conf['static_mode'] = FALSE;
$kona3conf['show_page_list'] = FALSE;
test_assert(__LINE__, $static_blocked, "pagelist: 静的モードではアクションが Kona3StaticException で拒否される");

// --- kona3getAllPages() は同一リクエスト内でメモ化される ---
$memo_first = kona3getAllPages(FALSE, TRUE); // 基準は実走査で取得する
$memo_file = KONA3_DIR_DATA . '/__pagelist_memo.txt';
file_put_contents($memo_file, 'memo');
$memo_second = kona3getAllPages();
test_eq(__LINE__, $memo_second, $memo_first, "pagelist: getAllPages は同一リクエスト内で再走査しない");
test_assert(__LINE__, !in_array('__pagelist_memo', $memo_second, TRUE), "pagelist: メモ化後に作ったページは結果に含まれない");
// $forceReload を渡すと再走査され、新規ページも結果に含まれる
$memo_reloaded = kona3getAllPages(FALSE, TRUE);
test_assert(__LINE__, in_array('__pagelist_memo', $memo_reloaded, TRUE), "pagelist: forceReload で再走査される");
@unlink($memo_file);
kona3getAllPages(FALSE, TRUE); // 後続テストへ削除済みページ名を残さない

echo "\npagelist tests completed.\n";
