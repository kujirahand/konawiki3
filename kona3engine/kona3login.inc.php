<?php
define("KONA3_LOGIN_TIME", "konawiki3login");

function kona3login() {
  $_SESSION[KONA3_LOGIN_TIME] = time();
}
function kona3logout() {
  unset($_SESSION[KONA3_LOGIN_TIME]);
}
function kona3isLogin() {
  if (isset($_SESSION[KONA3_LOGIN_TIME])) {
    $login = intval($_SESSION[KONA3_LOGIN_TIME]);
    if ($login > 0) return TRUE;
  }
  return FALSE;
}


