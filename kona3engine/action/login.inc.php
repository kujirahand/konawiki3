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
    $msg = '';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    if ($am == "trylogin") {
        if (!kona3_checkEditToken(KONA3_LOGIN_CSRF_KEY)) {
            $url = kona3getPageURL($page, 'login');
            kona3showMessage(
                lang('Invalid Token'),
                "<a href='$url'>" . lang('Login') . "</a>"
            );
            return;
        }
        if (kona3tryLogin($user, $pw)) {
            login_handle_success($page, $user, $ip);
            return;
        } else {
            $msg = lang('Invalid User or Password.');
            kona3error('Login failed.', "<a href='$action'>Please try again.</a>");
            return;
        }
    } else {
        $loginToken = kona3_getEditTokenForceUpdate(KONA3_LOGIN_CSRF_KEY);
    }
    // --- ログインフォームの表示 ---
    $kona3conf["robots"] = "noindex";
    kona3template('login.html', [
        "login_token" => $loginToken,
        "page_title" => $page,
        "msg" => $msg,
        "action" => $action,
        "signup_link" => kona3getPageURL($page, 'signup'),
    ]);
}

// --- 認証成功時の処理 ---
function login_handle_success($page, $user, $ip) {
    session_regenerate_id(true);
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

