<?php
require_once __DIR__ . '/test_common.inc.php';

// --- セキュリティ監査フォローアップ (#325) の回帰テスト ---
// signup 認証コード / login スロットリング / comment todo の仕様 /
// pdf の use_pdf_out / mermaid SVG検証 / 全削除のPOST化

require_once dirname(__DIR__) . '/action/signup.inc.php';
require_once dirname(__DIR__) . '/action/pdf.inc.php';
require_once dirname(__DIR__) . '/plugins/mermaid.inc.php';
require_once dirname(__DIR__) . '/plugins/comment.inc.php';

global $kona3conf;
$now325 = time();

// ============================================================
// 1. signup
// ============================================================

// --- 有効期限 ---
test_assert(__LINE__, !signup_code_is_expired(['mtime' => $now325], $now325), 'signup: 発行直後のコードは有効');
test_assert(__LINE__, !signup_code_is_expired(['mtime' => $now325 - SIGNUP_CODE_TTL + 10], $now325), 'signup: 期限内のコードは有効');
test_assert(__LINE__, signup_code_is_expired(['mtime' => $now325 - SIGNUP_CODE_TTL - 10], $now325), 'signup: 期限切れのコードは無効');
test_assert(__LINE__, signup_code_is_expired(['mtime' => 0], $now325), 'signup: 発行時刻不明のコードは無効 (fail-closed)');

// --- 再送間隔 ---
test_assert(__LINE__, signup_resend_is_too_soon(['mtime' => $now325], $now325), 'signup: 発行直後の再送は早すぎる');
test_assert(__LINE__, !signup_resend_is_too_soon(['mtime' => $now325 - SIGNUP_RESEND_INTERVAL - 10], $now325), 'signup: 間隔を過ぎれば再送できる');

// --- 照合失敗の記録と上限 ---
$sg_email = 'attempts325@example.com';
signup_clear_verify_failures($sg_email);
$issued325 = $now325 - 100;
test_eq(__LINE__, signup_verify_failure_count($sg_email, $issued325), 0, 'signup: 失敗記録は初期状態で0');
signup_record_verify_failure($sg_email, $now325 - 50);
signup_record_verify_failure($sg_email, $now325 - 40);
test_eq(__LINE__, signup_verify_failure_count($sg_email, $issued325), 2, 'signup: 失敗を記録して数えられる');
// 発行より前の失敗は数えない (再発行でリセットされる仕様)
db_exec("INSERT INTO meta (name, value_s, value_i) VALUES ('signup_error', ?, ?)", [$sg_email, $issued325 - 1000]);
test_eq(__LINE__, signup_verify_failure_count($sg_email, $issued325), 2, 'signup: 発行より前の失敗は数えない');
signup_clear_verify_failures($sg_email);
test_eq(__LINE__, signup_verify_failure_count($sg_email, $issued325), 0, 'signup: 失敗記録をクリアできる');

// --- 未認証アカウントの上書き禁止 ---
$pending_email = 'pending325@example.com';
db_exec("DELETE FROM users WHERE email=?", [$pending_email]);
$orig_hash = kona3getHash('original-pw-325');
db_exec(
    "INSERT INTO users (name, email, password, token, perm, enabled, ctime, mtime) VALUES (?,?,?,?,?,?,?,?)",
    ['pending-user', $pending_email, $orig_hash, '123456', 'normal', 0, $now325, $now325]
);
$sg_msg = '';
$sg_ok = signup_execute('attacker325', $pending_email, 'attacker-pw-325', $sg_msg);
test_eq(__LINE__, $sg_ok, FALSE, 'signup: 未認証アカウントがある場合は再登録を拒否する');
test_assert(__LINE__, $sg_msg !== '', 'signup: 拒否理由を表示する');
$pending_row = db_get1("SELECT * FROM users WHERE email=?", [$pending_email]);
test_eq(__LINE__, $pending_row['name'], 'pending-user', 'signup: 未認証行の名前は上書きされない');
test_eq(__LINE__, $pending_row['password'], $orig_hash, 'signup: 未認証行のパスワードは上書きされない');
test_eq(__LINE__, $pending_row['token'], '123456', 'signup: 未認証行の認証コードは上書きされない');
db_exec("DELETE FROM users WHERE email=?", [$pending_email]);

