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

  // init
  if ($key =='' && strpos($page, '/') !== false) {
    $key = dirname($page).'/';
  }
  
  // System Menu?
  $res .= "<ul>\n";
  $menus = ["FrontPage", "MenuBar", "GlobalBar", "SideBar", date("Y/m")];
  foreach ($menus as $m) {
    $url = kona3getPageURL($m, "edit");
    $res .= "<li><a href='$url'>$m</a></li>\n";
  }
  $res .= "</ul>\n";
  
  $key_ = kona3text2html($key);

  // show form
  $m_edit = lang('Edit');
  $form = <<<EOS
<div>
  <form
    class="pure-form" 
    method="post" action="$action">
    <input type="hidden" name="a_mode" value="new">
    <input type="text" name="a_key" value="$key_" size="40">
    <input class="pure-button" type="submit" value="$m_edit">
  </form>
</div>
<div>
{$res}
</div>
EOS;
  // show
  kona3template('message.html', array(
    "page_title" => kona3text2html($page),
    "page_body"  => $form,
  ));
}



