<?php
require_once __DIR__.'/jsonphp.lib.php';
define("KONA3_SESSKEY_LOGIN", "kona3_login_info");

if (!defined("KONA3_PASSWORD_SALT")) {
  define("KONA3_PASSWORD_SALT", 
    "tizIu*zC57#7GtF1OjGB!pSw:Ndg%zYi_QVXf");
}

function kona3login($user, $email, $perm, $user_id = 0) {
  $_SESSION[KONA3_SESSKEY_LOGIN] = [
    "user"  => $user,
    "email" => $email,
    "perm"  => $perm,
    "time"  => time(),
    "name"  => $user, // alias
    "user_id" => $user_id, // not used
  ];
}

function kona3logout() {
  unset($_SESSION[KONA3_SESSKEY_LOGIN]);
}

function kona3getLoginInfo() {
  if (empty($_SESSION[KONA3_SESSKEY_LOGIN])) {
    return FALSE;
  }
  return $_SESSION[KONA3_SESSKEY_LOGIN];
}
function kona3getUserName() {
  $u = kona3getLoginInfo();
  if (isset($u['name'])) return $u['name'];
  return 'Unknown';
}
function kona3getUserId() {
  $u = kona3getLoginInfo();
  if (isset($u['user_id'])) return $u['user_id'];
  return -1;
}

function kona3isLogin() {
  $i = kona3getLoginInfo();
  if (!$i) return FALSE;
  return (isset($i['time']) && $i['time'] > 0);
}

function kona3isAdmin() {
  $i = kona3getLoginInfo();
  if (!$i) return FALSE;
  return isset($i['perm']) && ($i['perm'] == 'admin');
}

function kona3getAdminUsers() {
  $adminuser_json = KONA3_DIR_PRIVATE.'/kona3adminuser.json.php';
  $users = jsonphp_load($adminuser_json, []);
  return $users;
}

function kona3tryLogin($user, $pw) {
  global $kona3conf;
  
  // Check Admin Users
  $users = kona3getAdminUsers();
  if (isset($users[$user])) {
    $hash = $users[$user]['hash'];
    $salt = $users[$user]['salt'];
    if (kona3getHash($pw, $salt) == $hash) {
      $email = isset($kona3conf['admin_email']) ? 
        $kona3conf['admin_email'] : '';
      kona3login($user, $email, "admin", 0);
      return TRUE;
    }
    return FALSE;
  }

  // Check Database
  $r = db_get1(
    "SELECT * FROM users ".
    "WHERE name=? AND password=? AND enabled=1",
    [$user, kona3getHash($pw)]);
  if ($r == null) return FALSE;
  kona3login($user, $r['email'], $r['perm'], $r['user_id']);
  return TRUE;
}

// Get Hash
function kona3getHash($password, $salt2 = '')
{
  $s = KONA3_PASSWORD_SALT . $salt2 . "::" . $password;
  return hash("sha512", $s, FALSE);
}

function kona3_getHashAutologin($token)
{
  return kona3getHash($token);
}

function kona3_getAutoLoginToken($email, $perm)
{
  $ua = empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'];
  $ip = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
  $token = bin2hex(random_bytes(32));
  // delete old token
  db_exec(
    'DELETE FROM tokens WHERE email=? AND user_agent=?',
    [$email, $ua],
    'autologin'
  );
  // insert new token
  db_exec(
    "INSERT INTO tokens (email, token, user_agent, ip_address, perm, mtime)" .
      "          VALUES (?,     ?,     ?,          ?,          ?,    ?)",
    [$email, kona3_getHashAutologin($token), $ua, $ip, $perm, time()],
    'autologin'
  );
  // remove old token
  $mtimeOld = time() - (60 * 60 * 24) * 30; // 30 days
  db_exec(
    "DELETE FROM tokens WHERE mtime <= ?",
    [$mtimeOld],
    'autologin'
  );
  return $token;
}

function kona3_checkAutoLoginToken($email, $token)
{
  $ua = empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'];
  $ip = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
  $row = db_get1(
    "SELECT * FROM tokens WHERE email=? AND token=? AND user_agent=?",
    [$email, kona3_getHashAutologin($token), $ua],
    'autologin'
  );
  if ($row == NULL) {
    return ['result' => FALSE, 'reason' => 'invalid token'];
  }
  $perm = $row['perm'];
  // update mtime and token
  /*
    $token = bin2hex(random_bytes(32));
    db_exec(
        "UPDATE tokens SET token=?, ip_address=?, mtime=? WHERE user_id=? AND user_agent=?",
        [kona3_getHashAutologin($token), $ip, time(), $userId, $ua],
        'autologin');
  */
  //$user, $email, $perm, $user_id
  kona3login($email, $email, $perm, 0);
  return ['result' => TRUE, 'token' => $token];
}

