<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/action/users.inc.php';

// issue #284: マイページの「ユーザー一覧」リンク(GET)で Invalid Token にならない
@session_start();
$token = kona3_getEditToken('edit_token');

$_REQUEST['edit_token'] = $token;
test_eq(__LINE__, users_checkEditToken(), TRUE, 'GETで渡した正しいトークンは通る');

$_REQUEST['edit_token'] = 'wrong';
test_eq(__LINE__, users_checkEditToken(), FALSE, '誤ったトークンは拒否');

unset($_REQUEST['edit_token']);
test_eq(__LINE__, users_checkEditToken(), FALSE, 'トークン未指定は拒否');

// user画面のリンクと users 側の検証で同じキーを使う
$act = file_get_contents(KONA3_DIR_ENGINE . '/action/user.inc.php');
test_eq(__LINE__, strpos($act, "kona3_getEditToken('edit_token')") !== FALSE, TRUE, 'user action: edit_token キーでトークン生成');
