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
  $menus = ["FrontPage", "MenuBar", "GlobalBar", "SideBar"];
  foreach ($menus as $m) {
    $edit_token = kona3_getEditToken($m, FALSE);
    $url = kona3getPageURL($m, "edit", "", "edit_token=$edit_token");
    $res .= "<li><a href='$url'>$m</a></li>\n";
  }
  
  $key_ = kona3text2html($key);

  // shortcut
  $hint_list = "";
  $menu2 = [
    ($page != "FrontPage") ? "$page/FrontPage" : "",
    date('Y/m'), date('Y/m/d'),
  ];
  foreach ($menu2 as $m) {
    if ($m == "") { continue; }
    $edit_token = kona3_getEditToken($m, FALSE);
    $url = kona3getPageURL($m, "edit", "", "edit_token=$edit_token");
    $label = htmlspecialchars($m, ENT_QUOTES);
    $hint_list .= "<li><a href='$url'>$label</a></li>\n";
  }

  // show form
  $m_edit = lang('Edit');
  $page_name = lang('Page name');
  $special_page = lang('Special Page');
  $hint = lang('Hint');
  $form = <<<EOS
<div>
  $page_name:
  <form
    class="pure-form" 
    method="post" action="$action">
    <input type="hidden" name="a_mode" value="new">
    <input type="text" id="new_key" name="a_key" value="$key_" size="40">
    <input class="pure-button" type="submit" value="$m_edit">
  </form>
</div>
<br>
<div class="block2">
  <div>$special_page:</div>
  <ul>
{$res}
  </ul>
</div>
<br>
<div class="block2">
  <div>$hint:</div>
  <ul>
{$hint_list}
  </ul>
</div>

EOS;
  // show
  kona3template('message.html', array(
    "page_title" => kona3text2html($page),
    "page_body"  => $form,
  ));
}



