<?php
define("KONA3_SESSKEY_LOGIN", "kona3_login_info");

if (!defined("KONA3_PASSWORD_SALT")) {
  define("KONA3_PASSWORD_SALT", 
    "tizIu*zC57#7GtF1OjGB!pSw:Ndg%zYi_QVXf");
}

function kona3login($user, $email, $perm) {
  $_SESSION[KONA3_SESSKEY_LOGIN] = [
    "user"  => $user,
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

function kona3tryLogin($user, $pw) {
  global $kona3conf;
  
  // Check Admin Users
  $users = $kona3conf['users'];
  if (isset($users[$user]) && $users[$user] == $pw) {
    kona3login($user, KONA3_ADMIN_EMAIL, "admin");
    return TRUE;
  }

  // Check Database
  $r = db_get1(
    "SELECT * FROM users ".
    "WHERE name=? AND password=? AND enabled=1",
    [$user, kona3getHash($pw)]);
  if ($r == null) return FALSE;
  kona3login($user, $r['email'], "normal");
  return TRUE;
}

function kona3getHash($password) {
  $s = KONA3_PASSWORD_SALT . "::" . $password;
  return hash("sha512", $s, FALSE);
}

