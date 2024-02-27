<?php
// login page
function kona3_action_login() {
  global $kona3conf;
  $page = kona3param('page', '');
  $action = kona3getPageURL($page, "login");
  $am   = kona3param('a_mode', '');
  $user = kona3param('a_user', '');
  $pw   = kona3param('a_pw',   '');
  $autologin = kona3param('autologin', '');
  $editTokenKey = 'login_page';
  $msg = '';
  // ログイン回数の記録に利用する
  $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

  // check user
  if ($am == "trylogin") {
    // check edit_token
    if (!kona3_checkEditToken($editTokenKey)) {
      $url = kona3getPageURL($page, 'login');
      kona3showMessage(lang('Invalid Token'), 
        "<a href='$url'>".lang('Login')."</a>");
      exit;
    }
    // 何度もログイン試行しようとするなら拒否する
    $time = time() - 60 * 10; // 10 min
    $clients = db_get("SELECT * FROM meta WHERE name='login_error' AND value_s=? AND value_i > ?", [$ip, $time]);
    if (count($clients) >= 6) {
      kona3showMessage(lang('Too many login attempts.'), lang('Please take a break and try again later.'));
      exit;
    }
    $msg = '';
    if (kona3tryLogin($user, $pw)) {
      // ok
      if ($autologin === 'yes') {
        $userInfo = kona3getLoginInfo();
        $email = $userInfo['email'];
        $perm = $userInfo['perm'];
        $autologinToken = kona3_getAutoLoginToken($email, $perm);
        $jsSrc = kona3getPageURL('kona3_autologin.js', 'resource');
        $msg .= "<script src='$jsSrc'></script><script>kona3setAutoLogin('{$user}','{$autologinToken}');</script>";
      }
      $editLink = kona3getPageURL($page, 'edit', '', "edit_token=".kona3_getEditToken($page, TRUE));
      $m_success = lang('Login successful.');
      $msg .= "<a href='$editLink' class='pure-button pure-button-primary'>$m_success</a>";
      if (kona3isAdmin()) {
        // ログイン失敗が何度あったか確認
        $login_errors = db_get("SELECT * FROM meta WHERE name='login_error'", []);
        if ($login_errors) {
          $msg .= "<div class='block2'><h3>Admin memo:</h3>\n";
          $msg .= "<p>" . lang('Recently Login Error Count') . ": " . count($login_errors) . "</p>\n";
          $msg .= "<ul>\n";
          $cnt = 0;
          foreach ($login_errors as $e) {
            $err_ip = htmlspecialchars($e['value_s']);
            $msg .= "<li>" . date('Y/m/d H:i:s', $e['value_i']) . ": $err_ip</li>\n";
            $cnt++;
            if ($cnt > 30) { break; }
          }
          $msg .= "</ul></div>\n";
        }
      }
      // このユーザーのログイン失敗の記録を削除
      db_exec("DELETE FROM meta WHERE name='login_error' AND value_s=?", [$ip]);
      // 古いログイン記録を削除
      $old_time = time() - 60 * 1; //60*60*24*3; // 3days
      db_exec("DELETE FROM meta WHERE name='login_error' AND value_i < ?", [$old_time]);
      // メッセージを表示
      kona3showMessage($page, $msg);
      exit;
    } else {
      // ng
      $msg = lang('Invalid User or Password.');
      // ログイン失敗を記録
      db_exec("INSERT INTO meta (name, value_s, value_i) VALUES ('login_error', ?, ?)", [$ip, time()]);
    }
  }
  else if ($am == 'autologin') {
    header('Content-Type', 'application/json');
    $email = kona3param('email', '');
    $token  = kona3param('token', '');
    $page_ = kona3param('page', '');
    if (strpos($page_, '?') !== FALSE) {
      list($page, $_action, $_status, $_args, $_script_path) = kona3parseURI($page_);
    }
    $res = kona3_checkAutoLoginToken($email, $token);
    if (!$res['result']) {
      echo json_encode([
        'result' => False,
        'message' => $res['reason'],
        'email' => $email,
      ]);
      exit;
    }
    echo json_encode([
      'result' => True,
      'token' => $res['token'],
      'nextUrl' => kona3getPageURL($page, 'show'),
    ]);
    exit;
  }
  // login form
  $kona3conf["robots"] = "noindex";
  kona3template('login.html', array(
    "page_title" => $page,
    "msg" => $msg,
    "action" => $action,
    "signup_link" => kona3getPageURL($page, 'signup'),
    "edit_token" => kona3_getEditToken($editTokenKey, TRUE),
  ));
}





