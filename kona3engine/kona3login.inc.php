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
  // 静的出力は常に匿名ユーザーとして描画する (docs/static_wiki.md 1章)
  global $kona3conf;
  if (!empty($kona3conf['static_mode'])) {
    return FALSE;
  }
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
function kona3getAdminUserFile() {
  return KONA3_DIR_PRIVATE.'/kona3adminuser.json.php';
}

function kona3getAdminUsers($strict = false) {
  $users = jsonphp_load(kona3getAdminUserFile(), [], true, $strict);
  if (!is_array($users)) return [];
  unset($users['enc:key']); // jsonphp内部の暗号鍵はユーザーではない
  return $users;
}

// 内部用: 破損を検知して例外を投げる strict 版管理者ユーザー取得
function kona3getAdminUsersStrict() {
  return kona3getAdminUsers(true);
}

// --- 管理者ユーザーの一覧を保存する ---
function kona3saveAdminUsers($users) {
  jsonphp_save(kona3getAdminUserFile(), $users);
}

// --- 管理者用のソルトを生成する (暗号学的に安全な乱数を使う) ---
function kona3admin_genSalt() {
  return bin2hex(random_bytes(32));
}

// --- 管理者ユーザーを作成/更新する (#295, #297) ---
// ブラウザの初期設定画面だけでなく、CLIスクリプトやテストからも
// 同じ手順で管理者を作れるようにするための共通関数。
// 既存の管理者は保持し、$userid のエントリだけを追加/更新する。
// 既存の管理者ファイルが破損している場合は JsonphpLoadException を投げて
// 保存処理を行わない（既存データを破損から保護する）。
// $userid    : 管理者ID (通常はメールアドレス)
// $password  : 平文パスワード (空文字は不可)
// $overwrite : FALSE のとき、既存ユーザーがいれば RuntimeException を投げる
// 戻り値: TRUE (成功時)。検証エラーは InvalidArgumentException、ファイル破損時は JsonphpLoadException を投げる。
function kona3createAdminUser($userid, $password, $overwrite = FALSE) {
  $userid = is_string($userid) ? trim($userid) : '';
  if ($userid === '') {
    throw new InvalidArgumentException('Admin user id must not be empty.');
  }
  if (preg_match('/[\s\x00-\x1f\x7f]/', $userid)) {
    throw new InvalidArgumentException('Admin user id must not contain whitespace or control characters.');
  }
  if (!is_string($password) || $password === '') {
    throw new InvalidArgumentException('Admin password must not be empty.');
  }
  $users = kona3getAdminUsersStrict();
  if (isset($users[$userid]) && !$overwrite) {
    throw new RuntimeException("Admin user already exists: {$userid}");
  }
  $salt = kona3admin_genSalt();
  $users[$userid] = [
    'hash' => kona3getHash($password, $salt),
    'salt' => $salt,
  ];
  kona3saveAdminUsers($users);
  return TRUE;
}

// --- ログイン失敗の理由 (#132) ---
// 認証失敗を一律に FALSE で潰すと、利用者には「パスワードが合っているのに
// ログインできない」ように見えてしまう。理由を区別して返し、ログインフォームに
// 正しい理由を表示できるようにする。ただし、ユーザーが存在しない場合と
// パスワードが違う場合は、どちらも同じ 'invalid_credentials' とする
// (どちらが間違っているかは明かさない)。
if (!defined('KONA3_LOGIN_REASON_OK')) {
  define('KONA3_LOGIN_REASON_OK', 'ok');
  define('KONA3_LOGIN_REASON_EMPTY', 'empty_input');
  define('KONA3_LOGIN_REASON_INVALID', 'invalid_credentials');
  define('KONA3_LOGIN_REASON_DISABLED', 'user_disabled');
  define('KONA3_LOGIN_REASON_SYSTEM', 'system_error');
  define('KONA3_LOGIN_REASON_THROTTLED', 'throttled');
}

// 理由コードからログインフォームに表示する文言 (langキー) を引く
function kona3loginReasonLangKey($reason) {
  $map = [
    KONA3_LOGIN_REASON_EMPTY => 'Please enter your user name and password.',
    KONA3_LOGIN_REASON_INVALID => 'Invalid User or Password.',
    KONA3_LOGIN_REASON_DISABLED => 'This account is disabled.',
    KONA3_LOGIN_REASON_SYSTEM => 'Login is unavailable due to a system error.',
    KONA3_LOGIN_REASON_THROTTLED => 'Too many login attempts.',
  ];
  return isset($map[$reason]) ? $map[$reason] : 'Invalid User or Password.';
}

