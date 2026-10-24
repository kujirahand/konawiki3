<?php
/**
 * Test for issue #64: 編集中にセッションが切れた時、うまく保存できない場合がある
 *
 * (案2) 保存に失敗した本文の退避・復旧 (kona3edit_stashRecovery /
 *       kona3edit_getRecovery / kona3edit_clearRecovery)
 * (案3) keepalive によるセッション維持と編集トークンの交換
 *       (kona3edit_keepaliveData)
 */
require_once __DIR__ . '/test_common.inc.php';
require_once __DIR__ . '/../action/edit.inc.php';

echo "=== Edit Session Test (issue #64) ===\n";

$pageA = 'SessionTestPageA';
$pageB = 'SessionTestPageB';

// -----------------------------------------------
// (案2) 本文の退避
// -----------------------------------------------
echo "Test 1: stash and recover a failed save\n";
$_SESSION = [];
$_REQUEST = [
    'a_mode' => 'trywrite',
    'edit_txt' => "退避する本文\n2行目",
    'tags' => 'tagA/tagB',
    'edit_ext' => 'md',
    'page_mode' => 'Markdown',
];
test_eq(__LINE__, kona3edit_stashRecovery($pageA), TRUE, 'stash: trywriteを退避できる');
$rec = kona3edit_getRecovery($pageA);
test_assert(__LINE__, $rec !== null, 'stash: 退避した本文を取得できる');
test_eq(__LINE__, $rec['txt'], "退避する本文\n2行目", 'stash: 本文がそのまま退避される');
test_eq(__LINE__, $rec['tags'], 'tagA/tagB', 'stash: タグも退避される');
test_eq(__LINE__, $rec['edit_ext'], 'md', 'stash: 拡張子も退避される');
test_eq(__LINE__, $rec['page_mode'], 'Markdown', 'stash: 記法モードも退避される');
test_eq(__LINE__, kona3edit_getRecovery($pageB), null, 'stash: 別のページには退避がない');

echo "Test 2: stash only happens for save requests\n";
$_SESSION = [];
$_REQUEST = ['a_mode' => '', 'edit_txt' => '本文'];
test_eq(__LINE__, kona3edit_stashRecovery($pageA), FALSE, 'stash: 表示リクエストは退避しない');
$_REQUEST = ['a_mode' => 'trywrite']; // edit_txt なし
test_eq(__LINE__, kona3edit_stashRecovery($pageA), FALSE, 'stash: 本文パラメータがなければ退避しない');
test_eq(__LINE__, kona3edit_getRecovery($pageA), null, 'stash: 退避されていない');

echo "Test 3: empty text (page deletion) is also stashed\n";
$_SESSION = [];
$_REQUEST = ['a_mode' => 'trywrite', 'edit_txt' => ''];
test_eq(__LINE__, kona3edit_stashRecovery($pageA), TRUE, 'stash: 空本文 (削除) も退避する');
$rec = kona3edit_getRecovery($pageA);
test_assert(__LINE__, $rec !== null && $rec['txt'] === '', 'stash: 空本文が取得できる');

echo "Test 4: recovery expires and can be cleared\n";
$_SESSION = [];
$_REQUEST = ['a_mode' => 'trygit', 'edit_txt' => '期限切れになる本文'];
test_eq(__LINE__, kona3edit_stashRecovery($pageA), TRUE, 'stash: trygitも退避できる');
$_SESSION[KONA3_EDIT_RECOVERY_KEY][$pageA]['time'] = time() - KONA3_EDIT_RECOVERY_TTL - 1;
test_eq(__LINE__, kona3edit_getRecovery($pageA), null, 'stash: 期限切れの退避は取得できない');
test_assert(__LINE__, !isset($_SESSION[KONA3_EDIT_RECOVERY_KEY][$pageA]), 'stash: 期限切れの退避は破棄される');

$_REQUEST = ['a_mode' => 'trywrite', 'edit_txt' => 'Aの本文'];
kona3edit_stashRecovery($pageA);
$_REQUEST = ['a_mode' => 'trywrite', 'edit_txt' => 'Bの本文'];
kona3edit_stashRecovery($pageB);
kona3edit_clearRecovery($pageA);
test_eq(__LINE__, kona3edit_getRecovery($pageA), null, 'stash: clearしたページは消える');
$recB = kona3edit_getRecovery($pageB);
test_assert(__LINE__, $recB !== null && $recB['txt'] === 'Bの本文', 'stash: clearはページ単位で効く');

