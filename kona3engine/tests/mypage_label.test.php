<?php
require_once __DIR__ . '/test_common.inc.php';

// issue #274: ログイン後のメニューはユーザー名ではなく「マイページ」と表示する
global $kona3conf;
$kona3conf['page'] = 'FrontPage';
$kona3conf['FrontPage'] = 'FrontPage';
$kona3conf['static_mode'] = FALSE;
@session_start();
kona3login('test@example.com', 'test@example.com', 'normal', 1);

$menu = kona3getCtrlMenuArray('list');
$labels = array_map(function ($it) { return $it[0]; }, $menu);
$hasMyPage = FALSE;
$hasUser = FALSE;
foreach ($labels as $l) {
    if (strpos($l, lang('My Page')) !== FALSE) $hasMyPage = TRUE;
    if (strpos($l, 'test@example.com') !== FALSE) $hasUser = TRUE;
}
test_eq(__LINE__, $hasMyPage, TRUE, 'menu has My Page label');
test_eq(__LINE__, $hasUser, FALSE, 'menu does not show user name');
kona3logout();
