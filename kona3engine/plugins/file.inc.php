<?php
/** 特定パスにある名前のファイルを取り込んで表示
 * - [書式] #file(name)
 * - [引数]
 * -- name ... パス
 */

include_once dirname(dirname(__FILE__)).'/kona3parser.inc.php';

function kona3plugins_file_execute($args) {
  global $kona3conf;
  $name = array_shift($args);
  $name = str_replace('..', '', $name);// fixed path traversal
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

