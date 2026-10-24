<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/action/login.inc.php';

// #132: ログイン失敗の理由を区別してログインフォームに表示する
// - ユーザー名かパスワードが違う場合は、どちらが違うか明かさず
//   「ユーザー名とパスワードが違います」と表示する
// - それ以外の理由 (未入力 / 無効化されたアカウント / 管理者ファイル破損 /
//   CSRFトークンエラー) は、本当の理由を表示する

// --- 理由コードと言語キーの対応 ---
test_eq(__LINE__, kona3loginReasonLangKey(KONA3_LOGIN_REASON_INVALID), 'Invalid User or Password.', 'login reason: invalid maps to Invalid User or Password.');
test_eq(__LINE__, kona3loginReasonLangKey(KONA3_LOGIN_REASON_EMPTY), 'Please enter your user name and password.', 'login reason: empty has its own message');
test_eq(__LINE__, kona3loginReasonLangKey(KONA3_LOGIN_REASON_DISABLED), 'This account is disabled.', 'login reason: disabled has its own message');
test_eq(__LINE__, kona3loginReasonLangKey(KONA3_LOGIN_REASON_SYSTEM), 'Login is unavailable due to a system error.', 'login reason: system error has its own message');
// 日本語の文言が用意されている (利用者にそのまま見える文言)
test_assert(__LINE__, lang(kona3loginReasonLangKey(KONA3_LOGIN_REASON_INVALID)) !== '', 'login reason: invalid message exists');

// --- 未入力は認証以前に区別する ---
$res = kona3tryLoginResult('', 'pw');
test_eq(__LINE__, $res['ok'], FALSE, 'login reason: empty user rejected');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_EMPTY, 'login reason: empty user is empty_input');
$res = kona3tryLoginResult('someone@example.com', '');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_EMPTY, 'login reason: empty password is empty_input');
$res = kona3tryLoginResult('   ', 'pw');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_EMPTY, 'login reason: blank user is empty_input');
// 配列など文字列でない入力でもエラーにせず理由を返す
$res = kona3tryLoginResult(['x'], 'pw');
test_eq(__LINE__, $res['ok'], FALSE, 'login reason: non-string user rejected without error');

// --- 管理者認証の理由 ---
$adminFile = kona3getAdminUserFile();
$adminBackup = file_exists($adminFile) ? file_get_contents($adminFile) : null;
@unlink($adminFile);
kona3createAdminUser('reason-admin@example.com', 'reason-secret-1');

$res = kona3tryLoginResult('reason-admin@example.com', 'reason-secret-1');
test_eq(__LINE__, $res['ok'], TRUE, 'login reason: admin login succeeds');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_OK, 'login reason: admin login reason is ok');
unset($_SESSION[KONA3_SESSKEY_LOGIN]);

$res = kona3tryLoginResult('reason-admin@example.com', 'wrong-pw');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_INVALID, 'login reason: admin wrong password is invalid_credentials');
$res = kona3tryLoginResult('no-such-admin@example.com', 'reason-secret-1');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_INVALID, 'login reason: unknown admin is also invalid_credentials (not distinguished)');

// --- 一般ユーザーの理由 (有効 / 無効化) ---
$uid = 151002;
$uemail = 'reason-user@example.com';
db_exec("DELETE FROM users WHERE user_id=? OR email=?", [$uid, $uemail]);
db_exec(
    "INSERT INTO users (user_id,name,email,password,perm,enabled,ctime,mtime) VALUES (?,?,?,?,?,?,?,?)",
    [$uid, 'reason-user', $uemail, kona3getHash('user-secret-1'), 'normal', 1, time(), time()]
);
$res = kona3tryLoginResult($uemail, 'user-secret-1');
test_eq(__LINE__, $res['ok'], TRUE, 'login reason: normal user login succeeds');
unset($_SESSION[KONA3_SESSKEY_LOGIN]);
$res = kona3tryLoginResult($uemail, 'wrong-pw');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_INVALID, 'login reason: user wrong password is invalid_credentials');
$res = kona3tryLoginResult('no-such-user@example.com', 'user-secret-1');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_INVALID, 'login reason: unknown user is also invalid_credentials (not distinguished)');

// 無効化されたアカウント: パスワードが正しい場合だけ理由を区別する
db_exec("UPDATE users SET enabled=0 WHERE user_id=?", [$uid]);
$res = kona3tryLoginResult($uemail, 'user-secret-1');
test_eq(__LINE__, $res['ok'], FALSE, 'login reason: disabled user cannot login');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_DISABLED, 'login reason: disabled user with correct password is user_disabled');
$res = kona3tryLoginResult($uemail, 'wrong-pw');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_INVALID, 'login reason: disabled user with wrong password stays invalid_credentials');
db_exec("DELETE FROM users WHERE user_id=? OR email=?", [$uid, $uemail]);

