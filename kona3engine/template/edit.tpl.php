<?php /* template */

global $kona3conf;

// body
$m_save_temp = lang('Save temp');
$m_save = lang('Save &amp; Show');
$page_body = <<<EOS
<!-- editor -->
<div id="wikimessage">
  <div id="wikiedit">
    <div class="edit_msg">{$msg}</div>
    <div id="outline_div"></div>
    <form method="post" action="$action" class="pure-form">
      <input type="hidden" name="a_mode" value="trywrite">
      <input type="hidden" id="a_hash" name="a_hash" value="$a_hash">
      <div class="editor">
        <textarea id="edit_txt" name="edit_txt">{$page_body}</textarea>
      </div>
      <div class="buttons">
        <input id="temporarily_save_btn"
          type="button" value="$m_save_temp">
        <input id="save_btn" 
          type="submit" value="$m_save">
      </div>
      <div>
        <input type="text" id="edit_info" class="info" readonly>
      </div>
      <div>
        <input type="text" id="edit_counter" class="info" readonly>
      </div>
    </form>
    <br>
    <div class="desc">
      <div>shortcut</div>
      <ul>
        <li>Ctrl+S ... save</li>
      </ul>
    </div>
  </div>
</div>

<div style="clear:both;"></div>
EOS;

// include script
$kona3conf['js'][] = 'index.php?edit.js&resource';
$kona3conf['css'][] = 'index.php?edit.css&resource';

// $page_title $page_body $wiki_menu
include 'frame.tpl.php';
