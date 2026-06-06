<?php
// --- kona3login.inc.php ---
// このファイルはKonaWiki3のログイン・ログアウト・認証関連の主要関数をまとめたものです。
// セッション管理、管理者・一般ユーザーの認証、パスワードハッシュ生成などを担当します。

require_once __DIR__.'/jsonphp.lib.php';

// セッションでログイン情報を保存する際のキー名
// $_SESSION[KONA3_SESSKEY_LOGIN] に配列でユーザー情報を格納
// 例: [user, email, perm, time, name, user_id]
define("KONA3_SESSKEY_LOGIN", "kona3_login_info");
define("KONA3_REMEMBER_COOKIE", "kona3_remember");
define("KONA3_REMEMBER_EXPIRES", 60 * 60 * 24 * 90);

// パスワードハッシュ生成時に使うシステム共通のソルト
if (!defined("KONA3_PASSWORD_SALT")) {
  define("KONA3_PASSWORD_SALT", "tizIu*zC57#7GtF1OjGB!pSw:Ndg%zYi_QVXf");
}

// --- ログイン情報をセッションに保存する ---
// $user   : ユーザー名
// $email  : メールアドレス
// $perm   : 権限（admin/normal など）
// $user_id: ユーザーID（未使用だが将来拡張用）
function kona3login($user, $email, $perm, $user_id = 0) {
  $_SESSION[KONA3_SESSKEY_LOGIN] = [
    "user"  => $user,      // ユーザー名
    "name"  => $user,      // エイリアス（互換用）
    "email" => $email,     // メールアドレス
    "perm"  => $perm,      // 権限
    "time"  => time(),     // ログイン時刻
    "user_id" => $user_id, // ユーザーID
  ];
}

