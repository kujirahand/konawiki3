<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/action/signup.inc.php';

// 終了するアクションは別プロセスで実行し、実際のレスポンスを検査する。
if (isset($argv[1]) && $argv[1] === '--signup-probe') {
    $kona3conf['allow_add_user'] = true;
    $kona3conf['admin_email'] = 'admin@example.com'; // 外部メールを送らない
    $kona3conf['page'] = 'signup';
    $_SERVER['HTTP_HOST'] = $argv[3];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $mode = $argv[2];
    if ($mode === 'resend') {
        db_exec('INSERT INTO users (name,email,password,token,enabled,ctime,mtime) VALUES (?,?,?,?,?,?,?)',
            ['probe-user', 'probe@example.com', kona3getHash('probe-pw'), '123456', 0, time() - 120, time() - 120]);
    }
    $_REQUEST = $_POST = [
        'a_mode' => $mode, 'a_user' => 'probe-user', 'a_email' => 'probe@example.com',
        'a_pw' => 'probe-pw', 'a_pw2' => 'probe-pw',
        'signup_token' => kona3_getEditToken(SIGNUP_CSRF_KEY),
    ];
    kona3_action_signup();
    exit;
}

$savedConfAuth = $kona3conf;
$savedSessionAuth = $_SESSION;
$savedCookieAuth = $_COOKIE;
$savedAdminsAuth = kona3getAdminUsers();
$authUid = 900501;
$authEmail = 'auth-hardening@example.com';
$authPw = str_repeat('long-password-', 8);

foreach (['localhost', 'localhost:8080', 'wiki.example.com'] as $host) {
    foreach (['try', 'resend'] as $mode) {
        $out = [];
        $code = -1;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --signup-probe '
            . escapeshellarg($mode) . ' ' . escapeshellarg($host) . ' 2>&1', $out, $code);
        $response = implode("\n", $out);
        test_eq(__LINE__, $code, 0, "signup $mode $host: action succeeds");
        test_assert(__LINE__, strpos($response, 'DEBUG:') === false, "signup $mode $host: no code disclosure");
        $expected = $mode === 'try' ? 'a_mode=verify' : 'name="a_code"';
        test_assert(__LINE__, strpos($response, $expected) !== false, "signup $mode $host: verification flow rendered");
    }
}

$newHashAuth = kona3password_hash($authPw);
test_assert(__LINE__, kona3_verify_user_password($authPw, $newHashAuth), 'new password: correct password accepted');
test_assert(__LINE__, !kona3_verify_user_password($authPw . 'x', $newHashAuth), 'new password: differences after byte 72 rejected');
test_ne(__LINE__, $newHashAuth, kona3password_hash($authPw), 'new password: independent random salts');
test_assert(__LINE__, !kona3password_needsRehash($newHashAuth), 'new password: current work factor');
test_assert(__LINE__, !kona3_verify_user_password('pw', ''), 'empty stored hash rejected');

db_exec('DELETE FROM users WHERE user_id=?', [$authUid]);
db_exec('INSERT INTO users (user_id,name,email,password,perm,enabled,ctime,mtime) VALUES (?,?,?,?,?,?,?,?)',
    [$authUid, 'auth-user', $authEmail, kona3getHash($authPw), 'admin', 1, time(), time()]);
test_assert(__LINE__, !kona3_user_auth($authEmail, 'wrong'), 'legacy password: wrong password rejected');
$authRow = db_get1('SELECT * FROM users WHERE user_id=?', [$authUid]);
test_eq(__LINE__, $authRow['password'], kona3getHash($authPw), 'failed login does not migrate password');
test_assert(__LINE__, kona3_user_auth($authEmail, $authPw), 'legacy password: login succeeds');
$authRow = db_get1('SELECT * FROM users WHERE user_id=?', [$authUid]);
test_assert(__LINE__, !kona3password_needsRehash($authRow['password']), 'legacy password: successful login migrates hash');
test_assert(__LINE__, kona3_verify_user_password($authPw, $authRow['password']), 'migrated password remains usable');
$migratedAuthHash = $authRow['password'];
test_assert(__LINE__, kona3_user_auth($authEmail, $authPw), 'modern password login succeeds');
$authRow = db_get1('SELECT * FROM users WHERE user_id=?', [$authUid]);
test_eq(__LINE__, $authRow['password'], $migratedAuthHash, 'current hash is not rewritten on every login');

