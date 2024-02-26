<?php

function kona3_action_editConf() {
  global $kona3conf;
  
  if (!kona3isAdmin()) {
    kona3error('Admin Only', 'Sorry, Admin Only.');
    exit;
  }
  if (!defined('KONA3_DIR_ADMIN')) {
    define('KONA3_DIR_ADMIN', dirname(KONA3_DIR_ENGINE).'/kona3admin');
  }  
  include_once KONA3_DIR_ADMIN.'/kona3setup.inc.php';
  kona3setup_config();
}

