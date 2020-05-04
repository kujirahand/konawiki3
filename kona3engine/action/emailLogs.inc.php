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
    $del = kona3getPageURL('delete', 'emailLogs', '',
      "q=deleteall-go&time=$tm");
    return kona3showMessage(
      lang('Delete All'), 
      lang('Really?')."--- <a href='$del'>".lang('Yes')."</a>");
  }
  if ($q == 'deleteall-go') {
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

