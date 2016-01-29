<?php

function kona3plugins_filecode_execute($args) {
  global $kona3conf;
  $name = array_shift($args);
  $fname = kona3getWikiFile($name, false);
  if (!file_exists($fname)) {
    return "<div class='error'>Not Exists:".
      kona3text2html($name).
      "</div>";
  }
  $url = kona3getWikiUrl($name);
  $txt = @file_get_contents($fname);
  if (preg_match('#\.php$#', $fname)) {
    $txt = trim($txt);
    $htm = highlight_string($txt, true);
    // <pre>するので不必要な改行を削除
    $htm = preg_replace('#[\r\n]#', '', $htm);
  } else {
    $htm = kona3text2html(trim($txt));
  }
  $name_ = htmlspecialchars($name, ENT_QUOTES);
  $code =
    "<div class='filecode'>".
    "  <div class='filename'>".
    "    <a href='$url'>file: {$name_}</a>".
    "  </div>".
    "  <pre class='code'>$htm</pre>".
    "</div>";
  return $code;
}