// --- 期限切れの未認証行は削除され、登録をやり直せる (agyレビュー対応) ---
$expired_email = 'expired325@example.com';
db_exec("DELETE FROM users WHERE email=?", [$expired_email]);
$expired_mtime = $now325 - SIGNUP_CODE_TTL - 100;
db_exec(
    "INSERT INTO users (name, email, password, token, perm, enabled, ctime, mtime) VALUES (?,?,?,?,?,?,?,?)",
    ['expired-user', $expired_email, kona3getHash('expired-pw-325'), '999999', 'normal', 0, $expired_mtime, $expired_mtime]
);
$saved_host325 = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
$saved_static325 = isset($kona3conf['static_mode']) ? $kona3conf['static_mode'] : null;
$_SERVER['HTTP_HOST'] = 'localhost'; // メール送信ではなくデバッグ表示にする
$kona3conf['static_mode'] = TRUE; // kona3showMessage を例外で止める
$sg_msg2 = '';
try {
    signup_execute('renewed-user', $expired_email, 'renewed-pw-325', $sg_msg2);
} catch (Throwable $e) {
    // 成功時のメッセージ表示 (static_mode では例外) はここでは無視する
}
if ($saved_host325 === null) { unset($_SERVER['HTTP_HOST']); } else { $_SERVER['HTTP_HOST'] = $saved_host325; }
if ($saved_static325 === null) { unset($kona3conf['static_mode']); } else { $kona3conf['static_mode'] = $saved_static325; }
$renewed_row = db_get1("SELECT * FROM users WHERE email=?", [$expired_email]);
test_assert(__LINE__, $renewed_row !== FALSE && $renewed_row !== null, 'signup: 期限切れ後は再登録の行が作られる');
test_eq(__LINE__, $renewed_row['name'], 'renewed-user', 'signup: 期限切れ後は新しい申込者で登録できる');
test_eq(__LINE__, $renewed_row['password'], kona3getHash('renewed-pw-325'), 'signup: 期限切れ後は新しいパスワードで登録できる');
test_assert(__LINE__, $renewed_row['token'] !== '999999' && $renewed_row['token'] !== '', 'signup: 期限切れ後の再登録では新しいコードが発行される');
test_eq(__LINE__, intval($renewed_row['enabled']), 0, 'signup: 再登録も未認証から始まる');
db_exec("DELETE FROM users WHERE email=?", [$expired_email]);

// --- ソース確認: CSRF と各制限が結線されていること ---
$src_signup = file_get_contents(dirname(__DIR__) . '/action/signup.inc.php');
test_assert(__LINE__, strpos($src_signup, "kona3_checkEditToken(SIGNUP_CSRF_KEY)") !== FALSE, 'signup: 状態変更にCSRFトークンを要求する');
test_assert(__LINE__, strpos($src_signup, 'signup_code_is_expired') !== FALSE, 'signup: 有効期限を検証する');
test_assert(__LINE__, strpos($src_signup, 'SIGNUP_MAX_VERIFY_ATTEMPTS') !== FALSE, 'signup: 試行回数制限がある');
test_assert(__LINE__, strpos($src_signup, 'REQUEST_METHOD') !== FALSE, 'signup: 再送はPOSTのみ');
$tpl_signup = file_get_contents(dirname(__DIR__) . '/template/signup.html');
test_assert(__LINE__, strpos($tpl_signup, 'name="signup_token"') !== FALSE, 'signup: 登録フォームにCSRFトークンがある');
$tpl_verify = file_get_contents(dirname(__DIR__) . '/template/signup_verify.html');
test_assert(__LINE__, strpos($tpl_verify, 'name="signup_token"') !== FALSE, 'signup: 認証フォームにCSRFトークンがある');
test_assert(__LINE__, strpos($tpl_verify, 'a_mode=resend&a_email') === FALSE, 'signup: 再送はGETリンクではない');
test_assert(__LINE__, strpos($tpl_verify, 'name="a_mode" value="resend"') !== FALSE, 'signup: 再送はPOSTフォームである');

