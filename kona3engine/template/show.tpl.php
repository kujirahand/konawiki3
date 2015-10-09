<?php /* template */

global $kona3conf;

$wikibody = <<<EOS
<div id="wikibody">{$page_body}</div>
<div id="wikimenu"><nav>{$wiki_menu}</nav></div>
<div style="clear:both;"></div>
EOS;

// $page_title $page_body $wiki_menu
include 'frame.tpl.php';
