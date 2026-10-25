<?php

// --- signup のセキュリティ制限 (#325) ---
// 認証コードは6桁数字 (100万通り) しかないため、総当たりを防ぐ制限を入れる。
if (!defined('SIGNUP_CODE_TTL')) {
  // 認証コードの有効期限 (秒): 発行 (users.mtime) から30分
  define('SIGNUP_CODE_TTL', 60 * 30);
  // 認証コードの照合に失敗できる回数 (これを超えたらコードを無効化する)
  define('SIGNUP_MAX_VERIFY_ATTEMPTS', 5);
  // 認証メールの再送間隔 (秒): これより短い間隔では再送しない
  define('SIGNUP_RESEND_INTERVAL', 60);
  // CSRFトークンのキー (ログイントークンと同じ方式)
  define('SIGNUP_CSRF_KEY', 'signup_token');
}

// 認証コード入力画面を表示して終了する
function signup_render_verify($action, $email, $msg) {
  kona3template('signup_verify.html', array(
    "action" => $action,
    "email" => $email,
    "msg" => $msg,
    "signup_token" => kona3_getEditToken(SIGNUP_CSRF_KEY),
  ));
  exit;
}

// 認証コードの発行時刻 (users.mtime) から有効期限切れかどうかを判定する
function signup_code_is_expired($row, $now = null) {
  if ($now === null) $now = time();
  $issued = isset($row['mtime']) ? intval($row['mtime']) : 0;
  if ($issued <= 0) return TRUE;
  return ($now - $issued) > SIGNUP_CODE_TTL;
}

// 認証メールの再送が間隔制限に引っかかるかどうかを判定する
function signup_resend_is_too_soon($row, $now = null) {
  if ($now === null) $now = time();
  $issued = isset($row['mtime']) ? intval($row['mtime']) : 0;
  if ($issued <= 0) return FALSE;
  return ($now - $issued) < SIGNUP_RESEND_INTERVAL;
}

// 現在の認証コードに対する照合失敗回数を数える
// (発行時刻より前の失敗は数えない: 再発行で実質リセットされる)
function signup_verify_failure_count($email, $issued = 0) {
  $rows = db_get(
    "SELECT value_i FROM meta WHERE name='signup_error' AND value_s=?",
    [$email]);
  $cnt = 0;
  foreach ($rows as $r) {
    if (intval($r['value_i']) >= intval($issued)) $cnt++;
  }
  return $cnt;
}

// 認証コードの照合失敗を記録する (古い記録は掃除する)
function signup_record_verify_failure($email, $now = null) {
  if ($now === null) $now = time();
  db_exec(
    "INSERT INTO meta (name, value_s, value_i) VALUES ('signup_error', ?, ?)",
    [$email, $now]);
  db_exec(
    "DELETE FROM meta WHERE name='signup_error' AND value_i < ?",
    [$now - SIGNUP_CODE_TTL]);
}

