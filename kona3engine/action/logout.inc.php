<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';

function kona3_action_logout() {
  global $kona3conf;
  $page = $kona3conf["page"];
  // logout
  kona3logout();
  session_regenerate_id(true); // remove old session
  // show message
  $kona3conf["robots"] = "noindex";
  kona3showMessage(
    $page,
    lang('You have been logged out.')
  );
}



