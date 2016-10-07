<?php /* template */

global $kona3conf;

// body
$wikibody = <<<EOS
<!-- editor -->
<div id="wikimessage">
  <div id="wikiedit">
    <div class="edit_msg">{$msg}</div>
    <div id="outline_div"></div>
    <form method="post" action="$action">
      <input type="hidden" name="a_mode" value="trywrite">
      <input type="hidden" id="a_hash" name="a_hash" value="$a_hash">
      <div>
        <textarea id="edit_txt" name="edit_txt">{$page_body}</textarea>
      </div>
      <div>
        <input id="save_ajax_btn" type="button" value="Save">
        <input id="save_btn" type="submit" value="Save &amp; show">
        <input id="auto_save" type="checkbox" value="auto_save" checked>
        <label id="auto_save_label" for="auto_save">auto save</label>
        <input id="outline_btn" type="button" value="Outline">
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
kona3use_jquery();
$kona3conf['js'][] = 'index.php?edit.js&resource';
$kona3conf['css'][] = 'index.php?edit.css&resource';

// $page_title $page_body $wiki_menu
include 'frame.tpl.php';
