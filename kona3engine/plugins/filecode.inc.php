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
    $txt = trim($txt);
    $htm = highlight_string($txt, true);
    // <pre>するので不必要な改行を削除
    $htm = preg_replace('#[\r\n]#', '', $htm);
  } else if (preg_match('#\.(nako|nako3)$#', $fname)) {
    // #nako3 plugin
    $src = kona3text2html(trim($txt));
    $txt = str_replace('{{{', ' {{{', $txt); // escape
    $txt = str_replace('}}}', ' }}}', $txt);
    $cnt = substr_count($txt, "\n") + 1;
    $script = "".
      "<script>\n".
      "var show_filecode_{$pid} = function(){\n".
      "  var nako = document.getElementById('filecode{$pid}');\n".
      "  nako.style.display = 'block';\n".
      "  var fsrc = document.getElementById('filecode_src{$pid}');\n".
      "  fsrc.style.display = 'none';\n".
      "}".
      "</script>\n";
    $btn = "".
      "<a href='javascript:show_filecode_{$pid}()'>".
      "[実行]</a> - ";
    $head = $script.
      "<div class='filecode'>\n".
      "  <div class='filename'>{$btn} file: {$name_}</div>\n".
      "  <pre id='filecode_src{$pid}' class='code'>$src</pre>\n".
      "</div>\n".
      "<div id='filecode{$pid}' style='display:none;'>";
    $foot = "".
      "</div><!-- #filecode{$pid} -->\n";
    $code  = "{{{#nako3(canvas,rows=$cnt,use_textarea)\n";
    $code .= trim($txt) . "\n";
    $code .= '}}}'."\n";
    // head + body + foot
    $htm = $head . konawiki_parser_convert($code) . $foot;
    return $htm;
  } else {
    $htm = kona3text2html(trim($txt));
  }
  $code =
    "<div class='filecode'>\n".
    "  <div class='filename'>file: {$name_}</div>\n".
    "  <pre class='code'>$htm</pre>\n".
    "</div>\n";
  return $code;
}

