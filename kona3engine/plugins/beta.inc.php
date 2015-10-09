<?php

function kona3plugins_beta_execute($args) {
  $text = array_shift($args);
  //
  $html = kona3text2html($text,ENT_QUOTES, "UTF-8");
  $html = str_replace("\n", "<br>", $html);
  return "<div class='filecode'><div class='beta'>{$html}</div></div>";
}


