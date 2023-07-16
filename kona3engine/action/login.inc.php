<?php

function kona3_action_login() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $action = kona3getPageURL($page, "login");
  
  $am   = kona3param('a_mode', '');
  $user = kona3param('a_user', '');
  $pw   = kona3param('a_pw',   '');
  $autologin = kona3param('autologin', '');
  $msg = '';

  // check user
  if ($am == "trylogin") {
    // check edit_token
    if (!kona3_checkEditToken()) {
      $url = kona3getPageURL($page, 'login');
      kona3showMessage(lang('Invalid Token'), 
        "<a href='$url'>".lang('Login')."</a>");
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
      $editLink = kona3getPageURL($page, 'edit');
      $m_success = lang('Login successful.');
      $msg .= "<a href='$editLink'>$m_success</a>";
      kona3showMessage($page, $msg);
      exit;
    } else {
      $msg = lang('Invalid User or Password.');
    }
  }
  else if ($am == 'autologin') {
    header('Content-Type', 'application/json');
    $email = kona3param('email', '');
    $token  = kona3param('token', '');
    $res = kona3_checkAutoLoginToken($email, $token);
    if (!$res['result']) {
      echo json_encode([
        'result' => False,
        'message' => $res['reason'],
        'email' => $email,
        'token' => $token,
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
  
  // show
  $kona3conf["robots"] = "noindex";
  kona3template('login.html', array(
    "page_title" => $page,
    "msg" => $msg,
    "action" => $action,
    "signup_link" => kona3getPageURL($page, 'signup'),
    "edit_token" => kona3_getEditToken(),
  ));
}





