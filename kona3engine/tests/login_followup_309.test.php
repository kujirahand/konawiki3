<?php
require_once __DIR__ . '/test_common.inc.php';
require_once KONA3_DIR_ENGINE . '/action/login.inc.php';

// #309: PR #308 (Issue #306) の agy レビュー指摘 (低3件) へのフォローアップ
// 低1: 管理者ファイル破損時は、DBユーザーが存在して DB側の理由を
//       返す経路でも、破損の error_log を必ず記録する
// 低2: Unicode の行区切り (U+0085 / U+2028 / U+2029) も
//       ログサニタイズの対象にする
// 低3: サニタイズは kona3sanitizeForLog() に切り出して入出力を
//       単体テストし、ログイン成功時のトークン更新は実行時に
//       セッション内の値が変わることで検証する

// --- 低2/低3: kona3sanitizeForLog() の単体テスト ---
test_eq(__LINE__, kona3sanitizeForLog('user@example.com'), 'user@example.com', '309 sanitize: plain text is unchanged');
test_eq(__LINE__, kona3sanitizeForLog(''), '', '309 sanitize: empty string');
test_eq(__LINE__, kona3sanitizeForLog("a\r\nb"), 'a  b', '309 sanitize: CR/LF become spaces');
test_eq(__LINE__, kona3sanitizeForLog("a\tb\x00c\x7Fd"), 'a b c d', '309 sanitize: TAB/NUL/DEL become spaces');
test_eq(__LINE__, kona3sanitizeForLog("a\x1Bb"), 'a b', '309 sanitize: ESC becomes space');
// Unicode の行区切り
test_eq(__LINE__, kona3sanitizeForLog("a\u{0085}b"), 'a b', '309 sanitize: U+0085 (NEL) becomes space');
test_eq(__LINE__, kona3sanitizeForLog("a\u{2028}b"), 'a b', '309 sanitize: U+2028 becomes space');
test_eq(__LINE__, kona3sanitizeForLog("a\u{2029}b"), 'a b', '309 sanitize: U+2029 becomes space');
// C1 制御文字 (Cc) も除去する
test_eq(__LINE__, kona3sanitizeForLog("a\u{0082}b"), 'a b', '309 sanitize: U+0082 (Cc) becomes space');
// 日本語は壊れず、区切りだけが置換される
test_eq(__LINE__, kona3sanitizeForLog("あ\u{2028}い"), 'あ い', '309 sanitize: multibyte text is preserved');
// ログ行の偽装ができないこと
$inj = kona3sanitizeForLog("evil@example.com\r\nFAKE LOG LINE\u{2028}MORE");
test_assert(__LINE__, strpos($inj, "\r") === FALSE && strpos($inj, "\n") === FALSE, '309 sanitize: no CR/LF remains');
test_assert(__LINE__, strpos($inj, "\u{2028}") === FALSE && strpos($inj, "\u{0085}") === FALSE, '309 sanitize: no unicode separator remains');
// 100文字制限 (ASCII / マルチバイトとも文字単位で数える)
test_eq(__LINE__, kona3sanitizeForLog(str_repeat('a', 150)), str_repeat('a', 100), '309 sanitize: truncated to 100 chars (ascii)');
$longJa = kona3sanitizeForLog(str_repeat('あ', 150));
test_eq(__LINE__, mb_strlen($longJa, 'UTF-8'), 100, '309 sanitize: truncated to 100 chars (multibyte, not bytes)');
test_assert(__LINE__, mb_check_encoding($longJa, 'UTF-8'), '309 sanitize: truncated multibyte stays valid UTF-8');
// 引数で長さを指定できる
test_eq(__LINE__, kona3sanitizeForLog('abcdef', 3), 'abc', '309 sanitize: custom max length');
// 不正な UTF-8 でも null や空にならず、制御文字だけが除去される
$bad = kona3sanitizeForLog("\xFF\xFEab\r\ncd");
test_assert(__LINE__, is_string($bad), '309 sanitize: invalid UTF-8 returns a string');
test_eq(__LINE__, $bad, "\xFF\xFEab  cd", '309 sanitize: invalid UTF-8 bytes are kept, controls removed');
// 非文字列と $maxLen<=0 のエッジケース (agy レビューの低2で追加)
test_eq(__LINE__, kona3sanitizeForLog(12345), '12345', '309 sanitize: int input is stringified');
test_eq(__LINE__, kona3sanitizeForLog(null), '', '309 sanitize: null becomes empty string');
test_eq(__LINE__, kona3sanitizeForLog(['x']), '', '309 sanitize: array becomes empty string');
test_eq(__LINE__, kona3sanitizeForLog('abc', 0), 'abc', '309 sanitize: maxLen 0 means no truncation');
test_eq(__LINE__, kona3sanitizeForLog('abc', -1), 'abc', '309 sanitize: negative maxLen means no truncation');