// ============================================================
// 2. login スロットリング
// ============================================================
$saved_remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
$th_ip = '203.0.113.99';
$_SERVER['REMOTE_ADDR'] = $th_ip;
$th_email = 'throttle325@example.com';
db_exec("DELETE FROM users WHERE email=?", [$th_email]);
db_exec("DELETE FROM meta WHERE name='login_error'");
db_exec(
    "INSERT INTO users (user_id, name, email, password, perm, enabled, ctime, mtime) VALUES (?,?,?,?,?,?,?,?)",
    [325001, 'throttle-user', $th_email, kona3getHash('throttle-pw-325'), 'normal', 1, $now325, $now325]
);
for ($i = 0; $i < KONA3_LOGIN_THROTTLE_MAX_PER_USER; $i++) {
    $rth = kona3tryLoginResult($th_email, 'wrong-pw-325');
    test_eq(__LINE__, $rth['reason'], KONA3_LOGIN_REASON_INVALID, "login throttle: 上限までの失敗は通常の認証失敗 ($i)");
}
test_eq(__LINE__, kona3loginThrottleFailureCount($th_ip, $th_email), KONA3_LOGIN_THROTTLE_MAX_PER_USER, 'login throttle: 失敗が記録される');
// 上限到達後は、正しいパスワードでも照合せずに拒否する
$rth = kona3tryLoginResult($th_email, 'throttle-pw-325');
test_eq(__LINE__, $rth['ok'], FALSE, 'login throttle: 上限到達後は正しいパスワードでも拒否');
test_eq(__LINE__, $rth['reason'], KONA3_LOGIN_REASON_THROTTLED, 'login throttle: 理由は throttled');
test_eq(__LINE__, kona3loginReasonLangKey(KONA3_LOGIN_REASON_THROTTLED), 'Too many login attempts.', 'login throttle: 表示文言がある');
// クリアすればログインできる
kona3loginThrottleClear($th_ip, $th_email);
test_eq(__LINE__, kona3loginThrottleFailureCount($th_ip, $th_email), 0, 'login throttle: クリアできる');
$rth = kona3tryLoginResult($th_email, 'throttle-pw-325');
test_eq(__LINE__, $rth['ok'], TRUE, 'login throttle: クリア後はログインできる');
kona3logout();
// IP全体の上限: 別ユーザーへの失敗を積むと、そのIPの新規ユーザーがブロックされる
$ip2 = '198.51.100.7';
for ($i = 0; $i < KONA3_LOGIN_THROTTLE_MAX_PER_IP; $i++) {
    kona3loginThrottleRecordFailure($ip2, "other{$i}@example.com", $now325);
}
test_assert(__LINE__, kona3loginThrottleIsBlocked($ip2, 'never-tried@example.com', $now325), 'login throttle: IP全体の上限でもブロックする');
test_assert(__LINE__, !kona3loginThrottleIsBlocked('192.0.2.1', 'never-tried@example.com', $now325), 'login throttle: 関係ないIPはブロックしない');
// 空IP (CLI等) ではIP全体の集計をせず、アカウント単位だけが効く (agyレビュー対応)
for ($i = 0; $i < KONA3_LOGIN_THROTTLE_MAX_PER_IP; $i++) {
    kona3loginThrottleRecordFailure('', "cli-other{$i}@example.com", $now325);
}
test_assert(__LINE__, !kona3loginThrottleIsBlocked('', 'cli-new@example.com', $now325), 'login throttle: 空IPではIP全体上限を適用しない');
for ($i = 0; $i < KONA3_LOGIN_THROTTLE_MAX_PER_USER; $i++) {
    kona3loginThrottleRecordFailure('', 'cli-same@example.com', $now325);
}
test_assert(__LINE__, kona3loginThrottleIsBlocked('', 'cli-same@example.com', $now325), 'login throttle: 空IPでもアカウント単位の上限は効く');
db_exec("DELETE FROM meta WHERE name='login_error'");
db_exec("DELETE FROM users WHERE email=?", [$th_email]);
if ($saved_remote === null) { unset($_SERVER['REMOTE_ADDR']); } else { $_SERVER['REMOTE_ADDR'] = $saved_remote; }
$src_login = file_get_contents(dirname(__DIR__) . '/action/login.inc.php');
test_assert(__LINE__, strpos($src_login, 'login_error') === FALSE || strpos($src_login, 'kona3loginThrottleClear') !== FALSE, 'login: 成功時の掃除はスロットリング用ヘルパーを使う');

