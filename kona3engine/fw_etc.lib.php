<?php
global $FW_ADMIN_EMAIL;
function get_param($name, $def = '') {
  if (isset($_GET[$name])) {
    return $_GET[$name];
  }
  return $def;
}

function post_param($name, $def = '') {
  if (isset($_POST[$name])) {
    return $_POST[$name];
  }
  return $def;
}

function redirect($url) {
  header("Location:$url");
  msgbox("<a href='$url'>こちらのページ</a>に移動してください。");
  exit;
}

function error_page($msg, $title = 'エラー') {
  template_render('message.html', [
    'msg' => $msg, 
    'title' => $title
  ]);
}

function msgbox($msg, $title = '情報') {
  template_render('message.html', [
    'msg' => $msg, 
    'title' => $title
  ]);
}

function lib_send_email($to, $subject, $email_body) {
  global $FW_ADMIN_EMAIL;
  if ($FW_ADMIN_EMAIL == '') {
    throw new Exception("global $ FW_ADMIN_EMAIL not set");
  }
  $headers = "From: $FW_ADMIN_EMAIL";
  @mb_send_mail($to, $subject, $email_body, $headers);
  //
  // 送信したことを記録する
  $db = database_get();
  $stmt = $db->prepare(
    'INSERT INTO email_logs (mailto, body, title, ctime) '.
    'VALUES(?, ?,?,?)');
  $stmt->execute([$to, $email_body, $subject, time()]);
}

function lib_send_email_to_admin($subject, $email_body) {
  global $FW_ADMIN_EMAIL;
  lib_send_email($FW_ADMIN_EMAIL, $subject, $email_body);
}




