<?php
/** ソースコードのパスを指定して囲んで表示
 * - [書式] #filecode(path, type, lineno)
 * - [引数]
 * -- path: filepath
 * -- type=(code|plain): code or plain(beta) (optional)
 * -- lineno=nn-mm: extract lines nn to mm (optional)
 * - [利用例]
 * #filecode(src/ch1/hello.py, type=code, lineno=1-3)
 */

function kona3plugins_filecode_execute($args) {
  global $kona3conf;
  
  // get pid
  $pid = kona3_getPluginInfo("filecode", "pid", 0) + 1;
  kona3_setPluginInfo("filecode", "pid", $pid);
  
  // get parameters
  $name = array_shift($args);
  $type = "code";
  $lineno_from = 0;
  $lineno_to = 0;
  foreach ($args as $arg) {
    if (preg_match('/^type=(.*)$/', $arg, $m)) {
      $type = $m[1];
    } else if (preg_match('/^lineno=([0-9\-\:]+?)$/', $arg, $m)) {
      $line = $m[1];
      if (preg_match('/^([0-9]+)[\-\:]([0-9]+)$/', $line, $m)) {
        $lineno_from = intval($m[1]);
        $lineno_to = intval($m[2]);
      } else if (preg_match('/^([0-9]+)$/', $line, $m)) {
        $lineno_from = $lineno_to = intval($m[1]);
      }
    }
  }

  // check parametes
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
  if ($lineno_from > 0) {
    $lines = explode("\n", trim($txt));
    $line_cnt = count($lines);
    $sublines = array_slice($lines, $lineno_from - 1, $lineno_to - $lineno_from + 1);
    $txt = implode("\n", $sublines);
    if ($lineno_from >= 2) {
      $txt = "…省略…\n".$txt;
    }
    if ($lineno_to < $line_cnt) {
      $txt = $txt."\n…省略…\n";
    }
  }
  $name_ = htmlspecialchars($name, ENT_QUOTES);
  if ($lineno_from > 0) {
    $name_ .= " (lineno=$lineno_from-$lineno_to)";
  }
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
  if ($type == 'beta' || $type == 'plain') {
    $htm = str_replace("\n", "<br>\n", $htm);
    $code =
      "<div class='filecode'>\n".
      "  <div class='filename'>file: {$name_}</div>\n".
      "  <div class='code'>{$htm}</div>\n".
      "</div>\n";
  }
  else { // if ($type == 'code') {
    $code =
      "<div class='filecode'>\n".
      "  <div class='filename'>file: {$name_}</div>\n".
      "  <pre class='code'>$htm</pre>\n".
      "</div>\n";
  }
  return $code;
}