// ============================================================
// 3. comment todo は協調利用が仕様であること (#325)
// ============================================================
// ログイン済み (投稿者でなくても) には切替コントロールを出す
kona3logout();
$guest_ctl = kona3plugins_comment_renderTodoControl('todo', 1);
test_assert(__LINE__, strpos($guest_ctl, '<span') !== FALSE && strpos($guest_ctl, 'chtodo') === FALSE, 'comment todo: 未ログインには切替操作を出さない');
kona3login('Todo User 325', 'todo325@example.com', 'normal', 325002);
$login_ctl = kona3plugins_comment_renderTodoControl('todo', 1);
test_assert(__LINE__, strpos($login_ctl, 'chtodo') !== FALSE, 'comment todo: ログイン済みには協調利用として切替操作を出す');
kona3logout();
$src_comment = file_get_contents(dirname(__DIR__) . '/plugins/comment.inc.php');
test_assert(__LINE__, strpos($src_comment, '協調利用') !== FALSE, 'comment todo: 協調利用の仕様がdocblockに明記されている');
$pos_todo = strpos($src_comment, 'if ($m == "todo")');
$todo_seg = substr($src_comment, $pos_todo, 400);
test_assert(__LINE__, strpos($todo_seg, 'kona3isLogin()') !== FALSE, 'comment todo: ログインが必要');
test_assert(__LINE__, strpos($todo_seg, "kona3_checkEditToken('edit_token')") !== FALSE, 'comment todo: edit_tokenが必要');

// ============================================================
// 4. pdf の use_pdf_out
// ============================================================
$saved_pdf = isset($kona3conf['use_pdf_out']) ? $kona3conf['use_pdf_out'] : null;
unset($kona3conf['use_pdf_out']);
test_eq(__LINE__, kona3_action_pdf_isEnabled(), FALSE, 'pdf: 既定では無効');
$kona3conf['use_pdf_out'] = TRUE;
test_eq(__LINE__, kona3_action_pdf_isEnabled(), TRUE, 'pdf: 設定で有効化できる');
$kona3conf['use_pdf_out'] = FALSE;
test_eq(__LINE__, kona3_action_pdf_isEnabled(), FALSE, 'pdf: 設定で無効化できる');
if ($saved_pdf === null) { unset($kona3conf['use_pdf_out']); } else { $kona3conf['use_pdf_out'] = $saved_pdf; }
$src_pdf = file_get_contents(dirname(__DIR__) . '/action/pdf.inc.php');
$pos_pdf_check = strpos($src_pdf, 'kona3_action_pdf_isEnabled()');
$pos_tcpdf = strpos($src_pdf, 'vendor/tecnickcom/tcpdf');
test_assert(__LINE__, $pos_pdf_check !== FALSE && $pos_tcpdf !== FALSE && $pos_pdf_check < $pos_tcpdf, 'pdf: TCPDF読み込みより前に use_pdf_out を確認する');

