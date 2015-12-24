<?php /* template */

global $kona3conf;

if (KONA3_PARTS_COUNTCHAR) {
  $parts_countchar = "<div>Char Count: $cnt_txt</div>";
  $page_body = $parts_countchar . $page_body . $parts_countchar;
}

$wikibody = <<<EOS
<div id="wikibody">{$page_body}</div>
<div id="wikimenu"><nav>{$wiki_menu}</nav></div>
<div style="clear:both;"></div>
EOS;

// $page_title $page_body $wiki_menu
include 'frame.tpl.php';