// -----------------------------------------------
// (案3) keepalive
// -----------------------------------------------
echo "Test 5: keepalive requires login\n";
$_SESSION = [];
$_REQUEST = [];
$data = kona3edit_keepaliveData($pageA);
test_eq(__LINE__, $data['result'], 'ng', 'keepalive: 未ログインは ng');
test_eq(__LINE__, $data['code'], 'nologin', 'keepalive: 未ログインは code=nologin');

echo "Test 6: keepalive returns the edit token and touches the session\n";
$_SESSION = [];
kona3login('session-tester', 'session@example.com', 'normal', 0);
$before = time();
$data = kona3edit_keepaliveData($pageA);
test_eq(__LINE__, $data['result'], 'ok', 'keepalive: ログイン中は ok');
test_assert(__LINE__, isset($data['edit_token']) && $data['edit_token'] !== '', 'keepalive: 編集トークンを返す');
$sname = kona3_getEditTokenKeyName('edit_token');
test_eq(__LINE__, $data['edit_token'], $_SESSION[$sname], 'keepalive: セッションのトークンと一致する');
test_assert(__LINE__, isset($_SESSION['kona3_last_activity']) && $_SESSION['kona3_last_activity'] >= $before, 'keepalive: 最終アクセス時刻が更新される (セッション維持)');

echo "Test 7: keepalive exchanges an expired edit token\n";
$snameTime = kona3_getEditTokenKeyName('edit_token.time');
$_SESSION[$sname] = 'expired-token-value';
$_SESSION[$snameTime] = time() - (60 * 60 * 24) - 10; // 1日の有効期間を過ぎている
$data = kona3edit_keepaliveData($pageA);
test_eq(__LINE__, $data['result'], 'ok', 'keepalive: 期限切れトークンでも ok');
test_ne(__LINE__, $data['edit_token'], 'expired-token-value', 'keepalive: 期限切れトークンは新しいものに交換される');
test_eq(__LINE__, $_SESSION[$sname], $data['edit_token'], 'keepalive: 交換したトークンがセッションに入る');

echo "Test 8: keepalive does not rotate a valid token every time\n";
$data2 = kona3edit_keepaliveData($pageA);
test_eq(__LINE__, $data2['edit_token'], $data['edit_token'], 'keepalive: 有効なトークンは使い回す (他タブのフォームを壊さない)');

// -----------------------------------------------
// 配線の確認 (JS / テンプレート / アクション)
// -----------------------------------------------
echo "Test 9: client and template wiring\n";
$editJs = file_get_contents(__DIR__ . '/../resource/edit.js');
test_assert(__LINE__, strpos($editJs, 'startKeepalive') !== FALSE, '配線: edit.js が keepalive を開始する');
test_assert(__LINE__, strpos($editJs, 'handleSessionLost') !== FALSE, '配線: edit.js がセッション切れを処理する');
test_assert(__LINE__, strpos($editJs, "'keepalive'") !== FALSE || strpos($editJs, '"keepalive"') !== FALSE, '配線: edit.js が keepalive を送信する');
test_assert(__LINE__, strpos($editJs, 'kona3tryAutologin') === FALSE, '配線: 存在しない kona3tryAutologin への参照が残っていない');
$editHtml = file_get_contents(__DIR__ . '/../template/edit.html');
test_assert(__LINE__, strpos($editHtml, 'id="login_url"') !== FALSE, '配線: edit.html に再ログイン用URLが埋め込まれる');
$editPhp = file_get_contents(__DIR__ . '/../action/edit.inc.php');
test_assert(__LINE__, strpos($editPhp, "'keepalive'") !== FALSE, '配線: edit アクションが keepalive を処理する');
test_assert(__LINE__, strpos($editPhp, "'invalid_token'") !== FALSE, '配線: トークンエラーが ajax 用コード invalid_token を返す');
test_assert(__LINE__, strpos($editPhp, 'kona3edit_getRecovery') !== FALSE, '配線: 編集画面が退避した本文を復旧する');

// 後片付け (他のテストへ影響を残さない)
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
$_SESSION = [];
$_REQUEST = [];
$_POST = [];
