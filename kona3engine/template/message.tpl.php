<?php /* template */

global $kona3conf;

$page_body = <<<EOS
<div id="wikimessage">
  <div class="box">
    {$page_body}
  </div>
</div>
<div style="clear:both;"></div>
EOS;

// $page_title $page_body $wiki_menu
include 'frame.tpl.php';
