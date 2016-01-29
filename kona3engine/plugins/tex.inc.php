<?php

function kona3plugins_tex_execute($args) {
  $text = array_shift($args);
  //
  $api = "https://chart.googleapis.com/chart?cht=tx&chl=";
  $url = $api .= urlencode($text);
  $html = "<div class='tex'><img src='{$url}'></div>";
  return $html;
}


