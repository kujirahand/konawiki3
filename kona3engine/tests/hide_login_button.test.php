<?php
require_once __DIR__ . '/test_common.inc.php';

// issue #332: ログインボタンを設定で隠せる
global $kona3conf;
$kona3conf['page'] = 'FrontPage';
$kona3conf['FrontPage'] = 'FrontPage';
$kona3conf['static_mode'] = FALSE;

// --- 設定項目 ---
$flat_items = kona3conf_getFlatConfigItems();
test_assert(__LINE__, isset($flat_items['hide_login_button']), "hide_login: 設定項目 hide_login_button がある");
test_eq(__LINE__, $flat_items['hide_login_button']['type'], 'bool', "hide_login: bool");
test_eq(__LINE__, $flat_items['hide_login_button']['default'], FALSE, "hide_login: 既定は FALSE");
foreach (['ja', 'en'] as $lc) {
    $kona3conf['lang'] = $lc;
    $texts = kona3conf_loadConfigTexts();
    test_assert(__LINE__, !empty($texts['items']['hide_login_button']['desc']), "hide_login: 設定説明 ($lc) がある");
    if ($lc == 'ja') {
        test_eq(__LINE__, $texts['items']['hide_login_button']['label'], 'ログインボタンを隠す', "hide_login: ja の label");
    }
}

$has_href = function ($menu, $word) {
    foreach ($menu as $it) {
        if (strpos($it[1], $word) !== FALSE) {
            return TRUE;
        }
    }
    return FALSE;
};

@session_start();
if (function_exists('kona3logout')) {
    kona3logout();
}

// --- 未ログイン: 既定 / FALSE では表示される ---
unset($kona3conf['hide_login_button']);
foreach (['bar', 'list'] as $type) {
    test_eq(__LINE__, $has_href(kona3getCtrlMenuArray($type), 'login'), TRUE, "hide_login: 未設定なら Login が出る ($type)");
}
$kona3conf['hide_login_button'] = FALSE;
foreach (['bar', 'list'] as $type) {
    test_eq(__LINE__, $has_href(kona3getCtrlMenuArray($type), 'login'), TRUE, "hide_login: FALSE なら Login が出る ($type)");
}

// --- 未ログイン: TRUE では隠れるが他は残る ---
$kona3conf['hide_login_button'] = TRUE;
foreach (['bar', 'list'] as $type) {
    $menu = kona3getCtrlMenuArray($type);
    test_eq(__LINE__, $has_href($menu, 'login'), FALSE, "hide_login: TRUE なら Login が出ない ($type)");
    test_eq(__LINE__, $has_href($menu, 'search'), TRUE, "hide_login: Search は残る ($type)");
}

// --- ログイン時: TRUE でも影響しない ---
kona3login('test@example.com', 'test@example.com', 'normal', 1);
$menu = kona3getCtrlMenuArray('list');
test_eq(__LINE__, $has_href($menu, 'logout'), TRUE, "hide_login: ログイン時は Logout が残る");
test_eq(__LINE__, $has_href($menu, 'user'), TRUE, "hide_login: ログイン時はマイページが残る");

// --- コメントプラグインのログイン案内も隠れる ---
require_once __DIR__ . '/../plugins/comment.inc.php';
if (empty($kona3conf['url.index'])) {
    $kona3conf['url.index'] = 'index.php';
}
$kona3conf['hide_login_button'] = FALSE;
$comment_html = _renderCommentLoginRequired('FrontPage');
test_assert(__LINE__, strpos($comment_html, 'comment_login_required') !== FALSE, "hide_login: 設定OFFではコメント欄にログイン案内が出る");
$kona3conf['hide_login_button'] = TRUE;
test_eq(__LINE__, _renderCommentLoginRequired('FrontPage'), '', "hide_login: 設定ONではコメント欄のログイン案内が出ない");

// 後片付け
kona3logout();
$kona3conf['hide_login_button'] = FALSE;

echo "\nhide_login_button tests completed.\n";