// --- ログイン試行回数制限 (スロットリング, #325) ---
// 2024年に導入した IP 単位の制限は、#132 のログイン画面の書き換えで
// チェックと失敗記録だけが落ち、成功時の掃除だけが残っていた。
// ここで共通関数として復活させる。失敗は meta テーブルに
// name='login_error' / value_s="{IP}|{ユーザー識別子のハッシュ}" /
// value_i=時刻 の行として記録する (旧形式の value_s="{IP}" の行も数える)。
if (!defined('KONA3_LOGIN_THROTTLE_WINDOW')) {
  // 失敗を数える期間 (秒)
  define('KONA3_LOGIN_THROTTLE_WINDOW', 60 * 10);
  // 同じ IP+ユーザー の失敗上限 (これ以上は期間内ログインを拒否)
  define('KONA3_LOGIN_THROTTLE_MAX_PER_USER', 5);
  // 同じ IP 全体の失敗上限 (多数アカウントへの総当たりを防ぐ)
  define('KONA3_LOGIN_THROTTLE_MAX_PER_IP', 20);
}

// スロットリング用のキー (IP と正規化したユーザー名のハッシュ)
function kona3loginThrottleKey($ip, $user) {
  $u = is_string($user) ? strtolower(trim($user)) : '';
  return (string)$ip . '|' . hash('sha256', $u);
}

function kona3loginThrottleClientIp() {
  return isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
    ? $_SERVER['REMOTE_ADDR'] : '';
}

// 期間内の失敗回数を数える。$user が null のときは IP 全体を数える
function kona3loginThrottleFailureCount($ip, $user = null, $now = null) {
  if ($now === null) $now = time();
  $since = $now - KONA3_LOGIN_THROTTLE_WINDOW;
  $rows = db_get(
    "SELECT value_s, value_i FROM meta WHERE name='login_error' AND value_i > ?",
    [$since]);
  $key = ($user === null) ? null : kona3loginThrottleKey($ip, $user);
  $cnt = 0;
  foreach ($rows as $r) {
    $vs = isset($r['value_s']) ? (string)$r['value_s'] : '';
    if ($user === null) {
      // IP全体: "{IP}" (旧形式) と "{IP}|..." の両方を数える
      if ($vs === (string)$ip || strpos($vs, (string)$ip . '|') === 0) $cnt++;
    } else {
      if ($vs === $key) $cnt++;
    }
  }
  return $cnt;
}

// これ以上ログインを試させてよいかどうか
function kona3loginThrottleIsBlocked($ip, $user, $now = null) {
  if ($now === null) $now = time();
  if (kona3loginThrottleFailureCount($ip, $user, $now) >= KONA3_LOGIN_THROTTLE_MAX_PER_USER) {
    return TRUE;
  }
  // IP全体の上限は、実IPがある場合にだけ適用する (agyレビュー対応)。
  // REMOTE_ADDR が無い環境 (CLI等) では空文字列に全アカウントの失敗が
  // 集約されてしまうため、IP単位の集計は行わない (アカウント単位は効く)。
  if ((string)$ip !== ''
      && kona3loginThrottleFailureCount($ip, null, $now) >= KONA3_LOGIN_THROTTLE_MAX_PER_IP) {
    return TRUE;
  }
  return FALSE;
}

// ログイン失敗を記録する (古い記録は掃除する)
function kona3loginThrottleRecordFailure($ip, $user, $now = null) {
  if ($now === null) $now = time();
  db_exec(
    "INSERT INTO meta (name, value_s, value_i) VALUES ('login_error', ?, ?)",
    [kona3loginThrottleKey($ip, $user), $now]);
  db_exec(
    "DELETE FROM meta WHERE name='login_error' AND value_i <= ?",
    [$now - KONA3_LOGIN_THROTTLE_WINDOW]);
}

// ログイン成功時に、その IP+ユーザー の失敗記録を消す
function kona3loginThrottleClear($ip, $user) {
  db_exec(
    "DELETE FROM meta WHERE name='login_error' AND value_s=?",
    [kona3loginThrottleKey($ip, $user)]);
  // 旧形式 (value_s が IP そのもの) の記録も消す
  db_exec(
    "DELETE FROM meta WHERE name='login_error' AND value_s=?",
    [(string)$ip]);
  db_exec(
    "DELETE FROM meta WHERE name='login_error' AND value_i <= ?",
    [time() - KONA3_LOGIN_THROTTLE_WINDOW]);
}

// --- ログイン認証処理（管理者・一般ユーザー両対応） ---
// $user: ユーザー名, $pw: 平文パスワード
// 戻り値: TRUE=認証成功, FALSE=失敗
function kona3tryLogin($user, $pw) {
  $res = kona3tryLoginResult($user, $pw);
  return $res['ok'];
}

