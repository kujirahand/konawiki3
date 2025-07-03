<?php

function kona3_action_signup() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $action = kona3getPageURL($page, "signup");
  $msg = "";
  
  // --- 設定ファイルでユーザー追加が許可されているかチェック ---
  if (!$kona3conf["allow_add_user"]) {
    kona3error('Config Error',
      'Please set "KONA3_ALLOW_ADD_USER" in setting.');
    exit;
  }

  // --- パラメータ取得 ---
  $mode = kona3param("a_mode", "");
  $user = trim(kona3param("a_user", ""));
  $email = trim(kona3param("a_email", ""));
  $pw = trim(kona3param("a_pw", ""));
  $pw2 = trim(kona3param("a_pw2", ""));
  $token = trim(kona3param("a_token", ""));
  
  // --- ユーザー登録処理（フォーム送信時） ---
  if ($mode == "try") {
    $ok = TRUE;
    // ユーザー名チェック
    if ($user == '') {
      $msg = lang('Invalid Username');
      $ok = FALSE;
    }
    // メールアドレス形式チェック
    if ($email == '' || !preg_match(
      "/^([a-zA-Z0-9])+([a-zA-Z0-9\?\*\[|\]%'=~^\{\}\/\+!#&\$\._-])*@([a-zA-Z0-9_-])+\.([a-zA-Z0-9\._-]+)+$/", 
      $email)) {
      $msg = lang('Invalid Email');
      $ok = FALSE;
    }
    // パスワード一致チェック
    if ($pw != $pw2) {
      $msg = lang('Passwords do not match.');
      $ok = FALSE;
    }
    // バリデーションOKなら登録処理
    if ($ok) {
      $ok = signup_execute($user, $email, $pw, $msg);
      if ($ok) return;
    }
  }
  // --- メール認証リンクからの有効化処理 ---
  if ($mode == "email") {
    $r = db_get1(
      "SELECT * FROM users WHERE email=? AND token=?",
      [$email, $token]);
    if ($r == null) {
      kona3error(lang("Invalid Token"), 
        lang('Invalid Token in email'));
      exit;
    }
    // ユーザー有効化
    db_exec("UPDATE users SET enabled=1 WHERE email=?",
      [$email]);
    $url = kona3getPageURL($kona3conf['FrontPage'], 'login');
    $title = lang('Success');
    $msg = lang('Login');
    kona3showMessage($title, 
      "<a class='pure-button' href='$url'>$msg</a>");
    exit;
  }
  // --- 新規登録フォームの表示 ---
  $kona3conf["robots"] = "noindex";
  kona3template('signup.html', array(
    "action" => $action,
    "signup_link" => kona3getPageURL('signup'),
    "msg" => $msg,
    "user" => $user,
    "email" => $email,
  ));
}

// --- ユーザー登録実行処理 ---
function signup_execute($user, $email, $pw, &$msg) {
  global $kona3conf;
  // --- 既存ユーザー名・メールアドレスの重複チェック ---
  if (isset($kona3conf['users'][$user])) {
    $msg = lang('The username alredy registerd.');
    return FALSE;
  }
  $r = db_get1(
    "SELECT * FROM users WHERE name=? AND enabled=1", [
      $user]);
  if ($r != null) {
    $msg = lang('The username alredy registerd.');
    return FALSE;
  }
  $r = db_get1(
    "SELECT * FROM users WHERE email=? AND enabled=1", [$email]);
  if ($r != null) {
    $msg = lang('The email already registerd.');
    return FALSE;
  }
  // --- ユーザー情報をDBに登録（enabled=0で仮登録） ---
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
  // --- 認証メール送信処理 ---
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
  $body_msg = lang('Please check email.');
  // --- localhostの場合はメール送信せずデバッグ表示 ---
  if (preg_match('/^(localhost|localhost\:\d+)$/', $_SERVER['HTTP_HOST'])) {
    $signup_body2 = htmlspecialchars($signup_body);
    $body_msg .= "<pre>DEBUG:\n$signup_body2</pre>";
  } else {
    kona3lib_send_email($email, $signup_title, $signup_body);
  }
  // --- 完了メッセージ表示 ---
  kona3showMessage(lang('Success'), $body_msg);
  return TRUE;
}




