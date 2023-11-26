<?php

/** コラムの囲み
 * - [書式] {{{#column(class) ... }}}
 * - [引数]
 * -- class ... クラス名(省略可)
 * - [公開設定] 公開
 */

function kona3plugins_column_execute($args) {
  if (!$args) return "";
  $class = "column";
  if (count($args) >= 2) {
      $class = trim(array_shift($args));
  }
  $body  = array_shift($args);
  if (!preg_match("/^([0-9a-zA-Z\-\_]+)$/",$class)) {
      $class = "column";
  }
  $html = konawiki_parser_convert($body, FALSE);
  return "<div class='$class'>{$html}</div>";
}
