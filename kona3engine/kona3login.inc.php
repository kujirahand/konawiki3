<?php

function kona3login() {
  $_SESSION['login'] = time();
}
function kona3logout() {
  unset($_SESSION['login']);
}
function kona3isLogin() {
  if (isset($_SESSION['login'])) {
    $login = intval($_SESSION['login']);
    if ($login > 0) return TRUE;
  }
  return FALSE;
}
