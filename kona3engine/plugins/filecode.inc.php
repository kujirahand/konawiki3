<?php
/**
 * #filecode(path)
 */

function kona3plugins_filecode_execute($args) {
  global $kona3conf;
  // get file path
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
    $txt = trim($txt);
    $htm = highlight_string($txt, true);
    // <pre>するので不必要な改行を削除
    $htm = preg_replace('#[\r\n]#', '', $htm);
  } else if (preg_match('#\.(nako|nako3)$#', $fname)) {
    // #nako3 plugin
    $txt = str_replace('{{{', ' {{{', $txt); // escape
    $txt = str_replace('}}}', ' }}}', $txt);
    $cnt = substr_count($txt, "\n");
    $head = "<div class='filecode'>".
            "  <div class='filename'>file: {$name_}</div>".
            "</div>";
    $code  = "{{{#nako3(canvas,rows=$cnt,use_textarea)\n";
    $code .= trim($txt) . "\n";
    $code .= '}}}'."\n";
    $htm = $head . konawiki_parser_convert($code);
    return $htm;
  } else {
    $htm = kona3text2html(trim($txt));
  }
  $code =
    "<div class='filecode'>".
    "  <div class='filename'>file: {$name_}</div>".
    "  <pre class='code'>$htm</pre>".
    "</div>";
  return $code;
}

