<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';

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
    $users = $kona3conf['users'];
    if (isset($users[$user]) && $users[$user] == $pw) {
      // ok
      $editLink = kona3getPageURL($page, 'edit');
      $m_success = lang('Success to login.');
      $msg = "<a href='$editLink'>$m_success</a>";
      kona3login();
      kona3showMessage($page, $msg);
      exit;
    } else {
      // ng
      $m_invalid = lang('Invalid User or Password.');
      $msg = "<div class=\"error\">$m_invalid</div>";
    }
  }
  
  // show
  $kona3conf["robots"] = "noindex";
  kona3template('login.html', array(
    "page_title" => $page,
    "msg" => $msg,
    "action" => $action,
  ));
}



