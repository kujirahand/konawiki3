<?php

function kona3plugins_analytics_execute($args) {
  $text = array_shift($args);
  $html = KONA3_ANALYTICS;
  return $html;
}

