<?php
/** pre要素で囲む
 * - [書式] {{{#code ... }}}
 * - [引数]
 * -- lang ... 言語名(省略可)
 * -- text ... テキスト
 */

function kona3plugins_code_execute($args) {
  $lang = array_shift($args);
  $text = array_shift($args);
  // #code (NULL) 対策
  if ($lang == "") $text = $lang;
  //
  $html = kona3text2html($text,ENT_QUOTES, "UTF-8");
  return "<div><pre class='code'>$html</pre></div>";
}


