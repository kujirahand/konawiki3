<?php
/** 脚注
 * - [書式] {{{#footnote(class) ... }}}
 * - [引数]
 * -- class ... クラス名(省略可)
 * - [公開設定] 公開
 */

require_once dirname(__DIR__).'/kona3parser.inc.php';

function kona3plugins_footnote_execute($args) {
  if (!$args) return "";
  $class = "footnote";
  if (count($args) >= 2) {
      $class = trim(array_shift($args));
  }
  $body  = array_shift($args);
  if (!preg_match("/^([0-9a-zA-Z\-\_\s]+)$/",$class)) {
      $class = "footnote";
  }
  $html = kona3markdown_parser_convert($body, FALSE);
  return "<div class='$class'>{$html}</div>";
}
