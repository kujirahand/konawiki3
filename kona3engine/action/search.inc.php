<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';

function kona3_action_search() {
  global $kona3conf;
  $page = $kona3conf["page"];
  $action = kona3getPageURL($page, "search");
  
  $am   = kona3param('a_mode', '');
  $key  = kona3param('a_key', '');

  $res= '';
  if ($am == "search") {
    $res = "TODO";
  }
  
  $key_ = kona3text2html($key);

  // show form
  $form = <<<EOS
<div>
  <form method="post" action="$action">
    <input type="hidden" name="a_mode" value="search">
    <input type="text" name="a_key" value="$key_">
    <input type="submit" value="Search">
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



