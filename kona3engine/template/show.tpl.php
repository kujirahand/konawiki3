<?php /* template */

global $kona3conf;

// menubar
$menubar = kona3getWikiPage("MenuBar");

// --- BODY ---
include_once dirname(__FILE__).'/parts_header.tpl.php';
echo <<<EOS
<div class="ctrl_menu">{$ctrl_menu}</div>

<div id="wikibody">
  <div class="pure-g">
    <div class="pure-u-1 pure-u-md-19-24">
    {$page_body}
    </div>
    <div class="pure-u-1 pure-u-md-5-24">
      <div id="wikimenu">
      {$menubar}
      </div>
    </div>
  </div><!-- /.pure-g -->
</div>
<div style="clear:both;"></div>
EOS;
include_once dirname(__FILE__).'/parts_footer.tpl.php';

