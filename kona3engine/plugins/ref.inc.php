<?php
/** #ref plugin
 * 画像を表示するプラグイン
 * [書式] #ref(image.png, w=400, h=300, 400x300, @link, *caption)
 */
function kona3plugins_ref_execute($args) {
  global $kona3conf;
  $page = kona3getPage();
  // get args
  $size = " width='400px' "; // set default image size
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
    // width=xx or w=xx
    if (preg_match("#(width|w)\=(\d+)(px|\%)?#", $arg, $m)) {
      $size = " width='{$m[2]}{$m[3]}'";
      continue;
    }
    // height=xx or h=xx
    if (preg_match("#(height|h)\=(\d+)(px|\%)?#", $arg, $m)) {
      $size = " height='{$m[2]}{$m[3]}'";
      continue;
    }
  }
  // make link
  $caption = htmlspecialchars($caption, ENT_QUOTES);
  $url = htmlspecialchars_url($url);
  if (!preg_match("#^https?\:\/\/#", $url)) {
    // file link
    $url2 = kona3plugins_ref_file_url($page, $url);
    if ($url2 === '') {
      $url = htmlspecialchars($url, ENT_QUOTES);
      return "<div class='error'>#ref:(No file:{$url})</div>";
    }
    $url = $url2;
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

function kona3plugins_ref_file_url($page, $url) {
  global $kona3conf;
  // Disallow up dir!! (1/2)
  $url = str_replace('..', '', $url);
  
  // is attach dir?
  $f = $kona3conf["path.attach"]."/".$url;
  if (file_exists($f)) return $kona3conf["url.attach"]."/".$url;

  // is data dir?
  $f = KONA3_DIR_DATA."/".$url;
  if (file_exists($f)) return $kona3conf["url.data"]."/".$url;
  
  // Is this file in same directory?
  if (strpos($page, "/") !== FALSE) {
    $url2 = dirname($page)."/".urldecode($url);
    // Disallow up dir!! (2/2)
    $url2 = str_replace('..', '', $url2);
    $f = KONA3_DIR_DATA."/".$url2;
    if (file_exists($f)) {
      if ($kona3conf["url.data"] == '') {
        $url = $url2;
      } else {
        $url = $kona3conf["url.data"]."/".$url2;
      }
      return $url;
    }
  }
  
  // default data
  $f = kona3getWikiFile($url, false);
  if (file_exists($f)) {
    $url = kona3getWikiUrl($url);
  }

  return '';
}

function htmlspecialchars_url($xss){
  $s=htmlspecialchars($xss, ENT_QUOTES, 'UTF-8');
  $s=str_replace('http:','http<',$s);
  $s=str_replace('https:','https<',$s);
  $s=str_replace(':','',$s);
  $s=str_replace(';','',$s);
  $s=str_replace('http<','http:',$s);
  $s=str_replace('https<','https:',$s);
  return $s;
}