// --- 管理者ファイル破損時は「パスワード違い」と誤案内しない ---
file_put_contents($adminFile, "this is not a jsonphp file");
$res = kona3tryLoginResult('reason-admin@example.com', 'reason-secret-1');
test_eq(__LINE__, $res['ok'], FALSE, 'login reason: corrupt admin file cannot login');
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_SYSTEM, 'login reason: corrupt admin file is system_error');
$ares = kona3_admin_auth_result('reason-admin@example.com', 'reason-secret-1', $kona3conf);
test_eq(__LINE__, $ares['reason'], KONA3_LOGIN_REASON_SYSTEM, 'login reason: admin auth result reports system_error');
// 従来の bool 版 API は互換を保つ
test_eq(__LINE__, kona3_admin_auth('reason-admin@example.com', 'reason-secret-1', $kona3conf), FALSE, 'login reason: bool admin auth still returns FALSE');
test_eq(__LINE__, kona3tryLogin('reason-admin@example.com', 'reason-secret-1'), FALSE, 'login reason: bool tryLogin still returns FALSE');

// 管理者ファイルを復旧
if ($adminBackup !== null) {
    file_put_contents($adminFile, $adminBackup);
} else {
    @unlink($adminFile);
}

// --- CSRFトークンの理由 (#132) ---
// セッションにトークンがない
$savedSession = $_SESSION;
$savedPost = $_POST;
$_SESSION = [];
$_POST = ['login_token' => 'something'];
test_eq(__LINE__, kona3_checkEditTokenReason(KONA3_LOGIN_CSRF_KEY), 'no_session_token', 'token reason: no session token');
test_eq(__LINE__, kona3_checkEditToken(KONA3_LOGIN_CSRF_KEY), FALSE, 'token reason: no session token fails check');
// 送信にトークンがない
$tok = kona3_getEditToken(KONA3_LOGIN_CSRF_KEY);
$_POST = [];
test_eq(__LINE__, kona3_checkEditTokenReason(KONA3_LOGIN_CSRF_KEY), 'no_post_token', 'token reason: no post token');
// トークンが一致しない
$_POST = ['login_token' => 'wrong-token'];
test_eq(__LINE__, kona3_checkEditTokenReason(KONA3_LOGIN_CSRF_KEY), 'mismatch', 'token reason: mismatch');
// 正常
$_POST = ['login_token' => $tok];
test_eq(__LINE__, kona3_checkEditTokenReason(KONA3_LOGIN_CSRF_KEY), 'ok', 'token reason: ok');
test_eq(__LINE__, kona3_checkEditToken(KONA3_LOGIN_CSRF_KEY), TRUE, 'token reason: ok passes check');
// 配列が送られてもエラーにしない
$_POST = ['login_token' => ['x']];
test_eq(__LINE__, kona3_checkEditTokenReason(KONA3_LOGIN_CSRF_KEY), 'no_post_token', 'token reason: array token is treated as missing');
$_SESSION = $savedSession;
$_POST = $savedPost;

// --- ログインフォームのトークンは開き直しても変わらない (#132 根本対策) ---
// 以前は表示のたびに強制更新され、別タブで開くと先のフォームが無効になった
$t1 = kona3_getEditToken(KONA3_LOGIN_CSRF_KEY);
$t2 = kona3_getEditToken(KONA3_LOGIN_CSRF_KEY);
test_eq(__LINE__, $t1, $t2, 'login token: re-displaying the form reuses the same token');

// --- トークンエラーの文言は理由ごとに分かれ、パスワード未確認を明示する ---
foreach (['no_session_token', 'no_post_token', 'mismatch'] as $tr) {
    $m = login_token_error_message($tr);
    test_assert(__LINE__, $m !== '', "token message: message exists for {$tr}");
    test_assert(__LINE__, $m !== lang('Invalid User or Password.'), "token message: {$tr} is not the password message");
}
test_ne(__LINE__, login_token_error_message('no_session_token'), login_token_error_message('mismatch'), 'token message: session expiry and mismatch have different messages');

// ログインフォーム (login.html) に理由表示とメールアドレス保持がある
$loginTpl = file_get_contents(KONA3_DIR_ENGINE . '/template/login.html');
test_assert(__LINE__, strpos($loginTpl, '{{ $msg }}') !== FALSE, 'login form: template shows the reason message');
test_assert(__LINE__, strpos($loginTpl, 'value="{{ $a_email }}"') !== FALSE, 'login form: template keeps the entered email');
// ログインアクションが汎用エラー画面ではなくフォームに理由を渡す
$loginAct = file_get_contents(KONA3_DIR_ENGINE . '/action/login.inc.php');
test_assert(__LINE__, strpos($loginAct, 'kona3tryLoginResult') !== FALSE, 'login form: action uses the reason-aware login');
test_assert(__LINE__, strpos($loginAct, 'kona3_checkEditTokenReason') !== FALSE, 'login form: action checks the token reason');
test_assert(__LINE__, strpos($loginAct, "kona3error('Login failed.'") === FALSE, 'login form: credential failure no longer goes to the generic error page');
test_assert(__LINE__, strpos($loginAct, 'kona3_getEditTokenForceUpdate(KONA3_LOGIN_CSRF_KEY)') === FALSE, 'login form: token is no longer force-updated on every display');
