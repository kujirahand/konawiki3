<?php /* template */

global $kona3conf;

$wikibody = <<<EOS
<div id="wikimessage">
  <div id="wikiedit">
    {$msg}
    <form method="post" action="$action">
    <input type="hidden" name="a_mode" value="trywrite">
    <input type="hidden" name="a_hash" value="$a_hash">
    <div>
      <textarea id="edit_txt" name="edit_txt">{$page_body}</textarea>
    </div>
    <div>
      <input type="submit" value="SAVE">
    </div>
    </form>
  </div>
</div>
<div style="clear:both;"></div>

EOS;

// $page_title $page_body $wiki_menu
include 'frame.tpl.php';
