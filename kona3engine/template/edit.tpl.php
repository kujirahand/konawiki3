<?php /* template */

global $kona3conf;

// body
$wikibody = <<<EOS
<!-- editor -->
<div id="wikimessage">
  <div id="wikiedit">
    <div class="edit_msg">{$msg}</div>
    <form method="post" action="$action">
      <input type="hidden" name="a_mode" value="trywrite">
      <input type="hidden" id="a_hash" name="a_hash" value="$a_hash">
      <div>
        <textarea id="edit_txt" name="edit_txt">{$page_body}</textarea>
      </div>
      <div>
        <input id="save_ajax_btn" type="button" value="Save">
        <input id="save_btn" type="submit" value="Save &amp; show">
      </div>
      <div><span id="edit_info" class="info"></span></div>
      <div id="func_area">
        <div id="recover_div"></div>
      </div>
    </form>
  </div>
</div>

<div style="clear:both;"></div>
EOS;

// include script
$kona3conf['header.tags'][] = <<< EOS
<script type="text/javascript" 
         src="index.php?jquery-3.1.0.min.js&resource"></script>
<script type="text/javascript" 
         src="index.php?edit.js&resource"></script>
<link rel="stylesheet" type="text/css" 
      href="index.php?edit.css&resource">
EOS;



// $page_title $page_body $wiki_menu
include 'frame.tpl.php';