// --- 低1: 管理者ファイル破損時の error_log は DB 経路でも残る ---
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
// error_log の出力先を一時ファイルに変えて 1 回の呼び出し分を捕捉する
$captureLog = function ($fn) {
    $logFile = tempnam(sys_get_temp_dir(), 'kona3_309_log_');
    $prevLog = ini_get('error_log');
    ini_set('error_log', $logFile);
    try {
        $result = $fn();
    } finally {
        ini_set('error_log', $prevLog === false ? '' : $prevLog);
    }
    $content = file_exists($logFile) ? file_get_contents($logFile) : '';
    @unlink($logFile);
    return [$result, $content];
};

@unlink($adminFile);
kona3createAdminUser('admin309@example.com', 'admin309-pw');
$mkUser(151020, 'db309@example.com', 'db309-pw', 1);
$mkUser(151021, 'db309off@example.com', 'db309off-pw', 0);
// 重なりケース用: 管理者と同じメールで DB に無効化行がある
$mkUser(151022, 'admin309@example.com', 'admin309-db-pw', 0);
// Unicode 区切りを含むメール (DB に存在するユーザーとして登録する)
$uniEmail = "db309uni@example.com\u{2028}FAKE";
$mkUser(151023, $uniEmail, 'db309uni-pw', 1);
file_put_contents($adminFile, "this is not a jsonphp file");

// DBユーザーのパスワード不一致: 理由は invalid のまま、破損ログは残る
list($res, $log) = $captureLog(function () {
    return kona3tryLoginResult('db309@example.com', 'wrong-pw');
});
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_INVALID, '309 log: db user wrong password is still invalid');
test_assert(__LINE__, strpos($log, 'admin user file could not be loaded (user=db309@example.com)') !== FALSE, '309 log: corrupt admin file is logged even when db user is found');
// 1回の試行で二重に記録しない
test_eq(__LINE__, substr_count($log, 'admin user file could not be loaded'), 1, '309 log: logged exactly once per attempt');

// DBユーザーの無効化: 理由は disabled のまま、破損ログは残る
list($res, $log) = $captureLog(function () {
    return kona3tryLoginResult('db309off@example.com', 'db309off-pw');
});
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_DISABLED, '309 log: db disabled user is still user_disabled');
test_assert(__LINE__, strpos($log, 'admin user file could not be loaded (user=db309off@example.com)') !== FALSE, '309 log: corrupt admin file is logged for disabled db user');

// 重なりケース: 管理者本人でも DB 側の理由が返る (#306 の優先順位は維持)
// が、破損ログは残るので管理者は破損に気づける
list($res, $log) = $captureLog(function () {
    return kona3tryLoginResult('admin309@example.com', 'admin309-db-pw');
});
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_DISABLED, '309 log: overlapped admin email still returns db reason');
test_assert(__LINE__, strpos($log, 'admin user file could not be loaded (user=admin309@example.com)') !== FALSE, '309 log: corrupt admin file is logged for overlapped admin email');

