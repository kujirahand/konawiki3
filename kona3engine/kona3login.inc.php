<?php
define("KONA3_SESSKEY_LOGIN", "kona3_login_info");

if (!defined("KONA3_PASSWORD_SALT")) {
  define("KONA3_PASSWORD_SALT", 
    "tizIu*zC57#7GtF1OjGB!pSw:Ndg%zYi_QVXf");
}

function kona3login($user, $email, $perm, $user_id) {
  $_SESSION[KONA3_SESSKEY_LOGIN] = [
    "user"  => $user,
    "name"  => $user, // alias
    "user_id" => $user_id,
    "email" => $email,
    "perm"  => $perm,
    "time"  => time(),
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
  $users = array();
  if ('base64:' == substr(KONA3_WIKI_USERS, 0, 7)) {
    $s = base64_decode(trim(substr(KONA3_WIKI_USERS, 7)));
    $users = json_decode($s, TRUE);
  } else {
    $users_a = explode(",", KONA3_WIKI_USERS);
    foreach ($users_a as $r) {
      $ra = explode(":", trim($r), 2);
      if (count($ra) == 2) {
        $users[$ra[0]] = $ra[1];
      }
    }
  }
  return $users;
}

function kona3tryLogin($user, $pw) {
  global $kona3conf;
  
  // Check Admin Users (by config file)
  $users = kona3getAdminUsers();
  if (isset($users[$user]) && $users[$user] == $pw) {
    kona3login($user, KONA3_ADMIN_EMAIL, "admin", 0);
    return TRUE;
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

function kona3getHash($password) {
  $s = KONA3_PASSWORD_SALT . "::" . $password;
  return hash("sha512", $s, FALSE);
}