function kona3remember_random()
{
  return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function kona3remember_cookie_secure()
{
  return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

function kona3remember_setCookie($selector, $token, $expires)
{
  if (php_sapi_name() === 'cli') return;
  setcookie(KONA3_REMEMBER_COOKIE, $selector . ':' . $token, [
    'expires' => $expires,
    'path' => '/',
    'secure' => kona3remember_cookie_secure(),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}

function kona3remember_clearCookie()
{
  unset($_COOKIE[KONA3_REMEMBER_COOKIE]);
  if (php_sapi_name() === 'cli') return;
  setcookie(KONA3_REMEMBER_COOKIE, '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => kona3remember_cookie_secure(),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}

function kona3remember_init()
{
  static $initialized = FALSE;
  if ($initialized) return;
  db_exec(
    "CREATE INDEX IF NOT EXISTS idx_remember_tokens_expires ON remember_tokens(expires)",
    [],
    'remember'
  );
  $initialized = TRUE;
}

function kona3remember_cleanup()
{
  kona3remember_init();
  db_exec("DELETE FROM remember_tokens WHERE expires < ?", [time()], 'remember');
}

function kona3remember_regenerateSession()
{
  if (!headers_sent()) {
    session_regenerate_id(true);
  }
}

function kona3remember_issue($loginInfo = null)
{
  if ($loginInfo === null) {
    $loginInfo = kona3getLoginInfo();
  }
  if (!$loginInfo) return FALSE;
  $oldCookie = isset($_COOKIE[KONA3_REMEMBER_COOKIE]) ? $_COOKIE[KONA3_REMEMBER_COOKIE] : '';
  $oldParsed = kona3remember_parseCookie($oldCookie);
  if ($oldParsed) {
    kona3remember_delete($oldParsed['selector']);
  }
  kona3remember_cleanup();
  $selector = kona3remember_random();
  $token = kona3remember_random();
  $now = time();
  $expires = $now + KONA3_REMEMBER_EXPIRES;
  $ua = empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'];
  db_exec(
    "INSERT INTO remember_tokens " .
    "(selector,token_hash,user_id,user,email,perm,expires,created_at,last_used_at,user_agent) " .
    "VALUES(?,?,?,?,?,?,?,?,?,?)",
    [
      $selector,
      hash('sha256', $token),
      isset($loginInfo['user_id']) ? intval($loginInfo['user_id']) : 0,
      isset($loginInfo['user']) ? $loginInfo['user'] : '',
      isset($loginInfo['email']) ? $loginInfo['email'] : '',
      isset($loginInfo['perm']) ? $loginInfo['perm'] : 'normal',
      $expires,
      $now,
      $now,
      $ua,
    ],
    'remember'
  );
  $_COOKIE[KONA3_REMEMBER_COOKIE] = $selector . ':' . $token;
  kona3remember_setCookie($selector, $token, $expires);
  return [
    'selector' => $selector,
    'token' => $token,
    'expires' => $expires,
  ];
}

function kona3remember_parseCookie($cookie)
{
  if (!is_string($cookie)) return FALSE;
  $a = explode(':', $cookie, 2);
  if (count($a) !== 2) return FALSE;
  if ($a[0] === '' || $a[1] === '') return FALSE;
  return ['selector' => $a[0], 'token' => $a[1]];
}

function kona3remember_delete($selector)
{
  if ($selector === '') return;
  kona3remember_init();
  db_exec("DELETE FROM remember_tokens WHERE selector=?", [$selector], 'remember');
}

function kona3remember_getCurrentLoginInfo($rememberRow)
{
  global $kona3conf;
  $userId = isset($rememberRow['user_id']) ? intval($rememberRow['user_id']) : 0;
  if ($userId > 0) {
    $u = db_get1(
      "SELECT * FROM users WHERE user_id=? AND enabled=1",
      [$userId]
    );
    if (!$u) return FALSE;
    return [
      'user' => $u['name'],
      'email' => $u['email'],
      'perm' => $u['perm'],
      'user_id' => intval($u['user_id']),
    ];
  }
  if (isset($rememberRow['perm']) && $rememberRow['perm'] === 'admin') {
    $adminUsers = kona3getAdminUsers();
    $adminName = isset($rememberRow['user']) ? $rememberRow['user'] : '';
    if (!isset($adminUsers[$adminName])) return FALSE;
    return [
      'user' => $adminName,
      'email' => isset($kona3conf['admin_email']) ? $kona3conf['admin_email'] : '',
      'perm' => 'admin',
      'user_id' => 0,
    ];
  }
  return FALSE;
}

function kona3remember_logout()
{
  $cookie = isset($_COOKIE[KONA3_REMEMBER_COOKIE]) ? $_COOKIE[KONA3_REMEMBER_COOKIE] : '';
  $parsed = kona3remember_parseCookie($cookie);
  if ($parsed) {
    kona3remember_delete($parsed['selector']);
  }
  kona3remember_clearCookie();
}

function kona3remember_tryAutoLogin()
{
  if (kona3isLogin()) return FALSE;
  $cookie = isset($_COOKIE[KONA3_REMEMBER_COOKIE]) ? $_COOKIE[KONA3_REMEMBER_COOKIE] : '';
  if ($cookie === '') return FALSE;
  $parsed = kona3remember_parseCookie($cookie);
  if (!$parsed) {
    kona3remember_clearCookie();
    return FALSE;
  }
  kona3remember_cleanup();
  $r = db_get1(
    "SELECT * FROM remember_tokens WHERE selector=?",
    [$parsed['selector']],
    'remember'
  );
  if (!$r || intval($r['expires']) < time()) {
    kona3remember_delete($parsed['selector']);
    kona3remember_clearCookie();
    return FALSE;
  }
  $tokenHash = hash('sha256', $parsed['token']);
  if (!hash_equals($r['token_hash'], $tokenHash)) {
    kona3remember_delete($parsed['selector']);
    kona3remember_clearCookie();
    return FALSE;
  }
  $loginInfo = kona3remember_getCurrentLoginInfo($r);
  if (!$loginInfo) {
    kona3remember_delete($parsed['selector']);
    kona3remember_clearCookie();
    return FALSE;
  }
  kona3remember_regenerateSession();
  kona3login($loginInfo['user'], $loginInfo['email'], $loginInfo['perm'], $loginInfo['user_id']);
  $newToken = kona3remember_random();
  $now = time();
  $expires = $now + KONA3_REMEMBER_EXPIRES;
  db_exec(
    "UPDATE remember_tokens SET token_hash=?, expires=?, last_used_at=? WHERE selector=?",
    [hash('sha256', $newToken), $expires, $now, $parsed['selector']],
    'remember'
  );
  $_COOKIE[KONA3_REMEMBER_COOKIE] = $parsed['selector'] . ':' . $newToken;
  kona3remember_setCookie($parsed['selector'], $newToken, $expires);
  return TRUE;
}

// --- ログアウト処理 ---
// セッションからログイン情報を削除する
function kona3logout() {
  kona3remember_logout();
  $info = kona3getLoginInfo();
  if ($info) {
    // ここでemailやUAを取得しているが、現状は何もしていない
    $_email = empty($info['email']) ? '' : $info['email'];
    $_ua = empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'];
  }
  unset($_SESSION[KONA3_SESSKEY_LOGIN]);
}

// --- 現在のログイン情報を取得 ---
// ログインしていなければFALSE、していれば配列を返す
function kona3getLoginInfo() {
  if (empty($_SESSION[KONA3_SESSKEY_LOGIN])) {
    return FALSE;
  }
  return $_SESSION[KONA3_SESSKEY_LOGIN];
}

// --- 現在ログイン中のユーザー名を取得 ---
function kona3getUserName() {
  $u = kona3getLoginInfo();
  if (isset($u['name'])) return $u['name'];
  return 'Unknown';
}

// --- 現在ログイン中のユーザーIDを取得 ---
function kona3getUserId() {
  $u = kona3getLoginInfo();
  if (isset($u['user_id'])) return $u['user_id'];
  return -1;
}

// --- ログインしているかどうか判定 ---
// 戻り値: TRUE=ログイン中, FALSE=未ログイン
function kona3isLogin() {
  $i = kona3getLoginInfo();
  if (!$i) return FALSE;
  return (isset($i['time']) && $i['time'] > 0);
}

// --- 管理者権限かどうか判定 ---
// 戻り値: TRUE=管理者, FALSE=一般ユーザー
function kona3isAdmin() {
  $i = kona3getLoginInfo();
  if (!$i) return FALSE;
  return isset($i['perm']) && ($i['perm'] == 'admin');
}

// --- 管理者ユーザーの一覧を取得 ---
// 管理者情報は private/kona3adminuser.json.php にJSONで保存されている
// 戻り値: [ユーザー名 => [hash, salt], ...]
function kona3getAdminUsers() {
  $adminuser_json = KONA3_DIR_PRIVATE.'/kona3adminuser.json.php';
  $users = jsonphp_load($adminuser_json, [], true);
  return $users;
}

// --- ログイン認証処理（管理者・一般ユーザー両対応） ---
// $user: ユーザー名, $pw: 平文パスワード
// 戻り値: TRUE=認証成功, FALSE=失敗
function kona3tryLogin($user, $pw) {
  global $kona3conf;
  // 管理者認証
  if (kona3_admin_auth($user, $pw, $kona3conf)) return TRUE;
  // 一般ユーザー認証
  return kona3_user_auth($user, $pw);
}

// --- 管理者ユーザー認証 ---
function kona3_admin_auth($user, $pw, $kona3conf) {
  $users = kona3getAdminUsers();
  if (!isset($users[$user])) return FALSE;
  $hash = $users[$user]['hash'];
  $salt = $users[$user]['salt'];
  // パスワード+ソルトでハッシュを生成し比較
  if (kona3getHash($pw, $salt) == $hash) {
    $email = isset($kona3conf['admin_email']) ? $kona3conf['admin_email'] : '';
    kona3login($user, $email, "admin", 0);
    return TRUE;
  }
  return FALSE;
}

// --- 一般ユーザー認証 ---
function kona3_user_auth($email, $pw) {
  $r = db_get1(
    "SELECT * FROM users ".
    "WHERE (email=?) AND (enabled=1)",
    [$email]);

  if ($r == null) return FALSE;
  $stored_password = isset($r['password']) ? $r['password'] : '';
  // パスワード検証
  if (!kona3_verify_user_password($pw, $stored_password)) return FALSE;
  // 認証成功時はセッションに情報を保存
  kona3login($r['name'], $r['email'], $r['perm'], $r['user_id']);
  return TRUE;
}

// --- 一般ユーザーのパスワード検証（複数方式対応） ---
function kona3_verify_user_password($pw, $stored_password) {
  // method1: 現行方式（ソルトなし）
  if (kona3getHash($pw) == $stored_password) return true;
  // method2: 空文字ソルト
  if (kona3getHash($pw, '') == $stored_password) return true;
  // method3: 旧方式（ソルト連結位置が異なる）
  $alt_hash = hash("sha512", KONA3_PASSWORD_SALT . "::" . $pw, FALSE);
  if ($alt_hash == $stored_password) return true;
  // いずれにも一致しなければ失敗
  return false;
}

// --- パスワードハッシュ生成関数 ---
// $password : 平文パスワード
// $salt2    : 追加ソルト（省略可）
// システム共通のソルトと追加ソルト、パスワードを連結し、sha512でハッシュ化
// 管理者は個別ソルト、一般ユーザーはソルトなしが基本
function kona3getHash($password, $salt2 = '')
{
  $s = KONA3_PASSWORD_SALT . $salt2 . "::" . $password;
  return hash("sha512", $s, FALSE);
}
