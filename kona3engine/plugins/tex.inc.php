<?php

/** Tex(数式)を画像で表示する
 * - [書式] #tex(code)
 * - [引数]
 * -- code ... コード
 */

function kona3plugins_tex_execute($args) {
  $text = array_shift($args);
  //
  $api = "https://chart.googleapis.com/chart?cht=tx&chl=";
  $url = $api .= urlencode($text);
  $html = "<div class='tex'><img src='{$url}'></div>";
  return $html;
}


