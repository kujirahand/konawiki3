<?php

include_once dirname(dirname(__FILE__)).'/kona3parser.inc.php';

function kona3plugins_file_execute($args) {
  global $kona3conf;
  $name = array_shift($args);
  $fname = kona3getWikiFile($name, false);
  if (!file_exists($fname)) {
    if (!file_exists($fname.".txt")) {
      return "<div class='error'>Not Exists:".
        kona3text2html($name).
        "</div>";
    } else {
      $fname = $fname.".txt";
    }
  }
  $txt = file_get_contents($fname);
  $htm = konawiki_parser_convert($txt);
  return "<div>".$htm."</div>";
}

