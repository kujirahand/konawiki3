<?php
// --- login.inc.php ---
// ログイン画面・ログイン処理を担当するアクション

function kona3_action_login()
{
    global $kona3conf;
    $page = kona3param('page', '');
    $action = kona3getPageURL($page, "login");
    $am   = kona3param('a_mode', '');
    $user = kona3param('a_user', '');
    $pw   = kona3param('a_pw',   '');
    $editTokenKey = 'login_page';
    $msg = '';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    if ($am == "trylogin") {
        if (!login_check_csrf($editTokenKey, $page)) return;
        if (!login_check_attempt_limit($ip)) return;
        if (kona3tryLogin($user, $pw)) {
            login_handle_success($page, $user, $ip);
            return;
        } else {
            $msg = lang('Invalid User or Password.');
            $msg .= login_admin_debug_info($user, $pw);
            login_handle_failure($ip);
        }
    }
    // --- ログインフォームの表示 ---
    $kona3conf["robots"] = "noindex";
    kona3template('login.html', [
        "page_title" => $page,
        "msg" => $msg,
        "action" => $action,
        "signup_link" => kona3getPageURL($page, 'signup'),
        "edit_token" => kona3_getEditToken($editTokenKey, TRUE),
    ]);
}

// --- CSRFトークンチェック ---
function login_check_csrf($editTokenKey, $page) {
    if (!kona3_checkEditToken($editTokenKey)) {
        $url = kona3getPageURL($page, 'login');
        kona3showMessage(
            lang('Invalid Token'),
            "<a href='$url'>" . lang('Login') . "</a>"
        );
        return false;
    }
    return true;
}

// --- ログイン試行回数制限 ---
function login_check_attempt_limit($ip) {
    $time = time() - 60 * 10; // 10分前以降
    $clients = db_get("SELECT * FROM meta WHERE name='login_error' AND value_s=? AND value_i > ?", [$ip, $time]);
    if (count($clients) >= 6) {
        kona3showMessage(lang('Too many login attempts.'), lang('Please take a break and try again later.'));
        return false;
    }
    return true;
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
    if (kona3isAdmin()) {
        $msg .= login_admin_error_list();
    }
    db_exec("DELETE FROM meta WHERE name='login_error' AND value_s=?", [$ip]);
    $old_time = time() - 60 * 1;
    db_exec("DELETE FROM meta WHERE name='login_error' AND value_i < ?", [$old_time]);
    kona3showMessage($page, $msg);
}

// --- 認証失敗時の処理（ログ記録） ---
function login_handle_failure($ip) {
    db_exec("INSERT INTO meta (name, value_s, value_i) VALUES ('login_error', ?, ?)", [$ip, time()]);
}

// --- 管理者向け: ログイン失敗履歴リストHTML ---
function login_admin_error_list() {
    $login_errors = db_get("SELECT * FROM meta WHERE name='login_error'", []);
    if (!$login_errors) return '';
    $msg = "<div class='block2'><h3>Admin memo:</h3>\n";
    $msg .= "<p>" . lang('Recently Login Error Count') . ": " . count($login_errors) . "</p>\n";
    $msg .= "<ul>\n";
    $cnt = 0;
    foreach ($login_errors as $e) {
        $err_ip = htmlspecialchars($e['value_s']);
        $msg .= "<li>" . date('Y/m/d H:i:s', $e['value_i']) . ": $err_ip</li>\n";
        $cnt++;
        if ($cnt > 30) break;
    }
    $msg .= "</ul></div>\n";
    return $msg;
}

// --- 管理者向け: 認証失敗時のデバッグ情報HTML ---
function login_admin_debug_info($user, $pw) {
    if (!kona3isAdmin()) return '';
    $debug_user = db_get1("SELECT name, enabled, password FROM users WHERE name=?", [$user]);
    if ($debug_user) {
        $debug_info = "Debug: User exists (enabled: {$debug_user['enabled']})";
        if (isset($_GET['debug']) && $_GET['debug'] == 'login') {
            $test_hash = kona3getHash($pw);
            $stored_hash = $debug_user['password'];
            $debug_info .= "<br>Computed hash: " . substr($test_hash, 0, 20) . "...";
            $debug_info .= "<br>Stored hash: " . substr($stored_hash, 0, 20) . "...";
            $debug_info .= "<br>Match: " . ($test_hash === $stored_hash ? 'YES' : 'NO');
        }
        return "<br><small style='color: gray;'>$debug_info</small>";
    } else {
        if (isset($_GET['debug']) && $_GET['debug'] == 'login') {
            return "<br><small style='color: gray;'>Debug: User not found in database</small>";
        }
    }
    return '';
}
