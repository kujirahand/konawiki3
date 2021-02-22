<?php

function kona3_action_login() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $action = kona3getPageURL($page, "login");
  
  $am   = kona3param('a_mode', '');
  $user = kona3param('a_user', '');
  $pw   = kona3param('a_pw',   '');
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
    if (kona3tryLogin($user, $pw)) {
      // ok
      $editLink = kona3getPageURL($page, 'edit');
      $m_success = lang('Login successful.');
      $msg = "<a href='$editLink'>$m_success</a>";
      kona3showMessage($page, $msg);
      exit;
    } else {
      $msg = lang('Invalid User or Password.');
    }
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