// ============================================================
// 5. mermaid SVG検証
// ============================================================
$valid325 = '<svg xmlns="http://www.w3.org/2000/svg"><defs><marker id="m"><path d="M0,0 L10,5"/></marker></defs><style>.label{font-family:sans-serif;fill:#333}</style><g><rect x="0" y="0" width="10" height="10" style="fill:#fff"/><text>OK</text><a href="#frag"><text>link</text></a></g></svg>';
test_assert(__LINE__, kona3plugins_mermaid_validate_svg($valid325), 'mermaid: 通常のSVGは通る');
test_assert(__LINE__, kona3plugins_mermaid_validate_svg('<svg xmlns="http://www.w3.org/2000/svg"><g><text style="marker-end:url(#m)">x</text></g></svg>'), 'mermaid: style属性の url(#fragment) は通る');
test_assert(__LINE__, !kona3plugins_mermaid_validate_svg('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'mermaid: scriptは拒否');
// #325で素通りした2種
test_assert(__LINE__, !kona3plugins_mermaid_validate_svg('<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/svg+xml;base64,PHN2Zz48c2NyaXB0PmFsZXJ0KDEpPC9zY3JpcHQ+PC9zdmc+"/></svg>'), 'mermaid: data: URIの入れ子SVG画像は拒否');
test_assert(__LINE__, !kona3plugins_mermaid_validate_svg('<svg xmlns="http://www.w3.org/2000/svg"><a href="java&#9;script:alert(1)"><text>x</text></a></svg>'), 'mermaid: 実体参照でタブ分割した javascript: href は拒否');
test_assert(__LINE__, !kona3plugins_mermaid_validate_svg("<svg xmlns=\"http://www.w3.org/2000/svg\"><a href=\"java\tscript:alert(1)\"><text>x</text></a></svg>"), 'mermaid: 実タブで分割した javascript: href は拒否');
test_assert(__LINE__, !kona3plugins_mermaid_validate_svg('<svg xmlns="http://www.w3.org/2000/svg"><a href="&#106;avascript:alert(1)"><text>x</text></a></svg>'), 'mermaid: 実体参照で隠した javascript: href は拒否');
test_assert(__LINE__, !kona3plugins_mermaid_validate_svg('<svg xmlns="http://www.w3.org/2000/svg"><use href="https://example.com/evil.svg#x"/></svg>'), 'mermaid: 外部参照の use は拒否');
test_assert(__LINE__, !kona3plugins_mermaid_validate_svg('<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div>html</div></foreignObject></svg>'), 'mermaid: foreignObjectは拒否');
test_assert(__LINE__, !kona3plugins_mermaid_validate_svg('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><g/></svg>'), 'mermaid: onload属性は拒否');
test_assert(__LINE__, !kona3plugins_mermaid_validate_svg('<svg xmlns="http://www.w3.org/2000/svg"><style>@import url(https://example.com/x.css);</style><g/></svg>'), 'mermaid: styleの@importは拒否');
test_assert(__LINE__, !kona3plugins_mermaid_validate_svg('<svg xmlns="http://www.w3.org/2000/svg"><a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=="><text>x</text></a></svg>'), 'mermaid: data: のhrefは拒否');
test_assert(__LINE__, !kona3plugins_mermaid_validate_svg('not svg'), 'mermaid: SVGでない入力は拒否');

// ============================================================
// 6. 全削除のPOST化
// ============================================================
$src_uph325 = file_get_contents(dirname(__DIR__) . '/action/update_page_history.inc.php');
test_assert(__LINE__, strpos($src_uph325, "isset(\$_POST['clear'])") !== FALSE, 'update_page_history: 全削除はPOSTでのみ受け付ける');
test_assert(__LINE__, strpos($src_uph325, "isset(\$_GET['clear'])") === FALSE, 'update_page_history: 全削除はGETで受け付けない');
test_assert(__LINE__, strpos($src_uph325, "method='post'") !== FALSE, 'update_page_history: 全削除はPOSTフォームから行う');
test_assert(__LINE__, strpos($src_uph325, 'clear=all&edit_token=') === FALSE, 'update_page_history: 全削除のGETリンク (トークン付きURL) を作らない');
$src_email325 = file_get_contents(dirname(__DIR__) . '/action/emailLogs.inc.php');
test_assert(__LINE__, strpos($src_email325, "method='post'") !== FALSE, 'emailLogs: 全削除の確認はPOSTフォームである');
test_assert(__LINE__, strpos($src_email325, 'q=deleteall-go&') === FALSE, 'emailLogs: 全削除のGETリンクを作らない');
$pos_email_method = strpos($src_email325, "REQUEST_METHOD");
$pos_email_delete = strpos($src_email325, 'DELETE FROM email_logs');
test_assert(__LINE__, $pos_email_method !== FALSE && $pos_email_delete !== FALSE && $pos_email_method < $pos_email_delete, 'emailLogs: 全削除の実行前にPOSTであることを確認する');
