<?php
require_once __DIR__ . '/test_common.inc.php';

// parts_header.html の実描画テスト
// ドロワー内の {{e:}} ブロックが $kona3conf を直接参照すると、
// テンプレートエンジンの extract スコープに $kona3conf は存在しないため
// "Undefined variable $kona3conf" 警告になり、ページ一覧ダイアログも働かない。
// (PR #333 で混入した問題の回帰テスト)

global $kona3conf;
$saved_page = $kona3conf['page'] ?? null;
$saved_show = $kona3conf['show_page_list'] ?? null;
$kona3conf['page'] = 'FrontPage';
$kona3conf['show_page_list'] = true; // ページ一覧ダイアログを有効化

$warnings = [];
set_error_handler(function ($errno, $errstr) use (&$warnings) {
    $warnings[] = $errstr;
    return true;
});
ob_start();
kona3template('parts_header.html', ['page_title' => 'FrontPage']);
$out = ob_get_clean();
restore_error_handler();

$kona3conf['page'] = $saved_page;
$kona3conf['show_page_list'] = $saved_show;

$kona3conf_warnings = array_filter($warnings, function ($w) {
    return strpos($w, 'kona3conf') !== false;
});
test_assert(__LINE__, count($kona3conf_warnings) === 0,
    'parts_header 描画で $kona3conf の警告が出ない: ' . implode(' / ', $kona3conf_warnings));
test_assert(__LINE__, strpos($out, 'drawer_wrapper') !== false, 'ドロワーが描画される');
test_assert(__LINE__, strpos($out, 'id="pagelist_open"') !== false,
    'ページ一覧がダイアログ用リンク (pagelist_open) になる');
