<?php
global $FW_ADMIN_EMAIL;

// $_GET を手軽に取得
function get_param($name, $def = '') {
  if (isset($_GET[$name])) {
    return $_GET[$name];
  }
  return $def;
}

// $_POST を手軽に取得
function post_param($name, $def = '') {
  if (isset($_POST[$name])) {
    return $_POST[$name];
  }
  return $def;
}

// $_SESSION を手軽に取得
function ss_param($name, $def = '') {
  if (isset($_SESSION[$name])) {
    return $_SESSION[$name];
  }
  return $def;
}

// リダイレクト
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

function fw_send_email($to, $subject, $email_body) {
  global $FW_ADMIN_EMAIL;
  if ($FW_ADMIN_EMAIL == '') {
    throw new Exception("global \$FW_ADMIN_EMAIL not set");
  }
  $headers = "From: $FW_ADMIN_EMAIL";
  @mb_send_mail($to, $subject, $email_body, $headers);

  // 送信したことを記録する
  if (db_table_exists('email_logs')) {
    $db = database_get();
    $stmt = $db->prepare(
      'INSERT INTO email_logs (mailto, body, title, ctime) '.
      'VALUES(?, ?,?,?)');
    $stmt->execute([$to, $email_body, $subject, time()]);
  }
}

function fw_send_email_to_admin($subject, $email_body) {
  global $FW_ADMIN_EMAIL;
  fw_send_email($FW_ADMIN_EMAIL, $subject, $email_body);
}

function has_ngword($s) {
  global $ngword_list;
  if (!isset($ngword_list)) {
    return FALSE;
  }
  foreach ($ngword_list as $word) {
    if (FALSE !== strpos($s, $word)) {
      return TRUE;
    }
  }
}