// DB 経路でもログ中のユーザー名はサニタイズされる (U+2028 を含まない)
list($res, $log) = $captureLog(function () use ($uniEmail) {
    return kona3tryLoginResult($uniEmail, 'wrong-pw');
});
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_INVALID, '309 log: unicode db user wrong password is invalid');
test_assert(__LINE__, strpos($log, 'user=db309uni@example.com FAKE') !== FALSE, '309 log: unicode separator is sanitized in log');
test_assert(__LINE__, strpos($log, "\u{2028}") === FALSE, '309 log: no U+2028 byte sequence remains in log');
test_assert(__LINE__, strpos($log, "\r") === FALSE, '309 log: no CR remains in log');

// DBに存在しないユーザー (従来経路): system_error でログも残る
list($res, $log) = $captureLog(function () {
    return kona3tryLoginResult('nobody309@example.com', 'any-pw');
});
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_SYSTEM, '309 log: unknown user with corrupt admin file is system_error');
test_assert(__LINE__, strpos($log, 'admin user file could not be loaded (user=nobody309@example.com)') !== FALSE, '309 log: corrupt admin file is logged for unknown user');

$delUser(151020);
$delUser(151021);
$delUser(151022);
$delUser(151023);
$restoreAdmin();

// 管理者ファイルが正常なときは破損ログを出さない
list($res, $log) = $captureLog(function () {
    return kona3tryLoginResult('nobody309@example.com', 'any-pw');
});
test_eq(__LINE__, $res['reason'], KONA3_LOGIN_REASON_INVALID, '309 log: healthy admin file gives invalid for unknown user');
test_assert(__LINE__, strpos($log, 'admin user file could not be loaded') === FALSE, '309 log: no corrupt log when admin file is healthy');

// --- 低3/低5: ログイン成功時にセッション内のログイントークンが変わる ---
// login_handle_success() は最後に kona3showMessage() で exit するため、
// static_mode にして Kona3StaticException で受け、exit を回避する
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
global $kona3conf;
$savedSession309 = $_SESSION;
$savedStatic309 = isset($kona3conf['static_mode']) ? $kona3conf['static_mode'] : FALSE;
$_SESSION = [];
$tokenBefore = kona3_getEditToken(KONA3_LOGIN_CSRF_KEY);
$kona3conf['static_mode'] = TRUE;
$reachedMessage = FALSE;
// テストランナーは出力済みのため session_regenerate_id() の警告が
// 出る環境がある (トークン更新自体は行われる)。その警告だけ抑える。
set_error_handler(function ($errno, $errstr) {
    return strpos($errstr, 'Session ID cannot be regenerated') !== FALSE;
});
try {
    login_handle_success('FrontPage', 'db309@example.com', '127.0.0.1', FALSE);
} catch (Kona3StaticException $e) {
    $reachedMessage = TRUE;
} finally {
    restore_error_handler();
}
$tokenKey309 = kona3_getEditTokenKeyName(KONA3_LOGIN_CSRF_KEY);
$tokenAfter = isset($_SESSION[$tokenKey309]) ? $_SESSION[$tokenKey309] : '';
test_assert(__LINE__, $reachedMessage, '309 rotate: login_handle_success reached the success message');
test_assert(__LINE__, $tokenBefore !== '' && $tokenAfter !== '', '309 rotate: login token exists before and after success');
test_ne(__LINE__, $tokenAfter, $tokenBefore, '309 rotate: login token in session changes on success');
// 更新後のトークンが現在のトークンとしてそのまま使える
test_eq(__LINE__, kona3_getEditToken(KONA3_LOGIN_CSRF_KEY), $tokenAfter, '309 rotate: rotated token is the current token');
$kona3conf['static_mode'] = $savedStatic309;
$_SESSION = $savedSession309;

// 後片付け
$restoreAdmin();
