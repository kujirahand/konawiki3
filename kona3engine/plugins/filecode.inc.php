<?php
/**
 * #filecode(path)
 */

function kona3plugins_filecode_execute($args) {
  global $kona3conf;
  
  // get pid
  $pid = kona3_getPluginInfo("filecode", "pid", 0) + 1;
  kona3_setPluginInfo("filecode", "pid", $pid);
  
  // get parameters
  $name = array_shift($args);
  $name = str_replace('..', '', $name);
  $fname = kona3getWikiFile($name, false);
  if (!file_exists($fname)) {
    $page = kona3getPage();
    $dir = dirname($page);
    if ($dir != '') $dir = $name = $dir.'/'.$name;
    $fname = kona3getWikiFile($name, false);
    if (!file_exists($fname)) {
      return "<div class='error'>Not Exists:".
        kona3text2html($name).
        "</div>";
    }
  }
  $url = kona3getWikiUrl($name);
  $txt = @file_get_contents($fname);
  $name_ = htmlspecialchars($name, ENT_QUOTES);
  if (preg_match('#\.php$#', $fname)) {
    // .php file
    $txt = trim($txt);
    $htm = highlight_string($txt, true);
    // <pre>するので不必要な改行を削除
    $htm = preg_replace('#[\r\n]#', '', $htm);
  } else if (preg_match('#\.(nako|nako3)$#', $fname)) {
    // .nako3 file
    $src = kona3text2html(trim($txt));
    $name_u = urlencode($name);
    $link = kona3getPageURL("", "plugin", "", 
              "name=nako3&mode=run&nakofile=$name_u&canvas");
    $btn = "<a href='$link'>[実行]</a>";
    $htm =
      "<div class='filecode'>\n".
      "  <div class='filename'>{$btn} file: {$name_}</div>\n".
      "  <pre class='code'>$src</pre>\n".
      "</div>\n";
    return $htm;
  } else {
    // other file
    $htm = kona3text2html(trim($txt));
  }
  $code =
    "<div class='filecode'>\n".
    "  <div class='filename'>file: {$name_}</div>\n".
    "  <pre class='code'>$htm</pre>\n".
    "</div>\n";
  return $code;
}

