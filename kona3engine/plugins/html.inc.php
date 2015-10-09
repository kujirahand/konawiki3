<?php

function kona3plugins_html_execute($args) {
  $html = array_shift($args);
  //$t = "<span>".kona3text2html($html)."</span>";
  $t = "<span>".$html."</span>";
  return $t; 
}



