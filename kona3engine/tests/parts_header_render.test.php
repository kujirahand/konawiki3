<?php
require_once __DIR__ . '/test_common.inc.php';

// parts_header.html の実描画テスト
// ドロワー内の {{e:}} ブロックが $kona3conf を直接参照すると、
// テンプレートエンジンの extract スコープに $kona3conf は存在しないため
// "Undefined variable $kona3conf" 警告になり、ページ一覧ダイアログも働かない。
// (PR #333 で混入した問題の回帰テスト)

global $kona3conf;
$backup = $kona3conf; // prepare が書き込む分も含め、丸ごと復元する
$kona3conf['page'] = 'FrontPage';
$kona3conf['show_page_list'] = true; // ページ一覧ダイアログを有効化

$warnings = [];
set_error_handler(function ($errno, $errstr) use (&$warnings) {
    $warnings[] = $errstr;
    return true;
});
try {
    ob_start();
    kona3template('parts_header.html', ['page_title' => 'FrontPage']);
    $out = ob_get_clean();
} finally {
    restore_error_handler();
    $kona3conf = $backup;
}

$bad_warnings = array_filter($warnings, function ($w) {
    return strpos($w, 'kona3conf') !== false
        || strpos($w, 'array offset on null') !== false;
});
test_assert(__LINE__, count($bad_warnings) === 0,
    'parts_header 描画で $kona3conf 系の警告が出ない: ' . implode(' / ', $bad_warnings));
test_assert(__LINE__, strpos($out, 'drawer_wrapper') !== false, 'ドロワーが描画される');
test_assert(__LINE__, strpos($out, 'id="pagelist_open"') !== false,
    'ページ一覧がダイアログ用リンク (pagelist_open) になる');
