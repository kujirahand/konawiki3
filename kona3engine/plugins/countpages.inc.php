<?php

/**
 * [usage] #countpages(path, pp=perPage, ignore=key1:key2:key3:key4...)
 * count text char length
 */
function kona3plugins_countpages_execute($args) {
  global $kona3conf;
  $ext = '.'.kona3getConf('def_text_ext', 'txt');
  $pattern = "";
  $ignore = [];
  $perpage = 1000;
  foreach ($args as $a) {
    // pattern
    if ($pattern == "") {
      $pattern = $a;
      continue;
    }
    // per page
    if (preg_match('#pp\=([0-9]+)#', $a, $m)) {
      $perpage = intval($m[1]);
      if ($perpage <= 0) { $perpage = 1000; }
    }
    // ignore
    if (preg_match('#ignore\=([a-zA-Z0-9_\-]+)#', $a, $m)) {
      $ignore = explode(":", $m[1]);
      foreach ($ignore as &$r) {
        $r = trim($r);
      }
      continue;
    }
  }
  // sanitize $pattern
  $pattern = str_replace('../', '', $pattern);
  // init param
  $path = rtrim(KONA3_DIR_DATA, '/');
  $cnt_txt = 0;
  $cnt_src = 0;
  $cnt_file = 0;
  $cnt_code = 0;
  //
  $pattern = str_replace('*', '', $pattern);
  $files = glob_files("$path/$pattern", $ext, $ignore);
  //
  foreach($files as $f) {
    // check $pattern
    $apath = str_replace($path, '', $f);
    if (substr($apath, 0, 1) == '/') {
      $apath = substr($apath, 1);
    }
    if (substr($apath, 0, strlen($pattern)) != $pattern) continue;
    // Count file
    $txt = @file_get_contents($f);
    $c = mb_strlen($txt);
    $cnt_txt += $c;
    $cnt_file++;
    
    // search #filecode(***)
    $lines = explode("\n", $txt);
    foreach ($lines as $line) {
      if (!preg_match("/[#♪]filecode\((.+)\)/", $line, $m)) continue;
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
  $page = floor($cnt / $perpage);
  //
  $cnt_f = number_format($cnt);
  $page_f = number_format($page);
  //
  $cnt_txt_f = number_format($cnt_txt);
  $page_txt = floor($cnt_txt / $perpage);
  return
    "<div><span>".
    "<b>合計 {$cnt_f}字</b>, {$page_f}p&nbsp;".
    "(text:{$cnt_txt_f}字, {$page_txt}p)".
    "(file:{$cnt_file}, include:{$cnt_code})".
    "</span></div>";
}

function glob_files($dir, $pat = "", $ignore = []) {
  $list = array();
  $dir = rtrim($dir, '/'); // chomp '/'
  $files = glob("$dir/*");
  foreach ($files as $file) {
    if ($file == "." || $file == "..") continue;
    $name = basename($file);
    if ($ignore) {
      if (array_search($name, $ignore) !== false) {
        continue;        
      }
    }
    if (is_dir($file)) {
      $files2 = glob_files($file, $pat, $ignore);
      $list = array_merge($list, $files2);
      continue;
    }
    $n = strlen($pat);
    $ext = substr($file, strlen($file) - $n, $n);
    if ($ext === $pat) {
      $list[] = $file;
      continue;
    }
  }
  return $list;
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





