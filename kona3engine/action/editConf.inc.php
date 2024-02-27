<?php
include_once __DIR__ . '/admin.inc.php';

function kona3_action_editConf() {
  if (!kona3isAdmin()) {
    kona3error('Admin Only', 'Sorry, Admin Only.');
    exit;
  }
  kona3setup_config();
}

