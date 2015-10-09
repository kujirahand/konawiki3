<?php

function kona3plugins_rem_execute($args) {
  $text = array_shift($args);
  $html = kona3text2html($text);
  return "<!-- $html -->";
}


