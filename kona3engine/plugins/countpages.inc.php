<?php

/**
 * [usage] #countpage(path, ignore=key1:key2:key3:key4...)
 */
function kona3plugins_countpages_execute($args) {
  global $kona3conf;
  $pattern = "";
  $ignore = [];
  foreach ($args as $a) {
    if ($pattern == "") {
      $pattern = $a;
      continue;
    }
    if (substr($a,0,7) == "ignore=") {
      $a = substr($a, 7);
      $ignore = explode(":", $a);
      continue;
    }
  }
  $path = $kona3conf["path.data"];
  $cnt_txt = 0;
  $cnt_src = 0;
  $cnt_file = 0;
  $cnt_code = 0;
  //
  $txt  = enum_files($path, "#\.txt$#");
  // $php  = enum_files($path, "#\.php$#");
  // $js   = enum_files($path, "#\.js$#");
  // $html = enum_files($path, "#\.html$#");
  // $html = [];
  //
  $files = $txt;
  //
  $pattern = str_replace('*', '', $pattern);
  foreach($files as $f) {
    // check $pattern
    $apath = str_replace($path, '', $f);
    if (substr($apath, 0, 1) == '/') {
      $apath = substr($apath, 1);
    }
    if (substr($apath, 0, strlen($pattern)) != $pattern) continue;
    // check $ignore
    if (count($ignore) > 0) {
      $flg = FALSE;
      foreach ($ignore as $ig) {
        if (strpos($apath, $ig) !== FALSE) { $flg = TRUE; break; }
      }
      if ($flg) continue;
    }
    // Count file
    $txt = @file_get_contents($f);
    $c = mb_strlen($txt);
    $cnt_txt += $c;
    $cnt_file++;
    
    // search #filecode(***)
    $lines = explode("\n", $txt);
    foreach ($lines as $line) {
      if (!preg_match("/\#filecode\((.+)\)/", $line, $m)) continue;
      $src = $path . "/" . $m[1];
      if (file_exists($src)) {
        $subtxt = @file_get_contents($src);
        $c = mb_strlen($subtxt);
        $cnt_src += $c;
        $cnt_code++;
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
    "<div><span>".
    "<b>合計 {$cnt_f}字</b>, {$page_f}p&nbsp;".
    "(text:{$cnt_txt_f}字, {$page_txt}p)".
    "(file:{$cnt_file}, include:{$cnt_code})".
    "</span></div>";
}

function enum_files($dir, $pat = NULL) {
  $files = scandir($dir);
  $dir = rtrim($dir, '/'); // chomp '/'
  $list = array();
  foreach ($files as $file) {
    if ($file == "." || $file == "..") continue;
    $fullpath = $dir . '/' . $file;
    if (is_file($fullpath)) {
      if ($pat !== NULL) {
        if (!preg_match($pat, $file)) continue;
      }
      $list[] = $fullpath;
    }
    if (is_dir($fullpath)) {
      $list = array_merge($list, enum_files($fullpath, $pat));
    }
  }
  return $list;
}





