<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';

function kona3_action_new() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $action = kona3getPageURL($page, "new");
  
  $am   = kona3param('a_mode', '');
  $key  = kona3param('a_key', '');

  $res = "";
  if ($am == "new") {
    $url = kona3getPageURL($key, "edit");
    header("Location: $url");
    exit;
  }
  
  $key_ = kona3text2html($key);

  // show form
  $form = <<<EOS
<div>
  <form method="post" action="$action">
    <input type="hidden" name="a_mode" value="new">
    <input type="text" name="a_key" value="$key_">
    <input type="submit" value="New">
  </form>
</div>
<div>
{$res}
</div>
EOS;
  // show
  kona3template('message', array(
    "page_title" => kona3text2html($page),
    "page_body"  => $form,
  ));
}



