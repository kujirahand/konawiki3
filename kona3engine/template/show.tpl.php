<?php /* template */

global $kona3conf;
$print_mode = isset($_GET['print']) ? $_GET['print'] : false;
if (KONA3_PARTS_COUNTCHAR) {
  $menu = kona3getMenu(); 
  $cnt_txt = number_format($cnt_txt);
  $parts_countchar = 
    "<div style='font-size:8px; text-align:right; ".
    " padding:8px; margin: 8px; background-color:#f0f0ff; '>".
    "$menu - CH=$cnt_txt</div>";
  $page_body = $parts_countchar . $page_body . $parts_countchar;
}

if (kona3isLogin()) {
  if (defined('KONA3_SHOW_DATA_DIR') && KONA3_SHOW_DATA_DIR) {
    $path = $page_file;
    $page_body .= "<div id='kona3_show_data_dir'>".
      "file: <input type='' value='$path' readonly/></div>";
  }
}

// menubar
$menubar = '';
$menufile = kona3getWikiFile("MenuBar");
if (file_exists($menufile)) {
  $menu = @file_get_contents($menufile);
  $menubar = konawiki_parser_convert($menu);
  $menubar = "<div id=\"wikimenu\"><nav>{$menubar}</nav></div>";
}

// contents
$wikibody = <<<EOS
<div id="wikibody">{$page_body}</div>
{$menubar}
<div style="clear:both;"></div>
EOS;


include $kona3conf['path.engine'].'/template/frame.tpl.php';

