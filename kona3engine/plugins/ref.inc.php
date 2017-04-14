<?php

function kona3plugins_ref_execute($args) {
  global $kona3conf;
  // get args
  $size = "";
  $caption = "";
  $link = "";
  $url = array_shift($args);
  while ($args) {
    $arg = array_shift($args);
    $arg = trim($arg);
    $c = substr($arg, 0, 1);
    if ($c == "*") {
      $caption = substr($arg, 1);
      continue;
    }
    if ($c == "@") {
      $link = substr($arg, 1);
      continue;
    }
    // width x height
    if (preg_match("#(\d+)x(\d+)#", $arg, $m)) {
      $size = " width='{$m[1]}' height='{$m[2]}'";
      continue;
    }
    // width=xx
    if (preg_match("#width=(\d+)#", $arg, $m)) {
      $size = " width='{$m[1]}'";
      continue;
    }
    // height=xx
    if (preg_match("#height=(\d+)#", $arg, $m)) {
      $size = " height='{$m[1]}'";
      continue;
    }
  }
  // make link
  if (!preg_match("#^http#", $url)) {
    // attach
    $f = $kona3conf["path.attach"]."/".urlencode($url);
    if (file_exists($f)) {
      $url = $kona3conf["url.attach"]."/".urlencode($url);
    }
    // data
    $f = kona3getWikiFile($url, false);
    if (file_exists($f)) {
      $url = kona3getWikiUrl($url);
    }
  }
  // Is image?
  if (preg_match('/\.(png|jpg|jpeg|gif|bmp|ico|svg)$/', $url)) {
    $caph = "<div class='memo'>".kona3text2html($caption)."</div>";
    $code = "<div>".
            "<div><a href='$url'><img src='$url'{$size}></a></div>".
            (($caption != "") ? $caph : "").
            "</div>";
  } else {
    if ($caption == "") $caption = $url;
    $code = "<div><a href='$url'>$caption</a></div>";
  }
  return $code;
}