// ログ出力用に文字列をサニタイズする (#309)
// - ASCII 制御文字 (CR/LF 等) と DEL を空白に置換する (#306)
// - Unicode の行区切りとして扱われうる文字 (U+0085 NEL /
//   U+2028 / U+2029) と、その他の Unicode 制御・区切り文字
//   (Cc/Zl/Zp) も空白に置換する
// - $maxLen 文字で切り詰める (マルチバイト文字の途中では切らない)
// 不正な UTF-8 が渡されても preg_replace が null を返して
// 文字列が消えたり壊れたりしないよう、行区切りは UTF-8 の
// バイト列で先に置換し、/u 付きの置換は有効な UTF-8 の場合だけ行う。
function kona3sanitizeForLog($text, $maxLen = 100) {
  if (!is_string($text)) {
    $text = is_scalar($text) ? (string)$text : '';
  }
  // U+0085 / U+2028 / U+2029 を UTF-8 バイト列で置換する
  // (バイト列の置換は不正な UTF-8 でも安全)
  $text = str_replace(
    ["\xC2\x85", "\xE2\x80\xA8", "\xE2\x80\xA9"],
    ' ',
    $text
  );
  // ASCII 制御文字と DEL (バイトモードなのでマルチバイトは壊れない)
  $replaced = preg_replace('/[\x00-\x1F\x7F]/', ' ', $text);
  if ($replaced !== null) {
    $text = $replaced;
  }
  // 有効な UTF-8 の場合だけ、残りの Cc/Zl/Zp (C1 制御文字など) も除去する
  $isUtf8 = !function_exists('mb_check_encoding')
    || mb_check_encoding($text, 'UTF-8');
  if ($isUtf8) {
    $replaced = preg_replace('/[\p{Cc}\p{Zl}\p{Zp}]/u', ' ', $text);
    if ($replaced !== null) {
      $text = $replaced;
    }
  }
  if ($maxLen > 0) {
    if ($isUtf8 && function_exists('mb_substr')) {
      $text = mb_substr($text, 0, $maxLen, 'UTF-8');
    } else {
      // 不正な UTF-8 では mb_substr が文字を壊すおそれがあるためバイト単位
      $text = substr($text, 0, $maxLen);
    }
  }
  return $text;
}

// 理由付きのログイン認証 (#132) + 試行回数制限 (#325)
// スロットリング中はパスワードを照合せずに拒否する。
// 照合に失敗 (invalid_credentials) したときだけ失敗を記録し、
// 成功したらその IP+ユーザー の失敗記録を消す。
// 空入力・無効化アカウント (パスワード自体は正しい)・システムエラーは
// 総当たりではないので記録しない。
// 戻り値: ['ok' => bool, 'reason' => KONA3_LOGIN_REASON_* のいずれか]
function kona3tryLoginResult($user, $pw) {
  $ip = kona3loginThrottleClientIp();
  if (is_string($user) && trim($user) !== '' && is_string($pw) && $pw !== '') {
    if (kona3loginThrottleIsBlocked($ip, $user)) {
      return ['ok' => FALSE, 'reason' => KONA3_LOGIN_REASON_THROTTLED];
    }
  }
  $res = kona3_tryLoginResultRaw($user, $pw);
  if ($res['ok']) {
    if (is_string($user)) kona3loginThrottleClear($ip, $user);
  } else if ($res['reason'] === KONA3_LOGIN_REASON_INVALID
      && is_string($user) && trim($user) !== ''
      && is_string($pw) && $pw !== '') {
    kona3loginThrottleRecordFailure($ip, $user);
  }
  return $res;
}

// スロットリングなしの理由付きログイン認証 (本体)
function kona3_tryLoginResultRaw($user, $pw) {
  global $kona3conf;
  if (!is_string($user) || !is_string($pw)) {
    return ['ok' => FALSE, 'reason' => KONA3_LOGIN_REASON_INVALID];
  }
  if (trim($user) === '' || $pw === '') {
    return ['ok' => FALSE, 'reason' => KONA3_LOGIN_REASON_EMPTY];
  }
  // 管理者認証
  $admin = kona3_admin_auth_result($user, $pw, $kona3conf);
  if ($admin['ok']) {
    return ['ok' => TRUE, 'reason' => KONA3_LOGIN_REASON_OK];
  }
  // 一般ユーザー認証
  $userRes = kona3_user_auth_result($user, $pw);
  if ($userRes['ok']) {
    return ['ok' => TRUE, 'reason' => KONA3_LOGIN_REASON_OK];
  }
  // 管理者ファイルの破損は、返却する理由の優先順位とは切り離して
  // 常に記録する (#309)。以前は system_error を返す経路でしか記録
  // されず、DBユーザーが存在する場合は早期リターンで記録が残らず、
  // 管理者が破損に気づきにくかった。同じメールが DB 行と管理者の
  // 両方に存在する重なりケースでも、返却理由は #306 のとおり
  // DB側を優先しつつ、破損自体はこのログで気づけるようにする。
  $adminBroken = ($admin['reason'] === KONA3_LOGIN_REASON_SYSTEM);
  if ($adminBroken) {
    // ログ注入を防ぐため、サニタイズしてから出力する (#306, #309)
    $safeUser = kona3sanitizeForLog($user);
    error_log("KonaWiki3 Error: login failed because admin user file could not be loaded (user={$safeUser})");
  }
  // DBにユーザーが存在する場合は、その照合結果が正である (#306)。
  // 管理者ファイルが壊れていても、DB照合済みのユーザーに
  // 「パスワードは確認されていません」と誤案内しない。
  if (!empty($userRes['found'])) {
    return ['ok' => FALSE, 'reason' => $userRes['reason']];
  }
  // DBに該当ユーザーがおらず、管理者ファイルが壊れていて管理者認証を
  // 確認できなかった場合は、「ユーザー名かパスワードの間違い」と
  // 誤案内しない (#297 と同じ考え方)
  if ($adminBroken) {
    return ['ok' => FALSE, 'reason' => KONA3_LOGIN_REASON_SYSTEM];
  }
  return ['ok' => FALSE, 'reason' => KONA3_LOGIN_REASON_INVALID];
}

