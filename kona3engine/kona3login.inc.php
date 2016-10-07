<?php
define("SESS_KEY_LOGIN", "konawiki3login");

function kona3login() {
  $_SESSION[SESS_KEY_LOGIN] = time();
}
function kona3logout() {
  unset($_SESSION[SESS_KEY_LOGIN]);
}
function kona3isLogin() {
  if (isset($_SESSION[SESS_KEY_LOGIN])) {
    $login = intval($_SESSION[SESS_KEY_LOGIN]);
    if ($login > 0) return TRUE;
  }
  return FALSE;
}


