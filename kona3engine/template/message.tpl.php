<?php /* template */

global $kona3conf;

$wikibody = <<<EOS
<div id="wikimessage">{$page_body}</div>
<div style="clear:both;"></div>
EOS;

// $page_title $page_body $wiki_menu
include 'frame.tpl.php';
