<?php
/** コラムの囲み
 * - [書式] {{{#md(class) ... }}}
 * - [引数]
 * -- class ... クラス名(省略可)
 * - [公開設定] 公開
 */

require_once dirname(__DIR__).'/kona3parser_md.inc.php';

function kona3plugins_md_execute($args) {
  if (!$args) return "";
  $class = "column";
  if (count($args) >= 2) {
      $class = trim(array_shift($args));
  }
  $body  = array_shift($args);
  if (!preg_match("/^([0-9a-zA-Z\-\_]+)$/",$class)) {
      $class = "column";
  }
  $html = kona3markdown_parser_convert($body, FALSE);
  return "<div class='$class'>{$html}</div>";
}
