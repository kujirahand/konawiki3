<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/action/login.inc.php';

// #306: PR #304 (Issue #132) の agy レビュー指摘へのフォローアップ
// 中1: 同一メールの複数行では有効な行を優先し、enabled は 1 のみ有効とする
// 中2: 管理者ファイル破損時も、DBユーザーが存在すればその照合結果を優先する
// 中3: error_log に制御文字をそのまま出さない (実装側で除去、ここでは理由の確認)
// 低4: 管理者ファイル未作成時の tryLoginResult の振る舞い
// 低5: ログイン成功時にログイントークンを更新する
// 低6: トークン比較は hash_equals (理由判定が従来どおり動くことで確認)

$adminFile = kona3getAdminUserFile();
$adminBackup = file_exists($adminFile) ? file_get_contents($adminFile) : null;
$restoreAdmin = function () use ($adminFile, $adminBackup) {
    if ($adminBackup !== null) {
        file_put_contents($adminFile, $adminBackup);
    } else {
        @unlink($adminFile);
    }
};
$mkUser = function ($uid, $email, $pw, $enabled) {
    db_exec("DELETE FROM users WHERE user_id=?", [$uid]);
    db_exec(
        "INSERT INTO users (user_id,name,email,password,perm,enabled,ctime,mtime) VALUES (?,?,?,?,?,?,?,?)",
        [$uid, "user{$uid}", $email, kona3getHash($pw), 'normal', $enabled, time(), time()]
    );
};
$delUser = function ($uid) {
    db_exec("DELETE FROM users WHERE user_id=?", [$uid]);
};

// --- 中1: 同一メールで enabled=0 の行が先にあっても enabled=1 でログインできる ---
$dupEmail = 'dup306@example.com';
$mkUser(151010, $dupEmail, 'disabled-pw', 0); // 先に作る (rowid が小さい)
$mkUser(151011, $dupEmail, 'enabled-pw', 1);
$res = kona3tryLoginResult($dupEmail, 'enabled-pw');
test_eq(__LINE__, $res['ok'], TRUE, '306 dup: enabled row wins over disabled row');
test_eq(__LINE__, kona3getUserId(), 151011, '306 dup: logged in as the enabled user');
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
// 無効な行のパスワードではログインできない (有効な行で照合される)
$res = kona3tryLoginResult($dupEmail, 'disabled-pw');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_INVALID, '306 dup: password of disabled row does not login');
$delUser(151010);
$delUser(151011);

// --- 中1: enabled=2 は有効扱いしない ---
$mkUser(151012, 'ena2-306@example.com', 'ena2-pw', 2);
$res = kona3tryLoginResult('ena2-306@example.com', 'ena2-pw');
test_eq(__LINE__, $res['ok'], FALSE, '306 enabled: enabled=2 cannot login');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_DISABLED, '306 enabled: enabled=2 is user_disabled');
$delUser(151012);

// --- 低4: 管理者ファイルが未作成でも一般ユーザーはログインできる ---
@unlink($adminFile);
$mkUser(151013, 'nofile306@example.com', 'nofile-pw', 1);
$res = kona3tryLoginResult('nofile306@example.com', 'nofile-pw');
test_eq(__LINE__, $res['ok'], TRUE, '306 no-admin-file: normal user can login');
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
$res = kona3tryLoginResult('nofile306@example.com', 'wrong-pw');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_INVALID, '306 no-admin-file: wrong password is invalid (not system_error)');
$res = kona3tryLoginResult('nobody306@example.com', 'any-pw');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_INVALID, '306 no-admin-file: unknown user is invalid (not system_error)');
$delUser(151013);