// --- 管理者ユーザー認証 ---
function kona3_admin_auth($user, $pw, $kona3conf) {
  $res = kona3_admin_auth_result($user, $pw, $kona3conf);
  return $res['ok'];
}

// 理由付きの管理者ユーザー認証
// 戻り値: ['ok' => bool, 'reason' => 'ok'|'invalid_credentials'|'system_error']
function kona3_admin_auth_result($user, $pw, $kona3conf) {
  try {
    $users = kona3getAdminUsersStrict();
  } catch (JsonphpLoadException $e) {
    return ['ok' => FALSE, 'reason' => KONA3_LOGIN_REASON_SYSTEM];
  }
  if (!isset($users[$user])) {
    return ['ok' => FALSE, 'reason' => KONA3_LOGIN_REASON_INVALID];
  }
  $hash = isset($users[$user]['hash']) ? $users[$user]['hash'] : '';
  $salt = isset($users[$user]['salt']) ? $users[$user]['salt'] : '';
  // パスワード+ソルトでハッシュを生成し比較
  if (hash_equals((string)$hash, kona3getHash($pw, $salt))) {
    $email = isset($kona3conf['admin_email']) ? $kona3conf['admin_email'] : '';
    kona3login($user, $email, "admin", 0);
    return ['ok' => TRUE, 'reason' => KONA3_LOGIN_REASON_OK];
  }
  return ['ok' => FALSE, 'reason' => KONA3_LOGIN_REASON_INVALID];
}

// --- 一般ユーザー認証 ---
function kona3_user_auth($email, $pw) {
  $res = kona3_user_auth_result($email, $pw);
  return $res['ok'];
}

// 理由付きの一般ユーザー認証
// パスワードが正しいのにアカウントが無効化されている場合だけを
// 'user_disabled' として区別する。パスワードが違う場合は、無効化されて
// いるかどうかを明かさず 'invalid_credentials' とする。
function kona3_user_auth_result($email, $pw) {
  // 同一メールアドレスの行が複数ある場合 (users.email に UNIQUE 制約は
  // ない) は、有効 (enabled=1) な行を優先して取得する (#306)。
  // #132 以前の SQL は `enabled=1` の行だけを対象にしていたので、
  // 無効な行を先に拾ってログインできなくなる回帰を防ぐ。
  $r = db_get1(
    "SELECT * FROM users ".
    "WHERE (email=?) ORDER BY (enabled=1) DESC, user_id ASC",
    [$email]);

  if ($r == null) {
    return ['ok' => FALSE, 'reason' => KONA3_LOGIN_REASON_INVALID, 'found' => FALSE];
  }
  $stored_password = isset($r['password']) ? $r['password'] : '';
  // パスワード検証
  if (!kona3_verify_user_password($pw, $stored_password)) {
    return ['ok' => FALSE, 'reason' => KONA3_LOGIN_REASON_INVALID, 'found' => TRUE];
  }
  // 有効なのは enabled=1 のみ。それ以外 (0 や 2 など) は無効化扱い (#306)
  if (intval(isset($r['enabled']) ? $r['enabled'] : 0) !== 1) {
    return ['ok' => FALSE, 'reason' => KONA3_LOGIN_REASON_DISABLED, 'found' => TRUE];
  }
  // 認証成功時はセッションに情報を保存
  kona3login($r['name'], $r['email'], $r['perm'], $r['user_id']);
  return ['ok' => TRUE, 'reason' => KONA3_LOGIN_REASON_OK, 'found' => TRUE];
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
