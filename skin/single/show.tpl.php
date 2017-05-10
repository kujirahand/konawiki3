<?php

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

$wikibody = <<<EOS
<div id="wikibody">{$page_body}</div>
<div style="clear:both;"></div>
EOS;

include $kona3conf['path.engine'].'/template/frame.tpl.php';

