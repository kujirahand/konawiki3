<?php

function kona3plugins_ls_execute($args) {
  global $kona3conf;

  $page = $kona3conf['page'];
  $fname = kona3getWikiFile($page);
  $dir = dirname($fname);
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
    // directory --- check index
    if (is_dir($f)) {
      $f .= "/index.txt";
      if (!file_exists($f)) continue;
    }
    $name = kona3getWikiName($f);
    $url = kona3getPageURL($name);
    $name = htmlentities($name);
    $code .= "<li><a href='$url'>$name</a></li>\n";
  }
  $code .= "</ul>\n";
  return $code;
}



