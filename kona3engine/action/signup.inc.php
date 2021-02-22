<?php

function kona3_action_signup() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $action = kona3getPageURL($page, "signup");
  $msg = "";
  
  // Check Config file
  if (!$kona3conf["allow_add_user"]) {
    kona3error('Config Error',
      'Please set "KONA3_ALLOW_ADD_USER" in setting.');
    exit;
  }

  // parameters
  $mode = kona3param("a_mode", "");
  $user = trim(kona3param("a_user", ""));
  $email = trim(kona3param("a_email", ""));
  $pw = trim(kona3param("a_pw", ""));
  $pw2 = trim(kona3param("a_pw2", ""));
  $token = trim(kona3param("a_token", ""));
  
  // try
  if ($mode == "try") {
    $ok = TRUE;
    if ($user == '') {
      $msg = lang('Invalid Username');
      $ok = FALSE;
    }
    if ($email == '' || !preg_match(
      "/^([a-zA-Z0-9])+([a-zA-Z0-9\?\*\[|\]%'=~^\{\}\/\+!#&\$\._-])*@([a-zA-Z0-9_-])+\.([a-zA-Z0-9\._-]+)+$/", 
      $email)) {
      $msg = lang('Invalid Email');
      $ok = FALSE;
    }
    if ($pw != $pw2) {
      $msg = lang('Passwords do not match.');
      $ok = FALSE;
    }
    if ($ok) {
      $ok = signup_execute($user, $email, $pw, $msg);
      if ($ok) return;
    }
  }
  if ($mode == "email") {
    $r = db_get1(
      "SELECT * FROM users WHERE email=? AND token=?",
      [$email, $token]);
    if ($r == null) {
      kona3error(lang("Invalid Token"), 
        lang('Invalid Token in email'));
      exit;
    }
    db_exec("UPDATE users SET enabled=1 WHERE email=?",
      [$email]);
    $url = kona3getPageURL($kona3conf['FrontPage'], 'login');
    $title = lang('Success');
    $msg = lang('Login');
    kona3showMessage($title, 
      "<a class='pure-button' href='$url'>$msg</a>");
    exit;
  }
  // render template
  $kona3conf["robots"] = "noindex";
  kona3template('signup.html', array(
    "action" => $action,
    "signup_link" => kona3getPageURL('signup'),
    "msg" => $msg,
    "user" => $user,
    "email" => $email,
  ));
}

function signup_execute($user, $email, $pw, &$msg) {
  global $kona3conf;
  // CHECK PARAMETERS
  if (isset($kona3conf['users'][$user])) {
    $msg = lang('The username alredy registerd.');
    return FALSE;
  }
  $r = db_get1(
    "SELECT * FROM users WHERE name=?", [
      $user]);
  if ($r != null) {
    $msg = lang('The username alredy registerd.');
    return FALSE;
  }
  $r = db_get1(
    "SELECT * FROM users WHERE email=?", [
      $email]);
  if ($r != null) {
    $msg = lang('The email already registerd.');
    return FALSE;
  }
  // INSERT
  try {
    $token = bin2hex(random_bytes(64));
    $id = db_insert("INSERT INTO users".
      "(name, email, password, token, enabled, ctime, mtime)".
      "VALUES(?,?,?,?,?,?,?)",[
        $user, $email, kona3getHash($pw), $token, 0, time(), time()
      ]);
  } catch (Exception $e) {
    kona3error("Database Error", $e->getMessage());
    exit;
  }
  // SEND EMAIL
  global $kona3conf;
  $wiki_title = $kona3conf['wiki_title'];
  $admin_email = $kona3conf['admin_email'];
  $signup_url = kona3getPageURL("user", "signup", "", 
    kona3getURLParams([
      "a_mode" => "email",
      "a_token" => $token,
      "a_email" => $email,
    ]));
  $signup_body = sprintf(
    lang("Please access here: %s")."\n".
    "------------\n".
    "$wiki_title<%s>",
    $signup_url, $admin_email);
  $signup_title = "[$wiki_title] ".lang('Signup');
  lib_send_email($email, $signup_title, $signup_body);
  kona3showMessage(lang('Success'), lang('Please check email.'));
  return TRUE;
}