// リクエスト開始時の更新を再現する。
db_exec("UPDATE users SET perm='normal',name='renamed-user' WHERE user_id=?", [$authUid]);
kona3login_refreshSession();
test_assert(__LINE__, kona3isLogin(), 'demotion preserves normal login');
test_assert(__LINE__, !kona3isAdmin(), 'demotion removes admin permission from existing session');
test_eq(__LINE__, kona3getUserName(), 'renamed-user', 'session refresh updates current user name');
$issuedAuth = kona3remember_issue();
db_exec('UPDATE users SET enabled=0 WHERE user_id=?', [$authUid]);
kona3login_refreshSession();
test_assert(__LINE__, !kona3isLogin(), 'disabled account loses existing session');
test_assert(__LINE__, !kona3remember_tryAutoLogin(), 'disabled account cannot automatically restore session');
test_eq(__LINE__, db_get1('SELECT * FROM remember_tokens WHERE selector=?', [$issuedAuth['selector']], 'remember'), false,
    'disabled session revokes current remember token');

db_exec('UPDATE users SET enabled=1 WHERE user_id=?', [$authUid]);
test_assert(__LINE__, kona3_user_auth($authEmail, $authPw), 'migrated user can log in again');
db_exec('DELETE FROM users WHERE user_id=?', [$authUid]);
kona3login_refreshSession();
test_assert(__LINE__, !kona3isLogin(), 'deleted account loses existing session');

// 設定ファイル上の管理者についても旧形式の移行と削除を確認する。
kona3saveAdminUsers(['legacy-auth-admin' => ['hash' => kona3getHash($authPw, 'legacy-salt'), 'salt' => 'legacy-salt']]);
test_assert(__LINE__, kona3_admin_auth('legacy-auth-admin', $authPw, $kona3conf), 'legacy admin password accepted');
$adminsAuth = kona3getAdminUsers();
test_assert(__LINE__, !kona3password_needsRehash($adminsAuth['legacy-auth-admin']['hash']), 'legacy admin password migrated');
kona3login_refreshSession();
test_assert(__LINE__, kona3isAdmin(), 'configured admin session remains valid');
kona3saveAdminUsers([]);
kona3login_refreshSession();
test_assert(__LINE__, !kona3isLogin(), 'removed configured admin loses session');
kona3createAdminUser('new-auth-admin', $authPw);
$adminsAuth = kona3getAdminUsers();
test_assert(__LINE__, !kona3password_needsRehash($adminsAuth['new-auth-admin']['hash']), 'new admin uses modern password hash');
test_assert(__LINE__, kona3_admin_auth('new-auth-admin', $authPw, $kona3conf), 'new admin password works');
test_assert(__LINE__, !kona3_admin_auth('new-auth-admin', $authPw . 'x', $kona3conf), 'new admin rejects different long password');

require_once KONA3_DIR_ENGINE . '/action/admin.inc.php';
kona3sestup_admin_write_pw('setup-auth-admin', $authPw);
$adminsAuth = kona3getAdminUsers();
test_assert(__LINE__, !kona3password_needsRehash($adminsAuth['setup-auth-admin']['hash']), 'setup screen uses modern password hash');

// 新規登録の保存形式も検査する (メールは送信しない)。
$kona3conf['admin_email'] = 'admin@example.com';
$kona3conf['static_mode'] = true;
$authSignupMsg = '';
try {
    signup_execute('new-auth-user', 'new-auth-user@example.com', $authPw, $authSignupMsg);
} catch (Kona3StaticException $e) {
    // メッセージ表示で処理を止め、保存された行を検査する。
}
$newSignupAuth = db_get1('SELECT * FROM users WHERE email=?', ['new-auth-user@example.com']);
test_assert(__LINE__, $newSignupAuth && !kona3password_needsRehash($newSignupAuth['password']), 'signup stores modern password hash');
test_assert(__LINE__, $newSignupAuth && kona3_verify_user_password($authPw, $newSignupAuth['password']), 'signup stored password verifies');
db_exec('DELETE FROM users WHERE email=?', ['new-auth-user@example.com']);

$kona3conf = $savedConfAuth;
$_SESSION = $savedSessionAuth;
$_COOKIE = $savedCookieAuth;
kona3saveAdminUsers($savedAdminsAuth);
