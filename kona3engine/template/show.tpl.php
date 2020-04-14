<?php /* template */

global $kona3conf;

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
$menubar = kona3getWikiPage("MenuBar");

// --- BODY ---
include_once dirname(__FILE__).'/parts_header.tpl.php';
echo <<<EOS
<div id="wikibody">
  <div class="pure-g">
    <div class="pure-u-1 pure-u-md-19-24">
    {$page_body}
    </div>
    <div class="pure-u-1 pure-u-md-5-24">
      <div id="wikimenu">
      {$menubar}
      </div>
    </div>
  </div><!-- /.pure-g -->
</div>
<div style="clear:both;"></div>
EOS;
include_once dirname(__FILE__).'/parts_footer.tpl.php';

