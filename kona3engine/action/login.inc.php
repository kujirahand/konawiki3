<?php
/** login action */
define("KONA3_LOGIN_CSRF_KEY", "login_token");

/** login action */
function kona3_action_login()
{
    global $kona3conf;
    // get parameters
    $page = kona3param('page', '');
    $action = kona3getPageURL($page, "login");
    $am   = kona3post('a_mode', '');
    $user = kona3post('a_email', '');
    $pw   = kona3post('a_pw',   '');
    $remember = kona3post('a_remember', '') === 'yes';
    if (!is_string($user)) $user = '';
    if (!is_string($pw)) $pw = '';
    $msg = '';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    if ($am == "trylogin") {
        // CSRFトークンのエラーは、パスワード以前の問題であることを
        // ログインフォーム上に明示する (#132)。
        // 以前は専用のメッセージ画面に飛ばしていたため、利用者には
        // パスワードを疑うしかなかった。
        $tokenReason = kona3_checkEditTokenReason(KONA3_LOGIN_CSRF_KEY);
        if ($tokenReason !== 'ok') {
            error_log("KonaWiki3 Login: token check failed (reason={$tokenReason})");
            $msg = login_token_error_message($tokenReason);
        } else {
            $res = kona3tryLoginResult($user, $pw);
            if ($res['ok']) {
                login_handle_success($page, $user, $ip, $remember);
                return;
            }
            // ユーザー名かパスワードが違う場合はその旨を、それ以外の
            // 理由 (未入力・無効化されたアカウント・システムエラー) は
            // 本当の理由を、ログインフォームに表示する (#132)
            $msg = lang(kona3loginReasonLangKey($res['reason']));
        }
    }
    // --- ログインフォームの表示 (失敗時もフォームに理由を表示する) ---
    // トークンは毎回作り直さない (#132 の根本対策)。
    // 以前は表示のたびに強制更新していたため、ログインフォームを
    // 別のタブで開いたり開き直したりすると、先に開いていたフォームの
    // トークンが無効になり、正しいパスワードでもログインできなかった。
    // 有効期間内 (kona3_getEditToken の既定は1日) は同じトークンを使い回す。
    $loginToken = kona3_getEditToken(KONA3_LOGIN_CSRF_KEY);
    $kona3conf["robots"] = "noindex";
    kona3template('login.html', [
        "login_token" => $loginToken,
        "page_title" => $page,
        "msg" => $msg,
        "action" => $action,
        "a_email" => $user,
        "signup_link" => kona3getPageURL($page, 'signup'),
    ]);
}

// --- CSRFトークンエラーの理由から表示文言を引く (#132) ---
// どの理由でも「パスワードは確認されていない」ことを明示し、
// 利用者がパスワードを疑わなくて済むようにする。
function login_token_error_message($tokenReason)
{
    switch ($tokenReason) {
        case 'no_session_token':
            return lang('Login session expired.');
        case 'no_post_token':
            return lang('Login token missing.');
        case 'mismatch':
        default:
            return lang('Login token mismatch.');
    }
}

// --- 認証成功時の処理 ---
function login_handle_success($page, $user, $ip, $remember = FALSE) {
    session_regenerate_id(true);
    // 認証前後でログイントークンを分離する (#306)
    kona3_getEditTokenForceUpdate(KONA3_LOGIN_CSRF_KEY);
    if ($remember) {
        kona3remember_issue();
    } else {
        kona3remember_logout();
    }
    $editToken = kona3_getEditToken($page, TRUE);
    $editLink = kona3getPageURL($page, 'edit', '');
    $m_success = lang('Login successful.');
    $msg = implode("", [
        "<form action='$editLink' method='post'>",
        "<input type='hidden' name='edit_token' value='$editToken'>",
        "<input type='submit' class='pure-button pure-button-primary' value='$m_success'>",
        "</form>",
    ]);
    db_exec("DELETE FROM meta WHERE name='login_error' AND value_s=?", [$ip]);
    $old_time = time() - 60 * 1;
    db_exec("DELETE FROM meta WHERE name='login_error' AND value_i < ?", [$old_time]);
    kona3showMessage($page, $msg);
}
