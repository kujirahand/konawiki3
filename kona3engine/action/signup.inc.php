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
  $code = trim(kona3param("a_code", ""));
  
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
  // --- 認証コード入力画面表示 ---
  if ($mode == "verify") {
    kona3template('signup_verify.html', array(
      "action" => $action,
      "email" => $email,
      "msg" => "",
    ));
    exit;
  }
  // --- 認証コードの再送信処理 ---
  if ($mode == "resend") {
    // メールアドレスで未認証のユーザーを検索
    $r = db_get1(
      "SELECT * FROM users WHERE email=? AND enabled=0",
      [$email]);
    if ($r == null) {
      kona3template('signup_verify.html', array(
        "action" => $action,
        "email" => $email,
        "msg" => lang('Code not found'),
      ));
      exit;
    }
    // 新しい6桁の認証コードを生成
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    // DBの認証コードを更新
    db_exec("UPDATE users SET token=?, mtime=? WHERE email=? AND enabled=0",
      [$code, time(), $email]);
    // 認証メール送信
    global $kona3conf;
    $wiki_title = $kona3conf['wiki_title'];
    $admin_email = $kona3conf['admin_email'];
    $signup_body = sprintf(
      lang("Verification code message")."\n".
      "------------\n".
      "$wiki_title<%s>",
      $code, $admin_email);
    $signup_title = "[$wiki_title] ".lang('Signup');
    // localhostの場合はデバッグ表示
    if (preg_match('/^(localhost|localhost\:\d+)$/', $_SERVER['HTTP_HOST'])) {
      $signup_body2 = htmlspecialchars($signup_body);
      $msg = lang('Verification code has been resent') . "<pre>DEBUG:\n$signup_body2</pre>";
    } else {
      kona3lib_send_email($email, $signup_title, $signup_body);
      $msg = lang('Verification code has been resent');
    }
    // 認証コード入力画面に戻る
    kona3template('signup_verify.html', array(
      "action" => $action,
      "email" => $email,
      "msg" => $msg,
    ));
    exit;
  }
  // --- 認証コードによる有効化処理 ---
  if ($mode == "verify_code") {
    $r = db_get1(
      "SELECT * FROM users WHERE email=? AND token=?",
      [$email, $code]);
    if ($r == null) {
      // コードが間違っている場合、再度入力画面を表示
      kona3template('signup_verify.html', array(
        "action" => $action,
        "email" => $email,
        "msg" => lang('Invalid verification code'),
      ));
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
  // 有効化済みのユーザー名チェック
  $r = db_get1(
    "SELECT * FROM users WHERE name=? AND enabled=1", [
      $user]);
  if ($r != null) {
    $msg = lang('The username alredy registerd.');
    return FALSE;
  }
  // 有効化済みのメールアドレスチェック
  $r = db_get1(
    "SELECT * FROM users WHERE email=? AND enabled=1", [$email]);
  if ($r != null) {
    $msg = lang('The email already registerd.');
    return FALSE;
  }
  // --- 未認証の既存ユーザーチェック ---
  // 同じメールアドレスで未認証のユーザーが既に存在する場合
  $existing = db_get1(
    "SELECT * FROM users WHERE email=? AND enabled=0", [$email]);
  
  // 6桁の数字コードを生成
  $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  
  if ($existing != null) {
    // 既存の未認証ユーザーの情報を更新（認証コードを新規生成）
    try {
      db_exec("UPDATE users SET name=?, password=?, token=?, mtime=? WHERE email=? AND enabled=0",
        [$user, kona3getHash($pw), $code, time(), $email]);
    } catch (Exception $e) {
      kona3error("Database Error", $e->getMessage());
      exit;
    }
  } else {
    // --- ユーザー情報をDBに新規登録（enabled=0で仮登録） ---
    try {
      $id = db_insert("INSERT INTO users".
        "(name, email, password, token, enabled, ctime, mtime)".
        "VALUES(?,?,?,?,?,?,?)",[
          $user, $email, kona3getHash($pw), $code, 0, time(), time()
        ]);
    } catch (Exception $e) {
      kona3error("Database Error", $e->getMessage());
      exit;
    }
  }
  // --- 認証メール送信処理 ---
  global $kona3conf;
  $wiki_title = $kona3conf['wiki_title'];
  $admin_email = $kona3conf['admin_email'];
  $signup_body = sprintf(
    lang("Verification code message")."\n".
    "------------\n".
    "$wiki_title<%s>",
    $code, $admin_email);
  $signup_title = "[$wiki_title] ".lang('Signup');
  $body_msg = lang('Please check email for code.');
  // --- localhostの場合はメール送信せずデバッグ表示 ---
  if (preg_match('/^(localhost|localhost\:\d+)$/', $_SERVER['HTTP_HOST'])) {
    $signup_body2 = htmlspecialchars($signup_body);
    $body_msg .= "<pre>DEBUG:\n$signup_body2</pre>";
  } else {
    kona3lib_send_email($email, $signup_title, $signup_body);
  }
  // --- コード入力画面へのリンクを表示 ---
  $verify_url = kona3getPageURL("user", "signup", "", 
    kona3getURLParams([
      "a_mode" => "verify",
      "a_email" => $email,
    ]));
  kona3showMessage(lang('Success'), 
    $body_msg . "<br><br>" .
    "<a class='pure-button pure-button-primary' href='$verify_url'>" . 
    lang('Enter verification code') . "</a>");
  return TRUE;
}




