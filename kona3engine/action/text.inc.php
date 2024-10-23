<?php
require __DIR__.'/show.inc.php';

function kona3_action_text() {
    // チェック
    global $kona3conf;
    $page = $kona3conf["page"];
    $page_h = htmlspecialchars($page);
  
    // check login
    kona3show_check_private($page);
  
    // detect file type
    $wiki_live = kona3show_detect_file($page, $fname, $ext);
    if (!$wiki_live) {
        $msg = lang('Please make wiki page.');
        header("Content-type: text/plain; charset=UTF-8");
        echo $msg."\n";
        exit;
    }
    header("Content-type: text/plain; charset=UTF-8");
    readfile($fname);
}
  
