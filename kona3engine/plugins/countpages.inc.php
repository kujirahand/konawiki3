<?php

function kona3plugins_countpages_execute($args) {
  global $kona3conf;
  $path = $kona3conf["path.data"];
  $cnt_txt = 0;
  $cnt_src = 0;
  $files = glob($path."/*.txt");
  //
  foreach($files as $f) {
    $txt = @file_get_contents($f);
    $c = mb_strlen($txt);
    $cnt_txt += $c;
    
    // search #filecode(***)
    $lines = explode("\n", $txt);
    foreach ($lines as $line) {
      if (!preg_match("/\#filecode\((.+)\)/", $line, $m)) continue;
      $src = $path . "/" . $m[1];
      if (file_exists($src)) {
        $subtxt = @file_get_contents($src);
        $c = mb_strlen($subtxt);
        $cnt_src += $c;
      }
    }
  }
  // 
  $cnt = $cnt_txt + $cnt_src;
  $page = floor($cnt / 1000);
  //
  $cnt_f = number_format($cnt);
  $page_f = number_format($page);
  //
  $cnt_txt_f = number_format($cnt_txt);
  $page_txt = floor($cnt_txt / 1000);
  return
    "<span>合計 {$cnt_f}字, {$page_f}ページ".
    "&nbsp;(テキストのみ:{$cnt_txt_f}字, {$page_txt}ページ)</span>";
}


