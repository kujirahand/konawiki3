<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';

function kona3_action_logout() {
  global $kona3conf;
  $page = $kona3conf["page"];
  kona3logout();
  $kona3conf["robots"] = "noindex";
  kona3showMessage(
    $page,
    lang('You have logged out.')
  );
}



