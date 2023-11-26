<?php

/** コメントをHTMLに埋め込む
 * - [書式] #rem(comment)
 * - [引数]
 * -- comment ... コメント
 */

function kona3plugins_rem_execute($args) {
  $text = array_shift($args);
  $html = kona3text2html($text);
  return "<!-- $html -->";
}


