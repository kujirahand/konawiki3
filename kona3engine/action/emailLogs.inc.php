<?php

function kona3_action_emailLogs() {
  if (!kona3isAdmin()) {
    kona3error(lang('Admin Page'), 
      lang('You do not have admin perm.'));
    exit;
  }
  $q = kona3param('q');
  if ($q == 'deleteall') {
    $tm = time();
    // 確認画面は POST フォームにする (#325)。全削除のような破壊的操作を
    // トークン付きでも GET リンクにしない (URLへのトークン残留と
    // プリフェッチ等による誤実行を防ぐ)。CSRFトークンはフォームで送る。
    $token = kona3_getEditToken('edit_token');
    $form_action = htmlspecialchars(
      kona3getPageURL('delete', 'emailLogs'), ENT_QUOTES, 'UTF-8');
    $token_h = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
    $yes = htmlspecialchars(lang('Yes'), ENT_QUOTES, 'UTF-8');
    $form = "<form method='post' action='$form_action'>"
      . "<input type='hidden' name='q' value='deleteall-go'>"
      . "<input type='hidden' name='time' value='$tm'>"
      . "<input type='hidden' name='edit_token' value='$token_h'>"
      . "<input class='pure-button pure-button-primary' type='submit' value='$yes'>"
      . "</form>";
    return kona3showMessage(
      lang('Delete All'),
      lang('Really?')."--- ".$form);
  }
  if ($q == 'deleteall-go') {
    // 全削除の実行は POST のみ受け付ける (#325)
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      return kona3error(lang('Invalid Token'), lang('Invalid edit token.'));
    }
    if (!kona3_checkEditToken('edit_token')) {
      return kona3error(lang('Invalid Token'), lang('Invalid edit token.'));
    }
    $time = intval(kona3param('time'));
    if ((time() - $time) > 30) { // 有効期間30秒
      return kona3error('Time over', 'Please retry.');
    }
    db_exec('DELETE FROM email_logs');
    return redirect(kona3getPageURL('ok', 'emailLogs'));
  }
  
  // logs
  $logs = db_get("SELECT * FROM email_logs ".
    "ORDER BY email_log_id DESC LIMIT 100");
  
  // render
  $delete_link = kona3getPageURL('delete', 'emailLogs',
    '', 'q=deleteall');
  
  kona3template("emailLogs.html", [
    "logs" => $logs,
    "delete_link" => $delete_link,
  ]);
}

