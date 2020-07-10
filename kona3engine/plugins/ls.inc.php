<?php

function kona3plugins_ls_execute($args) {
  global $kona3conf;

  $page = $kona3conf['page'];  
  // .. があれば削除
  $page = str_replace('..', '', $page);
  if (strpos($page, '//') !== false) {
    return "";
  }
  $fname = kona3getWikiFile($page, false);
  // dir?
  if (is_dir($fname)) {
    $dir = $fname;
  } else {
    $dir = dirname($fname);
  }
  // get all files
  $files = glob($dir."/*");
  sort($files);

  # filter
  $pat = array_shift($args);
  if ($pat != null) {
    $res = array();
    foreach ($files as $f) {
      if (fnmatch($pat, basename($f))) $res[] = $f;
    }
    $files = $res;
  }

  $code = "<ul>";
  foreach ($files as $f) {
    $name = kona3getWikiName($f);
    $url = kona3getPageURL($name);
    $name = htmlentities($name);
    if (is_dir($f)) {
      $name = "&lt;$name&gt;"; 
    }
    $code .= "<li><a href='$url'>$name</a></li>\n";
  }
  $code .= "</ul>\n";
  return $code;
}
