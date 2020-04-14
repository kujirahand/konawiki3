<?php /* template */

global $kona3conf;

// menubar
$menubar = kona3getWikiPage("MenuBar");

// --- BODY ---
include_once dirname(__FILE__).'/parts_header.tpl.php';
echo <<<EOS
<!-- #wikibody -->
<div id="wikibody">
  <div class="pure-g wikibody_pad">
    <!-- .page_body -->
    <div class="pure-u-1 pure-u-md-19-24 page_body">
      {$page_body}
    </div><!-- end of .page_body -->

    <!-- .body-menubar -->
    <div class="pure-u-1 pure-u-md-5-24 body-menubar">
      <div id="wikimenu">
        {$menubar}
      </div><!-- end of #wikimenu -->
    </div><!-- end of .body-menubar -->

  </div><!-- end of .pure-g wikibody_pad -->
</div><!-- end of #wikibody -->
<div style="clear:both;"></div>
EOS;
include_once dirname(__FILE__).'/parts_footer.tpl.php';

