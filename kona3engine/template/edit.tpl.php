<?php /* template */

global $kona3conf;

// body
$m_save_temp = lang('Save temp');
$m_save = lang('Save &amp; Show');
$m_git_save = lang('Save to Repo');
$m_ls_load = lang('Load from Browser');

// git button
$git_button = '';
if ($kona3conf['git.enabled']) {
  $git_button =<<<__EOS
<input id="git_save_btn" class="pure-button button-short" 
 type="button" value="$m_git_save">
__EOS;
}

$page_body = <<<__EOS__
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
          class="pure-button button-long"
          type="button" value="$m_save_temp">
        <input id="save_btn" 
          class="pure-button button-short"
          type="submit" value="$m_save">
        {$git_button}
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
      <div>Shortcut:</div>
      <ul>
        <li>Ctrl+S ... save</li>
      </ul>
    </div>
    <div class="desc">
      <div>etc...</div>
      <ul>
        <li><input id="ls_load_btn" type="button" class="pure-button"
             value="$m_ls_load"></li>
      </ul>
    </div>
  </div>
</div>
<div style="clear:both;"></div>
__EOS__;

// include script
$kona3conf['js'][] = kona3getResourceURL('edit.js', TRUE);
$kona3conf['css'][] = kona3getResourceURL('edit.css', TRUE);

// $page_title $page_body $wiki_menu
include 'frame.tpl.php';



