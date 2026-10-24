<?php
require_once __DIR__ . '/test_common.inc.php';

// issue #274: ログイン後のメニューはユーザー名ではなく「マイページ」と表示する
global $kona3conf;
$kona3conf['page'] = 'FrontPage';
$kona3conf['FrontPage'] = 'FrontPage';
$kona3conf['static_mode'] = FALSE;
@session_start();
kona3login('test@example.com', 'test@example.com', 'normal', 1);

// 一般ユーザーと管理者の両方を確認する
foreach (['normal', 'admin'] as $perm) {
    kona3login('test@example.com', 'test@example.com', $perm, 1);
    $menu = kona3getCtrlMenuArray('list');
    $labels = array_map(function ($it) { return $it[0]; }, $menu);
    $hasMyPage = FALSE;
    $hasUser = FALSE;
    foreach ($labels as $l) {
        if (strpos($l, lang('My Page')) !== FALSE) $hasMyPage = TRUE;
        if (strpos($l, 'test@example.com') !== FALSE) $hasUser = TRUE;
    }
    test_eq(__LINE__, $hasMyPage, TRUE, "$perm: menu has My Page label");
    test_eq(__LINE__, $hasUser, FALSE, "$perm: menu does not show user name");
}

// マイページ画面: 本人のときだけ「マイページ」、他人のときはID付きの名前を表示する
$tpl = file_get_contents(KONA3_DIR_ENGINE . '/template/user.html');
test_eq(__LINE__, strpos($tpl, '{{if $is_me }}') !== FALSE, TRUE, 'user.html: is_me で見出しを切り替える');
$act = file_get_contents(KONA3_DIR_ENGINE . '/action/user.inc.php');
test_eq(__LINE__, strpos($act, '"is_me" => $is_me') !== FALSE, TRUE, 'user action: is_me をテンプレートに渡す');
kona3logout();
