<?php

/** コラムなどに使える要素で囲む
 * - [書式] {{{#block(class) ... }}} の書式で使う
 * - [引数]
 * -- class ... クラス名(省略可)
 * -- text ... テキスト
 */

function kona3plugins_block_execute($args) {
  $class = "column";
  if (count($args) >= 2) {
    $test = array_shift($args);
    if (preg_match('/^[a-zA-Z0-9\_\-]+$/', $test)) {
      $class = $test;
    }
  }
  $text = array_shift($args);
  $html = konawiki_parser_convert($text);
  $t = "<div class='$class'>$html</div>";
  return $t;
}



