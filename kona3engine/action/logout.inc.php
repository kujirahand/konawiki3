<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';

function kona3_action_logout() {
  global $kona3conf;
  $page = $kona3conf["page"];
  kona3logout();
  kona3showMessage(
    $page,
    lang('Success to logout.')
  );
}