// --- 中2: 管理者ファイル破損時、DBユーザーの照合結果が system_error より優先される ---
@unlink($adminFile);
kona3createAdminUser('admin306@example.com', 'admin306-pw');
$mkUser(151014, 'db306@example.com', 'db306-pw', 1);
$mkUser(151015, 'db306off@example.com', 'db306off-pw', 0);
file_put_contents($adminFile, "this is not a jsonphp file");
// DBユーザーのパスワード不一致は invalid (以前は system_error になっていた)
$res = kona3tryLoginResult('db306@example.com', 'wrong-pw');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_INVALID, '306 corrupt: db user wrong password is invalid (not system_error)');
// DBユーザーのパスワード正解+無効化は disabled
$res = kona3tryLoginResult('db306off@example.com', 'db306off-pw');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_DISABLED, '306 corrupt: db disabled user is user_disabled (not system_error)');
// DBユーザーの正常ログインは管理者ファイルが壊れていても成功する
$res = kona3tryLoginResult('db306@example.com', 'db306-pw');
test_eq(__LINE__, $res['ok'], TRUE, '306 corrupt: db user can still login');
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
// DBに存在しないユーザー (管理者の可能性) は system_error のまま
$res = kona3tryLoginResult('admin306@example.com', 'admin306-pw');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_SYSTEM, '306 corrupt: admin candidate is system_error');
$res = kona3tryLoginResult("evil306@example.com\r\nFAKE LOG LINE", 'x');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_SYSTEM, '306 corrupt: CRLF user still returns system_error (log line is sanitized in code)');
$delUser(151014);
$delUser(151015);
$restoreAdmin();

// --- 中2: 文言から内部詳細を外した ---
$sysMsgJa = 'システムエラーのためログインできません。管理者にお問い合わせください。パスワードは確認されていません。';
test_assert(__LINE__, strpos(file_get_contents(KONA3_DIR_ENGINE . '/lang/ja.inc.php'), $sysMsgJa) !== FALSE, '306 wording: ja system message has no internal detail');
test_assert(__LINE__, strpos(file_get_contents(KONA3_DIR_ENGINE . '/lang/ja.inc.php'), '管理者ユーザーの情報を読み込めませんでした') === FALSE, '306 wording: ja system message detail removed');
test_assert(__LINE__, strpos(file_get_contents(KONA3_DIR_ENGINE . '/lang/en.inc.php'), 'administrator user data could not be loaded') === FALSE, '306 wording: en system message detail removed');

// --- 低5: ログイン成功時にログイントークンが更新される実装になっている ---
$loginAct = file_get_contents(KONA3_DIR_ENGINE . '/action/login.inc.php');
$successPos = strpos($loginAct, 'function login_handle_success');
$rotatePos = strpos($loginAct, 'kona3_getEditTokenForceUpdate(KONA3_LOGIN_CSRF_KEY)', $successPos);
test_assert(__LINE__, $successPos !== FALSE && $rotatePos !== FALSE, '306 rotate: login_handle_success rotates the login token');

// --- 低6: トークン照合が hash_equals で行われる ---
$libSrc = file_get_contents(KONA3_DIR_ENGINE . '/kona3lib.inc.php');
$reasonPos = strpos($libSrc, 'function kona3_checkEditTokenReason');
test_assert(__LINE__, $reasonPos !== FALSE && strpos($libSrc, 'hash_equals($sessionValue, $postValue)', $reasonPos) !== FALSE, '306 hash_equals: token comparison uses hash_equals');
// 理由判定の動作は従来どおり
$savedSession = $_SESSION;
$savedPost = $_POST;
$_SESSION = [];
$tok306 = kona3_getEditToken(KONA3_LOGIN_CSRF_KEY);
$_POST = ['login_token' => $tok306];
test_eq(__LINE__, kona3_checkEditTokenReason(KONA3_LOGIN_CSRF_KEY), 'ok', '306 hash_equals: correct token is ok');
$_POST = ['login_token' => $tok306 . 'x'];
test_eq(__LINE__, kona3_checkEditTokenReason(KONA3_LOGIN_CSRF_KEY), 'mismatch', '306 hash_equals: wrong token is mismatch');
$_SESSION = $savedSession;
$_POST = $savedPost;

// 後片付け
$restoreAdmin();
