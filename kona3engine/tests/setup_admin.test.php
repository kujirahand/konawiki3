<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/kona3login.inc.php';

// #295: スクリプトから管理者ユーザーを作成できること
// (テストサンドボックスの private/ に作るので実データには影響しない)
$adminFile = kona3getAdminUserFile();
@unlink($adminFile);
test_eq(__LINE__, kona3getAdminUsers(), [], "setup_admin: no admin users at first");

test_eq(__LINE__, kona3createAdminUser('admin@example.com', 'secret-pw-1'), TRUE, "setup_admin: create returns TRUE");
test_assert(__LINE__, file_exists($adminFile), "setup_admin: admin file created");

$users = kona3getAdminUsers();
test_assert(__LINE__, isset($users['admin@example.com']), "setup_admin: user is listed");
test_ne(__LINE__, $users['admin@example.com']['hash'], 'secret-pw-1', "setup_admin: password is not stored in plain text");
test_assert(__LINE__, !empty($users['admin@example.com']['salt']), "setup_admin: salt is saved");

// 作成した管理者で認証できる (間違ったパスワードは不可)
global $kona3conf;
test_eq(__LINE__, kona3_admin_auth('admin@example.com', 'secret-pw-1', $kona3conf), TRUE, "setup_admin: auth succeeds");
test_eq(__LINE__, kona3isAdmin(), TRUE, "setup_admin: auth gives admin perm");
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
test_eq(__LINE__, kona3_admin_auth('admin@example.com', 'wrong-pw', $kona3conf), FALSE, "setup_admin: wrong password rejected");
test_eq(__LINE__, kona3_admin_auth('nobody@example.com', 'secret-pw-1', $kona3conf), FALSE, "setup_admin: unknown user rejected");

// 既存ユーザーは上書き指定なしでは作り直せない
$threw = FALSE;
try {
    kona3createAdminUser('admin@example.com', 'other-pw');
} catch (RuntimeException $e) {
    $threw = TRUE;
}
test_assert(__LINE__, $threw, "setup_admin: duplicate without overwrite throws");
test_eq(__LINE__, kona3_admin_auth('admin@example.com', 'secret-pw-1', $kona3conf), TRUE, "setup_admin: password unchanged after duplicate attempt");
unset($_SESSION[KONA3_SESSKEY_LOGIN]);

// 上書き指定でパスワードを変更でき、他の管理者は保持される
kona3createAdminUser('second@example.com', 'second-pw');
kona3createAdminUser('admin@example.com', 'secret-pw-2', TRUE);
test_eq(__LINE__, kona3_admin_auth('admin@example.com', 'secret-pw-1', $kona3conf), FALSE, "setup_admin: old password rejected after overwrite");
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
test_eq(__LINE__, kona3_admin_auth('admin@example.com', 'secret-pw-2', $kona3conf), TRUE, "setup_admin: new password accepted after overwrite");
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
test_eq(__LINE__, kona3_admin_auth('second@example.com', 'second-pw', $kona3conf), TRUE, "setup_admin: other admin preserved");
unset($_SESSION[KONA3_SESSKEY_LOGIN]);

// ソルトはユーザーごとに異なる
$users = kona3getAdminUsers();
test_ne(__LINE__, $users['admin@example.com']['salt'], $users['second@example.com']['salt'], "setup_admin: salts differ per user");

// 検証エラー: 空のID / 空のパスワード / 空白を含むID
foreach ([['', 'pw'], ['   ', 'pw'], ['admin@example.com', ''], ['a b@example.com', 'pw']] as $bad) {
    $threw = FALSE;
    try {
        kona3createAdminUser($bad[0], $bad[1], TRUE);
    } catch (InvalidArgumentException $e) {
        $threw = TRUE;
    }
    test_assert(__LINE__, $threw, "setup_admin: invalid input rejected ({$bad[0]})");
}

// CLIスクリプトが用意されている
test_assert(__LINE__, file_exists(dirname(KONA3_DIR_ENGINE) . '/script/create_admin.php'), "setup_admin: create_admin.php exists");
test_assert(__LINE__, file_exists(dirname(KONA3_DIR_ENGINE) . '/script/makewiki.php'), "setup_admin: makewiki.php exists");

// 後片付け (他のテストに管理者ファイルを残さない)
@unlink($adminFile);
test_eq(__LINE__, kona3getAdminUsers(), [], "setup_admin: cleaned up");
