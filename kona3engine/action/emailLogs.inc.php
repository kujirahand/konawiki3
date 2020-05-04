<?php

function kona3_action_emailLogs() {
  if (!kona3isAdmin()) {
    kona3error(lang('Admin Page'), 
      lang('You do not have admin perm.'));
    exit;
  }
  $logs = db_get("SELECT * FROM email_logs ".
    "ORDER BY email_log_id DESC LIMIT 100");
  kona3template("emailLogs.html", [
    "logs" => $logs,
  ]);
}