// 認証コードの照合失敗の記録を消す (成功時・再発行時)
function signup_clear_verify_failures($email) {
  db_exec(
    "DELETE FROM meta WHERE name='signup_error' AND value_s=?",
    [$email]);
}

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

  // --- 状態変更を伴うモードはCSRFトークンを要求する (#325) ---
  // フォーム (signup.html / signup_verify.html) が signup_token を送る。
  $needs_token = in_array($mode, ["try", "verify_code", "resend"], TRUE);
  if ($needs_token && !kona3_checkEditToken(SIGNUP_CSRF_KEY)) {
    if ($mode == "try") {
      // 登録フォームに戻して理由を表示する
      $msg = lang('Invalid Token');
    } else {
      signup_render_verify($action, $email, lang('Invalid Token'));
    }
  }

  // --- ユーザー登録処理（フォーム送信時） ---
  if ($mode == "try" && $msg == "") {
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
    signup_render_verify($action, $email, "");
  }
  // --- 認証コードの再送信処理 (POSTのみ: GETリンクでは再送しない, #325) ---
  if ($mode == "resend") {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      signup_render_verify($action, $email, lang('Invalid Token'));
    }
    // メールアドレスで未認証のユーザーを検索
    $r = db_get1(
      "SELECT * FROM users WHERE email=? AND enabled=0",
      [$email]);
    if ($r == null) {
      signup_render_verify($action, $email, lang('Code not found'));
    }
    // 再送間隔制限: 短時間に繰り返して任意アドレスへメールを送らせない
    if (signup_resend_is_too_soon($r)) {
      signup_render_verify($action, $email,
        lang('Please wait a moment before resending the code.'));
    }
    // 新しい6桁の認証コードを生成
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    // DBの認証コードを更新
    db_exec("UPDATE users SET token=?, mtime=? WHERE email=? AND enabled=0",
      [$code, time(), $email]);
    // 新しいコードを発行したので照合失敗の記録はリセットする
    signup_clear_verify_failures($email);
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
    if (preg_match('/^(localhost|localhost\:\d+)$/', $_SERVER['HTTP_HOST'] ?? '')) {
      $signup_body2 = htmlspecialchars($signup_body);
      $msg = lang('Verification code has been resent') . "<pre>DEBUG:\n$signup_body2</pre>";
    } else {
      kona3lib_send_email($email, $signup_title, $signup_body);
      $msg = lang('Verification code has been resent');
    }
    // 認証コード入力画面に戻る
    signup_render_verify($action, $email, $msg);
  }
  // --- 認証コードによる有効化処理 ---
  if ($mode == "verify_code") {
    // コードではなく未認証の行を先に取得し、期限・試行回数を確認する
    $r = db_get1(
      "SELECT * FROM users WHERE email=? AND enabled=0",
      [$email]);
    if ($r == null || !isset($r['token']) || $r['token'] === '') {
      signup_render_verify($action, $email,
        lang('Invalid verification code'));
    }
    // 有効期限切れのコードは使えない
    if (signup_code_is_expired($r)) {
      signup_render_verify($action, $email,
        lang('Verification code has expired. Please request a new code.'));
    }
    // 試行回数の上限に達していたらコード自体を無効化する
    $issued = isset($r['mtime']) ? intval($r['mtime']) : 0;
    if (signup_verify_failure_count($email, $issued) >= SIGNUP_MAX_VERIFY_ATTEMPTS) {
      db_exec("UPDATE users SET token='' WHERE email=? AND enabled=0",
        [$email]);
      signup_render_verify($action, $email,
        lang('Too many verification attempts. Please request a new code.'));
    }
    // コード照合 (タイミング差を避けるため hash_equals を使う)
    if ($code === '' || !hash_equals((string)$r['token'], $code)) {
      signup_record_verify_failure($email);
      if (signup_verify_failure_count($email, $issued) >= SIGNUP_MAX_VERIFY_ATTEMPTS) {
        db_exec("UPDATE users SET token='' WHERE email=? AND enabled=0",
          [$email]);
        signup_render_verify($action, $email,
          lang('Too many verification attempts. Please request a new code.'));
      }
      // コードが間違っている場合、再度入力画面を表示
      signup_render_verify($action, $email,
        lang('Invalid verification code'));
    }
    // ユーザー有効化 (使ったコードは消し、失敗記録も消す)
    db_exec("UPDATE users SET enabled=1, token='' WHERE email=?",
      [$email]);
    signup_clear_verify_failures($email);
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
    "signup_token" => kona3_getEditToken(SIGNUP_CSRF_KEY),
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
  // 同じメールアドレスで未認証のユーザーが既に存在する場合は、
  // その行の name/password/token を申込者の入力で上書きしない (#325)。
  // 上書きを許すと、メールアドレスの持ち主が登録中のアカウントを
  // 第三者が別のパスワードで乗っ取ることができてしまう。
  // 本人であれば、認証コードの入力か再送で登録を完了できる。
  $existing = db_get1(
    "SELECT * FROM users WHERE email=? AND enabled=0", [$email]);
  if ($existing != null) {
    if (!signup_code_is_expired($existing)) {
      $msg = lang('This email is already awaiting verification. '.
        'Please enter the verification code or resend it.');
      return FALSE;
    }
    // 認証コードの有効期限が切れた未認証行は、登録をやり直せるよう
    // 削除してから新規登録を受け付ける (agyレビュー対応)。
    // 期限内の行を守ることで乗っ取りは防ぎつつ、期限切れの行が残って
    // そのメールアドレスで二度と登録できなくなる事態を避ける。
    // なお期限切れのコードはどのみち使えず、新規登録は通常の仮登録と
    // 同じ手続き (メール所持確認) を踏むため、安全性は変わらない。
    db_exec("DELETE FROM users WHERE email=? AND enabled=0", [$email]);
    signup_clear_verify_failures($email);
  }

  // 6桁の数字コードを生成
  $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

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
  // 新規発行なので照合失敗の記録はリセットする
  signup_clear_verify_failures($email);
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
  if (preg_match('/^(localhost|localhost\:\d+)$/', $_SERVER['HTTP_HOST'] ?? '')) {
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
