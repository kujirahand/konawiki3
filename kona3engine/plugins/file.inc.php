<?php
/** 特定パスにある名前のファイルを取り込んで表示
 * - [書式] #file(name)
 * - [引数]
 * -- name ... パス
 */

include_once dirname(dirname(__FILE__)).'/kona3parser.inc.php';

if (!defined('KONA3_INCLUDE_MAX_DEPTH')) {
  define('KONA3_INCLUDE_MAX_DEPTH', 5);
}

/** $fname の実パスが data ディレクトリ配下にある通常ファイルか (シンボリックリンク経由の外部読み取りを防ぐ, #324) */
function kona3plugins_file_inDataDir($fname) {
  $real = realpath($fname);
  $data = realpath(KONA3_DIR_DATA);
  if ($real === false || $data === false) return false;
  if (!is_file($real)) return false;
  $data = rtrim($data, '/\\');
  return strpos($real, $data . DIRECTORY_SEPARATOR) === 0;
}

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
  // シンボリックリンク経由で data の外のファイルを読まない (#324)
  if (!kona3plugins_file_inDataDir($fname)) {
    return "<div class='error'>Not Exists:".
      kona3text2html($name).
      "</div>";
  }
  // #include と共有する深さ制限 (自己・相互参照による無限再帰を防ぐ, #324)
  global $kona3_include_depth;
  if (!isset($kona3_include_depth)) $kona3_include_depth = 0;
  if ($kona3_include_depth >= KONA3_INCLUDE_MAX_DEPTH) {
    return "<div class='error'>Error: include depth limit exceeded.</div>";
  }
  $txt = file_get_contents($fname);
  $kona3_include_depth++;
  try {
    $htm = konawiki_parser_convert($txt);
  } finally {
    $kona3_include_depth--;
  }
  return "<div>".$htm."</div>";
}
